<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminBackLink from '@/shared/ui/admin/AdminBackLink.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import PaymentProofPreview from '@/shared/ui/payment-proof/PaymentProofPreview.vue';
import { statusTagColor } from '@/shared/lib/admin/tagColors';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    appeal: Object,
    order: Object,
    orderHref: String,
});

const { t } = useI18n();

const reasonLabel = computed(() => t(`admin.appeals.reasons.${props.appeal.reason}`, props.appeal.reason));
const sideLabel = computed(() => t(`admin.appeals.sides.${props.appeal.side}`, props.appeal.side));
const statusLabel = computed(() => t(`admin.appeals.statuses.${props.appeal.status}`, props.appeal.status));

function formatDate(value) {
    return value ? new Date(value).toLocaleString('ru-RU') : t('admin.shared.empty');
}
</script>

<template>
    <Head :title="t('admin.appeals.show.headTitle', { id: appeal.id })" />

    <AdminLayout>
        <template #title>{{ t('admin.appeals.show.title', { id: appeal.id }) }}</template>

        <AdminPage>
            <AdminBackLink href="/admin/appeals" />

            <a-card :bordered="false" size="small" class="mb-4">
                <div class="flex flex-wrap items-center gap-2">
                    <a-tag>{{ sideLabel }}</a-tag>
                    <a-tag :color="statusTagColor(appeal.status)">{{ statusLabel }}</a-tag>
                    <span class="admin-ant-meta">{{ formatDate(appeal.created_at) }}</span>
                </div>

                <a-descriptions :column="1" size="small" class="mt-4">
                    <a-descriptions-item :label="t('admin.appeals.columns.order')">
                        <Link :href="orderHref">#{{ order.id }}</Link>
                    </a-descriptions-item>
                    <a-descriptions-item :label="t('admin.appeals.columns.client')">
                        {{ order.user?.phone ?? '—' }}
                    </a-descriptions-item>
                    <a-descriptions-item :label="t('admin.appeals.columns.reason')">
                        {{ reasonLabel }}
                    </a-descriptions-item>
                    <a-descriptions-item :label="t('admin.appeals.show.openedBy')">
                        {{ appeal.opened_by }}
                    </a-descriptions-item>
                    <a-descriptions-item :label="t('admin.appeals.columns.amount')">
                        {{ order.fiat_amount }} ₸ / {{ order.crypto_amount }} USDT
                    </a-descriptions-item>
                </a-descriptions>

                <div class="mt-4">
                    <p class="mb-2 font-medium">{{ t('admin.appeals.show.description') }}</p>
                    <p class="whitespace-pre-wrap text-sm">
                        {{ appeal.description || t('admin.appeals.show.noDescription') }}
                    </p>
                </div>
            </a-card>

            <a-card :bordered="false" size="small">
                <p class="mb-3 font-medium">{{ t('admin.appeals.show.attachments') }}</p>

                <div v-if="appeal.attachments?.length" class="space-y-3">
                    <PaymentProofPreview
                        v-for="attachment in appeal.attachments"
                        :key="attachment.id"
                        :proof="attachment"
                    />
                </div>
                <a-empty v-else :description="t('admin.appeals.show.noAttachments')" />
            </a-card>
        </AdminPage>
    </AdminLayout>
</template>
