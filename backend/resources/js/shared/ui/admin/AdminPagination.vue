<script setup>
import { antPaginationLocale } from '@/plugins/antd';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    pagination: {
        type: Object,
        default: null,
    },
    pageParam: {
        type: String,
        default: 'page',
    },
});

function onChange(page) {
    const url = new URL(window.location.href);
    const params = Object.fromEntries(url.searchParams.entries());

    router.get(
        url.pathname,
        {
            ...params,
            [props.pageParam]: page,
        },
        { preserveState: true, preserveScroll: true },
    );
}
</script>

<template>
    <div v-if="pagination && pagination.last_page > 1" class="admin-ant-pagination">
        <a-pagination
            :current="pagination.current_page"
            :total="pagination.total"
            :page-size="pagination.per_page"
            :locale="antPaginationLocale"
            :show-total="(total, range) => t('admin.shared.pagination', { from: range[0], to: range[1], total })"
            show-less-items
            @change="onChange"
        />
    </div>
</template>

<style scoped>
.admin-ant-pagination {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}

@media (max-width: 768px) {
    .admin-ant-pagination {
        justify-content: center;
    }

    .admin-ant-pagination :deep(.ant-pagination) {
        flex-wrap: wrap;
        justify-content: center;
        row-gap: 8px;
    }
}
</style>
