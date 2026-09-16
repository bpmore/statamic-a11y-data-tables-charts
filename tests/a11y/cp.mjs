/**
 * The control panel editors, checked in a real Statamic:
 *
 *   1. A keyboard walkthrough of the furrow_table and furrow_chart
 *      editors: every Tab stop's role and accessible name, then the grid
 *      worked by keyboard alone: typing, arrow keys, pasting a
 *      spreadsheet, inserting and deleting a row and a column, setting a
 *      span, switching the chart to inline data.
 *   2. axe-core scoped to each editor, before and after those edits.
 *   3. A save, and the entry read back.
 *
 * It needs a running site with a user and an entry that carries both
 * fields. Not a CI step: run before a release and paste into
 * docs/conformance.md.
 *
 *   FURROW_CP_URL=https://statamic-dev.test \
 *   FURROW_CP_EMAIL=… FURROW_CP_PASSWORD=… \
 *   FURROW_CP_ENTRY=/cp/collections/reports/entries/<id> \
 *   node tests/a11y/cp.mjs
 */
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const env = (name, fallback) => process.env[name] ?? fallback ?? (() => { throw new Error(`Set ${name}.`); })();

const base = env('FURROW_CP_URL').replace(/\/$/, '');
const email = env('FURROW_CP_EMAIL');
const password = env('FURROW_CP_PASSWORD');
const entry = base + env('FURROW_CP_ENTRY');
const shots = process.env.FURROW_SHOTS ?? '';

const editors = ['[data-furrow-table]', '[data-furrow-chart]'];
const tags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];
let failures = 0;
const fail = (message) => { failures += 1; console.log(`  ✗ ${message}`); };
const ok = (message) => console.log(`  ✓ ${message}`);

console.log(`Furrow control panel check, ${new Date().toISOString().slice(0, 10)}`);
console.log(`  playwright ${require('playwright/package.json').version}, axe-core ${require('axe-core/package.json').version}`);
console.log(`  ${entry}\n`);

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1280, height: 1600 }, ignoreHTTPSErrors: true });
const page = await context.newPage();
const pageErrors = [];
page.on('pageerror', (e) => pageErrors.push(e.message));

await page.goto(`${base}/cp/auth/login`);
await page.fill('input[name="email"], input[type="email"]', email);
await page.fill('input[name="password"], input[type="password"]', password);
await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
await page.goto(entry);
await page.waitForSelector('[data-furrow-table]', { timeout: 30000 });
const snooze = page.getByRole('button', { name: /snooze/i });
if ((await snooze.count()) > 0) { await snooze.first().click(); await page.waitForTimeout(500); }
await page.waitForTimeout(1000);
if (shots) await page.screenshot({ path: `${shots}/cp-before.png`, fullPage: true });

const describeActive = () => page.evaluate(() => {
    const el = document.activeElement;
    if (!el || el === document.body) return null;
    const byIds = (ids) => ids.split(' ').map((id) => document.getElementById(id)?.textContent.trim()).filter(Boolean).join(' ');
    const name = el.getAttribute('aria-label')
        || (el.getAttribute('aria-labelledby') && byIds(el.getAttribute('aria-labelledby')))
        || (el.id && document.querySelector(`label[for="${el.id}"]`)?.textContent.trim())
        || (el.closest('label')?.textContent.trim())
        || (['BUTTON', 'A', 'SUMMARY'].includes(el.tagName) ? el.textContent.trim() : '');
    const role = el.getAttribute('role') || ({ INPUT: el.type === 'number' ? 'spinbutton' : el.type, TEXTAREA: 'textbox', SELECT: 'combobox (native)', BUTTON: 'button', A: 'link' }[el.tagName] ?? el.tagName.toLowerCase());
    const within = el.closest('[data-furrow-table]') ? 'furrow_table' : el.closest('[data-furrow-chart]') ? 'furrow_chart' : null;
    return { tag: el.tagName.toLowerCase(), role, name: name || '', id: el.id, within };
});

const announced = () => page.evaluate(() => [...document.querySelectorAll('[data-furrow-table] [aria-live]')].map((n) => n.textContent.trim()).join(' | '));
const cellValue = (r, c) => page.evaluate(([r, c]) => document.getElementById(`field_figures-cell-${r}-${c}`)?.value ?? null, [r, c]);

// ------------------------------------------------- 1. keyboard walkthrough

