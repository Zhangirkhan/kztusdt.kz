<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    options: {
        type: Object,
        required: true,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['submitted']);

const { t } = useI18n();

const form = useForm({
    source_of_funds: '',
    source_of_funds_other: '',
    occupation: '',
    industry: '',
    industry_other: '',
    annual_income: '',
    platform_purpose: '',
    platform_purpose_other: '',
});

const showSourceOther = computed(() => form.source_of_funds === 'other');
const showIndustryOther = computed(() => form.industry === 'other');
const showPurposeOther = computed(() => form.platform_purpose === 'other');

function optionLabel(group, value) {
    return t(`dueDiligence.options.${group}.${value}`);
}

function validateClient() {
    const required = [
        ['source_of_funds', t('dueDiligence.fields.sourceOfFunds')],
        ['occupation', t('dueDiligence.fields.occupation')],
        ['industry', t('dueDiligence.fields.industry')],
        ['annual_income', t('dueDiligence.fields.annualIncome')],
        ['platform_purpose', t('dueDiligence.fields.platformPurpose')],
    ];

    const errors = {};

    for (const [key, label] of required) {
        if (!form[key]) {
            errors[key] = t('dueDiligence.requiredField', { field: label });
        }
    }

    if (showSourceOther.value && !form.source_of_funds_other.trim()) {
        errors.source_of_funds_other = t('dueDiligence.otherPlaceholder');
    }
    if (showIndustryOther.value && !form.industry_other.trim()) {
        errors.industry_other = t('dueDiligence.otherPlaceholder');
    }
    if (showPurposeOther.value && !form.platform_purpose_other.trim()) {
        errors.platform_purpose_other = t('dueDiligence.otherPlaceholder');
    }

    form.clearErrors();
    if (Object.keys(errors).length > 0) {
        form.setError(errors);
        return false;
    }

    return true;
}

function submit() {
    if (!validateClient()) {
        return;
    }

    form.post(route('due-diligence.store'), {
        preserveScroll: true,
        onSuccess: () => emit('submitted'),
    });
}
</script>

<template>
    <form
        class="due-diligence-form"
        :class="{ 'due-diligence-form--compact': compact }"
        novalidate
        @submit.prevent="submit"
    >
        <div v-if="!compact" class="due-diligence-form__intro">
            <p class="text-label-caps uppercase tracking-wide text-text-dim">{{ t('dueDiligence.title') }}</p>
            <p class="mt-2 text-body-sm text-text-muted">{{ t('dueDiligence.subtitle') }}</p>
        </div>

        <div class="due-diligence-form__fields">
            <div class="due-diligence-form__field">
                <label class="due-diligence-form__label">{{ t('dueDiligence.fields.sourceOfFunds') }}</label>
                <div class="due-diligence-form__select-wrap">
                    <select v-model="form.source_of_funds" class="due-diligence-form__select" required>
                        <option disabled value="">{{ t('dueDiligence.selectPlaceholder') }}</option>
                        <option v-for="value in options.sourceOfFunds" :key="value" :value="value">
                            {{ optionLabel('sourceOfFunds', value) }}
                        </option>
                    </select>
                </div>
                <input
                    v-if="showSourceOther"
                    v-model="form.source_of_funds_other"
                    class="input-field due-diligence-form__other"
                    :placeholder="t('dueDiligence.otherPlaceholder')"
                    required
                />
                <p v-if="form.errors.source_of_funds || form.errors.source_of_funds_other" class="due-diligence-form__error">
                    {{ form.errors.source_of_funds || form.errors.source_of_funds_other }}
                </p>
            </div>

            <div class="due-diligence-form__field">
                <label class="due-diligence-form__label">{{ t('dueDiligence.fields.occupation') }}</label>
                <div class="due-diligence-form__select-wrap">
                    <select v-model="form.occupation" class="due-diligence-form__select" required>
                        <option disabled value="">{{ t('dueDiligence.selectPlaceholder') }}</option>
                        <option v-for="value in options.occupations" :key="value" :value="value">
                            {{ optionLabel('occupations', value) }}
                        </option>
                    </select>
                </div>
                <p v-if="form.errors.occupation" class="due-diligence-form__error">{{ form.errors.occupation }}</p>
            </div>

            <div class="due-diligence-form__field">
                <label class="due-diligence-form__label">{{ t('dueDiligence.fields.industry') }}</label>
                <div class="due-diligence-form__select-wrap">
                    <select v-model="form.industry" class="due-diligence-form__select" required>
                        <option disabled value="">{{ t('dueDiligence.selectPlaceholder') }}</option>
                        <option v-for="value in options.industries" :key="value" :value="value">
                            {{ optionLabel('industries', value) }}
                        </option>
                    </select>
                </div>
                <input
                    v-if="showIndustryOther"
                    v-model="form.industry_other"
                    class="input-field due-diligence-form__other"
                    :placeholder="t('dueDiligence.otherPlaceholder')"
                    required
                />
                <p v-if="form.errors.industry || form.errors.industry_other" class="due-diligence-form__error">
                    {{ form.errors.industry || form.errors.industry_other }}
                </p>
            </div>

            <div class="due-diligence-form__field">
                <label class="due-diligence-form__label">{{ t('dueDiligence.fields.annualIncome') }}</label>
                <div class="due-diligence-form__select-wrap">
                    <select v-model="form.annual_income" class="due-diligence-form__select" required>
                        <option disabled value="">{{ t('dueDiligence.selectPlaceholder') }}</option>
                        <option v-for="value in options.annualIncomes" :key="value" :value="value">
                            {{ optionLabel('annualIncomes', value) }}
                        </option>
                    </select>
                </div>
                <p v-if="form.errors.annual_income" class="due-diligence-form__error">{{ form.errors.annual_income }}</p>
            </div>

            <div class="due-diligence-form__field">
                <label class="due-diligence-form__label">{{ t('dueDiligence.fields.platformPurpose') }}</label>
                <div class="due-diligence-form__select-wrap">
                    <select v-model="form.platform_purpose" class="due-diligence-form__select" required>
                        <option disabled value="">{{ t('dueDiligence.selectPlaceholder') }}</option>
                        <option v-for="value in options.platformPurposes" :key="value" :value="value">
                            {{ optionLabel('platformPurposes', value) }}
                        </option>
                    </select>
                </div>
                <input
                    v-if="showPurposeOther"
                    v-model="form.platform_purpose_other"
                    class="input-field due-diligence-form__other"
                    :placeholder="t('dueDiligence.otherPlaceholder')"
                    required
                />
                <p v-if="form.errors.platform_purpose || form.errors.platform_purpose_other" class="due-diligence-form__error">
                    {{ form.errors.platform_purpose || form.errors.platform_purpose_other }}
                </p>
            </div>

            <p v-if="form.errors.form" class="due-diligence-form__error">{{ form.errors.form }}</p>
        </div>

        <div class="due-diligence-form__actions">
            <button type="submit" class="btn-primary" :disabled="form.processing">
                {{ form.processing ? t('dueDiligence.submitting') : t('dueDiligence.submit') }}
            </button>
        </div>
    </form>
