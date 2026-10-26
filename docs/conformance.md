# A11y Data Tables and Charts: WCAG 2.1 AA conformance note

**Product:** `bpmore/statamic-a11y-data-tables-charts` 0.1.3 (26 October 2026)
**Target:** WCAG 2.1 Level AA, with 2.2 AA noted where met
**Date:** 15 September 2026
**Prepared by:** Had A Farm LLC, from the addon's own checks

## 1. Scope of the claim

The claim covers what the addon itself produces: the markup of the rendered
table, the markup of the rendered chart with its data table, the stylesheet
`{{ a11y_data:styles }}` inlines, and the two control panel editors,
`a11y_table` and `a11y_chart`.

It does not cover:

- **The page around the output.** The site's theme, its colours, its fonts and
  the rest of the page are the site's. Text in the chart is `currentColor` and
  inherits the page's font, so its contrast is the page's contrast.
- **What authors type.** A caption that reads "Table 1" passes the caption
  check. Whether a caption, a header or a summary is a good one is editorial.
- **Statamic's own control panel.** Where a Statamic component fell short,
  the addon used a native element instead, and §7 says where.
- **A palette the site has set to fail.** The check refuses a failing palette
  at three points, but it checks only what it is told: series colours against
  the configured background. A site whose pages are not the colour it
  configured is outside this claim.

Automated checks are not conformance. This note records what tools found and
what a scripted keyboard walkthrough found. §5 records what two real screen
readers said when a script drove them through the fixture pages. That is
not the same as a person using them, and §5 says where the difference
matters.

## 2. What was tested, with what

| Surface | Method | Tools and versions | Date |
|---|---|---|---|
| Table and chart markup | 65 Pest tests asserting the output contract: header pairing by id (asserted as pairs), scope, the corner cell, no ARIA on the table, the scroll region, class-based alignment, distinct patterns, dashes and markers, the data table under every option, no script or handler, title and desc, text as text, currentColor, palette rejection at 2.90:1 and acceptance at 3.05:1 against hand-computed luminance | Pest 4, PHP 8.4, `composer test` | 2026-09-15 |
| Rendered pages | axe-core over eight fixture pages (every chart type, negatives and a gap, a collapsed table, a complex table, a wide table, twelve series) at 320, 768 and 1280 px; no sideways page scroll at 320 px; forced colours read back; print layout; the scroll region by keyboard | axe-core 4.13.0, Playwright 1.63.0, Chromium (Playwright build), `php tests/a11y/render-fixtures.php && node tests/a11y/frontend.mjs` | 2026-09-15 |
| Control panel editors | Scripted keyboard walkthrough of both editors on a real Statamic 6.32: every Tab stop's role and name; typing, arrow keys, pasting a 2x2 grid, inserting and deleting a row and a column, setting and undoing a span, switching the chart's data source; axe-core scoped to each editor; a save and a read-back | Playwright 1.63.0, axe-core 4.13.0, `node tests/a11y/cp.mjs` | 2026-09-15 |
| Grid editing rules | 13 Node tests over the pure editing module: layout with spans, inserting and deleting rows and columns across spans, merging and unmerging, TSV parsing, filling from a paste | `node --test` | 2026-09-15 |
| Assistive technology | NVDA with Firefox and VoiceOver with WebKit, driven by script through the complex table, a bar chart and a collapsed chart table, every phrase recorded and checked against the headers, names and states this note claims | NVDA 2026.2 (Guidepup build) on Windows Server 2025, Firefox 155 (Playwright build); VoiceOver on macOS 26, WebKit 26.6 (Playwright build); Guidepup 0.36.4, `@guidepup/playwright` 0.20.0, `.github/workflows/screen-readers.yml` | 2026-10-26 |

Results on the date above: **65 of 65** Pest tests; **39 checks, 0 failed** on
the fixture pages (24 axe runs, 8 overflow checks, 4 forced-colour readings,
print, 2 keyboard checks); **0 violations** from axe on both editors and
**every one of 53 Tab stops named**; the entry saved and read back unchanged.

axe reported items for a person to review, all of one kind: it cannot
determine the background behind SVG `<text>` or behind a label over a
gradient button. Those are the page's colours, not the addon's; see §1.

## 3. Level A

Status: **Meets**, held by the tests and checks named above. **Meets (by
construction)**, the design cannot fail it and no tool checks it. **N/A**,
does not apply, with the reason.

