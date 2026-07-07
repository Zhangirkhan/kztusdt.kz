<script setup>
import { useAdminManualKyc } from '@/features/admin-manual-kyc/model/useAdminManualKyc';
import { computed, ref } from 'vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
});

const open = ref(false);
const { form, submit, isLegalEntity } = useAdminManualKyc(props.user);

const documentOptions = computed(() => (isLegalEntity.value
    ? [
        { label: 'Свидетельство о регистрации', value: 'registration' },
        { label: 'Удостоверение представителя', value: 'id_card' },
        { label: 'Паспорт представителя', value: 'passport' },
    ]
    : [
        { label: 'Удостоверение', value: 'id_card' },
        { label: 'Паспорт', value: 'passport' },
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
        Ручное одобрение KYC
    </a-button>

    <a-modal
        v-model:open="open"
        title="Ручное одобрение KYC"
        ok-text="Одобрить"
        cancel-text="Отмена"
        :confirm-loading="form.processing"
        width="640px"
        destroy-on-close
        @ok="handleSubmit"
    >
        <a-typography-text type="secondary" class="admin-ant-block">
            Используйте, если клиент прошёл проверку офлайн или документы получены через поддержку.
        </a-typography-text>

        <a-alert
            type="warning"
            message="KYC будет одобрен без проверки загруженных документов на сервере."
            show-icon
            class="admin-ant-block"
        />

        <a-form layout="vertical">
            <a-row :gutter="16">
                <a-col v-if="isLegalEntity" :span="24">
                    <a-form-item label="Наименование организации" required>
                        <a-input v-model:value="form.company_name" />
                    </a-form-item>
                </a-col>
                <a-col :xs="24" :md="12">
                    <a-form-item :label="isLegalEntity ? 'Имя представителя' : 'Имя'" required>
                        <a-input v-model:value="form.first_name" />
                    </a-form-item>
                </a-col>
                <a-col :xs="24" :md="12">
                    <a-form-item :label="isLegalEntity ? 'Фамилия представителя' : 'Фамилия'" required>
                        <a-input v-model:value="form.last_name" />
                    </a-form-item>
                </a-col>
                <a-col :xs="24" :md="12">
                    <a-form-item label="Тип документа">
                        <a-select v-model:value="form.document_type" :options="documentOptions" />
                    </a-form-item>
                </a-col>
                <a-col :xs="24" :md="12">
                    <a-form-item :label="isLegalEntity ? 'БИН / номер документа' : 'Номер документа'">
                        <a-input v-model:value="form.document_number" />
                    </a-form-item>
                </a-col>
                <a-col :span="24">
                    <a-form-item label="Комментарий">
                        <a-textarea v-model:value="form.comment" placeholder="Необязательно" :rows="2" />
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