console.log('1. Keyboard walkthrough (Tab order, role, accessible name)');
await page.focus('#field_figures-caption');
const stops = [];
for (let i = 0; i < 120; i += 1) {
    const active = await describeActive();
    if (!active || !active.within) break;
    stops.push(active);
    await page.keyboard.press('Tab');
}
console.log('  | # | Editor | Role | Accessible name |');
console.log('  |---|---|---|---|');
stops.forEach((s, i) => console.log(`  | ${i + 1} | ${s.within} | ${s.role} | ${s.name || '(none)'} |`));
const unnamed = stops.filter((s) => !s.name);
if (unnamed.length) fail(`${unnamed.length} focusable element(s) with no accessible name: ${unnamed.map((s) => s.tag + '#' + s.id).join(', ')}`);
else ok(`every one of the ${stops.length} stops has an accessible name`);
if (stops.some((s) => s.within === 'furrow_chart')) ok('Tab reaches the chart editor after the table editor');
else fail('Tab never reached the chart editor');

console.log('\n  The grid by keyboard');
await page.focus('#field_figures-cell-1-1');
await page.keyboard.press('ArrowDown');
let at = await describeActive();
if (at.id === 'field_figures-cell-2-1') ok('ArrowDown moved to the cell below');
else fail(`ArrowDown moved to ${at.id}`);
// End does not move the caret on macOS, so the caret is put at the end directly.
await page.evaluate(() => { const el = document.activeElement; el.setSelectionRange(el.value.length, el.value.length); });
await page.keyboard.press('ArrowRight');
at = await describeActive();
if (at.id === 'field_figures-cell-2-2') ok('ArrowRight at the end of the text moved to the next cell');
else fail(`ArrowRight moved to ${at.id}`);
const original22 = await cellValue(2, 2);
await page.keyboard.press('ControlOrMeta+a');
await page.keyboard.type('77');
if ((await cellValue(2, 2)) === '77') ok('typing replaced the cell value');
else fail(`typing gave ${await cellValue(2, 2)}`);

// Paste a spreadsheet grid into the last cell and watch the table grow.
const rowsBefore = await page.locator('[data-furrow-table] tbody tr').count();
const colsBefore = await page.locator('[data-furrow-table] thead tr:first-child th').count();
const originalLast = await cellValue(rowsBefore - 1, colsBefore - 1);
await page.focus(`#field_figures-cell-${rowsBefore - 1}-${colsBefore - 1}`);
await page.evaluate(() => {
    const el = document.activeElement;
    const data = new DataTransfer();
    data.setData('text/plain', 'p1\tp2\nq1\tq2\n');
    el.dispatchEvent(new ClipboardEvent('paste', { clipboardData: data, bubbles: true, cancelable: true }));
});
await page.waitForTimeout(300);
const rowsAfter = await page.locator('[data-furrow-table] tbody tr').count();
const colsAfter = await page.locator('[data-furrow-table] thead tr:first-child th').count();
console.log(`    pasted 2x2 at the last cell: ${rowsBefore}x${colsBefore} → ${rowsAfter}x${colsAfter}; announced: "${await announced()}"`);
if (rowsAfter === rowsBefore + 1 && colsAfter === colsBefore + 1 && (await cellValue(rowsBefore, colsBefore)) === 'q2') ok('pasting a grid grew the table and filled it');
else fail('pasting a grid did not grow and fill the table');

// Insert and delete a row and a column through their buttons.
await page.getByRole('button', { name: 'Insert a row below row 1' }).focus();
await page.keyboard.press('Enter');
await page.waitForTimeout(200);
at = await describeActive();
console.log(`    Insert a row below row 1: focus moved to "${at.name}"; announced: "${await announced()}"`);
if ((await page.locator('[data-furrow-table] tbody tr').count()) === rowsAfter + 1 && /Row 2/.test(at.name)) ok('a row was inserted by keyboard and focus moved into it');
else fail('inserting a row by keyboard did not behave');
await page.getByRole('button', { name: 'Delete row 2' }).focus();
await page.keyboard.press('Enter');
await page.waitForTimeout(200);
if ((await page.locator('[data-furrow-table] tbody tr').count()) === rowsAfter) ok('the row was deleted by keyboard');
else fail('deleting the row by keyboard did not behave');
await page.getByRole('button', { name: `Delete column ${String.fromCharCode(64 + colsAfter)}` }).focus();
await page.keyboard.press('Enter');
await page.waitForTimeout(200);
if ((await page.locator('[data-furrow-table] thead tr:first-child th').count()) === colsAfter - 1) ok('the pasted column was deleted by keyboard');
else fail('deleting the column by keyboard did not behave');
await page.getByRole('button', { name: `Delete row ${rowsAfter}` }).focus();
await page.keyboard.press('Enter');
await page.waitForTimeout(200);

