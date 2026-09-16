/**
 * The table's shape, and the edits the editor makes to it. Pure functions
 * over the stored value, with no Vue in them, so they can be tested with
 * `node --test` and reasoned about apart from the component.
 *
 * The stored shape is the one the addon fixes:
 *
 *   { caption, columns: [{ label, type, header }], rows: [{ header, cells: [{ value, colspan, rowspan, abbr }] }] }
 *
 * A row's cells are the cells that *start* in that row, in order. Where
 * they land once spans are applied is `layout()`, computed with the rule
 * HTML uses: left to right, skipping slots a span from above already fills.
 */

export const emptyCell = () => ({ value: '' });

export const emptyColumn = () => ({ label: '', type: 'text', header: false });

export function normalise(value) {
    const v = value && typeof value === 'object' ? value : {};
    const rows = Array.isArray(v.rows)
        ? v.rows.map((r) => ({
              header: !!(r && r.header),
              cells: Array.isArray(r?.cells)
                  ? r.cells.map((c) => normaliseCell(c))
                  : [],
          }))
        : [];
    const width = Math.max(
        Array.isArray(v.columns) ? v.columns.length : 0,
        layout(rows).width,
    );
    const columns = Array.isArray(v.columns) ? v.columns.map((c) => ({ ...emptyColumn(), ...(c || {}) })) : [];
    while (columns.length < width) columns.push(emptyColumn());

    return { caption: typeof v.caption === 'string' ? v.caption : '', columns, rows };
}

function normaliseCell(c) {
    if (c === null || typeof c !== 'object') return { value: c == null ? '' : String(c) };
    const cell = { value: c.value == null ? '' : String(c.value) };
    if (c.colspan > 1) cell.colspan = Number(c.colspan);
    if (c.rowspan > 1) cell.rowspan = Number(c.rowspan);
    if (typeof c.abbr === 'string' && c.abbr.trim() !== '') cell.abbr = c.abbr;
    return cell;
}

/**
 * Where every cell lands. Returns the width, and for each row a list of
 * slots: `{ r, c, i }` (origin row, column, and index in the row's cells)
 * for a cell starting there, `{ covered: {r, c, i} }` for a slot a span
 * fills, and null past the row's end.
 */
export function layout(rows) {
    const slots = [];
    let width = 0;

    rows.forEach((row, r) => {
        slots[r] = slots[r] || [];
        let c = 0;
        row.cells.forEach((cell, i) => {
            while (slots[r][c]) c++;
            const colspan = cell.colspan > 1 ? cell.colspan : 1;
            const rowspan = cell.rowspan > 1 ? cell.rowspan : 1;
            for (let dr = 0; dr < rowspan; dr++) {
                slots[r + dr] = slots[r + dr] || [];
                for (let dc = 0; dc < colspan; dc++) {
                    slots[r + dr][c + dc] = dr === 0 && dc === 0 ? { r, c, i } : { covered: { r, c, i } };
                }
            }
            c += colspan;
            width = Math.max(width, c);
        });
    });

    return { width, slots: slots.slice(0, rows.length).map((row) => row || []) };
}

/** The cell that starts at, or covers, a slot. */
export function cellAt(table, r, c) {
    const slot = layout(table.rows).slots[r]?.[c];
    if (!slot) return null;
    const origin = slot.covered || slot;
    return { origin, cell: table.rows[origin.r].cells[origin.i] };
}

/** A copy of the table, so every edit is a new value the publish form sees as one change. */
const clone = (table) => JSON.parse(JSON.stringify(table));

export function setCaption(table, caption) {
    return { ...clone(table), caption };
}

export function setCell(table, r, i, patch) {
    const t = clone(table);
    const cell = { ...t.rows[r].cells[i], ...patch };
    if (!(cell.colspan > 1)) delete cell.colspan;
    if (!(cell.rowspan > 1)) delete cell.rowspan;
    if (!cell.abbr) delete cell.abbr;
    t.rows[r].cells[i] = cell;
    return t;
}

