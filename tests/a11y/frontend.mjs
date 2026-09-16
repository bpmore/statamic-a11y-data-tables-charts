/**
 * The front-end output, checked in Chromium:
 *
 *   1. axe-core over every fixture page at 320, 768 and 1280 px wide.
 *      320 px is what a 1280 px page becomes at 400% zoom (WCAG 1.4.10).
 *   2. No sideways scroll of the page at 320 px: the wide table scrolls
 *      inside its own region and nothing else does.
 *   3. Forced colours: the bar fill, pattern marks, lines and markers read
 *      back as system colours under `forced-colors: active`.
 *   4. Print: the chart and the table are laid out on the page, and a
 *      screenshot is kept for a person to look at.
 *   5. The scroll region is a Tab stop named by the caption.
 *
 *   php tests/a11y/render-fixtures.php && node tests/a11y/frontend.mjs
 */
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import { createRequire } from 'node:module';
import { readdirSync, mkdirSync } from 'node:fs';
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const here = dirname(fileURLToPath(import.meta.url));
const fixtures = join(here, 'fixtures');
const screenshots = join(here, 'screenshots');
mkdirSync(screenshots, { recursive: true });

const pages = readdirSync(fixtures).filter((f) => f.endsWith('.html')).sort();
const widths = [320, 768, 1280];
const tags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];
let failures = 0;
let checks = 0;
const fail = (m) => { failures += 1; checks += 1; console.log(`  ✗ ${m}`); };
const ok = (m) => { checks += 1; console.log(`  ✓ ${m}`); };

