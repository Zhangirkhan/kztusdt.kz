<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    profiles: Object,
    filterStatus: String,
    stats: Object,
    sumsubAdminEnabled: { type: Boolean, default: false },
});

function setFilter(status) {
    router.get('/admin/kyc', { status }, { preserveState: true });
}
</script>

<template>
    <Head title="KYC Admin" />

    <AdminLayout>
        <template #title>KYC — проверка</template>

        <div class="mb-6 flex flex-wrap gap-2">
            <button
                v-for="item in [
                    { key: 'pending_review', label: `На проверке (${stats.pending})` },
                    { key: 'approved', label: `Одобрено (${stats.approved})` },
                    { key: 'rejected', label: `Отклонено (${stats.rejected})` },
                    { key: 'all', label: 'Все' },
                ]"
                :key="item.key"
                class="rounded-xl px-4 py-2 text-sm font-semibold transition"
                :class="filterStatus === item.key ? 'bg-accent text-on-accent' : 'bg-surface-container text-text-dim'"
                @click="setFilter(item.key)"
            >
                {{ item.label }}
            </button>
        </div>

        <div class="space-y-3">
            <Link
                v-for="profile in profiles.data"
                :key="profile.id"
                :href="`/admin/kyc/${profile.id}`"
                class="card block no-underline transition hover:border-accent/40"
            >
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="font-semibold text-on-surface">
                            {{ [profile.first_name, profile.last_name].filter(Boolean).join(' ') || profile.user?.name || `User #${profile.user?.id}` }}
                        </p>
                        <p class="mt-1 text-body-sm text-text-muted">
                            {{ profile.user?.phone ?? '—' }}
                            <span v-if="sumsubAdminEnabled && profile.provider === 'sumsub'"> · Sumsub</span>
                            <span v-else-if="profile.document_type || profile.document_number">
                                · {{ profile.document_type }} · {{ profile.document_number }}
                            </span>
                        </p>
                    </div>
                    <span class="rounded-lg bg-surface-container-high px-3 py-1 text-xs font-semibold uppercase text-accent">
                        {{ profile.status }}
                    </span>
                </div>
            </Link>

            <p v-if="profiles.data.length === 0" class="text-center text-text-dim">Заявок нет</p>
        </div>
    </AdminLayout>
</template>