export function setRowHeader(table, r, header) {
    const t = clone(table);
    t.rows[r].header = !!header;
    return t;
}

export function setColumn(table, c, patch) {
    const t = clone(table);
    t.columns[c] = { ...t.columns[c], ...patch };
    return t;
}

/** A new row after row `r` (or first, for r = -1), with a cell for every slot a span does not already fill. */
export function insertRow(table, r) {
    const t = clone(table);
    const { width, slots } = layout(t.rows);
    const at = r + 1;
    const cells = [];

    for (let c = 0; c < Math.max(width, t.columns.length, 1); c++) {
        // A span from above that also reaches below the new row grows by one.
        const above = at > 0 ? slots[at - 1]?.[c] : null;
        const below = slots[at]?.[c];
        const origin = above ? above.covered || above : null;
        if (origin && below && (below.covered || below).r === origin.r && (below.covered || below).c === origin.c) {
            t.rows[origin.r].cells[origin.i].rowspan = (t.rows[origin.r].cells[origin.i].rowspan || 1) + 1;
            continue;
        }
        // A colspan on the row above is not copied: the new row is plain.
        cells.push(emptyCell());
    }

    t.rows.splice(at, 0, { header: false, cells });
    return t;
}

export function deleteRow(table, r) {
    const t = clone(table);
    if (t.rows.length <= 1) {
        // The last row is emptied rather than removed: a table with no rows has nowhere to type.
        t.rows[0] = { header: t.rows[0].header, cells: t.rows[0].cells.map(() => emptyCell()) };
        return t;
    }

    const { slots } = layout(t.rows);

    // Spans from above that cross this row shrink by one.
    (slots[r] || []).forEach((slot, c) => {
        if (slot && slot.covered && slot.covered.r < r) {
            const cell = t.rows[slot.covered.r].cells[slot.covered.i];
            // Once per cell, at its own column; a colspan covers this row in several slots.
            if (slot.covered.c === c) {
                cell.rowspan = Math.max(1, (cell.rowspan || 1) - 1);
                if (cell.rowspan <= 1) delete cell.rowspan;
            }
        }
    });

    // Cells starting here that span down move to the next row, one shorter.
    const moved = [];
    (slots[r] || []).forEach((slot, c) => {
        if (slot && !slot.covered && slot.r === r) {
            const cell = t.rows[r].cells[slot.i];
            if (cell.rowspan > 1) {
                moved.push({ c, cell: { ...cell, rowspan: cell.rowspan - 1 } });
            }
        }
    });

    t.rows.splice(r, 1);

    if (moved.length && t.rows[r]) {
        // Insert each moved cell so it lands in its own column of the next row.
        const next = t.rows[r];
        moved.forEach(({ c, cell }) => {
            if (!(cell.rowspan > 1)) delete cell.rowspan;
            const { slots: s } = layout(t.rows);
            let index = 0;
            for (let cc = 0; cc < c; cc++) {
                const sl = s[r]?.[cc];
                if (sl && !sl.covered && sl.r === r) index = sl.i + 1;
            }
            next.cells.splice(index, 0, cell);
        });
    }

    return t;
}

/** A new column after slot `c` (or first, for c = -1). A span crossing the seam grows; otherwise each row gets a cell. */
export function insertColumn(table, c) {
    const t = clone(table);
    const { slots } = layout(t.rows);
    const at = c + 1;

    t.rows.forEach((row, r) => {
        const left = at > 0 ? slots[r]?.[at - 1] : null;
        const right = slots[r]?.[at];
        const lo = left ? left.covered || left : null;
        const ro = right ? right.covered || right : null;

        if (lo && ro && lo.r === ro.r && lo.c === ro.c) {
            // Inside one cell's span: the cell grows, once, at its origin row.
            if (lo.r === r) {
                const cell = t.rows[lo.r].cells[lo.i];
                cell.colspan = (cell.colspan || 1) + 1;
            }
            return;
        }

        // Index in this row's cells: after the last cell that starts at or before slot `at - 1`.
        let index = 0;
        for (let cc = 0; cc < at; cc++) {
            const sl = slots[r]?.[cc];
            if (sl && !sl.covered && sl.r === r) index = sl.i + 1;
        }
        row.cells.splice(index, 0, emptyCell());
    });

    t.columns.splice(at, 0, emptyColumn());
    return t;
}

