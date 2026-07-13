<script setup>
import { useAdminBreakpoint } from '@/composables/useAdminBreakpoint';
import { computed } from 'vue';

const props = defineProps({
    columns: {
        type: Array,
        required: true,
    },
    dataSource: {
        type: Array,
        default: () => [],
    },
    rowKey: {
        type: [String, Function],
        default: 'id',
    },
    size: {
        type: String,
        default: 'middle',
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const { isMobile } = useAdminBreakpoint('content');

const rows = computed(() => props.dataSource ?? []);

function resolveRowKey(record, index) {
    if (typeof props.rowKey === 'function') {
        return props.rowKey(record);
    }

    return record?.[props.rowKey] ?? index;
}
</script>

<template>
    <div class="admin-responsive-table">
        <a-table
            v-if="!isMobile"
            :columns="columns"
            :data-source="rows"
            :pagination="false"
            :row-key="rowKey"
            :size="size"
            :loading="loading"
        >
            <template #bodyCell="slotProps">
                <slot name="bodyCell" v-bind="slotProps" />
            </template>

            <template #emptyText>
                <slot name="emptyText" />
            </template>
        </a-table>

        <div v-else class="admin-responsive-table__mobile" :aria-busy="loading || undefined">
            <a-spin :spinning="loading">
                <div v-if="rows.length" class="admin-responsive-table__list">
                    <article
                        v-for="(record, index) in rows"
                        :key="resolveRowKey(record, index)"
                        class="admin-responsive-table__card"
                    >
                        <slot name="mobile" :record="record" :index="index" />
                    </article>
                </div>

                <div v-else class="admin-responsive-table__empty">
                    <slot name="emptyText" />
                </div>
            </a-spin>
        </div>
    </div>
</template>

<style scoped>
.admin-responsive-table__list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.admin-responsive-table__card {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 14px;
    border: 1px solid #f0f0f0;
    border-radius: 12px;
    background: #fff;
}

.admin-responsive-table__card :deep(.ant-btn) {
    min-height: 40px;
}

.admin-responsive-table__card :deep(.admin-responsive-table__actions) {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 4px;
}

.admin-responsive-table__card :deep(.admin-responsive-table__actions .ant-btn),
.admin-responsive-table__card :deep(.admin-responsive-table__actions .ant-space),
.admin-responsive-table__card :deep(.admin-responsive-table__actions a) {
    width: 100%;
}

.admin-responsive-table__card :deep(.admin-responsive-table__actions .ant-btn) {
    width: 100%;
}

.admin-responsive-table__card :deep(.admin-responsive-table__actions .ant-space) {
    display: flex;
    width: 100%;
}

.admin-responsive-table__card :deep(.admin-responsive-table__actions .ant-space-item) {
    flex: 1;
}

.admin-responsive-table__card :deep(.admin-responsive-table__actions .ant-space-item .ant-btn) {
    width: 100%;
}

.admin-responsive-table__empty {
    padding: 24px 8px;
}
</style>
