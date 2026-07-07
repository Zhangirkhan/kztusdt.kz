<script setup>
import { usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <div class="admin-ant-page">
        <a-alert
            v-if="page.props.flash?.success"
            type="success"
            show-icon
            :message="page.props.flash.success"
            class="admin-ant-block"
        />
        <a-alert
            v-if="page.props.errors?.form"
            type="error"
            show-icon
            :message="page.props.errors.form"
            class="admin-ant-block"
        />
        <slot />
    </div>
</template>