export function deleteColumn(table, c) {
    const t = clone(table);
    const { width, slots } = layout(t.rows);
    if (Math.max(width, t.columns.length) <= 1) {
        t.rows.forEach((row) => { row.cells = row.cells.length ? [emptyCell()] : row.cells; });
        return t;
    }

    t.rows.forEach((row, r) => {
        const slot = slots[r]?.[c];
        if (!slot) return;
        const origin = slot.covered || slot;
        if (origin.r !== r) return; // handled at the origin row
        const cell = t.rows[r].cells[origin.i];
        if ((cell.colspan || 1) > 1) {
            cell.colspan -= 1;
            if (cell.colspan <= 1) delete cell.colspan;
        } else {
            t.rows[r].cells.splice(origin.i, 1);
        }
    });

    t.columns.splice(c, 1);
    return t;
}

/**
 * Text pasted from a spreadsheet: tab-separated cells, newline-separated
 * rows. Quoted cells (a value with a tab or newline in it) are unwrapped.
 */
export function parseTsv(text) {
    const rows = [];
    let row = [];
    let cell = '';
    let quoted = false;
    const s = String(text).replace(/\r\n?/g, '\n');

    for (let i = 0; i < s.length; i++) {
        const ch = s[i];
        if (quoted) {
            if (ch === '"' && s[i + 1] === '"') { cell += '"'; i++; }
            else if (ch === '"') quoted = false;
            else cell += ch;
            continue;
        }
        if (ch === '"' && cell === '') quoted = true;
        else if (ch === '\t') { row.push(cell); cell = ''; }
        else if (ch === '\n') { row.push(cell); rows.push(row); row = []; cell = ''; }
        else cell += ch;
    }
    row.push(cell);
    rows.push(row);

    // A trailing newline leaves one empty row behind.
    while (rows.length > 1 && rows[rows.length - 1].every((v) => v === '')) rows.pop();

    return rows;
}

/** Whether pasted text is a grid worth filling from, rather than one value. */
export function looksLikeGrid(text) {
    return /[\t\n]/.test(String(text).replace(/\r\n?$/, '').replace(/\n$/, ''));
}

/**
 * Fill from slot (r, c) with a grid of values, adding rows and columns as
 * needed. Cells covered by a span are skipped, not overwritten.
 */
export function fillFrom(table, r, c, grid) {
    let t = clone(table);
    const needRows = r + grid.length;
    const needCols = c + Math.max(0, ...grid.map((g) => g.length));

    while (t.rows.length < needRows) t = insertRow(t, t.rows.length - 1);
    while (Math.max(layout(t.rows).width, t.columns.length) < needCols) t = insertColumn(t, Math.max(layout(t.rows).width, t.columns.length) - 1);

    grid.forEach((values, dr) => {
        values.forEach((value, dc) => {
            const hit = cellAt(t, r + dr, c + dc);
            if (hit && hit.origin.r === r + dr && hit.origin.c === c + dc) {
                t.rows[hit.origin.r].cells[hit.origin.i].value = value;
            }
        });
    });

    return t;
}

/** A starting table for a field with nothing in it yet. */
export function starter({ firstRowHeader = true, firstColumnHeader = false } = {}, rows = 3, cols = 3) {
    return {
        caption: '',
        columns: Array.from({ length: cols }, (_, c) => ({ label: '', type: c === 0 ? 'text' : 'number', header: c === 0 && firstColumnHeader })),
        rows: Array.from({ length: rows }, (_, r) => ({ header: r === 0 && firstRowHeader, cells: Array.from({ length: cols }, emptyCell) })),
    };
}

