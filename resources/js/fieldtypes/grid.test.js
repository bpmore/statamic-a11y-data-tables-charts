import { test } from 'node:test';
import assert from 'node:assert/strict';
import { layout, insertRow, deleteRow, insertColumn, deleteColumn, parseTsv, fillFrom, cellAt, normalise, starter, looksLikeGrid } from './grid.js';

const t = (rows, columns) => normalise({ caption: 'x', columns, rows: rows.map((cells, r) => ({ header: r === 0, cells })) });
const plain = (v) => ({ value: v });

test('layout skips slots a span fills', () => {
    const table = t([[plain(''), { value: 'Sales', colspan: 2 }], [plain('Region'), plain('Q1'), plain('Q2')]]);
    const { width, slots } = layout(table.rows);
    assert.equal(width, 3);
    assert.deepEqual(slots[0][1], { r: 0, c: 1, i: 1 });
    assert.deepEqual(slots[0][2], { covered: { r: 0, c: 1, i: 1 } });
    assert.deepEqual(slots[1][2], { r: 1, c: 2, i: 2 });
});

test('rowspan covers the row below and cells there shift right', () => {
    const table = t([[plain('G'), plain('I'), plain('V')], [{ value: 'Fruit', rowspan: 2 }, plain('Apple'), plain('1')], [plain('Pear'), plain('2')]]);
    assert.equal(cellAt(table, 2, 0).cell.value, 'Fruit');
    assert.equal(cellAt(table, 2, 1).cell.value, 'Pear');
    assert.equal(cellAt(table, 2, 2).cell.value, '2');
});

test('insertColumn inside a colspan widens the span, elsewhere adds a cell', () => {
    const table = t([[plain(''), { value: 'Sales', colspan: 2 }], [plain('Region'), plain('Q1'), plain('Q2')]]);
    const wider = insertColumn(table, 1);
    assert.equal(wider.rows[0].cells[1].colspan, 3);
    assert.equal(wider.rows[1].cells.length, 4);
    assert.equal(wider.columns.length, 4);
    assert.equal(layout(wider.rows).width, 4);

    const before = insertColumn(table, -1);
    assert.equal(before.rows[0].cells.length, 3);
    assert.equal(before.rows[0].cells[0].value, '');
    assert.equal(before.rows[0].cells[2].colspan, 2);
    assert.equal(layout(before.rows).width, 4);
});

test('deleteColumn under a colspan narrows it, and removes plain cells', () => {
    const table = t([[plain(''), { value: 'Sales', colspan: 2 }], [plain('Region'), plain('Q1'), plain('Q2')]]);
    const narrower = deleteColumn(table, 2);
    assert.equal(narrower.rows[0].cells[1].colspan, undefined);
    assert.deepEqual(narrower.rows[1].cells.map((c) => c.value), ['Region', 'Q1']);
    assert.equal(layout(narrower.rows).width, 2);
    assert.equal(narrower.columns.length, 2);
});

test('insertRow across a rowspan grows the span; deleteRow at its origin moves it down', () => {
    const table = t([[plain('G'), plain('I'), plain('V')], [{ value: 'Fruit', rowspan: 2 }, plain('Apple'), plain('1')], [plain('Pear'), plain('2')]]);
    const taller = insertRow(table, 1);
    assert.equal(taller.rows[1].cells[0].rowspan, 3);
    assert.equal(taller.rows[2].cells.length, 2);
    assert.equal(layout(taller.rows).width, 3);
    assert.equal(cellAt(taller, 2, 0).cell.value, 'Fruit');

    const shorter = deleteRow(table, 1);
    assert.equal(shorter.rows.length, 2);
    assert.equal(cellAt(shorter, 1, 0).cell.value, 'Fruit');
    assert.equal(shorter.rows[1].cells[0].rowspan, undefined);
    assert.deepEqual(shorter.rows[1].cells.map((c) => c.value), ['Fruit', 'Pear', '2']);
});

test('deleteRow under a rowspan shortens the span', () => {
    const table = t([[plain('G'), plain('I'), plain('V')], [{ value: 'Fruit', rowspan: 2 }, plain('Apple'), plain('1')], [plain('Pear'), plain('2')]]);
    const out = deleteRow(table, 2);
    assert.equal(out.rows[1].cells[0].rowspan, undefined);
    assert.equal(out.rows.length, 2);
});

