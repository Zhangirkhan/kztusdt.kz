<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowLeftOutlined } from '@ant-design/icons-vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        default: '',
    },
});

const { t } = useI18n();
</script>

<template>
    <Link :href="href" class="admin-ant-back">
        <ArrowLeftOutlined />
        <span>{{ label || t('admin.shared.back') }}</span>
    </Link>
</template>

<style scoped>
.admin-ant-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    color: #1677ff;
    font-weight: 500;
    text-decoration: none;
}

.admin-ant-back:hover {
    color: #4096ff;
}
</style>