| SC | Criterion | Status | How, and what holds it |
|---|---|---|---|
| 1.1.1 | Non-text Content | Meets | The SVG is `role="img"` named by `<title>` and described by `<desc>`, and every value it draws is in the data table beneath it, which is real text. A chart cannot render without its table: `ChartRendererTest` tries sixteen option combinations. |
| 1.3.1 | Info and Relationships | Meets | `<caption>` on every table. `<th scope>` on simple tables; `id`/`headers` on complex ones, asserted as resolved pairs in `TableRendererTest`. Header rows in `<thead>`. Empty corner is `<td>`. No ARIA duplicating native table semantics. In the editors, every cell input is named from the visible column and row headers by `aria-labelledby`; settings are native labelled controls; the cell options are a `<fieldset>` with a `<legend>`. |
| 1.3.2 | Meaningful Sequence | Meets (by construction) | The table's DOM order is the reading order. The chart's `<title>` and `<desc>` are its first children; the legend, axes, marks and then the table follow. |
| 1.3.3 | Sensory Characteristics | Meets | Nothing in the output or the editor says "the blue bar" or "on the right": series are named in the legend and the table. |
| 1.4.1 | Use of Color | Meets | Bars get one fill pattern per series, lines one dash array and one marker shape per series, and the legend shows them. `ChartRendererTest` asserts five distinct patterns, five distinct dash arrays and five distinct marker shapes for five series, and that the pattern definitions differ in what they draw. Under `forced-colors: active` every fill becomes a system colour and the patterns still read: `frontend.mjs` reads the computed values back, and screenshots are kept. |
| 1.4.2 | Audio Control | N/A | No audio. |
| 2.1.1 | Keyboard | Meets | Front end: the scroll region is `tabindex="0"` and arrow keys scroll it (`frontend.mjs` §5); `<details>` is native. Control panel: every action in both editors is a button, input, select or checkbox; the walkthrough in `cp.mjs` types, moves with arrows, pastes, inserts, deletes, spans and saves by keyboard alone. |
| 2.1.2 | No Keyboard Trap | Meets | The walkthrough tabs through both editors and out the far side (53 stops, then focus leaves). Arrow keys in a cell move to a neighbour only when one exists and the caret is at the edge; otherwise the key does what it does in a text input. |
| 2.1.4 | Character Key Shortcuts | Meets (by construction) | No single-character shortcuts. Arrow keys and Enter in a cell act only while that cell is focused. |
| 2.2.1 | Timing Adjustable | N/A | No time limits. |
| 2.2.2 | Pause, Stop, Hide | N/A | Nothing moves, blinks or updates on its own. |
| 2.3.1 | Three Flashes | N/A | Nothing flashes. |
| 2.4.1 | Bypass Blocks | Meets (by construction) | The table's region is a landmark named by the caption; the chart's is named "Data table: caption", so a page with both has two distinct landmarks (`landmark-unique` passes). |
| 2.4.2 | Page Titled | N/A | The addon renders a fragment, not a page. |
| 2.4.3 | Focus Order | Meets | Front end: the region, then the `<details>` summary. Editors: caption, column settings left to right, cells row by row, row settings, cell options, add row, add column; then the chart's type, source, table or grid, summary. The walkthrough table in `cp.mjs` is the record. |
| 2.4.4 | Link Purpose | N/A | No links. |
| 2.5.1 | Pointer Gestures | Meets (by construction) | No gestures. Spans are set in number inputs, not by dragging. |
| 2.5.2 | Pointer Cancellation | Meets (by construction) | Every action fires on click, none on pointer down. |
| 2.5.3 | Label in Name | Meets | Every button's accessible name begins with its visible text ("Delete" is named "Delete column B"). |
| 2.5.4 | Motion Actuation | N/A | No motion input. |
| 3.1.1 | Language of Page | N/A | Fragment. |
| 3.2.1 | On Focus | Meets | Focusing a cell records it as the active cell for the options fieldset; it changes no value. |
| 3.2.2 | On Input | Meets | Changing a column type, a header checkbox or a span changes only the table and announces what changed. Changing the chart's data source shows or hides the grid; the value is kept either way. |
| 3.3.1 | Error Identification | Meets | A missing caption, a missing table field, a bad chart type and a failing palette each produce a validation message on the field, in words, from `ValidTable`, `ValidChart` and `PaletteContrast`. `FieldtypesTest` asserts each. |
| 3.3.2 | Labels or Instructions | Meets | Every input has a label; the caption, summary and palette fields carry instructions saying what they are for. |
| 4.1.1 | Parsing | Meets | The rendered fragments and the editors' markup are well formed; axe's parsing checks pass on every fixture. |
| 4.1.2 | Name, Role, Value | Meets | Native elements throughout. The one ARIA role on the front end is `region` on the wrapper, named by `aria-labelledby`; the SVG is `role="img"` named and described by ids that resolve, asserted in `ChartRendererTest`. |

