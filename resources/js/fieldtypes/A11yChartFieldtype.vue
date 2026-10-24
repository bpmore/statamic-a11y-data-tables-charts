<script setup>
/**
 * The a11y_chart editor.
 *
 * Inputs in this order: chart type, where the data comes from, then
 * either the table field to draw (a list of the a11y_table fields on
 * this form, read from the publish container's blueprint, so an author
 * cannot name one that is not there) or the shared grid for data entered
 * here, then an optional one-sentence summary for the picture's
 * description. There is no preview: the chart is server-rendered and the
 * front end is where it is checked.
 *
 * The two choices are native selects rather than the kit's combobox. The
 * combobox is two Tab stops and fails axe's aria-allowed-attr on its
 * anchor; a native select is one stop, and there is nothing to review.
 */
import { computed, inject, useId } from 'vue';
import { Fieldtype } from '@statamic/cms';
import { Field, Textarea, publishContextKey } from '@statamic/cms/ui';
import TableEditor from './TableEditor.vue';
import * as grid from './grid.js';

const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { update, isReadOnly } = Fieldtype.use(emit, props);

const generatedId = useId();
const base = computed(() => props.id || generatedId);
const id = (part) => `${base.value}-${part}`;

const container = inject(publishContextKey, null);

const EMPTY = { type: 'bar', source: 'field', table: null, data: null, summary: '' };

const value = computed(() => ({ ...EMPTY, ...(props.value ?? {}) }));

function set(patch) {
    update({ ...value.value, ...patch });
}

const types = computed(() => (props.meta?.types ?? ['bar', 'grouped_bar', 'line']).map((t) => ({
    value: t,
    label: { bar: __('Bar'), grouped_bar: __('Grouped bar'), line: __('Line') }[t] ?? t,
})));

const type = computed({ get: () => value.value.type, set: (t) => set({ type: t }) });
const source = computed({ get: () => value.value.source, set: (s) => set({ source: s }) });
const tableHandle = computed({ get: () => value.value.table, set: (h) => set({ table: h || null }) });
const summary = computed({ get: () => value.value.summary ?? '', set: (s) => set({ summary: s ?? '' }) });

/**
 * The table fields on this form, at any depth of tab and section but not
 * inside sets: a chart inside a set draws the table beside it, and that
 * container has its own blueprint.
 */
const tableFields = computed(() => {
    const blueprint = container?.blueprint?.value ?? container?.blueprint ?? null;
    const wanted = props.meta?.tableFieldtype ?? 'a11y_table';
    const found = [];
    (blueprint?.tabs ?? []).forEach((tab) => {
        (tab.sections ?? []).forEach((section) => {
            (section.fields ?? []).forEach((field) => {
                if (field.type === wanted) found.push({ value: field.handle, label: `${field.display || field.handle} (${field.handle})` });
            });
        });
    });
    return found;
});

const missingReference = computed(() => tableHandle.value && !tableFields.value.some((f) => f.value === tableHandle.value));

/** What the named table holds right now, for the note beneath the select. */
const referencedCaption = computed(() => {
    const values = container?.values?.value ?? container?.values ?? null;
    const table = values && tableHandle.value ? values[tableHandle.value] : null;
    return table && typeof table === 'object' ? (table.caption || '') : '';
});

const inlineData = computed(() => {
    const d = value.value.data;
    return d && typeof d === 'object' && Array.isArray(d.rows) && d.rows.length ? grid.normalise(d) : grid.starter({ firstRowHeader: true, firstColumnHeader: true });
});

function onInlineUpdate(next) {
    set({ data: next });
}
</script>

