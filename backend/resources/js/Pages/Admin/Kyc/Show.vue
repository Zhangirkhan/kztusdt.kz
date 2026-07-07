<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import { statusTagColor } from '@/shared/lib/admin/tagColors';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    profile: Object,
    sumsubAdminEnabled: { type: Boolean, default: false },
});

const rejectForm = useForm({ reason: '' });
const approveForm = useForm({ comment: '' });
const resetForm = useForm({ comment: '' });
const showRejectModal = ref(false);
const showApproveModal = ref(false);
const showResetModal = ref(false);

const canReset = computed(() => ['approved', 'rejected', 'pending_review'].includes(props.profile.status));

const displayName = computed(() => {
    const sumsub = props.sumsubAdminEnabled ? props.profile.sumsub : null;
    if (sumsub?.first_name || sumsub?.last_name) {
        return [sumsub.first_name, sumsub.middle_name, sumsub.last_name].filter(Boolean).join(' ');
    }

    // Prefer the identity that the user submitted for KYC.
    // Account name/phone can be anything and should not be treated as KYC identity.
    return [props.profile.first_name, props.profile.last_name].filter(Boolean).join(' ') || '—';
});

const documentLine = computed(() => {
    const sumsub = props.sumsubAdminEnabled ? props.profile.sumsub : null;
    const type = props.profile.document_type ?? sumsub?.document_type;
    const number = props.profile.document_number ?? sumsub?.document_number;

    if (!type && !number) {
        return '—';
    }

    return [type, number].filter(Boolean).join(' · ');
});

function approve() {
    approveForm.post(`/admin/kyc/${props.profile.id}/approve`, {
        onSuccess: () => {
            showApproveModal.value = false;
        },
    });
}

function reject() {
    rejectForm.post(`/admin/kyc/${props.profile.id}/reject`, {
        onSuccess: () => {
            showRejectModal.value = false;
        },
    });
}