</template>

<style scoped>
.due-diligence-form {
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.due-diligence-form__intro {
    margin-bottom: 20px;
}

.due-diligence-form__fields {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.due-diligence-form--compact {
    flex: 1;
    min-height: 0;
}

.due-diligence-form--compact .due-diligence-form__fields {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    gap: 16px;
    padding-right: 2px;
    -webkit-overflow-scrolling: touch;
}

.due-diligence-form__field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.due-diligence-form__label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.35;
    letter-spacing: 0.01em;
    color: var(--color-text-secondary, #64748b);
}

.due-diligence-form__select-wrap {
    position: relative;
}

.due-diligence-form__select {
    display: block;
    width: 100%;
    min-height: 52px;
    padding: 14px 44px 14px 16px;
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: 14px;
    background-color: var(--color-surface-muted, #f8fafc);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none'%3E%3Cpath d='M6 9l6 6 6-6' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    background-size: 18px 18px;
    color: var(--color-text, #0f172a);
    font-size: 15px;
    font-weight: 500;
    line-height: 1.4;
    cursor: pointer;
    outline: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
}

.due-diligence-form__select:hover {
    border-color: color-mix(in srgb, var(--color-accent, #2563eb) 35%, var(--color-border, #e2e8f0));
}

.due-diligence-form__select:focus {
    border-color: var(--color-accent, #2563eb);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-accent, #2563eb) 18%, transparent);
}

.due-diligence-form__select:invalid {
    color: var(--color-text-muted, #94a3b8);
    font-weight: 400;
}

.due-diligence-form__select option {
    color: var(--color-text, #0f172a);
    background: var(--color-surface, #fff);
    font-weight: 500;
}

.due-diligence-form__select option:disabled {
    color: var(--color-text-muted, #94a3b8);
}

.due-diligence-form__other {
    margin-top: 0;
    min-height: 52px;
    border-radius: 14px;
}

.due-diligence-form__error {
    margin: 0;
    font-size: 13px;
    line-height: 1.4;
    color: var(--color-error, #ef4444);
}

.due-diligence-form__actions {
    flex-shrink: 0;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--color-border, #e2e8f0);
}

.due-diligence-form--compact .due-diligence-form__actions {
    margin-top: 12px;
    padding-top: 12px;
}

:global(html.dark) .due-diligence-form__label {
    color: var(--color-text-secondary, #b8b8b8);
}

:global(html.dark) .due-diligence-form__select {
    background-color: var(--color-surface-muted, #1c1c1c);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none'%3E%3Cpath d='M6 9l6 6 6-6' stroke='%23a3a3a3' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    border-color: var(--color-border, #333);
    color: var(--color-text, #f5f5f5);
}

:global(html.dark) .due-diligence-form__select:hover {
    border-color: color-mix(in srgb, var(--color-accent, #7dd3fc) 40%, var(--color-border, #333));
}

:global(html.dark) .due-diligence-form__select:focus {
    border-color: var(--color-accent, #7dd3fc);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-accent, #7dd3fc) 22%, transparent);
}

:global(html.dark) .due-diligence-form__select:invalid {
    color: var(--color-text-muted, #a1a1a1);
}

:global(html.dark) .due-diligence-form__select option {
    color: var(--color-text, #f5f5f5);
    background: var(--color-surface, #171717);
}
</style>
