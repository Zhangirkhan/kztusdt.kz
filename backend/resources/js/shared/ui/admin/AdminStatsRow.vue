<script setup>
defineProps({
    items: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <a-row :gutter="[16, 16]" class="admin-ant-block">
        <a-col v-for="item in items" :key="item.label" :xs="24" :sm="12" :lg="6">
            <a-card size="small" :bordered="true">
                <a-statistic
                    :title="item.label"
                    :value="item.value"
                    :value-style="item.color ? { color: item.color } : undefined"
                >
                    <template v-if="item.hint" #suffix>
                        <span class="admin-ant-stat-hint">{{ item.hint }}</span>
                    </template>
                </a-statistic>
            </a-card>
        </a-col>
    </a-row>
</template>

<style scoped>
.admin-ant-stat-hint {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    color: rgba(0, 0, 0, 0.45);
    font-weight: 400;
}
</style>
