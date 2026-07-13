<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import { statusTagColor } from '@/shared/lib/admin/tagColors';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

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
const { t } = useI18n();

const canReset = computed(() => ['approved', 'rejected', 'pending_review'].includes(props.profile.status));

const displayName = computed(() => {
    const sumsub = props.sumsubAdminEnabled ? props.profile.sumsub : null;
    if (sumsub?.first_name || sumsub?.last_name) {
        return [sumsub.first_name, sumsub.middle_name, sumsub.last_name].filter(Boolean).join(' ');
    }

    // Prefer the identity that the user submitted for KYC.
    // Account name/phone can be anything and should not be treated as KYC identity.
    return [props.profile.first_name, props.profile.last_name].filter(Boolean).join(' ') || t('admin.shared.empty');
});

const documentLine = computed(() => {
    const sumsub = props.sumsubAdminEnabled ? props.profile.sumsub : null;
    const type = props.profile.document_type ?? sumsub?.document_type;
    const number = props.profile.document_number ?? sumsub?.document_number;

    if (!type && !number) {
        return t('admin.shared.empty');
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
        return t('admin.shared.empty');
    }

    return new Date(value).toLocaleString('ru-RU');
}
</script>

<template>
    <Head :title="t('admin.kyc.show.title', { id: profile.id })" />

    <AdminLayout>
        <template #title>{{ t('admin.kyc.show.title', { id: profile.id }) }}</template>

        <AdminPage>
            <a-space class="admin-ant-block">
                <a-tag :color="statusTagColor(profile.status)">{{ profile.status_label }}</a-tag>
                <a-tag>{{ profile.provider_label }}</a-tag>
            </a-space>

            <a-row :gutter="[16, 16]">
                <a-col :xs="24" :lg="12">
                    <a-card :title="t('admin.kyc.show.cards.client')" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item :label="t('admin.kyc.show.labels.fullName')">{{ displayName }}</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.kyc.show.labels.phone')">{{ profile.user?.phone ?? t('admin.shared.empty') }}</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.kyc.show.labels.iin')">{{ profile.user?.iin ?? profile.document_number ?? t('admin.shared.empty') }}</a-descriptions-item>
                            <a-descriptions-item label="User ID">{{ profile.user?.id ?? t('admin.shared.empty') }}</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.kyc.show.labels.document')">{{ documentLine }}</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.kyc.show.labels.submitted')">
                                <template v-if="profile.submitted_at">{{ formatDate(profile.submitted_at) }}</template>
                                <template v-else>{{ t('admin.kyc.show.labels.notSubmitted') }}</template>
                            </a-descriptions-item>
                            <a-descriptions-item :label="t('admin.kyc.show.labels.decision')">{{ formatDate(profile.reviewed_at) }}</a-descriptions-item>
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

                <a-col v-if="profile.provider === 'aitu'" :xs="24" :lg="12">
                    <a-card title="Aitu Passport" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item label="sessionDocumentId">
                                <a-typography-text code copyable>
                                    {{ profile.provider_verification_id ?? t('admin.shared.empty') }}
                                </a-typography-text>
                            </a-descriptions-item>
                            <a-descriptions-item label="sid">
                                <a-typography-text code copyable>
                                    {{ profile.provider_session_id ?? t('admin.shared.empty') }}
                                </a-typography-text>
                            </a-descriptions-item>
                            <a-descriptions-item :label="t('admin.kyc.show.labels.verificationDate')">
                                {{ formatDate(profile.reviewed_at) }}
                            </a-descriptions-item>
                        </a-descriptions>
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
                                <a-descriptions-item v-if="profile.sumsub.created_at" :label="t('admin.kyc.show.labels.created')">
                                    {{ profile.sumsub.created_at }}
                                </a-descriptions-item>
                                <a-descriptions-item v-if="profile.sumsub.platform" :label="t('admin.kyc.show.labels.platform')">
                                    {{ profile.sumsub.platform }}
                                </a-descriptions-item>
                                <a-descriptions-item v-if="profile.sumsub.review_status" :label="t('admin.kyc.show.labels.status')">
                                    {{ profile.sumsub.review_status }}
                                    <template v-if="profile.sumsub.review_answer"> · {{ profile.sumsub.review_answer }}</template>
                                </a-descriptions-item>
                            </a-descriptions>
                            <p v-if="profile.sumsub.moderation_comment" class="admin-ant-meta admin-ant-block">
                                {{ t('admin.kyc.show.sumsub.comment', { text: profile.sumsub.moderation_comment }) }}
                            </p>
                            <a-alert
                                v-if="profile.sumsub.reject_labels?.length"
                                type="error"
                                :message="t('admin.kyc.show.sumsub.rejectLabels', { labels: profile.sumsub.reject_labels.join(', ') })"
                                show-icon
                                class="admin-ant-block"
                            />
                            <a
                                v-if="profile.sumsub.dashboard_url"
                                :href="profile.sumsub.dashboard_url"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <a-button type="link" style="padding-left: 0">{{ t('admin.kyc.show.sumsub.openInSumsub') }}</a-button>
                            </a>
                            <a-typography-text type="secondary" class="admin-ant-meta">
                                {{ t('admin.kyc.show.sumsub.storageNote') }}
                            </a-typography-text>
                        </template>
                    </a-card>
                </a-col>

                <a-col v-else :xs="24" :lg="12">
                    <a-card :title="t('admin.kyc.show.cards.uploadedDocuments')" size="small">
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
                        <a-empty v-else :description="t('admin.kyc.show.documentsEmpty')" />
                    </a-card>
                </a-col>
            </a-row>

            <a-space v-if="profile.status === 'pending_review' && (!sumsubAdminEnabled || profile.provider !== 'sumsub')" class="admin-ant-actions admin-ant-block">
                <a-button type="primary" @click="showApproveModal = true">{{ t('admin.kyc.show.actions.approve') }}</a-button>
                <a-button danger @click="showRejectModal = true">{{ t('admin.kyc.show.actions.reject') }}</a-button>
            </a-space>

            <a-typography-text
                v-else-if="sumsubAdminEnabled && profile.status === 'pending_review' && profile.provider === 'sumsub'"
                type="secondary"
                class="admin-ant-block"
            >
                {{ t('admin.kyc.show.sumsub.autoReviewNote') }}
            </a-typography-text>

            <a-space v-if="canReset" class="admin-ant-actions admin-ant-block">
                <a-button @click="showResetModal = true">{{ t('admin.kyc.show.actions.reset') }}</a-button>
            </a-space>

            <a-modal
                v-model:open="showApproveModal"
                :title="t('admin.kyc.show.modals.approve.title')"
                :ok-text="t('admin.kyc.show.modals.approve.ok')"
                :cancel-text="t('admin.shared.actions.cancel')"
                :confirm-loading="approveForm.processing"
                destroy-on-close
                @ok="approve"
            >
                <a-form layout="vertical">
                    <a-form-item :label="t('admin.kyc.show.modals.approve.commentLabel')">
                        <a-textarea v-model:value="approveForm.comment" :rows="2" />
                    </a-form-item>
                </a-form>
            </a-modal>

            <a-modal
                v-model:open="showRejectModal"
                :title="t('admin.kyc.show.modals.reject.title')"
                :ok-text="t('admin.shared.actions.confirm')"
                :cancel-text="t('admin.shared.actions.cancel')"
                :confirm-loading="rejectForm.processing"
                @ok="reject"
            >
                <a-form layout="vertical">
                    <a-form-item :label="t('admin.kyc.show.modals.reject.reasonLabel')" required>
                        <a-textarea v-model:value="rejectForm.reason" :rows="3" />
                    </a-form-item>
                </a-form>
            </a-modal>

            <a-modal
                v-model:open="showResetModal"
                :title="t('admin.kyc.show.modals.reset.title')"
                :ok-text="t('admin.shared.actions.confirm')"
                :cancel-text="t('admin.shared.actions.cancel')"
                :confirm-loading="resetForm.processing"
                @ok="resetVerification"
            >
                <a-typography-paragraph type="secondary">
                    {{ t('admin.kyc.show.modals.reset.description') }}
                </a-typography-paragraph>
                <a-form layout="vertical">
                    <a-form-item :label="t('admin.kyc.show.modals.reset.commentLabel')">
                        <a-textarea
                            v-model:value="resetForm.comment"
                            :placeholder="t('admin.kyc.show.modals.reset.commentPlaceholder')"
                            :rows="2"
                        />
                    </a-form-item>
                </a-form>
            </a-modal>
        </AdminPage>
    </AdminLayout>
</template>
