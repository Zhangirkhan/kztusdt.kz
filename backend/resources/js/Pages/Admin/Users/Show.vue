<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminBackLink from '@/shared/ui/admin/AdminBackLink.vue';
import AdminManualKycForm from '@/features/admin-manual-kyc/ui/AdminManualKycForm.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import { formatDateTime } from '@/shared/lib/format/date';
import { statusTagColor } from '@/shared/lib/admin/tagColors';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    user: Object,
});

const showStatusModal = ref(false);
const { t } = useI18n();

const form = useForm({
    status: props.user.status,
});

const manualKycForm = useForm({
    manual_kyc_enabled: props.user.manual_kyc_enabled,
});

const referralBenefitForm = useForm({
    type: 'fee_discount',
    value: props.user.referral?.active_benefit?.value ?? null,
    note: props.user.referral?.active_benefit?.note ?? '',
    is_active: props.user.referral?.active_benefit?.is_active ?? true,
    expires_at: props.user.referral?.active_benefit?.expires_at ?? null,
});

const canManualApprove = computed(() => props.user.kyc_status !== 'approved');

const statusLabels = computed(() => ({
    active: t('admin.users.index.filters.status.active'),
    suspended: t('admin.users.index.filters.status.suspended'),
    blocked: t('admin.users.index.filters.status.blocked'),
}));

const statusOptions = computed(() => [
    { label: statusLabels.value.active, value: 'active' },
    { label: statusLabels.value.suspended, value: 'suspended' },
    { label: statusLabels.value.blocked, value: 'blocked' },
]);

function openStatusModal() {
    form.status = props.user.status;
    form.clearErrors();
    showStatusModal.value = true;
}

function submitStatus() {
    form.patch(route('admin.users.status', props.user.id), {
        preserveScroll: true,
        onSuccess: () => {
            showStatusModal.value = false;
        },
    });
}

function submitManualKyc(enabled) {
    manualKycForm.manual_kyc_enabled = enabled;
    manualKycForm.patch(route('admin.users.manual-kyc', props.user.id), {
        preserveScroll: true,
    });
}

function submitReferralBenefit() {
    referralBenefitForm.post(route('admin.users.referral-benefits.store', props.user.id), {
        preserveScroll: true,
    });
}