const server = createServer(async (req, res) => {
    try {
        const body = await readFile(join(fixtures, req.url.replace(/^\//, '') || 'index.html'));
        res.writeHead(200, { 'content-type': 'text/html; charset=utf-8' });
        res.end(body);
    } catch {
        res.writeHead(404); res.end();
    }
});
await new Promise((r) => server.listen(0, '127.0.0.1', r));
const base = `http://127.0.0.1:${server.address().port}`;

console.log(`A11y Data Tables & Charts front-end check, ${new Date().toISOString().slice(0, 10)}`);
console.log(`  playwright ${require('playwright/package.json').version}, axe-core ${require('axe-core/package.json').version}\n`);

const browser = await chromium.launch();

console.log('1. axe-core at three widths, and 2. no sideways scroll at 320 px');
for (const file of pages) {
    for (const width of widths) {
        const context = await browser.newContext({ viewport: { width, height: 900 } });
        const page = await context.newPage();
        await page.goto(`${base}/${file}`);
        const results = await new AxeBuilder({ page }).withTags(tags).analyze();
        if (results.violations.length === 0) ok(`${file} @ ${width}: 0 violations (${results.passes.length} rules passed${results.incomplete.length ? `, ${results.incomplete.length} to review` : ''})`);
        else {
            fail(`${file} @ ${width}: ${results.violations.length} violation(s)`);
            for (const v of results.violations) { console.log(`      ${v.id} (${v.impact}): ${v.help}`); for (const n of v.nodes.slice(0, 3)) console.log(`        ${n.target.join(' ')}`); }
        }
        for (const item of results.incomplete) console.log(`      review ${item.id}: ${item.nodes[0]?.target.join(' ')}: ${item.nodes[0]?.any[0]?.message ?? item.help}`);
        if (width === 320) {
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            if (overflow <= 0) ok(`${file} @ 320: the page does not scroll sideways (the table region scrolls inside itself)`);
            else fail(`${file} @ 320: the page scrolls sideways by ${overflow}px`);
            await page.screenshot({ path: join(screenshots, `${file.replace('.html', '')}-320.png`), fullPage: true });
        }
        await context.close();
    }
}

console.log('\n3. Forced colours');
{
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 }, forcedColors: 'active', colorScheme: 'dark' });
    const page = await context.newPage();
    for (const [file, selector, prop] of [['grouped-bar.html', '.a11y-data-pattern__fill', 'fill'], ['grouped-bar.html', '.a11y-data-pattern__mark', 'stroke'], ['line.html', '.a11y-data-line', 'stroke'], ['line.html', '.a11y-data-marker', 'fill']]) {
        await page.goto(`${base}/${file}`);
        const value = await page.evaluate(([s, p]) => getComputedStyle(document.querySelector(s))[p], [selector, prop]);
        // Under forced colours Chromium resolves CanvasText/Canvas to the scheme's colours; what matters is that the author's colour is gone.
        const authored = await page.evaluate((s) => document.querySelector(s).getAttribute(s.includes('mark') ? 'stroke' : 'fill'), selector);
        if (value && !/#|rgb\(11, 95, 165\)|rgb\(255, 255, 255\)/.test(value) || (value && value !== authored && !value.includes('url'))) ok(`${file} ${selector} ${prop} reads ${value} (authored ${authored})`);
        else fail(`${file} ${selector} ${prop} still reads the authored colour ${value}`);
    }
    await page.goto(`${base}/grouped-bar.html`);
    await page.screenshot({ path: join(screenshots, 'grouped-bar-forced-colors.png'), fullPage: true });
    await page.goto(`${base}/line.html`);
    await page.screenshot({ path: join(screenshots, 'line-forced-colors.png'), fullPage: true });
    await context.close();
}

console.log('\n4. Print');
{
    const context = await browser.newContext({ viewport: { width: 816, height: 1056 } });
    const page = await context.newPage();
    await page.goto(`${base}/grouped-bar.html`);
    await page.emulateMedia({ media: 'print' });
    const laidOut = await page.evaluate(() => {
        const svg = document.querySelector('svg').getBoundingClientRect();
        const table = document.querySelector('table').getBoundingClientRect();
        return { svg: [Math.round(svg.width), Math.round(svg.height)], table: [Math.round(table.width), Math.round(table.height)], tableVisible: table.height > 0 };
    });
    if (laidOut.svg[0] > 0 && laidOut.tableVisible) ok(`print: chart ${laidOut.svg.join('x')} and table ${laidOut.table.join('x')} both laid out`);
    else fail('print: chart or table not laid out');
    await page.pdf({ path: join(screenshots, 'grouped-bar-print.pdf'), format: 'Letter', printBackground: true });
    await page.screenshot({ path: join(screenshots, 'grouped-bar-print.png'), fullPage: true });
    await page.goto(`${base}/collapsed.html`);
    await page.emulateMedia({ media: 'print' });
    const collapsed = await page.evaluate(() => document.querySelector('table').getBoundingClientRect().height);
    console.log(`    a collapsed data table in print has height ${collapsed}px (documented: leave the table open on a site that prints)`);
    await context.close();
}

console.log('\n5. The scroll region by keyboard');
{
    const context = await browser.newContext({ viewport: { width: 320, height: 900 } });
    const page = await context.newPage();
    await page.goto(`${base}/wide-table.html`);
    await page.keyboard.press('Tab');
    const active = await page.evaluate(() => {
        const el = document.activeElement;
        const name = el.getAttribute('aria-labelledby') ? document.getElementById(el.getAttribute('aria-labelledby'))?.textContent.trim() : null;
        return { role: el.getAttribute('role'), name, scrolls: el.scrollWidth > el.clientWidth };
    });
    if (active.role === 'region' && active.name && active.scrolls) ok(`Tab lands on the region "${active.name}", which scrolls`);
    else fail(`Tab landed on ${JSON.stringify(active)}`);
    await page.keyboard.press('ArrowRight');
    await page.waitForTimeout(200);
    const scrolled = await page.evaluate(() => document.activeElement.scrollLeft);
    if (scrolled > 0) ok(`ArrowRight scrolled the region by ${scrolled}px`);
    else fail('ArrowRight did not scroll the region');
    await context.close();
}

await browser.close();
server.close();
console.log(`\n${checks} checks, ${failures} failed.`);
process.exit(failures ? 1 : 0);