<template>
    <div class="a11y-data-chart-editor space-y-6" :data-a11y-data-chart="base">
        <Field :id="id('type')" :label="__('Chart type')" :instructions="__('Bar and grouped bar for amounts by category; line for change over time. No pie: a pie cannot be read without colour past a few slices.')" :read-only="isReadOnly" required>
            <select :id="id('type')" v-model="type" class="a11y-data-chart-editor__select" :disabled="isReadOnly">
                <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
        </Field>

        <fieldset class="space-y-3">
            <legend class="text-sm font-medium">{{ __('Data') }}</legend>
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Drawing a table field on this entry is the default: one set of numbers, shown twice, that cannot drift apart.') }}</p>
            <div class="a11y-data-chart-editor__radios">
                <label class="a11y-data-chart-editor__radio">
                    <input type="radio" :name="id('source')" value="field" :checked="source === 'field'" :disabled="isReadOnly" @change="source = 'field'" />
                    <span>{{ __('Draw a table field on this entry') }}</span>
                </label>
                <label class="a11y-data-chart-editor__radio">
                    <input type="radio" :name="id('source')" value="inline" :checked="source === 'inline'" :disabled="isReadOnly" @change="source = 'inline'" />
                    <span>{{ __('Enter the data here') }}</span>
                </label>
            </div>
        </fieldset>

        <template v-if="source === 'field'">
            <Field :id="id('table')" :label="__('Table field')" :instructions="__('An A11y data table field on this entry. The chart follows it whenever it changes.')" :read-only="isReadOnly" required>
                <select v-if="tableFields.length" :id="id('table')" v-model="tableHandle" class="a11y-data-chart-editor__select" :disabled="isReadOnly">
                    <option :value="null">{{ __('Choose a table field') }}</option>
                    <option v-for="f in tableFields" :key="f.value" :value="f.value">{{ f.label }}</option>
                </select>
                <p v-else class="text-sm">{{ __('There is no A11y data table field on this blueprint. Add one, or enter the data here instead.') }}</p>
            </Field>
            <p v-if="missingReference" class="text-sm a11y-data-chart-editor__warning" role="status">
                {{ __('The field this chart names is not on this blueprint any more:') }} {{ tableHandle }}. {{ __('On the site, a notice shows in its place until another table is chosen.') }}
            </p>
            <p v-else-if="tableHandle && !referencedCaption" class="text-sm a11y-data-chart-editor__warning" role="status">
                {{ __('That table has no caption yet. The entry will not save until it does.') }}
            </p>
            <p v-else-if="tableHandle" class="text-sm text-gray-600 dark:text-gray-400">{{ __('Drawing:') }} {{ referencedCaption }}</p>
        </template>

        <template v-else>
            <TableEditor
                :model-value="inlineData"
                :id-base="id('data')"
                :read-only="isReadOnly"
                :caption-instructions="__('What the chart shows, in a few words. It is the chart\'s title and the data table\'s caption, and the chart will not save without it.')"
                @update:model-value="onInlineUpdate"
            />
        </template>

        <Field :id="id('summary')" :label="__('Summary')" :instructions="__('Optional. One sentence saying what the chart shows, like a trend or a comparison, for people who do not see the picture. Left blank, the chart writes one from the numbers: the lowest and highest values and where they are.')" :read-only="isReadOnly">
            <Textarea :id="id('summary')" v-model="summary" :rows="2" :read-only="isReadOnly" />
        </Field>
    </div>
</template>

<style>
.a11y-data-chart-editor__radios {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.a11y-data-chart-editor__radio {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.a11y-data-chart-editor__radio input:focus-visible {
    outline: 2px solid var(--color-blue-500, #2563eb);
    outline-offset: 2px;
}
.a11y-data-chart-editor__select {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--color-gray-300, #d1d5db);
    border-radius: 0.5rem;
    background: var(--color-white, #fff);
    color: inherit;
    font: inherit;
}
.a11y-data-chart-editor__select:focus-visible {
    outline: 2px solid var(--color-blue-500, #2563eb);
    outline-offset: 1px;
}
@media (prefers-color-scheme: dark) {
    .a11y-data-chart-editor__select { background: var(--color-gray-900, #111827); border-color: var(--color-gray-700, #374151); }
}
.a11y-data-chart-editor__warning {
    color: var(--color-red-700, #b91c1c);
}
</style>
