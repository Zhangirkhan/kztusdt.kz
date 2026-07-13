<script setup>
import { useAdminManualKyc } from '@/features/admin-manual-kyc/model/useAdminManualKyc';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
});

const { t } = useI18n();
const open = ref(false);
const { form, submit, isLegalEntity } = useAdminManualKyc(props.user);

const documentOptions = computed(() => (isLegalEntity.value
    ? [
        { label: t('admin.manualKycForm.documents.registration'), value: 'registration' },
        { label: t('admin.manualKycForm.documents.repId'), value: 'id_card' },
        { label: t('admin.manualKycForm.documents.repPassport'), value: 'passport' },
    ]
    : [
        { label: t('admin.manualKycForm.documents.idCard'), value: 'id_card' },
        { label: t('admin.manualKycForm.documents.passport'), value: 'passport' },
    ]));

function handleSubmit() {
    submit({
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <a-button type="primary" @click="open = true">
        {{ t('admin.manualKycForm.open') }}
    </a-button>

    <a-modal
        v-model:open="open"
        :title="t('admin.manualKycForm.title')"
        :ok-text="t('admin.shared.actions.approve')"
        :cancel-text="t('admin.shared.actions.cancel')"
        :confirm-loading="form.processing"
        width="640px"
        destroy-on-close
        @ok="handleSubmit"
    >
        <a-typography-text type="secondary" class="admin-ant-block">
            {{ t('admin.manualKycForm.hint') }}
        </a-typography-text>

        <a-alert
            type="warning"
            :message="t('admin.manualKycForm.warning')"
            show-icon
            class="admin-ant-block"
        />

        <a-form layout="vertical">
            <a-row :gutter="16">
                <a-col v-if="isLegalEntity" :span="24">
                    <a-form-item :label="t('admin.manualKycForm.companyName')" required>
                        <a-input v-model:value="form.company_name" />
                    </a-form-item>
                </a-col>
                <a-col :xs="24" :md="12">
                    <a-form-item :label="isLegalEntity ? t('admin.manualKycForm.repFirstName') : t('admin.manualKycForm.firstName')" required>
                        <a-input v-model:value="form.first_name" />
                    </a-form-item>
                </a-col>
                <a-col :xs="24" :md="12">
                    <a-form-item :label="isLegalEntity ? t('admin.manualKycForm.repLastName') : t('admin.manualKycForm.lastName')" required>
                        <a-input v-model:value="form.last_name" />
                    </a-form-item>
                </a-col>
                <a-col :xs="24" :md="12">
                    <a-form-item :label="t('admin.manualKycForm.documentType')">
                        <a-select v-model:value="form.document_type" :options="documentOptions" />
                    </a-form-item>
                </a-col>
                <a-col :xs="24" :md="12">
                    <a-form-item :label="isLegalEntity ? t('admin.manualKycForm.binOrDocument') : t('admin.manualKycForm.documentNumber')">
                        <a-input v-model:value="form.document_number" />
                    </a-form-item>
                </a-col>
                <a-col :span="24">
                    <a-form-item :label="t('admin.manualKycForm.comment')">
                        <a-textarea v-model:value="form.comment" :placeholder="t('admin.manualKycForm.commentPlaceholder')" :rows="2" />
                    </a-form-item>
                </a-col>
            </a-row>

            <a-alert
                v-if="form.errors.manual_kyc"
                type="error"
                :message="form.errors.manual_kyc"
                show-icon
            />
        </a-form>
    </a-modal>
</template>
