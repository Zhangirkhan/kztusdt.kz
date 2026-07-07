<script setup>
import { KYC_DOCUMENT_TYPES } from '@/entities/kyc/lib/pendingReviewHint';
import { useKycManualForm } from '@/features/kyc-manual-form/model/useKycManualForm';

const props = defineProps({
    profile: {
        type: Object,
        default: null,
    },
    showAitu: {
        type: Boolean,
        default: false,
    },
});

const { form, submit, onFile } = useKycManualForm(props.profile);
</script>

<template>
    <section class="card space-y-stack-element">
        <div>
            <p class="text-label-caps uppercase text-text-dim">Ручная верификация</p>
            <p class="mt-2 text-body-sm text-text-muted">
                Заполните анкету и загрузите фото документа. Служба безопасности проверит заявку вручную.
            </p>
            <p v-if="showAitu" class="mt-2 text-sm font-semibold text-text-muted">или используйте форму ниже вместо Aitu</p>
        </div>

        <form class="space-y-stack-element" @submit.prevent="submit">
            <div>
                <label class="mb-2 block text-label-caps uppercase text-text-dim">Имя</label>
                <input v-model="form.first_name" class="input-field" required />
                <p v-if="form.errors.first_name" class="mt-2 text-sm text-error">{{ form.errors.first_name }}</p>
            </div>
            <div>
                <label class="mb-2 block text-label-caps uppercase text-text-dim">Фамилия</label>
                <input v-model="form.last_name" class="input-field" required />
                <p v-if="form.errors.last_name" class="mt-2 text-sm text-error">{{ form.errors.last_name }}</p>
            </div>
            <div>
                <label class="mb-2 block text-label-caps uppercase text-text-dim">Тип документа</label>
                <select v-model="form.document_type" class="input-field">
                    <option v-for="doc in KYC_DOCUMENT_TYPES" :key="doc.value" :value="doc.value">{{ doc.label }}</option>
                </select>
            </div>
            <div>
                <label class="mb-2 block text-label-caps uppercase text-text-dim">Номер документа</label>
                <input v-model="form.document_number" class="input-field" required />
                <p v-if="form.errors.document_number" class="mt-2 text-sm text-error">{{ form.errors.document_number }}</p>
            </div>

            <div class="space-y-3">
                <label class="block text-label-caps uppercase text-text-dim">Документы</label>
                <label v-for="field in ['id_front', 'id_back', 'selfie']" :key="field" class="card block cursor-pointer">
                    <span class="text-sm">
                        {{ field === 'id_front' ? 'Лицевая сторона' : field === 'id_back' ? 'Обратная сторона' : 'Селфи с документом' }}
                    </span>
                    <input type="file" accept="image/*" class="mt-2 block w-full text-sm" required @change="onFile(field, $event)" />
                    <p v-if="form.errors[field]" class="mt-2 text-sm text-error">{{ form.errors[field] }}</p>
                </label>
            </div>

            <p v-if="form.errors.form" class="text-sm text-error">{{ form.errors.form }}</p>
            <button type="submit" class="btn-primary" :disabled="form.processing">
                {{ form.processing ? 'Отправка…' : 'Отправить на проверку' }}
            </button>
        </form>
    </section>
</template>