## 4. Level AA

| SC | Criterion | Status | How, and what holds it |
|---|---|---|---|
| 1.3.4 | Orientation | Meets (by construction) | Nothing is locked to an orientation. |
| 1.3.5 | Identify Input Purpose | N/A | No inputs collect information about the user. |
| 1.4.3 | Contrast (Minimum) | Partly the page's | Text in the chart and the table is `currentColor` on the page's background: its contrast is the page's. The addon's own colours are the series colours, which are non-text (1.4.11). The editors' text is Statamic's palette; axe found no failure. |
| 1.4.4 | Resize Text | Meets | The chart is a `viewBox` SVG scaled to its container; the table is text. At 320 px (a 1280 px page at 400% zoom) no fixture scrolls sideways: the wide table scrolls inside its own region (`frontend.mjs` §2). |
| 1.4.5 | Images of Text | Meets | Every label in the SVG is a `<text>` element, asserted in `ChartRendererTest`; no text is drawn as a path. |
| 1.4.10 | Reflow | Meets, with the exception the criterion allows | Data tables are the criterion's own named exception to two-dimensional scrolling. The table scrolls inside a region that is a Tab stop; no pattern converts the table to blocks, because that destroys the header associations. Everything else reflows. |
| 1.4.11 | Non-text Contrast | Meets | Every series colour is checked against the background at 3:1 by `PaletteCheck`, using WCAG's relative luminance formula verified in `ContrastTest` against hand-computed values (2.9006:1 refused, 3.0504:1 accepted). Refused at blueprint save, at entry save and before render. Bars carry a `currentColor` outline as well. The editors' focus rings are 2px solid on every control. |
| 1.4.12 | Text Spacing | Meets (by construction) | No fixed heights around text; the table and the editors' controls grow with their content. |
| 1.4.13 | Content on Hover or Focus | Meets (by construction) | No tooltips, no hover content. `ChartRendererTest` asserts no `<title>` on any mark and no `title` attribute anywhere in the SVG. |
| 2.4.5 | Multiple Ways | N/A | Fragment. |
| 2.4.6 | Headings and Labels | Meets | The caption is the table's heading and the chart's title. Editor labels say what each control is for a specific column or row ("Type of column B", "Delete row 3"). |
| 2.4.7 | Focus Visible | Meets | Front end: a 3px `currentColor` outline on the region and on the `<details>` summary. Editors: a 2px outline on every native control and the kit's own ring on its buttons. |
| 3.1.2 | Language of Parts | N/A | No language changes are introduced. |
| 3.2.3 | Consistent Navigation | N/A | Fragment. |
| 3.2.4 | Consistent Identification | Meets | The same controls have the same names in every row and column, and the same grid is used for a table field and for a chart's inline data. |
| 3.3.3 | Error Suggestion | Meets | Each validation message says what to do: add a caption, choose a table field, use one of the listed chart types, or which colour fails and by how much. |
| 3.3.4 | Error Prevention | Meets (by construction) | Saving an entry is Statamic's own flow, with its own confirmation. The editor announces what a span removed. |
| 4.1.3 | Status Messages | Meets | The grid has a polite live region. Pasting, inserting, deleting and spanning announce what happened and the new size; a refused span announces why. `cp.mjs` prints the announcements. |