test('parseTsv reads tabs, newlines and quoted cells', () => {
    assert.deepEqual(parseTsv('a\tb\nc\td\n'), [['a', 'b'], ['c', 'd']]);
    assert.deepEqual(parseTsv('"x\ty"\tz'), [['x\ty', 'z']]);
    assert.deepEqual(parseTsv('one'), [['one']]);
    assert.equal(looksLikeGrid('one'), false);
    assert.equal(looksLikeGrid('a\tb'), true);
    assert.equal(looksLikeGrid('a\n'), false);
});

test('fillFrom grows the table and skips covered slots', () => {
    const table = starter({}, 2, 2);
    const filled = fillFrom(table, 1, 1, [['a', 'b', 'c'], ['d', 'e', 'f']]);
    assert.equal(filled.rows.length, 3);
    assert.equal(layout(filled.rows).width, 4);
    assert.equal(filled.columns.length, 4);
    assert.equal(cellAt(filled, 2, 3).cell.value, 'f');
    assert.equal(cellAt(filled, 1, 1).cell.value, 'a');
    assert.equal(cellAt(filled, 0, 0).cell.value, '');
});

test('normalise pads columns to the widest row and keeps only real spans', () => {
    const out = normalise({ caption: 'c', columns: [], rows: [{ cells: [{ value: 1, colspan: 1, rowspan: '2', abbr: '' }, 'x'] }] });
    assert.equal(out.columns.length, 2);
    assert.deepEqual(out.rows[0].cells[0], { value: '1', rowspan: 2 });
    assert.deepEqual(out.rows[0].cells[1], { value: 'x' });
});

test('deleteRow under a cell spanning both rows and columns shortens it once', () => {
    const table = t([[plain('a'), plain('b'), plain('c')], [{ value: 'big', rowspan: 2, colspan: 2 }, plain('1')], [plain('2')]]);
    const out = deleteRow(table, 2);
    assert.equal(out.rows[1].cells[0].rowspan, undefined);
    assert.equal(out.rows[1].cells[0].colspan, 2);
});

import { setSpan } from './grid.js';

test('setSpan merges the cells it grows over and gives them back when it shrinks', () => {
    const table = t([[plain('Year'), plain('North'), plain('South'), plain('Online')], [plain('2022'), plain('130'), plain('70'), plain('35')]]);
    const grown = setSpan(table, 0, 1, { colspan: 2, rowspan: 1 });
    assert.equal(grown.error, null);
    assert.deepEqual(grown.table.rows[0].cells.map((c) => c.value), ['Year', 'North', 'Online']);
    assert.equal(grown.table.rows[0].cells[1].colspan, 2);
    assert.deepEqual(grown.lost, ['South']);
    assert.equal(layout(grown.table.rows).width, 4);
    assert.equal(grown.table.columns.length, 4);

    const back = setSpan(grown.table, 0, 1, { colspan: 1, rowspan: 1 });
    assert.deepEqual(back.table.rows[0].cells.map((c) => c.value), ['Year', 'North', '', 'Online']);
    assert.equal(back.table.rows[0].cells[1].colspan, undefined);
});

test('setSpan with a rowspan takes the cell below and returns it on shrink', () => {
    const table = t([[plain('G'), plain('I')], [plain('Fruit'), plain('Apple')], [plain('x'), plain('Pear')]]);
    const grown = setSpan(table, 1, 0, { colspan: 1, rowspan: 2 });
    assert.equal(grown.error, null);
    assert.deepEqual(grown.table.rows[2].cells.map((c) => c.value), ['Pear']);
    assert.equal(cellAt(grown.table, 2, 0).cell.value, 'Fruit');
    assert.deepEqual(grown.lost, ['x']);

    const back = setSpan(grown.table, 1, 0, { colspan: 1, rowspan: 1 });
    assert.deepEqual(back.table.rows[2].cells.map((c) => c.value), ['', 'Pear']);
});

test('setSpan refuses to cut through another span, and clamps to the table edge', () => {
    const table = t([[plain(''), { value: 'Sales', colspan: 2 }], [plain('Region'), plain('Q1'), plain('Q2')]]);
    const refused = setSpan(table, 0, 0, { colspan: 2, rowspan: 1 });
    assert.match(refused.error, /cut through/);
    assert.deepEqual(refused.table, table);

    const clamped = setSpan(table, 1, 1, { colspan: 9, rowspan: 9 });
    assert.equal(clamped.error, null);
    assert.equal(clamped.table.rows[1].cells[1].colspan, 2);
    assert.equal(clamped.table.rows[1].cells[1].rowspan, undefined);
});
