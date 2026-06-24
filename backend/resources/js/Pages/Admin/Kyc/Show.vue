<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    profile: Object,
});

const rejectForm = useForm({ reason: '' });
const approveForm = useForm({ comment: '' });
const showReject = ref(false);

const displayName = computed(() => {
    const sumsub = props.profile.sumsub;
    if (sumsub?.first_name || sumsub?.last_name) {
        return [sumsub.first_name, sumsub.middle_name, sumsub.last_name].filter(Boolean).join(' ');
    }

    return [props.profile.first_name, props.profile.last_name].filter(Boolean).join(' ') || props.profile.user?.name || '—';
});

const documentLine = computed(() => {
    const sumsub = props.profile.sumsub;
    const type = props.profile.document_type ?? sumsub?.document_type;
    const number = props.profile.document_number ?? sumsub?.document_number;

    if (!type && !number) {
        return '—';
    }

    return [type, number].filter(Boolean).join(' · ');
});

function approve() {
    approveForm.post(`/admin/kyc/${props.profile.id}/approve`);
}

function reject() {
    rejectForm.post(`/admin/kyc/${props.profile.id}/reject`, {
        onSuccess: () => {
            showReject.value = false;
        },
    });
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('ru-RU');
}
</script>

<template>
    <Head :title="`KYC #${profile.id}`" />

    <AdminLayout>
        <template #title>KYC #{{ profile.id }}</template>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="rounded-lg bg-surface-container-high px-3 py-1 text-xs font-semibold uppercase text-accent">
                {{ profile.status_label }}
            </span>
            <span class="rounded-lg bg-surface-container px-3 py-1 text-xs text-text-dim">
                {{ profile.provider_label }}
            </span>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="card space-y-4">
                <div>
                    <p class="text-label-caps uppercase text-text-dim">Клиент</p>
                    <p class="mt-2 text-xl font-bold">{{ displayName }}</p>
                    <p class="text-body-sm text-text-muted">{{ profile.user?.phone ?? '—' }}</p>
                    <p v-if="profile.user?.telegram_username" class="text-body-sm text-text-muted">
                        Telegram: @{{ profile.user.telegram_username }}
                    </p>
                    <p class="mt-1 text-xs text-text-dim">User ID: {{ profile.user?.id ?? '—' }}</p>
                </div>

                <div>
                    <p class="text-label-caps uppercase text-text-dim">Документ</p>
                    <p class="mt-2">{{ documentLine }}</p>
                    <p v-if="profile.sumsub?.dob" class="mt-1 text-sm text-text-muted">
                        Дата рождения: {{ profile.sumsub.dob }}
                    </p>
                    <p v-if="profile.sumsub?.country" class="mt-1 text-sm text-text-muted">
                        Страна: {{ profile.sumsub.country }}
                    </p>
                </div>

                <div>
                    <p class="text-label-caps uppercase text-text-dim">Проверка</p>
                    <p class="mt-2 text-sm text-text-muted">Отправлено: {{ formatDate(profile.submitted_at) }}</p>
                    <p class="text-sm text-text-muted">Решение: {{ formatDate(profile.reviewed_at) }}</p>
                    <p v-if="profile.reviewer?.name" class="text-sm text-text-muted">
                        Проверил: {{ profile.reviewer.name }}
                    </p>
                    <p v-if="profile.rejection_reason" class="mt-2 text-sm text-error">{{ profile.rejection_reason }}</p>
                </div>
            </section>

            <section v-if="profile.provider === 'sumsub'" class="card space-y-4">
                <div>
                    <p class="mb-2 text-label-caps uppercase text-text-dim">Sumsub</p>
                    <p v-if="profile.sumsub?.error" class="text-sm text-amber-300">{{ profile.sumsub.error }}</p>
                    <template v-else-if="profile.sumsub">
                        <p class="font-mono text-xs text-text-dim break-all">
                            Applicant: {{ profile.sumsub_applicant_id }}
                        </p>
                        <p v-if="profile.sumsub.created_at" class="mt-2 text-sm text-text-muted">
                            Создан в Sumsub: {{ profile.sumsub.created_at }}
                        </p>
                        <p v-if="profile.sumsub.platform" class="text-sm text-text-muted">
                            Платформа: {{ profile.sumsub.platform }}
                        </p>
                        <p v-if="profile.sumsub.review_status" class="text-sm text-text-muted">
                            Статус Sumsub: {{ profile.sumsub.review_status }}
                            <span v-if="profile.sumsub.review_answer"> · {{ profile.sumsub.review_answer }}</span>
                        </p>
                        <p v-if="profile.sumsub.moderation_comment" class="mt-2 text-sm text-text-muted">
                            Комментарий: {{ profile.sumsub.moderation_comment }}
                        </p>
                        <p v-if="profile.sumsub.reject_labels?.length" class="mt-2 text-sm text-error">
                            Метки отказа: {{ profile.sumsub.reject_labels.join(', ') }}
                        </p>
                        <a
                            v-if="profile.sumsub.dashboard_url"
                            :href="profile.sumsub.dashboard_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-accent hover:underline"
                        >
                            Открыть в Sumsub
                            <span class="material-symbols-outlined text-base">open_in_new</span>
                        </a>
                        <p class="mt-3 text-xs text-text-dim">
                            Документы и селфи хранятся в Sumsub, не на сервере обменника.
                        </p>
                    </template>
                </div>
            </section>

            <section v-else class="card">
                <p class="mb-4 text-label-caps uppercase text-text-dim">Загруженные документы</p>
                <div v-if="profile.documents.length" class="grid gap-3">
                    <a
                        v-for="doc in profile.documents"
                        :key="doc.id"
                        :href="`/admin/kyc/${profile.id}/documents/${doc.type}`"
                        target="_blank"
                        class="flex items-center justify-between rounded-xl bg-surface-container-low px-4 py-3 text-sm no-underline text-on-surface hover:text-accent"
                    >
                        <span>{{ doc.label }}</span>
                        <span class="material-symbols-outlined text-base">open_in_new</span>
                    </a>
                </div>
                <p v-else class="text-sm text-text-dim">Документы не загружены</p>
            </section>
        </div>

        <div v-if="profile.status === 'pending_review' && profile.provider !== 'sumsub'" class="mt-6 flex flex-wrap gap-3">
            <button class="btn-primary w-auto px-8" @click="approve">Одобрить KYC</button>
            <button class="btn-secondary w-auto px-8" @click="showReject = !showReject">Отклонить</button>
        </div>

        <p v-else-if="profile.status === 'pending_review' && profile.provider === 'sumsub'" class="mt-6 text-sm text-text-dim">
            Заявка проверяется в Sumsub автоматически. Решение придёт по webhook или после синхронизации статуса.
        </p>

        <form v-if="showReject" class="mt-4 card space-y-3" @submit.prevent="reject">
            <label class="block text-sm text-text-dim">Причина отклонения</label>
            <textarea v-model="rejectForm.reason" class="input-field min-h-24" required />
            <button type="submit" class="btn-primary w-auto px-8" :disabled="rejectForm.processing">
                Подтвердить отклонение
            </button>
        </form>
    </AdminLayout>
</template>