/** Whether there is nothing in the table at all. */
export function isBlank(table) {
    return table.caption.trim() === '' && table.rows.every((row) => row.cells.every((cell) => String(cell.value).trim() === ''));
}

/**
 * Insert an empty cell into row `r` so that it lands at slot `c`, given
 * the current layout: after the last cell of that row that starts before
 * slot `c`.
 */
function insertCellAtSlot(t, r, c) {
    const { slots } = layout(t.rows);
    let index = 0;
    for (let cc = 0; cc < c; cc++) {
        const sl = slots[r]?.[cc];
        if (sl && !sl.covered && sl.r === r) index = sl.i + 1;
    }
    t.rows[r].cells.splice(index, 0, emptyCell());
}

/**
 * Change how far a cell reaches, the way a spreadsheet merges: the cells
 * it grows over are removed (their text is lost, and the editor says so),
 * and the slots it shrinks away from get empty cells back. A span is
 * refused, with a reason, when it would cut through another cell's span;
 * the table is returned unchanged with `error` set.
 *
 * @returns {{ table: object, error: string|null }}
 */
export function setSpan(table, r, i, { colspan, rowspan }) {
    let t = clone(table);
    const cell = t.rows[r].cells[i];
    const oldCs = cell.colspan > 1 ? cell.colspan : 1;
    const oldRs = cell.rowspan > 1 ? cell.rowspan : 1;
    const before = layout(t.rows);
    const c = (before.slots[r] || []).findIndex((s) => s && !s.covered && s.r === r && s.i === i);
    if (c < 0) return { table, error: 'That cell could not be found.' };

    const width = Math.max(before.width, t.columns.length);
    const cs = Math.max(1, Math.min(Number(colspan) || oldCs, width - c));
    const rs = Math.max(1, Math.min(Number(rowspan) || oldRs, t.rows.length - r));
    if (cs === oldCs && rs === oldRs) return { table, error: null };

    // 1. Shrink to one slot, giving every freed slot an empty cell.
    delete cell.colspan;
    delete cell.rowspan;
    const freed = [];
    for (let dr = 0; dr < oldRs; dr++) for (let dc = 0; dc < oldCs; dc++) if (dr || dc) freed.push([r + dr, c + dc]);
    // Row by row, left to right, so each insertion sees the layout it changed.
    freed.sort((a, b) => a[0] - b[0] || a[1] - b[1]).forEach(([fr, fc]) => insertCellAtSlot(t, fr, fc));

    // 2. Grow: every slot in the region must hold a one-slot cell of its own.
    const after = layout(t.rows);
    const region = [];
    for (let dr = 0; dr < rs; dr++) {
        for (let dc = 0; dc < cs; dc++) {
            if (!dr && !dc) continue;
            const slot = after.slots[r + dr]?.[c + dc];
            if (!slot) { region.push(null); continue; }
            const origin = slot.covered || slot;
            const other = t.rows[origin.r].cells[origin.i];
            if (slot.covered || (other.colspan > 1) || (other.rowspan > 1)) {
                return { table, error: 'That span would cut through another cell that already spans. Reduce that cell first.' };
            }
            region.push(origin);
        }
    }

    const lost = [];
    // Remove from the highest index down so earlier indexes stay valid.
    region.filter(Boolean).sort((a, b) => b.r - a.r || b.i - a.i).forEach((origin) => {
        const removed = t.rows[origin.r].cells.splice(origin.i, 1)[0];
        if (removed && String(removed.value).trim() !== '') lost.push(removed.value);
    });

    const target = t.rows[r].cells.find((x) => x === cell) ? cell : t.rows[r].cells[i];
    if (cs > 1) target.colspan = cs; else delete target.colspan;
    if (rs > 1) target.rowspan = rs; else delete target.rowspan;

    return { table: t, error: null, lost };
}
