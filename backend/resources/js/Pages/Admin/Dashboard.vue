<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    stats: Object,
});
</script>

<template>
    <Head title="Admin" />

    <AdminLayout>
        <template #title>Dashboard</template>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="card">
                <p class="text-label-caps uppercase text-text-dim">Пользователи</p>
                <p class="mt-2 text-3xl font-bold text-accent">{{ stats.users_total }}</p>
            </div>
            <div class="card">
                <p class="text-label-caps uppercase text-text-dim">KYC на проверке</p>
                <p class="mt-2 text-3xl font-bold">{{ stats.kyc_pending }}</p>
            </div>
            <div class="card">
                <p class="text-label-caps uppercase text-text-dim">KYC одобрено</p>
                <p class="mt-2 text-3xl font-bold">{{ stats.kyc_approved }}</p>
            </div>
        </div>
    </AdminLayout>
</template>
