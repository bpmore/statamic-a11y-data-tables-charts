# A11y Data Tables & Charts for Statamic

Two fieldtypes that make it hard to publish an inaccessible data table or
chart: `a11y_table` and `a11y_chart`. One dataset, two renderings, both
correct.

A table cannot be saved without a caption. A complex table gets `id` and
`headers` pairing, which is where hand-built tables fail. A chart is
server-rendered SVG with no script, tells its series apart without colour,
refuses a palette that fails contrast, and always renders its data table
beneath the picture. There is no setting that removes it.

This is a paid addon for Statamic 6.31 or later on PHP 8.2 or later. It does
not support Statamic 5: the control panel editors are Vue 3 components built
on Statamic 6's UI kit, and there is no Vue 2 version.

## Status

0.1.0, unreleased. Everything in the brief is built and covered by the test
suite. What is not yet done is listed in `docs/conformance.md` §5: screen
reader verification with NVDA and VoiceOver on a released build.

## Install

```
composer require bpmore/statamic-a11y-data-tables-charts
```

Nothing else to run. The control panel assets are built and shipped with the
package. The optional config file is published with:

```
php please vendor:publish --tag=statamic-a11y-data-tables-charts-config
```

## The fields

Add a **A11y data table** field to a blueprint. Authors get a caption input, a
grid of cells with a header row of column letters and a header column of row
numbers, per-column settings (text or number, and whether the column holds row
headers), per-row settings (header row, insert, delete), and cell options for
spans and abbreviations. Pasting rows from a spreadsheet into a cell fills the
grid from there and grows it to fit. Everything works from the keyboard: arrow
keys move between cells, and every button has a name.

Add a **A11y data chart** field to the same blueprint. Authors choose bar, grouped
bar or line, then either pick a A11y data table field on the entry to draw (the
default, so one set of numbers is shown twice and cannot drift) or enter data
in the same grid. An optional summary sentence becomes the picture's
description; left blank, the chart writes one from the numbers.

The chart's series are the table's number columns, named by its header row,
and its categories are the row header column (or the first column when none
is marked).

## Templates

A field renders itself when printed:

```
{{ a11y_data:styles }}

{{ figures }}
{{ picture }}
```

`{{ a11y_data:styles }}` inlines the addon's stylesheet: the scroll region, the
number alignment, the chart scaling, print. Put it in the layout once. The same
file is published to `public/vendor/statamic-a11y-data-tables-charts/css/a11y-data.css` if you
would rather link it.

The parts are there by path: `{{ figures:caption }}`, `{{ figures:rows }}`,
`{{ picture:type }}`, `{{ picture:summary }}`. `{{ picture:missing }}` is true
when the chart could not be drawn (see below).

The tags do the same thing explicitly, and also take a dataset built in the
template:

```
{{ a11y_data:table field="figures" }}
{{ a11y_data:chart field="picture" }}
{{ a11y_data:chart :data="my_dataset" type="line" summary="Up and to the right." }}
{{ a11y_data:chart :data="figures" type="line" caption="The same figures, as lines" }}
```

`:data` takes an array a template built, or a table field's own value, so one
table can be drawn again as another chart type. `caption` replaces the caption
for that rendering; two renderings of one dataset on a page need two captions,
because each is a landmark named by its caption.

## What the output is

**Table.** A `<div role="region" tabindex="0" aria-labelledby="…">` with
`overflow-x: auto`, so a wide table scrolls inside itself and a keyboard can
reach it, named by the caption. Inside, a plain `<table>` with a `<caption>`.
Column headers are `<th scope="col">`, row headers `<th scope="row">`, and an
empty corner is a `<td>`. When there is more than one header row, more than one
header column, or any header that spans, every header gets an `id` and every
cell a `headers` attribute naming exactly the headers that apply to it.
Alignment comes from `a11y-data-cell--number` and `a11y-data-cell--text`, never a
`style` attribute. No ARIA role is put on the table.

**Chart.** A `<figure>` holding an inline `<svg role="img">` named by its
`<title>` (the caption) and described by its `<desc>` (the summary), then a
`<details>` with the same table, rendered by the same renderer. Bars are filled
with one pattern per series; lines get one dash array and one marker shape per
series; a legend shows the same. Every label is a `<text>` element in
`currentColor`. There is no `<script>`, no event handler, no tooltip, and no
text drawn as a path. Under `forced-colors: active` the fills and strokes
become system colours and the patterns still tell the series apart.

## Config

`config/statamic-a11y-data-tables-charts.php` has two settings.

**`palette`**: the background the chart sits on and one colour per series.
Every series colour must reach 3:1 against the background (WCAG 1.4.11). A
palette that does not is refused: the chart field will not save with it, the
entry will not save with it, and the chart will not render with it. A chart
field can override the palette in its blueprint settings, and that override is
checked when the blueprint is saved. The default eight colours clear 3:1 on
white by a margin; if your pages are not white, set the background to what they
are, because that is what the check is against.

**`data_table`**: `open` or `collapsed`. Every chart renders its data table in
a `<details>`; this is whether it starts expanded. A chart field can choose
either in its blueprint settings. Collapsed tables are not printed by most
browsers, so a site that prints its pages should leave this open.

## When a chart cannot be drawn

If the table field a chart names is removed from the entry, or its palette
cannot be read, the page shows a paragraph saying so in the chart's place,
with the class `a11y-data-chart--missing`. Nothing is thrown and nothing is
silently blank. `{{ picture:missing }}` is true in that case.

## What the addon does not do

- It is not a spreadsheet. No formulas, sorting, filtering or pagination.
- It is not a dashboard and does not fetch data. Numbers are typed or pasted.
- It draws bar, grouped bar and line charts. It does not draw pie or donut
  charts, and will not: a pie cannot meet WCAG 1.4.1 past a few slices without
  labels that make the picture redundant.
- It does not check the colours around the chart. The palette check is
  between the series colours and the background you tell it about.
- It does not make an inaccessible caption accessible. "Table 1" passes the
  check; a caption that says what the table shows is the author's job.

## Conformance

`docs/conformance.md` says which WCAG 2.1 success criteria the output
satisfies, how, and what holds each one. It is written for the person who
audits your site, and it says plainly what has and has not been verified.

## Support

Bugs and questions go to
[GitHub issues](https://github.com/bpmore/statamic-a11y-data-tables-charts/issues).
A report that includes the field's stored YAML and the rendered HTML gets an
answer fastest. This addon is maintained by one person; expect a reply within
a few working days, not hours.

The repository is private while the addon is being tested and becomes public
when it is submitted to the Statamic Marketplace, so the link above works
from that point on.

## Licence

Proprietary. See `LICENSE.md`. One licence per production site; local
development and CI are free.
