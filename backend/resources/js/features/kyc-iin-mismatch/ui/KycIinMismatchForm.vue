<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { localizedPath } from '@/utils/localizedPath';

const emit = defineEmits(['confirmed']);
const { t } = useI18n();

const iin = ref('');
const submitting = ref(false);
const errorMessage = ref('');

const isIinComplete = computed(() => /^\d{12}$/.test(iin.value));
const iinFormatError = computed(() => {
    if (iin.value.length === 0 || isIinComplete.value) {
        return '';
    }

    return t('auth.iinError');
});
const canSubmit = computed(() => isIinComplete.value && !submitting.value);

function onIinInput(event) {
    iin.value = event.target.value.replace(/\D/g, '').slice(0, 12);
    event.target.value = iin.value;
    errorMessage.value = '';
}

async function submit() {
    if (!canSubmit.value) {
        return;
    }

    submitting.value = true;
    errorMessage.value = '';

    try {
        const response = await fetch(localizedPath('/kyc/confirm-iin'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ iin: iin.value }),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const validationError = data?.errors?.iin?.[0];
            errorMessage.value = validationError || data?.error || t('kyc.iinMismatch.submitFailed');

            return;
        }

        emit('confirmed', data);
    } catch {
        errorMessage.value = t('kyc.iinMismatch.submitFailed');
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <section class="card border border-amber-200 bg-amber-50">
        <div class="flex items-start gap-3">
            <span class="material-symbols-outlined mt-0.5 text-2xl text-amber-700">warning</span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-on-surface">{{ t('kyc.iinMismatch.title') }}</p>
                <p class="mt-1 text-body-sm text-text-muted">
                    {{ t('kyc.iinMismatch.subtitle') }}
                </p>

                <label class="mt-4 mb-2 block text-label-caps uppercase text-text-dim">
                    {{ t('auth.iinLabel') }}
                </label>
                <input
                    :value="iin"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    maxlength="12"
                    class="input-field"
                    @input="onIinInput"
                >
                <p v-if="errorMessage" class="mt-2 text-sm text-error">{{ errorMessage }}</p>
                <p v-else-if="iinFormatError" class="mt-2 text-sm text-error">{{ iinFormatError }}</p>

                <button
                    type="button"
                    class="btn-primary mt-4 w-full"
                    :disabled="!canSubmit"
                    @click="submit"
                >
                    {{ submitting ? t('kyc.iinMismatch.submitting') : t('kyc.iinMismatch.submit') }}
                </button>
            </div>
        </div>
    </section>
</template>
