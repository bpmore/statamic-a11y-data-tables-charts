/**
 * NVDA with Firefox, in browse mode, using the keys an NVDA user reaches
 * for: T for the next table, G for the next graphic, Control+Alt+arrows to
 * move between cells, and the down arrow to read on.
 */
import { nvdaTest as test } from '@guidepup/playwright';
import { open, walk, landedOn, heardAll } from './walk.js';

test.use({ nvdaStartOptions: { capture: true } });

test('complex table: each cell is read with its spanning header, column header and row header', async ({ page, nvda }, testInfo) => {
    const k = nvda.keyboardCommands;
    await open(page, nvda, 'complex-table');
    const heard = await walk(nvda, 'nvda', 'complex-table', [
        ['T (next table)', () => nvda.perform(k.moveToNextTable)],
        ['Control+Alt+Down', () => nvda.perform(k.moveToNextRow)],
        ['Control+Alt+Down', () => nvda.perform(k.moveToNextRow)],
        ['Control+Alt+Right', () => nvda.perform(k.moveToNextColumn)],
        ['Control+Alt+Right', () => nvda.perform(k.moveToNextColumn)],
        ['Control+Alt+Right', () => nvda.perform(k.moveToNextColumn)],
        ['Control+Alt+Down', () => nvda.perform(k.moveToNextRow)],
    ], testInfo);

    heardAll(heard[0].said, ['table', 'Sales by region and quarter'], 'the table is named by its caption');
    heardAll(landedOn(heard, 'North'), ['North'], 'the row header is read on entering its row');
    heardAll(landedOn(heard, '10'), ['First half', 'Q1'], 'North, Q1 is read with its headers');
    heardAll(landedOn(heard, '30'), ['Second half', 'Q3'], 'North, Q3 is read with its headers');
    heardAll(landedOn(heard, '50'), ['South'], 'South, Q3 is read with its row header');
});

test('chart: the picture is read with its title and description, then the data table beneath it', async ({ page, nvda }, testInfo) => {
    const k = nvda.keyboardCommands;
    await open(page, nvda, 'bar');
    const heard = await walk(nvda, 'nvda', 'bar', [
        ['G (next graphic)', () => nvda.perform(k.moveToNextGraphic)],
        ['Down arrow', () => nvda.next()],
        ['T (next table)', () => nvda.perform(k.moveToNextTable)],
        ['Control+Alt+Down', () => nvda.perform(k.moveToNextRow)],
        ['Control+Alt+Right', () => nvda.perform(k.moveToNextColumn)],
    ], testInfo);

    heardAll(heard[0].said, ['graphic', 'Apples harvested per year', 'Values range from 980'], 'the picture');
    heardAll(heard[1].said, ['Data table', 'expanded'], 'the disclosure after the picture');
    heardAll(heard[2].said, ['table', 'Apples harvested per year'], 'the data table is named by its caption');
    heardAll(landedOn(heard, '2021'), ['2021'], 'the row header is read on entering its row');
    heardAll(landedOn(heard, '1,200'), ['Apples (kg)'], 'a value is read with its column header');
});

test('collapsed chart table: the disclosure says it is collapsed', async ({ page, nvda }, testInfo) => {
    const k = nvda.keyboardCommands;
    await open(page, nvda, 'collapsed');
    const heard = await walk(nvda, 'nvda', 'collapsed', [
        ['G (next graphic)', () => nvda.perform(k.moveToNextGraphic)],
        ['Down arrow', () => nvda.next()],
    ], testInfo);

    heardAll(heard[0].said, ['graphic', 'Apples harvested per year'], 'the picture');
    heardAll(heard[1].said, ['Data table', 'collapsed'], 'the disclosure after the picture');
});