function resetVerification() {
    resetForm.post(`/admin/kyc/${props.profile.id}/reset`, {
        onSuccess: () => {
            showResetModal.value = false;
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

        <AdminPage>
            <a-space class="admin-ant-block">
                <a-tag :color="statusTagColor(profile.status)">{{ profile.status_label }}</a-tag>
                <a-tag>{{ profile.provider_label }}</a-tag>
            </a-space>

            <a-row :gutter="[16, 16]">
                <a-col :xs="24" :lg="12">
                    <a-card title="Клиент" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item label="ФИО">{{ displayName }}</a-descriptions-item>
                            <a-descriptions-item label="Телефон">{{ profile.user?.phone ?? '—' }}</a-descriptions-item>
                            <a-descriptions-item label="User ID">{{ profile.user?.id ?? '—' }}</a-descriptions-item>
                            <a-descriptions-item label="Документ">{{ documentLine }}</a-descriptions-item>
                            <a-descriptions-item label="Отправлено">
                                <template v-if="profile.submitted_at">{{ formatDate(profile.submitted_at) }}</template>
                                <template v-else>Не отправлено (черновик)</template>
                            </a-descriptions-item>
                            <a-descriptions-item label="Решение">{{ formatDate(profile.reviewed_at) }}</a-descriptions-item>
                        </a-descriptions>
                        <a-alert
                            v-if="profile.rejection_reason"
                            type="error"
                            :message="profile.rejection_reason"
                            show-icon
                            style="margin-top: 16px"
                        />
                    </a-card>
                </a-col>

                <a-col v-if="sumsubAdminEnabled && profile.provider === 'sumsub'" :xs="24" :lg="12">
                    <a-card title="Sumsub" size="small">
                        <a-alert v-if="profile.sumsub?.error" type="warning" :message="profile.sumsub.error" show-icon />
                        <template v-else-if="profile.sumsub">
                            <a-descriptions :column="1" size="small">
                                <a-descriptions-item label="Applicant">
                                    <a-typography-text code copyable>{{ profile.sumsub_applicant_id }}</a-typography-text>
                                </a-descriptions-item>
                                <a-descriptions-item v-if="profile.sumsub.created_at" label="Создан">
                                    {{ profile.sumsub.created_at }}
                                </a-descriptions-item>
                                <a-descriptions-item v-if="profile.sumsub.platform" label="Платформа">
                                    {{ profile.sumsub.platform }}
                                </a-descriptions-item>
                                <a-descriptions-item v-if="profile.sumsub.review_status" label="Статус">
                                    {{ profile.sumsub.review_status }}
                                    <template v-if="profile.sumsub.review_answer"> · {{ profile.sumsub.review_answer }}</template>
                                </a-descriptions-item>
                            </a-descriptions>
                            <p v-if="profile.sumsub.moderation_comment" class="admin-ant-meta admin-ant-block">
                                Комментарий: {{ profile.sumsub.moderation_comment }}
                            </p>
                            <a-alert
                                v-if="profile.sumsub.reject_labels?.length"
                                type="error"
                                :message="`Метки отказа: ${profile.sumsub.reject_labels.join(', ')}`"
                                show-icon
                                class="admin-ant-block"
                            />
                            <a
                                v-if="profile.sumsub.dashboard_url"
                                :href="profile.sumsub.dashboard_url"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <a-button type="link" style="padding-left: 0">Открыть в Sumsub ↗</a-button>
                            </a>
                            <a-typography-text type="secondary" class="admin-ant-meta">
                                Документы и селфи хранятся в Sumsub, не на сервере обменника.
                            </a-typography-text>
                        </template>
                    </a-card>
                </a-col>

                <a-col v-else :xs="24" :lg="12">
                    <a-card title="Загруженные документы" size="small">
                        <a-space v-if="profile.documents.length" wrap>
                            <a
                                v-for="doc in profile.documents"
                                :key="doc.id"
                                :href="`/admin/kyc/${profile.id}/documents/${doc.type}`"
                                target="_blank"
                            >
                                <a-button>{{ doc.label }}</a-button>
                            </a>
                        </a-space>
                        <a-empty v-else description="Документы не загружены" />
                    </a-card>
                </a-col>
            </a-row>

            <a-space v-if="profile.status === 'pending_review' && (!sumsubAdminEnabled || profile.provider !== 'sumsub')" class="admin-ant-actions admin-ant-block">
                <a-button type="primary" @click="showApproveModal = true">Одобрить KYC</a-button>
                <a-button danger @click="showRejectModal = true">Отклонить</a-button>
            </a-space>

            <a-typography-text
                v-else-if="sumsubAdminEnabled && profile.status === 'pending_review' && profile.provider === 'sumsub'"
                type="secondary"
                class="admin-ant-block"
            >
                Заявка проверяется в Sumsub автоматически.
            </a-typography-text>

            <a-space v-if="canReset" class="admin-ant-actions admin-ant-block">
                <a-button @click="showResetModal = true">Сбросить верификацию</a-button>
            </a-space>

            <a-modal
                v-model:open="showApproveModal"
                title="Одобрить KYC"
                ok-text="Одобрить"
                cancel-text="Отмена"
                :confirm-loading="approveForm.processing"
                destroy-on-close
                @ok="approve"
            >
                <a-form layout="vertical">
                    <a-form-item label="Комментарий (необязательно)">
                        <a-textarea v-model:value="approveForm.comment" :rows="2" />
                    </a-form-item>
                </a-form>
            </a-modal>

            <a-modal
                v-model:open="showRejectModal"
                title="Отклонить KYC"
                ok-text="Подтвердить"
                cancel-text="Отмена"
                :confirm-loading="rejectForm.processing"
                @ok="reject"
            >
                <a-form layout="vertical">
                    <a-form-item label="Причина отклонения" required>
                        <a-textarea v-model:value="rejectForm.reason" :rows="3" />
                    </a-form-item>
                </a-form>
            </a-modal>

            <a-modal
                v-model:open="showResetModal"
                title="Сбросить верификацию"
                ok-text="Подтвердить"
                cancel-text="Отмена"
                :confirm-loading="resetForm.processing"
                @ok="resetVerification"
            >
                <a-typography-paragraph type="secondary">
                    Клиент сможет пройти KYC заново. Кошелёк и баланс не удаляются.
                </a-typography-paragraph>
                <a-form layout="vertical">
                    <a-form-item label="Комментарий (необязательно)">
                        <a-textarea v-model:value="resetForm.comment" placeholder="Причина сброса" :rows="2" />
                    </a-form-item>
                </a-form>
            </a-modal>
        </AdminPage>
    </AdminLayout>
</template>
