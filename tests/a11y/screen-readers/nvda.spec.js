/**
 * NVDA with Firefox, in browse mode, using the keys an NVDA user reaches
 * for: T for the next table, G for the next graphic, Control+Alt+arrows to
 * move between cells, and the down arrow to read on.
 */
import { nvdaTest as test } from '@guidepup/playwright';
import { WindowsKeyCodes, WindowsModifiers } from '@guidepup/guidepup';
import { fixture, walk, landedOn, heardAll, heardNone } from './walk.js';

test.use({ nvdaStartOptions: { capture: true } });

const TOP = { keyCode: [WindowsKeyCodes.Home], modifiers: [WindowsModifiers.Control] };

/**
 * Make sure NVDA is in browse mode, where T and G are quick keys. Guidepup
 * reaches the page by tabbing once, and on a page whose first Tab stop is
 * the table's scroll region NVDA is left in focus mode, where T is just a
 * letter. NVDA signals a mode change with a sound, not speech, so the test
 * is Control+Home: in browse mode it reads the page's heading, in focus
 * mode it says nothing.
 */
async function browseMode(nvda) {
    for (let tries = 0; tries < 2; tries++) {
        if (/heading/i.test((await nvda.spokenPhraseLog()).join(' '))) return;
        await nvda.perform(nvda.keyboardCommands.toggleBetweenBrowseAndFocusMode);
        await nvda.perform(TOP);
    }
}

/**
 * Open a fixture with NVDA at the top of it in browse mode. Guidepup gets
 * onto the page by clicking the middle of the body and pressing Tab. On
 * the complex table page the middle is the scroll region, so the click
 * focuses it and the Tab leaves the page for Firefox's toolbar. For those
 * two inputs the regions are out of the Tab order and a plain button waits
 * at the end of the page; both are put back as they were straight after.
 * Tab to the table is its own test, below.
 */
async function arrive(page, nvda, name) {
    await page.goto(fixture(name), { waitUntil: 'load' });
    await page.locator('main h1').waitFor();
    await page.evaluate(() => {
        document.querySelectorAll('[tabindex="0"]').forEach((e) => {
            e.dataset.tabindex = '0';
            e.removeAttribute('tabindex');
        });
        document.body.insertAdjacentHTML('beforeend', '<button id="sr-arrive">Arrive</button>');
    });
    await nvda.navigateToWebContent();
    await page.evaluate(() => {
        document.getElementById('sr-arrive').remove();
        document.querySelectorAll('[data-tabindex]').forEach((e) => {
            e.setAttribute('tabindex', e.dataset.tabindex);
            delete e.dataset.tabindex;
        });
        document.activeElement?.blur();
    });
    await nvda.perform(TOP);
    await browseMode(nvda);
    await nvda.clearSpokenPhraseLog();
    await nvda.perform(TOP);
}

test('complex table: each cell is read with its spanning header, column header and row header', async ({ page, nvda }, testInfo) => {
    const k = nvda.keyboardCommands;
    await arrive(page, nvda, 'complex-table');
    const heard = await walk(nvda, 'nvda', 'complex-table', [
        ['T (next table)', () => nvda.perform(k.moveToNextTable)],
        ['Down arrow (from the caption to the first cell)', () => nvda.next()],
        ['Control+Alt+Down', () => nvda.perform(k.moveToNextRow)],
        ['Control+Alt+Down', () => nvda.perform(k.moveToNextRow)],
        ['Control+Alt+Right', () => nvda.perform(k.moveToNextColumn)],
        ['Control+Alt+Right', () => nvda.perform(k.moveToNextColumn)],
        ['Control+Alt+Right', () => nvda.perform(k.moveToNextColumn)],
        ['Control+Alt+Down', () => nvda.perform(k.moveToNextRow)],
    ], testInfo);

    heardAll(heard[1].said, ['table', 'Sales by region and quarter'], 'the table is named by its caption');
    const cells = heard.slice(2);
    heardAll(landedOn(cells, 'North'), ['North'], 'the row header is read on entering its row');
    heardAll(landedOn(cells, '10'), ['First half', 'Q1'], 'North, Q1 is read with its headers');
    heardAll(landedOn(cells, '30'), ['Second half', 'Q3'], 'North, Q3 is read with its headers');
    heardAll(landedOn(cells, '50'), ['South'], 'South, Q3 is read with its row header');
});

test('chart: the picture is read with its title and description, then the data table beneath it', async ({ page, nvda }, testInfo) => {
    const k = nvda.keyboardCommands;
    await arrive(page, nvda, 'bar');
    const heard = await walk(nvda, 'nvda', 'bar', [
        ['G (next graphic)', () => nvda.perform(k.moveToNextGraphic)],
        ['Down arrow', () => nvda.next()],
        ['T (next table)', () => nvda.perform(k.moveToNextTable)],
        ['Down arrow (from the caption to the first cell)', () => nvda.next()],
        ['Control+Alt+Down', () => nvda.perform(k.moveToNextRow)],
        ['Control+Alt+Right', () => nvda.perform(k.moveToNextColumn)],
    ], testInfo);

    heardAll(heard[1].said, ['graphic', 'Apples harvested per year', 'Values range from 980'], 'the picture');
    // Axis ticks, the axis title and a value only the drawing has: the picture is its title and description.
    heardNone(heard[1].said, ['500', 'Apples (kg)', '1,550'], 'the drawing inside the picture');
    heardAll(heard[2].said, ['Data table', 'expanded'], 'the disclosure after the picture');
    heardAll(heard[3].said, ['table', 'Apples harvested per year'], 'the data table is named by its caption');
    // Only the steps inside the table: the picture's own labels include the same numbers.
    const cells = heard.slice(4);
    heardAll(landedOn(cells, '2021'), ['2021'], 'the row header is read on entering its row');
    heardAll(landedOn(cells, '1,200'), ['Apples (kg)'], 'a value is read with its column header');
});

test('collapsed chart table: the disclosure says it is collapsed', async ({ page, nvda }, testInfo) => {
    const k = nvda.keyboardCommands;
    await arrive(page, nvda, 'collapsed');
    const heard = await walk(nvda, 'nvda', 'collapsed', [
        ['G (next graphic)', () => nvda.perform(k.moveToNextGraphic)],
        ['Down arrow', () => nvda.next()],
    ], testInfo);

    heardAll(heard[1].said, ['graphic', 'Apples harvested per year'], 'the picture');
    heardAll(heard[2].said, ['Data table', 'collapsed'], 'the disclosure after the picture');
});

test('Tab to the table: the scroll region is named by the caption, and the table can be read from there', async ({ page, nvda }, testInfo) => {
    const k = nvda.keyboardCommands;
    await arrive(page, nvda, 'complex-table');
    const heard = await walk(nvda, 'nvda', 'tab-to-table', [
        ['Tab', () => nvda.perform(k.readNextFocusableItem)],
        ['Control+Alt+Down', () => nvda.perform(k.moveToNextRow)],
        ['Down arrow', () => nvda.next()],
        ['Escape', () => nvda.perform(k.exitFocusMode)],
        ['Down arrow', () => nvda.next()],
        ['Control+Alt+Down', () => nvda.perform(k.moveToNextRow)],
    ], testInfo);

    heardAll(heard[1].said, ['Sales by region and quarter'], 'the scroll region is named by the caption');
});
