/**
 * VoiceOver with WebKit, using the keys a VoiceOver user reaches for:
 * VO+Command+T for the next table, VO+Command+G for the next graphic,
 * VO+Shift+Down to go into the table, VO+arrows to move between cells,
 * and VO+Right to read on. VO is Control+Option.
 */
import { voiceOverTest as test } from '@guidepup/playwright';
import { open, walk, landedOn, heardAll, heardNone } from './walk.js';

// `true` keeps the hints too, which is where VoiceOver reads a description.
test.use({ voiceOverStartOptions: { capture: true } });

test('complex table: each cell is read with its spanning header, column header and row header', async ({ page, voiceOver }, testInfo) => {
    const k = voiceOver.keyboardCommands;
    await open(page, voiceOver, 'complex-table');
    const heard = await walk(voiceOver, 'voiceover', 'complex-table', [
        ['VO+Command+T (next table)', () => voiceOver.perform(k.findNextTable)],
        ['VO+Shift+Down (into the table)', () => voiceOver.interact()],
        ['VO+Down', () => voiceOver.perform(k.moveDown)],
        ['VO+Down', () => voiceOver.perform(k.moveDown)],
        ['VO+Right', () => voiceOver.next()],
        ['VO+Right', () => voiceOver.next()],
        ['VO+Right', () => voiceOver.next()],
        ['VO+Down', () => voiceOver.perform(k.moveDown)],
    ], testInfo);

    heardAll(heard[1].said, ['table', 'Sales by region and quarter'], 'the table is named by its caption');
    const cells = heard.slice(2);
    heardAll(landedOn(cells, 'North'), ['North'], 'the row header is read on entering its row');
    heardAll(landedOn(cells, '10'), ['First half', 'Q1'], 'North, Q1 is read with its headers');
    heardAll(landedOn(cells, '30'), ['Second half', 'Q3'], 'North, Q3 is read with its headers');
    heardAll(landedOn(cells, '50'), ['South'], 'South, Q3 is read with its row header');
});

test('chart: the picture is read with its title and description, then the data table beneath it', async ({ page, voiceOver }, testInfo) => {
    const k = voiceOver.keyboardCommands;
    await open(page, voiceOver, 'bar');
    const heard = await walk(voiceOver, 'voiceover', 'bar', [
        ['VO+Command+G (next graphic)', () => voiceOver.perform(k.findNextGraphic)],
        ['VO+Right', () => voiceOver.next()],
        ['VO+Command+T (next table)', () => voiceOver.perform(k.findNextTable)],
        ['VO+Shift+Down (into the table)', () => voiceOver.interact()],
        ['VO+Down', () => voiceOver.perform(k.moveDown)],
        ['VO+Right', () => voiceOver.next()],
    ], testInfo);

    heardAll(heard[1].said, ['image', 'Apples harvested per year', 'Values range from 980'], 'the picture');
    // Axis ticks, the axis title and a value only the drawing has: the picture is its title and description.
    heardNone(heard[1].said, ['500', 'Apples (kg)', '1,550'], 'the drawing inside the picture');
    heardAll(heard[2].said, ['Data table', 'expanded'], 'the disclosure after the picture');
    heardAll(heard[3].said, ['table', 'Apples harvested per year'], 'the data table is named by its caption');
    // Only the steps inside the table: the picture's own description includes the same numbers.
    const cells = heard.slice(4);
    heardAll(landedOn(cells, '2021'), ['2021'], 'the row header is read on entering its row');
    heardAll(landedOn(cells, '1,200'), ['Apples (kg)'], 'a value is read with its column header');
});

test('collapsed chart table: the disclosure says it is collapsed', async ({ page, voiceOver }, testInfo) => {
    const k = voiceOver.keyboardCommands;
    await open(page, voiceOver, 'collapsed');
    const heard = await walk(voiceOver, 'voiceover', 'collapsed', [
        ['VO+Command+G (next graphic)', () => voiceOver.perform(k.findNextGraphic)],
        ['VO+Right', () => voiceOver.next()],
    ], testInfo);

    heardAll(heard[1].said, ['image', 'Apples harvested per year'], 'the picture');
    heardAll(heard[2].said, ['Data table', 'collapsed'], 'the disclosure after the picture');
});
