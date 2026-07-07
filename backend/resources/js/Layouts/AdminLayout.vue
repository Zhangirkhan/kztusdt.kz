<script setup>
import AdminLayout from '@/widgets/admin-shell/ui/AdminLayout.vue';

defineOptions({ inheritAttrs: false });
</script>

<template>
    <AdminLayout v-bind="$attrs">
        <template v-for="(_, name) in $slots" #[name]="slotData">
            <slot :name="name" v-bind="slotData ?? {}" />
        </template>
    </AdminLayout>
</template>