WCAG 2.2 AA criteria met by the same evidence: 2.4.11 Focus Not Obscured
(nothing sticky covers the editors), 2.5.7 Dragging Movements (nothing is
dragged), 2.5.8 Target Size (the kit's `xs` buttons are 24px tall; native
checkboxes are Statamic's), 3.2.6 Consistent Help (N/A), 3.3.7 Redundant
Entry (the chart draws the table field rather than asking for the numbers
again), 3.3.8 Accessible Authentication (N/A).

## 5. Assistive technology

Scripted, on 26 October 2026, against 0.1.1's renderers and again against
0.1.2's. Guidepup drove each reader with the keys its users use and
recorded every phrase it spoke. The
specs are in `tests/a11y/screen-readers/`; the full transcripts are kept as
an artifact of every run of `.github/workflows/screen-readers.yml`. All 7
checks passed on the first attempt: 4 on NVDA, 3 on VoiceOver.

**Complex table** (`complex-table.html`: a spanning header row over a
column header row, row headers down the first column). Moving between cells
announces the spanning header, the column header and the row header:

| Move | NVDA with Firefox | VoiceOver with WebKit |
|---|---|---|
| To the table | "Sales by region and quarter, region, Sales by region and quarter, table, with 4 rows and 5 columns, caption, Sales by region and quarter" | "Sales by region and quarter table 5 columns, 4 rows" |
| Down to North | "row 3, North" | "row 3 of 4 North" |
| Right to North, Q1 | "First half Q 1, column 2, 10" | "First half, Q1, and North 10 column 2 of 5" |
| Right to North, Q3 | "Second half Q 3, column 4, 30" | "Second half and Q3 30 column 4 of 5" |
| Down to South, Q3 | "South, row 4, 50" | "row 4 of 4 South 50" |

**Chart** (`bar.html`, `collapsed.html`). The picture is read with its
title and its description, the disclosure after it says whether it is open,
and the table inside reads its headers:

| Move | NVDA with Firefox | VoiceOver with WebKit |
|---|---|---|
| To the picture | "figure, Apples harvested per year, graphic, Values range from 980 (in 2023) to 1,730 (in 2024)." | "Apples harvested per year Values range from 980 (in 2023) to 1,730 (in 2024). image" |
| On to the disclosure | "button, expanded, Data table:, Apples harvested per year" (collapsed fixture: "button, collapsed, …") | "Data table: Apples harvested per year expanded summary" (collapsed fixture: "collapsed summary") |
| Into the table, to 2021's value | "Apples (kg), column 2, 1,200" | "Apples (kg) 1,200 column 2 of 2" |

**Found, and changed in 0.1.2:**

- **NVDA read the chart's labels before its name.** On reaching the
  picture with G, NVDA in Firefox first read every text label inside it:
  "figure, Apples harvested per year, 0, 500, 1,000, 1,500, 2,000, Apples
  (kg), 2021, 2022, 2023, 2024, 1,200, 1,550, 980, 1,730", then the name and
  description. VoiceOver read only the name and description. The drawing is
  now one `aria-hidden` group beside the title and desc, and NVDA says
  "figure, Apples harvested per year, graphic, Values range from 980 (in
  2023) to 1,730 (in 2024)." The specs now fail if a label is spoken.

**Found, and not yet changed:**

- **Tab onto the table reads all of it under NVDA.** When the scroll region
  takes focus, NVDA reads the whole table, cell by cell, before the user
  moves. After it, the down arrow goes into the table and reads it
  normally. On a long table that first reading is long. It is the same with
  the region as `role="group"`, named by `aria-label` instead of the
  caption, and with the Tab stop on the table instead of its wrapper, so it
  is how NVDA with Firefox treats a focused container rather than something
  the markup controls. The Tab stop stays: it is how a keyboard user
  reaches a wide table to scroll it (2.1.1, §3).
- **VoiceOver adds a word on one header cell.** On "Region" (row 2,
  column 1, a column header in the second header row) VoiceOver says
  "South Region". The markup gives that cell no row header. It is the same
  with `scope` alone, with `id` and `headers` alone, and with both, so it
  is not a choice the renderer makes. It does not happen when the author
  puts "Region" in the top-left cell spanning both header rows: VoiceOver
  then reads the column without the stray word ("row 3 of 4 North"), and
  the cells still read their spanning, column and row headers. The README
  gives authors that tip. Checked on 26 October 2026 against the renderer's
  own output for that layout.

**What this does not cover:**

- A person. A script records what a reader says; it does not judge whether
  the experience is good, and it does not try what a user might try instead.
- Safari and a stock Firefox. The browsers are Playwright's builds of WebKit
  and Firefox. VoiceOver with the Safari app was not run.
- The control panel editors. They need a running Statamic site, and the
  runners have none. Their keyboard walkthrough (§2) is not a screen reader
  test.
- JAWS, TalkBack, VoiceOver on iOS and Narrator.

## 6. Print and zoom

Print was emulated in Chromium on the grouped bar fixture: the chart and its
table are both laid out, and a PDF and a screenshot are kept under
`tests/a11y/screenshots/`. A collapsed data table is not printed by Chromium,
which is why the config and the field setting say to leave it open on a site
that prints.

400% zoom was checked as a 320 px viewport: no fixture scrolls the page
sideways, and the wide table scrolls inside its region.

## 7. Known limits

- **Statamic's combobox** (the kit's `Select`) fails axe's `aria-allowed-attr`
  on its anchor in 6.32 and is two Tab stops. The chart editor uses native
  `<select>` elements instead. Statamic's own fields on the same form are
  Statamic's.
- **Category labels on a crowded axis** are thinned (every nth) when they
  cannot fit. Every category is in the table.
- **More than eight series** cycle through the eight patterns, dashes and
  markers with the palette's colours. The table is the reliable reading.
- **Automatic summaries** name the lowest and highest values and where they
  are. They do not describe trends. An author who wants a trend described
  writes the summary.
