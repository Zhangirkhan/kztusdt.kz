<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminBackLink from '@/shared/ui/admin/AdminBackLink.vue';
import AdminManualKycForm from '@/features/admin-manual-kyc/ui/AdminManualKycForm.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import { formatDateTime } from '@/shared/lib/format/date';
import { statusTagColor } from '@/shared/lib/admin/tagColors';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    user: Object,
});

const showStatusModal = ref(false);

const form = useForm({
    status: props.user.status,
});

const canManualApprove = computed(() => props.user.kyc_status !== 'approved');

const statusOptions = [
    { label: 'active', value: 'active' },
    { label: 'suspended', value: 'suspended' },
    { label: 'blocked', value: 'blocked' },
];

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
</script>

<template>
    <Head :title="`User #${user.id}`" />

    <AdminLayout>
        <template #title>Пользователь #{{ user.id }}</template>

        <AdminPage>
            <AdminBackLink href="/admin/users" label="К списку" />

            <a-space class="admin-ant-block">
                <AdminManualKycForm v-if="canManualApprove" :user="user" />
                <a-button @click="openStatusModal">Изменить статус</a-button>
            </a-space>

            <a-row :gutter="[16, 16]">
                <a-col :xs="24" :lg="12">
                    <a-card title="Профиль" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item label="Тип клиента">{{ user.client_type_label }}</a-descriptions-item>
                            <a-descriptions-item label="Имя">{{ user.name || '—' }}</a-descriptions-item>
                            <a-descriptions-item v-if="user.company_name" label="Организация">{{ user.company_name }}</a-descriptions-item>
                            <a-descriptions-item v-if="user.iin" label="ИИН">{{ user.iin }}</a-descriptions-item>
                            <a-descriptions-item v-if="user.bin" label="БИН">{{ user.bin }}</a-descriptions-item>
                            <a-descriptions-item label="Телефон">{{ user.phone || '—' }}</a-descriptions-item>
                            <a-descriptions-item label="Email">{{ user.email || '—' }}</a-descriptions-item>
                            <a-descriptions-item label="KYC">{{ user.kyc_status }}</a-descriptions-item>
                            <a-descriptions-item label="Статус">
                                <a-tag :color="statusTagColor(user.status)">{{ user.status }}</a-tag>
                            </a-descriptions-item>
                            <a-descriptions-item label="Телефон подтверждён">{{ user.phone_verified ? 'Да' : 'Нет' }}</a-descriptions-item>
                            <a-descriptions-item label="Подписка">{{ user.has_subscription ? 'Да' : 'Нет' }}</a-descriptions-item>
                            <a-descriptions-item label="Регистрация">{{ formatDateTime(user.created_at) }}</a-descriptions-item>
                        </a-descriptions>
                        <Link v-if="user.kyc_profile?.id" :href="`/admin/kyc/${user.kyc_profile.id}`">
                            <a-button type="link" style="padding-left: 0; margin-top: 8px">Открыть KYC заявку →</a-button>
                        </Link>
                    </a-card>
                </a-col>

                <a-col :xs="24" :lg="12">
                    <a-card title="Активность" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item label="Ордера">{{ user.counts.orders }}</a-descriptions-item>
                            <a-descriptions-item label="Выводы">{{ user.counts.withdrawals }}</a-descriptions-item>
                            <a-descriptions-item label="Депозиты">{{ user.counts.deposits }}</a-descriptions-item>
                            <a-descriptions-item v-if="user.kyc_profile" label="KYC профиль">
                                {{ user.kyc_profile.name }} ({{ user.kyc_profile.status }})
                            </a-descriptions-item>
                            <a-descriptions-item v-if="user.roles?.length" label="Роли">{{ user.roles.join(', ') }}</a-descriptions-item>
                        </a-descriptions>
                    </a-card>
                </a-col>
            </a-row>

            <a-modal
                v-model:open="showStatusModal"
                title="Статус аккаунта"
                ok-text="Сохранить"
                cancel-text="Отмена"
                :confirm-loading="form.processing"
                destroy-on-close
                @ok="submitStatus"
            >
                <a-form layout="vertical">
                    <a-form-item label="Статус">
                        <a-select v-model:value="form.status" :options="statusOptions" style="width: 100%" />
                    </a-form-item>
                </a-form>
            </a-modal>
        </AdminPage>
    </AdminLayout>
</template>
