# Furrow: Build Brief

Package `bpmore/statamic-furrow`, namespace `Bpmore\Furrow`, repo `~/Herd/statamic-furrow`.
Read `build-conventions.md` first. Paid addon, target $49.

---

## 1. What it is

Two fieldtypes that make it hard to publish an inaccessible data table or chart: `furrow_table` and `furrow_chart`. One dataset, two renderings, both correct.

**Market check, verified September 2026:** no accessible table addon exists. The only table-adjacent listing is Table on Steroids at $12, a CSV parser. **Nothing in 497 addons renders charts at all.**

This is the most common accessibility failure in content published by universities and health systems, and the buyer already trusts you on exactly this.

## 2. Architecture

`src/Table/` and `src/Chart/` are pure PHP: they take a dataset structure and return markup. No Statamic dependencies. The fieldtypes and tags wrap them.

Shared dataset shape, since a chart can reference a table field:

```php
[
  'caption' => string,
  'columns' => [ ['label' => string, 'type' => 'text'|'number', 'header' => bool] ],
  'rows'    => [ ['header' => bool, 'cells' => [ ['value' => mixed, 'colspan' => int, 'rowspan' => int, 'abbr' => ?string] ]] ],
]
```

## 3. Table requirements

**Caption is required.** The field does not validate without one. This is the single highest-value constraint in the addon, and it should not be overridable.

- Header cells render `<th>` with an explicit `scope` of `col` or `row`
- **Complex tables** (more than one header row, or headers spanning) require `id` and `headers` attribute pairing. `scope` alone is insufficient once headers span, and this is where every hand-built table fails
- `abbr` on header cells when the label is long
- No empty `<th>` in the corner position: it renders as an empty `<td>`
- Column type drives alignment through a class, never through a `style` attribute
- **Responsive overflow uses the correct pattern**: a wrapping element with `overflow-x: auto`, `tabindex="0"`, `role="region"`, and an accessible name referencing the caption. That combination makes a scrollable table keyboard reachable. Do not use any pattern that converts table markup to blocks at small widths: it destroys the header associations the rest of this addon exists to create
- No `role="table"` on a real `<table>`. Never add ARIA that duplicates native semantics

## 4. Chart requirements

**Server-rendered inline SVG. Zero JavaScript.** These sites are statically cached and the chart must work in the cache, in print, and with scripting off.

v1 supports exactly three types: `bar`, `grouped_bar`, `line`. No pie, no donut. If asked to add pie later, the answer is that a pie chart cannot satisfy 1.4.1 for more than a few slices without labels that make the chart redundant.

- **Every chart renders a visible data table beneath or beside it.** Not `alt` text, not `aria-label`, not a `<desc>`. A real table, using the table renderer above. It may be inside a `<details>` element, expanded by default, configurable to collapsed
- `role="img"` on the SVG with `aria-labelledby` pointing at a `<title>`, and `aria-describedby` at a `<desc>` containing a one-sentence summary of the trend
- **Series must be distinguishable without color** (WCAG 1.4.1). Bars get fill patterns, lines get dash arrays plus distinct point markers. Direct labels where the series count allows
- **Non-text contrast** (WCAG 1.4.11): every rendered element that carries meaning is checked at 3:1 against its background. The palette checker is a core class with its own tests, and **it must reject a failing palette at save time in the CP rather than warning at render time**
- `forced-colors: active` support: use `currentColor` and system color keywords where possible, never rely on fill alone
- No tooltips. Hover-only data fails 1.4.13 and keyboard access. All values are in the data table
- Text in the SVG is real `<text>`, never paths

## 5. Fieldtype behavior

- `furrow_table`: grid editor. Toggle header row and header column. Add and remove rows and columns by keyboard. Paste from a spreadsheet parses TSV
- `furrow_chart`: either inline data or a reference to a `furrow_table` field on the same entry. The reference path is preferred and should be the default, because one dataset with two renderings cannot drift
- Both fieldtypes are keyboard operable end to end, including the grid. Test this early, because grid editors are where CP accessibility usually dies

## 6. Discriminating tests

1. **Complex header pairing.** A table with two header rows where one cell spans two columns produces `id` and `headers` attributes that resolve correctly for every data cell. Assert the actual pairs, not that attributes exist.
2. **Scope correctness on a row-header table.** Row headers get `scope="row"`, column headers `scope="col"`, and a corner cell is a `<td>`.
3. **Contrast rejection.** A palette with a 2.9:1 pair is rejected. A 3.05:1 pair is accepted. Verify against a hand-computed WCAG relative luminance value, not against your own implementation's output.
4. **Non-color encoding.** A five-series grouped bar chart renders five distinct pattern identifiers, and a five-series line chart renders five distinct dash arrays plus five distinct marker shapes.
5. **Data table always present.** No configuration combination produces a chart without its table. Try to make it fail.
6. **Zero JavaScript.** Rendered chart output contains no `<script>` and no event handler attributes.
7. **Reference integrity.** A chart referencing a table field updates when the table changes, and degrades visibly rather than fatally when the referenced field is deleted.

## 7. Definition of done

Conventions file section 10, plus:

- A published conformance note in the repo: which success criteria the output satisfies and how. This is a sales asset as much as an engineering one
- Screen reader verification of one complex table: NVDA plus Firefox and VoiceOver plus Safari, confirming header announcements on cell navigation
- Print stylesheet verified: chart and table both readable on paper
- 400% zoom verified

## 8. Explicit non-goals

Not a spreadsheet. No formulas, no sorting, no filtering, no pagination. Not a dashboard. Not a live data connection: data is entered or pasted, not fetched.
