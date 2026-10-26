# Decisions

Choices that are not obvious from the code, with the alternatives turned down.
Read this before changing an approach. Add an entry when a change settles a
question.

## The name is A11y Data Tables & Charts, and it goes all the way down

Decided 15 September 2026, before any release. The working name was
Furrow, in the style of the other addons. The marketplace name says what
the product is, and since nothing had shipped, the technical names changed
with it rather than leaving a second name underneath: package
`bpmore/statamic-a11y-data-tables-charts`, namespace
`Bpmore\A11yDataTablesCharts`, fieldtypes `a11y_table` and `a11y_chart`,
tag `a11y_data`, and the `a11y-data-` prefix on every class and id. The
alternative, renaming the product and keeping `furrow_*` inside, was turned
down because every stored blueprint and template would have carried a name
nobody could explain.

On 26 October 2026 the display name became "A11y Data Tables and Charts",
with "and" for the "&", to match the marketplace listing. Only the words
people read changed; every technical name above stayed as it was.

## The palette lives in config, with a per-field override

Decided 15 September 2026. The brief says a failing palette is refused at save
time in the control panel but not who sets it. Three readings: site config
only, site config plus a per-field override in the blueprint, or a palette
chosen per chart by the author. The middle one was chosen, on the grounds that
authors should not be picking colours and that one site can still want a
second palette for one kind of chart. The override is checked when the
blueprint field is saved (`PaletteContrast`, a data-aware rule on the series
list that reads the background from the same form), and the effective palette
is checked again when an entry is saved (`ValidChart`) and before every render
(`ChartRenderer`), so a config file edited by hand is caught too.

## A bar chart of several series is drawn grouped

`bar` and `grouped_bar` are both accepted. A `bar` chart of a dataset with
several series draws them all, grouped, rather than dropping every series but
the first. Dropping data silently would be worse than the naming being loose.
The `figure` carries the requested type as a class either way.

## Spans merge, they do not push

Setting a colspan of 2 on a cell in the editor removes the cell to its right,
as a spreadsheet merge does, and reducing it gives an empty cell back. The
first version pushed the neighbours right instead, which grew the table by a
column every time a span was set and left a phantom column definition behind
after it was undone. The editor says what it removed.

## Native selects in the chart editor

The kit's combobox is two Tab stops (the anchor and a clear button) and its
anchor fails axe's `aria-allowed-attr` rule in Statamic 6.32. For two short
choices a native `<select>` is one stop and has nothing to review, so the
chart editor uses those. The grid's own controls are native for the same
reason. Text inputs, textareas and buttons are the kit's.

## Cell inputs are named by real headers, not aria-label

Each cell input has `aria-labelledby` pointing at the column letter header
and the row number header, so its name is "Column B Row 3" from text that is
on the screen. The conventions forbid `aria-label` on non-interactive
elements; an input is interactive, but a name from visible text is better
still and costs nothing.

## The chart's data table region is named "Data table: <caption>"

A page that renders a table field and a chart of it has two scroll regions.
Named by the caption alone they would share a name, and axe's
`landmark-unique` rule flags that. The chart's copy takes the "Data table:"
prefix from its `<summary>` into the region's name, so the two differ and the
caption is still referenced, as the brief asks.

## No preview in the control panel

The chart is rendered by PHP, so a preview would mean a control panel route
that renders on every change. It is left out of the first release: the front
end is where the chart is checked, and Statamic's live preview already shows
it there.

## Statamic 6.31 is the floor

The brief asks for Statamic 5 compatibility where it is free. It is not free:
the editors are Vue 3 components on the 6.x UI kit. The floor is 6.31 because
that is the oldest version Composer will install today; every earlier 6.x
release is blocked by a security advisory, so a lower floor could not be
tested.
