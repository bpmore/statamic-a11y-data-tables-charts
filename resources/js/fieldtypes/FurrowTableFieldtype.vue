<script setup>
/**
 * The furrow_table editor: the shared grid, bound to this field's value.
 * A field with nothing in it yet starts as a small blank table shaped by
 * the blueprint's "start with a header row / column" settings.
 */
import { computed, useId } from 'vue';
import { Fieldtype } from '@statamic/cms';
import TableEditor from './TableEditor.vue';
import * as grid from './grid.js';

const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { update, isReadOnly } = Fieldtype.use(emit, props);

const generatedId = useId();
const base = computed(() => props.id || generatedId);

const value = computed(() => {
    const v = props.value;
    if (!v || typeof v !== 'object' || !Array.isArray(v.rows) || v.rows.length === 0) {
        return grid.starter({ firstRowHeader: props.meta?.firstRowHeader ?? true, firstColumnHeader: props.meta?.firstColumnHeader ?? false });
    }
    return grid.normalise(v);
});

function onUpdate(next) {
    update(next);
}
</script>

<template>
    <div :data-furrow-table="base">
        <TableEditor :model-value="value" :id-base="base" :read-only="isReadOnly" @update:model-value="onUpdate" />
    </div>
</template>