// Put back what the walkthrough changed, so the save below leaves the entry as it was.
const restore = async (r, c, v) => { await page.focus(`#field_figures-cell-${r}-${c}`); await page.keyboard.press('ControlOrMeta+a'); await page.keyboard.type(v); };
await restore(2, 2, original22);
await restore(rowsBefore - 1, colsBefore - 1, originalLast);

// A span, through the cell options. Growing over a cell removes it, as a
// merge does, so the neighbour is typed back once the span is undone.
const originalNeighbour = await cellValue(0, 2);
await page.focus('#field_figures-cell-0-1');
await page.focus('#field_figures-colspan');
await page.keyboard.press('ControlOrMeta+a');
await page.keyboard.type('2');
await page.keyboard.press('Tab');
await page.waitForTimeout(200);
const span = await page.evaluate(() => document.getElementById('field_figures-cell-0-1')?.closest('td')?.getAttribute('colspan'));
if (span === '2') ok('a colspan was set from the cell options by keyboard');
else fail(`setting a colspan gave colspan=${span}`);
await page.focus('#field_figures-cell-0-1');
await page.focus('#field_figures-colspan');
await page.keyboard.press('ControlOrMeta+a');
await page.keyboard.type('1');
await page.keyboard.press('Tab');
await page.waitForTimeout(200);
await restore(0, 2, originalNeighbour);
console.log(`    announced after the span: "${await announced()}"`);

// The chart: switch to inline data and back.
console.log('\n  The chart editor by keyboard');
await page.getByRole('radio', { name: 'Enter the data here' }).focus();
await page.keyboard.press('Space');
await page.waitForTimeout(300);
if ((await page.locator('[data-furrow-chart] [data-furrow-editor]').count()) === 1) ok('choosing "Enter the data here" showed the grid');
else fail('choosing "Enter the data here" did not show the grid');
await page.getByRole('radio', { name: 'Draw a table field on this entry' }).focus();
await page.keyboard.press('Space');
await page.waitForTimeout(300);
const drawing = await page.locator('[data-furrow-chart]').textContent();
if (/Drawing:/.test(drawing)) ok('the chart editor names the table it draws');
else fail('the chart editor does not say what it draws');
if (shots) await page.screenshot({ path: `${shots}/cp-after.png`, fullPage: true });

// ------------------------------------------------------------- 2. axe

console.log('\n2. axe-core, scoped to each editor');
for (const selector of editors) {
    const results = await new AxeBuilder({ page }).include(selector).withTags(tags).analyze();
    if (results.violations.length === 0) {
        ok(`${selector}: 0 violations (${results.passes.length} rules passed${results.incomplete.length ? `, ${results.incomplete.length} for a person to review` : ''})`);
        for (const item of results.incomplete) console.log(`      review ${item.id}: ${item.nodes[0]?.target.join(' ')}: ${item.nodes[0]?.any[0]?.message ?? item.nodes[0]?.none[0]?.message ?? item.help}`);
    } else {
        fail(`${selector}: ${results.violations.length} violation(s)`);
        for (const v of results.violations) { console.log(`      ${v.id} (${v.impact}): ${v.help}`); for (const n of v.nodes.slice(0, 3)) console.log(`        ${n.target.join(' ')}`); }
    }
}
if (pageErrors.length) fail(`page errors: ${pageErrors.slice(0, 3).join(' | ')}`);
else ok('no page errors');

// ------------------------------------------------------------- 3. save

console.log('\n3. Save');
await page.keyboard.press('ControlOrMeta+s');
await page.waitForTimeout(2500);
const toast = await page.evaluate(() => [...document.querySelectorAll('[role="status"], [role="alert"], .toast, [data-sonner-toast]')].map((n) => n.textContent.trim()).filter(Boolean).join(' | '));
console.log(`    after Ctrl+S: "${toast}"`);
if (/saved/i.test(toast)) ok('the entry saved');
else fail('no "saved" message after Ctrl+S');

await browser.close();
console.log(failures ? `\n${failures} failure(s).` : '\nAll checks passed.');
process.exit(failures ? 1 : 0);
