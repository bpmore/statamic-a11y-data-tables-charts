/**
 * What both readers' specs share: the fixture URLs, a walk that records
 * what was spoken after each keystroke, and the checks on what was heard.
 */
import { expect } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));

export const fixture = (name) => pathToFileURL(join(here, '..', 'fixtures', `${name}.html`)).href;

/**
 * Open a fixture and put the reader at the top of its content.
 */
export async function open(page, reader, name) {
    await page.goto(fixture(name), { waitUntil: 'load' });
    await page.locator('main h1').waitFor();
    await reader.navigateToWebContent();
}

/**
 * Run each step and keep what the reader said in answer to it. A step is a
 * label and a function that presses something. The whole walk is written
 * to logs/<reader>-<name>.txt before any check runs, so a failure still
 * leaves the transcript behind.
 */
export async function walk(reader, readerName, name, steps, testInfo) {
    const heard = [];
    for (const [label, press] of steps) {
        const before = (await reader.spokenPhraseLog()).length;
        await press();
        const said = (await reader.spokenPhraseLog()).slice(before).join(' | ');
        heard.push({ label, said });
    }
    const text = heard.map((h) => `${h.label}\n    ${h.said}`).join('\n');
    mkdirSync(join(here, 'logs'), { recursive: true });
    writeFileSync(join(here, 'logs', `${readerName}-${name}.txt`), `${text}\n`);
    await testInfo.attach(`${readerName}-${name}`, { body: text, contentType: 'text/plain' });
    return heard;
}

/**
 * The step whose speech included `value` as a whole word: the cell the
 * reader landed on, wherever in the walk that turned out to be.
 */
export function landedOn(heard, value) {
    const word = new RegExp(`(^|[^\\d,.])${value.replace(/[.,]/g, '\\$&')}([^\\d,.]|$)`);
    return heard.find((h) => word.test(h.said))?.said ?? '';
}

/** Soft, so one walk reports every missing phrase, not just the first. */
export function heardAll(said, phrases, what) {
    for (const phrase of phrases) {
        expect.soft(said.toLowerCase(), `${what}: "${phrase}"`).toContain(phrase.toLowerCase());
    }
}
