# Changelog

## 0.1.0 (unreleased)

The first build, against the brief in `docs/build-furrow.md`.

- `furrow_table` fieldtype: a caption (required, with no setting that waives it), header rows and header columns, column types, spans and abbreviations, edited in a grid that works from the keyboard and accepts a pasted spreadsheet.
- `furrow_chart` fieldtype: bar, grouped bar and line, drawn from a `furrow_table` field on the same entry (the default) or from data of its own, as server-rendered SVG with no script, with the data table beneath it in every case.
- The table renderer pairs headers with cells by `scope` on simple tables and by `id`/`headers` on complex ones, renders the empty corner as `<td>`, aligns by column type through a class, and wraps the table in a keyboard-reachable scroll region named by the caption.
- The chart renderer tells series apart by fill pattern, dash array and marker shape as well as colour, names the SVG through `<title>` and `<desc>`, writes a one-sentence summary when the author has not, draws text as text, and honours `forced-colors: active`.
- A palette check (WCAG relative luminance, 3:1 minimum) refuses a failing palette when a chart field's blueprint settings are saved, when an entry is saved, and before every render.
- `{{ furrow:styles }}`, `{{ furrow:table }}` and `{{ furrow:chart }}` tags; fields also render themselves when printed in a template.
- A conformance note in `docs/conformance.md`.
