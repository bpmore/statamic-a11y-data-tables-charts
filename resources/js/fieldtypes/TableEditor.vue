<script setup>
/**
 * The grid: a caption, then a real table of inputs with a header row of
 * column letters and a header column of row numbers, so every cell input
 * has an accessible name ("Column B, Row 3") from real text rather than
 * an aria-label. Column settings (type, row headers) sit in a second
 * header row; row settings (header row, insert, delete) sit in a last
 * column. Cell options (colspan, rowspan, abbr) are a fieldset beneath the
 * grid for whichever cell was last focused, so spans are set by keyboard
 * without a drag.
 *
 * Every edit goes through grid.js and comes back as a whole new value,
 * emitted once, so the publish form sees one field change.
 */
import { computed, nextTick, ref, watch } from 'vue';
import { Button, Field, Input } from '@statamic/cms/ui';
import * as grid from './grid.js';

const props = defineProps({
    modelValue: { type: Object, required: true },
    idBase: { type: String, required: true },
    readOnly: { type: Boolean, default: false },
    captionInstructions: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const table = computed(() => grid.normalise(props.modelValue));
const lay = computed(() => grid.layout(table.value.rows));
const width = computed(() => Math.max(lay.value.width, table.value.columns.length));

const id = (part) => `${props.idBase}-${part}`;
const letter = (c) => {
    let s = '';
    let n = c;
    do { s = String.fromCharCode(65 + (n % 26)) + s; n = Math.floor(n / 26) - 1; } while (n >= 0);
    return s;
};

const announcement = ref('');
function announce(text) {
    announcement.value = '';
    nextTick(() => { announcement.value = text; });
}

function commit(next, message) {
    emit('update:modelValue', next);
    if (message) announce(message);
}

/** The slots of a row that start a cell there, in order. */
function originSlots(r) {
    return (lay.value.slots[r] || []).map((slot, c) => ({ slot, c })).filter(({ slot }) => slot && !slot.covered && slot.r === r);
}

/* Caption */
const caption = computed({
    get: () => table.value.caption,
    set: (v) => commit(grid.setCaption(table.value, v ?? '')),
});

/* Cells */
const cellInput = (r, c) => id(`cell-${r}-${c}`);

function onCellInput(r, i, event) {
    commit(grid.setCell(table.value, r, i, { value: event.target.value }));
}

/** Focus the input of the cell covering slot (r, c), if there is one. */
function focusSlot(r, c) {
    const hit = grid.cellAt(table.value, r, c);
    if (!hit) return false;
    const el = document.getElementById(cellInput(hit.origin.r, hit.origin.c));
    if (!el) return false;
    el.focus();
    return true;
}

function onCellKeydown(r, c, event) {
    const input = event.target;
    const atStart = input.selectionStart === 0 && input.selectionEnd === 0;
    const atEnd = input.selectionStart === input.value.length && input.selectionEnd === input.value.length;
    const hit = grid.cellAt(table.value, r, c);
    const rowspan = hit?.cell.rowspan || 1;
    const colspan = hit?.cell.colspan || 1;

    if (event.key === 'ArrowDown' || event.key === 'Enter') {
        if (focusSlot(r + rowspan, c)) event.preventDefault();
    } else if (event.key === 'ArrowUp') {
        if (r > 0 && focusSlot(r - 1, c)) event.preventDefault();
    } else if (event.key === 'ArrowRight' && atEnd) {
        if (focusSlot(r, c + colspan)) event.preventDefault();
    } else if (event.key === 'ArrowLeft' && atStart) {
        if (c > 0 && focusSlot(r, c - 1)) event.preventDefault();
    }
}

function onPaste(r, c, event) {
    const text = event.clipboardData?.getData('text/plain') ?? '';
    if (!grid.looksLikeGrid(text)) return;

    event.preventDefault();
    const values = grid.parseTsv(text);
    const cols = Math.max(...values.map((v) => v.length));
    commit(grid.fillFrom(table.value, r, c, values), `Pasted ${values.length} ${values.length === 1 ? 'row' : 'rows'} and ${cols} ${cols === 1 ? 'column' : 'columns'} starting at column ${letter(c)}, row ${r + 1}.`);
}

/* Active cell and its options */
const active = ref(null);

function onCellFocus(r, c, i) {
    active.value = { r, c, i };
}

const activeCell = computed(() => (active.value ? table.value.rows[active.value.r]?.cells[active.value.i] ?? null : null));

watch(table, () => {
    if (active.value && !table.value.rows[active.value.r]?.cells[active.value.i]) active.value = null;
});

function setActiveOption(key, raw) {
    if (!active.value) return;
    const { r, i } = active.value;

    if (key === 'abbr') {
        commit(grid.setCell(table.value, r, i, { abbr: String(raw ?? '') }));
        return;
    }

    const cell = table.value.rows[r].cells[i];
    const wanted = Math.max(1, parseInt(raw, 10) || 1);
    const result = grid.setSpan(table.value, r, i, {
        colspan: key === 'colspan' ? wanted : cell.colspan || 1,
        rowspan: key === 'rowspan' ? wanted : cell.rowspan || 1,
    });

    if (result.error) {
        announce(result.error);
        return;
    }

    const lost = result.lost?.length ? ` ${__('Removed')}: ${result.lost.join(', ')}.` : '';
    commit(result.table, `${key === 'colspan' ? __('Columns spanned') : __('Rows spanned')}: ${wanted}.${lost}`);
}

/* Rows */
function insertRow(r) {
    commit(grid.insertRow(table.value, r), `Row ${r + 2} added. ${table.value.rows.length + 1} rows.`);
    nextTick(() => focusSlot(r + 1, 0));
}

function deleteRow(r) {
    const count = table.value.rows.length;
    commit(grid.deleteRow(table.value, r), count <= 1 ? 'Row 1 cleared. A table keeps at least one row.' : `Row ${r + 1} deleted. ${count - 1} rows.`);
    nextTick(() => focusSlot(Math.min(r, table.value.rows.length - 1), 0));
}

function setRowHeader(r, event) {
    commit(grid.setRowHeader(table.value, r, event.target.checked));
}

/* Columns */
function insertColumn(c) {
    commit(grid.insertColumn(table.value, c), `Column ${letter(c + 1)} added. ${width.value + 1} columns.`);
    nextTick(() => focusSlot(0, c + 1));
}

function deleteColumn(c) {
    const count = width.value;
    commit(grid.deleteColumn(table.value, c), count <= 1 ? 'Column A cleared. A table keeps at least one column.' : `Column ${letter(c)} deleted. ${count - 1} columns.`);
    nextTick(() => focusSlot(0, Math.min(c, width.value - 1)));
}

function setColumn(c, patch) {
    commit(grid.setColumn(table.value, c, patch));
}

const columnIndexes = computed(() => Array.from({ length: width.value }, (_, c) => c));
</script>

<template>
    <div class="furrow-editor space-y-4" :data-furrow-editor="idBase">
        <Field
            :id="id('caption')"
            :label="__('Caption')"
            :instructions="captionInstructions || __('What the table shows, in a few words. Screen readers announce it as the table\'s name, and the table will not save without it.')"
            :read-only="readOnly"
            required
        >
            <Input :id="id('caption')" v-model="caption" :read-only="readOnly" autocomplete="off" />
        </Field>

        <div class="furrow-editor__scroll">
            <table class="furrow-editor__grid" :aria-describedby="id('help')">
                <caption class="sr-only">{{ __('Table cells') }}</caption>
                <thead>
                    <tr>
                        <td></td>
                        <th v-for="c in columnIndexes" :key="'h' + c" scope="col" :id="id('col-' + c)">{{ __('Column') }} {{ letter(c) }}</th>
                        <td></td>
                    </tr>
                    <tr class="furrow-editor__settings">
                        <th scope="row" :id="id('colsettings')">{{ __('Column settings') }}</th>
                        <td v-for="c in columnIndexes" :key="'s' + c">
                            <div class="furrow-editor__stack">
                                <label :for="id('type-' + c)" class="sr-only">{{ __('Type of column') }} {{ letter(c) }}</label>
                                <select
                                    :id="id('type-' + c)"
                                    class="furrow-editor__select"
                                    :value="table.columns[c]?.type ?? 'text'"
                                    :disabled="readOnly"
                                    @change="setColumn(c, { type: $event.target.value })"
                                >
                                    <option value="text">{{ __('Text') }}</option>
                                    <option value="number">{{ __('Number') }}</option>
                                </select>
                                <label class="furrow-editor__check">
                                    <input type="checkbox" :checked="!!table.columns[c]?.header" :disabled="readOnly" @change="setColumn(c, { header: $event.target.checked })" />
                                    <span>{{ __('Row headers') }}</span>
                                </label>
                                <span v-if="!readOnly" class="furrow-editor__actions">
                                    <Button size="xs" variant="default" :text="__('Insert after')" :aria-label="__('Insert a column after column') + ' ' + letter(c)" @click="insertColumn(c)" />
                                    <Button size="xs" variant="default" :text="__('Delete')" :aria-label="__('Delete column') + ' ' + letter(c)" @click="deleteColumn(c)" />
                                </span>
                            </div>
                        </td>
                        <td></td>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, r) in table.rows" :key="'r' + r" :class="{ 'furrow-editor__header-row': row.header }">
                        <th scope="row" :id="id('row-' + r)">{{ __('Row') }} {{ r + 1 }}</th>
                        <td
                            v-for="{ slot, c } in originSlots(r)"
                            :key="'c' + r + '-' + c"
                            :colspan="row.cells[slot.i].colspan > 1 ? row.cells[slot.i].colspan : null"
                            :rowspan="row.cells[slot.i].rowspan > 1 ? row.cells[slot.i].rowspan : null"
                            :class="{ 'furrow-editor__header-cell': row.header || table.columns[c]?.header }"
                        >
                            <input
                                :id="cellInput(r, c)"
                                type="text"
                                class="furrow-editor__cell"
                                :value="row.cells[slot.i].value"
                                :aria-labelledby="id('col-' + c) + ' ' + id('row-' + r)"
                                :readonly="readOnly"
                                autocomplete="off"
                                @input="onCellInput(r, slot.i, $event)"
                                @focus="onCellFocus(r, c, slot.i)"
                                @keydown="onCellKeydown(r, c, $event)"
                                @paste="onPaste(r, c, $event)"
                            />
                        </td>
                        <td class="furrow-editor__rowsettings">
                            <div class="furrow-editor__row">
                                <label class="furrow-editor__check">
                                    <input type="checkbox" :checked="row.header" :disabled="readOnly" @change="setRowHeader(r, $event)" />
                                    <span>{{ __('Header row') }}</span>
                                </label>
                                <span v-if="!readOnly" class="furrow-editor__actions">
                                    <Button size="xs" variant="default" :text="__('Insert below')" :aria-label="__('Insert a row below row') + ' ' + (r + 1)" @click="insertRow(r)" />
                                    <Button size="xs" variant="default" :text="__('Delete')" :aria-label="__('Delete row') + ' ' + (r + 1)" @click="deleteRow(r)" />
                                </span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p :id="id('help')" class="furrow-editor__help">
            {{ __('Type in a cell, or paste rows from a spreadsheet into one and the table grows to fit. Arrow keys move between cells. A header row names the columns; a row headers column names the rows.') }}
        </p>

        <fieldset v-if="activeCell && !readOnly" class="furrow-editor__options">
            <legend>{{ __('Cell options') }}: {{ __('Column') }} {{ letter(active.c) }}, {{ __('Row') }} {{ active.r + 1 }}</legend>
            <div class="furrow-editor__options-grid">
                <div>
                    <label :for="id('colspan')">{{ __('Columns spanned') }}</label>
                    <input :id="id('colspan')" type="number" min="1" :max="width - active.c" class="furrow-editor__cell" :value="activeCell.colspan || 1" @change="setActiveOption('colspan', $event.target.value)" />
                </div>
                <div>
                    <label :for="id('rowspan')">{{ __('Rows spanned') }}</label>
                    <input :id="id('rowspan')" type="number" min="1" :max="table.rows.length - active.r" class="furrow-editor__cell" :value="activeCell.rowspan || 1" @change="setActiveOption('rowspan', $event.target.value)" />
                </div>
                <div v-if="table.rows[active.r].header || table.columns[active.c]?.header">
                    <label :for="id('abbr')">{{ __('Short form for screen readers') }}</label>
                    <input :id="id('abbr')" type="text" class="furrow-editor__cell" :value="activeCell.abbr || ''" @change="setActiveOption('abbr', $event.target.value)" />
                </div>
            </div>
        </fieldset>

        <div v-if="!readOnly" class="furrow-editor__toolbar">
            <Button size="sm" :text="__('Add row')" @click="insertRow(table.rows.length - 1)" />
            <Button size="sm" :text="__('Add column')" @click="insertColumn(width - 1)" />
        </div>

        <div class="sr-only" aria-live="polite" aria-atomic="true">{{ announcement }}</div>
    </div>
</template>

<style>
.furrow-editor__scroll {
    overflow-x: auto;
    max-width: 100%;
}
.furrow-editor__grid {
    border-collapse: collapse;
    min-width: 100%;
    font-size: 0.875rem;
}
.furrow-editor__grid th,
.furrow-editor__grid td {
    border: 1px solid var(--color-gray-300, #d1d5db);
    padding: 0.25rem;
    vertical-align: top;
    text-align: left;
}
.furrow-editor__grid thead th,
.furrow-editor__grid tbody th {
    background: var(--color-gray-100, #f3f4f6);
    font-weight: 500;
    white-space: nowrap;
}
.furrow-editor__header-row td.furrow-editor__header-cell,
td.furrow-editor__header-cell {
    background: var(--color-gray-50, #f9fafb);
}
.furrow-editor__cell,
.furrow-editor__select {
    width: 100%;
    min-width: 6rem;
    padding: 0.375rem 0.5rem;
    border: 1px solid var(--color-gray-300, #d1d5db);
    border-radius: 0.375rem;
    background: var(--color-white, #fff);
    color: inherit;
    font: inherit;
}
.furrow-editor__header-cell .furrow-editor__cell {
    font-weight: 600;
}
.furrow-editor__cell:focus-visible,
.furrow-editor__select:focus-visible,
.furrow-editor__check input:focus-visible {
    outline: 2px solid var(--color-blue-500, #2563eb);
    outline-offset: 1px;
}
.furrow-editor__stack {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    min-width: 8rem;
}
.furrow-editor__check {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    white-space: nowrap;
}
.furrow-editor__actions {
    display: inline-flex;
    gap: 0.25rem;
    flex-wrap: wrap;
}
.furrow-editor__rowsettings {
    white-space: nowrap;
}
.furrow-editor__row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.furrow-editor__help {
    font-size: 0.8125rem;
    color: var(--color-gray-600, #4b5563);
}
.furrow-editor__options {
    border: 1px solid var(--color-gray-300, #d1d5db);
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
}
.furrow-editor__options legend {
    padding: 0 0.25rem;
    font-weight: 500;
}
.furrow-editor__options-grid {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
}
.furrow-editor__options label {
    display: block;
    font-size: 0.8125rem;
    margin-bottom: 0.25rem;
}
.furrow-editor__toolbar {
    display: flex;
    gap: 0.5rem;
}
@media (prefers-color-scheme: dark) {
    .furrow-editor__grid th,
    .furrow-editor__grid td { border-color: var(--color-gray-700, #374151); }
    .furrow-editor__grid thead th,
    .furrow-editor__grid tbody th,
    td.furrow-editor__header-cell { background: var(--color-gray-800, #1f2937); }
    .furrow-editor__cell,
    .furrow-editor__select { background: var(--color-gray-900, #111827); border-color: var(--color-gray-700, #374151); }
}
</style>