function deactivateReferralBenefit(benefitId) {
    referralBenefitForm.patch(route('admin.users.referral-benefits.deactivate', [props.user.id, benefitId]), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('admin.users.show.title', { id: user.id })" />

    <AdminLayout>
        <template #title>{{ t('admin.users.show.title', { id: user.id }) }}</template>

        <AdminPage>
            <AdminBackLink href="/admin/users" :label="t('admin.users.show.backToList')" />

            <a-space class="admin-ant-block">
                <AdminManualKycForm v-if="canManualApprove" :user="user" />
                <a-button @click="openStatusModal">{{ t('admin.users.show.changeStatus') }}</a-button>
            </a-space>

            <a-row :gutter="[16, 16]">
                <a-col :xs="24" :lg="12">
                    <a-card :title="t('admin.users.show.cards.profile')" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item :label="t('admin.users.show.labels.clientType')">{{ user.client_type_label }}</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.users.show.labels.name')">{{ user.name || t('admin.shared.empty') }}</a-descriptions-item>
                            <a-descriptions-item v-if="user.company_name" :label="t('admin.users.show.labels.organization')">{{ user.company_name }}</a-descriptions-item>
                            <a-descriptions-item v-if="user.iin" :label="t('admin.users.show.labels.iin')">{{ user.iin }}</a-descriptions-item>
                            <a-descriptions-item v-if="user.bin" :label="t('admin.users.show.labels.bin')">{{ user.bin }}</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.users.show.labels.phone')">{{ user.phone || t('admin.shared.empty') }}</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.users.show.labels.email')">{{ user.email || t('admin.shared.empty') }}</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.users.show.labels.kyc')">{{ user.kyc_status }}</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.users.show.labels.manualKyc')">
                                <a-switch
                                    :checked="user.manual_kyc_enabled"
                                    :loading="manualKycForm.processing"
                                    :checked-children="t('admin.shared.boolean.on')"
                                    :un-checked-children="t('admin.shared.boolean.off')"
                                    @change="submitManualKyc"
                                />
                            </a-descriptions-item>
                            <a-descriptions-item v-if="user.kyc_profile" :label="t('admin.users.show.labels.kycApplication')">
                                <template v-if="user.kyc_profile.submitted_at">
                                    {{ t('admin.users.show.labels.kycSubmitted', { date: formatDateTime(user.kyc_profile.submitted_at) }) }}
                                </template>
                                <template v-else>
                                    {{ t('admin.users.show.labels.kycDraft') }}
                                </template>
                                <template v-if="user.kyc_profile.reviewed_at">
                                    {{ t('admin.users.show.labels.kycDecision', { date: formatDateTime(user.kyc_profile.reviewed_at) }) }}
                                </template>
                            </a-descriptions-item>
                            <a-descriptions-item :label="t('admin.users.show.labels.status')">
                                <a-tag :color="statusTagColor(user.status)">{{ statusLabels[user.status] ?? user.status }}</a-tag>
                            </a-descriptions-item>
                            <a-descriptions-item :label="t('admin.users.show.labels.phoneVerified')">
                                {{ user.phone_verified ? t('admin.shared.boolean.yes') : t('admin.shared.boolean.no') }}
                            </a-descriptions-item>
                            <a-descriptions-item :label="t('admin.users.show.labels.subscription')">
                                {{ user.has_subscription ? t('admin.shared.boolean.yes') : t('admin.shared.boolean.no') }}
                            </a-descriptions-item>
                            <a-descriptions-item :label="t('admin.users.show.labels.registeredAt')">{{ formatDateTime(user.created_at) }}</a-descriptions-item>
                        </a-descriptions>
                        <Link v-if="user.kyc_profile?.id" :href="`/admin/kyc/${user.kyc_profile.id}`">
                            <a-button type="link" style="padding-left: 0; margin-top: 8px">{{ t('admin.users.show.openKycApplication') }}</a-button>
                        </Link>
                    </a-card>
                </a-col>

                <a-col :xs="24" :lg="12">
                    <a-card :title="t('admin.users.show.cards.activity')" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item :label="t('admin.users.show.labels.orders')">{{ user.counts.orders }}</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.users.show.labels.withdrawals')">{{ user.counts.withdrawals }}</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.users.show.labels.deposits')">{{ user.counts.deposits }}</a-descriptions-item>
                            <a-descriptions-item v-if="user.kyc_profile" :label="t('admin.users.show.labels.kycProfile')">
                                {{ user.kyc_profile.name }} ({{ user.kyc_profile.status }})
                            </a-descriptions-item>
                            <a-descriptions-item v-if="user.roles?.length" :label="t('admin.users.show.labels.roles')">{{ user.roles.join(', ') }}</a-descriptions-item>
                        </a-descriptions>
                    </a-card>
                </a-col>
            </a-row>

            <a-row :gutter="[16, 16]" class="admin-ant-block">
                <a-col :span="24">
                    <a-card :title="t('admin.users.show.cards.referrals')" size="small">
                        <a-descriptions :column="1" size="small" class="mb-4">
                            <a-descriptions-item :label="t('admin.users.show.referrals.code')">
                                <span class="font-mono">{{ user.referral.code }}</span>
                            </a-descriptions-item>
                            <a-descriptions-item :label="t('admin.users.show.referrals.link')">
                                <a-typography-text copyable>{{ user.referral.link }}</a-typography-text>
                            </a-descriptions-item>
                            <a-descriptions-item :label="t('admin.users.show.referrals.count')">
                                {{ user.referral.referrals_count }}
                            </a-descriptions-item>
                            <a-descriptions-item v-if="user.referral.referred_by" :label="t('admin.users.show.referrals.referredBy')">
                                <Link :href="route('admin.users.show', user.referral.referred_by.id)">
                                    {{ user.referral.referred_by.name }} (#{{ user.referral.referred_by.id }})
                                </Link>
                            </a-descriptions-item>
                        </a-descriptions>

                        <a-table
                            v-if="user.referral.referrals.length"
                            :columns="[
                                { title: t('admin.users.show.referrals.columns.user'), dataIndex: 'name', key: 'name' },
                                { title: t('admin.users.show.referrals.columns.phone'), dataIndex: 'phone_masked', key: 'phone_masked' },
                                { title: t('admin.users.show.referrals.columns.kyc'), dataIndex: 'kyc_status', key: 'kyc_status' },
                                { title: t('admin.users.show.referrals.columns.activity'), key: 'activity' },
                                { title: t('admin.users.show.referrals.columns.registered'), dataIndex: 'registered_at', key: 'registered_at' },
                                { title: '', key: 'actions' },
                            ]"
                            :data-source="user.referral.referrals"
                            :pagination="false"
                            size="small"
                            row-key="id"
                        >
                            <template #bodyCell="{ column, record }">
                                <template v-if="column.key === 'activity'">
                                    {{ t('admin.users.index.activity', { orders: record.orders_count, withdrawals: 0, deposits: record.deposits_count }) }}
                                </template>
                                <template v-else-if="column.key === 'registered_at'">
                                    {{ formatDateTime(record.registered_at) }}
                                </template>
                                <template v-else-if="column.key === 'actions'">
                                    <Link :href="route('admin.users.show', record.id)">{{ t('admin.shared.actions.open') }}</Link>
                                </template>
                            </template>
                        </a-table>
                        <a-empty v-else :description="t('admin.users.show.referrals.empty')" />

                        <a-divider>{{ t('admin.users.show.referrals.benefitTitle') }}</a-divider>

                        <a-form layout="vertical" @submit.prevent="submitReferralBenefit">
                            <a-row :gutter="16">
                                <a-col :xs="24" :md="8">
                                    <a-form-item :label="t('admin.users.show.referrals.benefitValue')">
                                        <a-input-number
                                            v-model:value="referralBenefitForm.value"
                                            :min="0"
                                            :max="100"
                                            :step="0.01"
                                            style="width: 100%"
                                            :placeholder="t('admin.users.show.referrals.benefitValuePlaceholder')"
                                        />
                                    </a-form-item>
                                </a-col>
                                <a-col :xs="24" :md="8">
                                    <a-form-item :label="t('admin.users.show.referrals.benefitActive')">
                                        <a-switch v-model:checked="referralBenefitForm.is_active" />
                                    </a-form-item>
                                </a-col>
                            </a-row>
                            <a-form-item :label="t('admin.users.show.referrals.benefitNote')">
                                <a-textarea v-model:value="referralBenefitForm.note" :rows="2" />
                            </a-form-item>
                            <a-space>
                                <a-button type="primary" html-type="submit" :loading="referralBenefitForm.processing">
                                    {{ t('admin.users.show.referrals.saveBenefit') }}
                                </a-button>
                            </a-space>
                        </a-form>

                        <a-table
                            v-if="user.referral.benefits.length"
                            class="mt-4"
                            :columns="[
                                { title: t('admin.users.show.referrals.benefitColumns.type'), dataIndex: 'type', key: 'type' },
                                { title: t('admin.users.show.referrals.benefitColumns.value'), dataIndex: 'value', key: 'value' },
                                { title: t('admin.users.show.referrals.benefitColumns.note'), dataIndex: 'note', key: 'note' },
                                { title: t('admin.users.show.referrals.benefitColumns.status'), key: 'status' },
                                { title: '', key: 'actions' },
                            ]"
                            :data-source="user.referral.benefits"
                            :pagination="false"
                            size="small"
                            row-key="id"
                        >
                            <template #bodyCell="{ column, record }">
                                <template v-if="column.key === 'value'">
                                    {{ record.value ?? t('admin.shared.empty') }}%
                                </template>
                                <template v-else-if="column.key === 'status'">
                                    <a-tag :color="record.is_active ? 'success' : 'default'">
                                        {{ record.is_active ? t('admin.shared.boolean.on') : t('admin.shared.boolean.off') }}
                                    </a-tag>
                                </template>
                                <template v-else-if="column.key === 'actions'">
                                    <a-button
                                        v-if="record.is_active"
                                        type="link"
                                        size="small"
                                        :loading="referralBenefitForm.processing"
                                        @click="deactivateReferralBenefit(record.id)"
                                    >
                                        {{ t('admin.users.show.referrals.deactivateBenefit') }}
                                    </a-button>
                                </template>
                            </template>
                        </a-table>
                    </a-card>
                </a-col>
            </a-row>

            <a-modal
                v-model:open="showStatusModal"
                :title="t('admin.users.show.modal.title')"
                :ok-text="t('admin.shared.actions.save')"
                :cancel-text="t('admin.shared.actions.cancel')"
                :confirm-loading="form.processing"
                destroy-on-close
                @ok="submitStatus"
            >
                <a-form layout="vertical">
                    <a-form-item :label="t('admin.users.show.modal.statusLabel')">
                        <a-select v-model:value="form.status" :options="statusOptions" style="width: 100%" />
                    </a-form-item>
                </a-form>
            </a-modal>
        </AdminPage>
    </AdminLayout>
</template>
