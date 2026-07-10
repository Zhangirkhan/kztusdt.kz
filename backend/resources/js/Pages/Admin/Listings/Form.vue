<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminBackLink from '@/shared/ui/admin/AdminBackLink.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import { formatKzt, formatRate } from '@/utils/formatNumber';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    listing: { type: Object, default: null },
    banks: { type: Array, default: () => [] },
    paymentTerms: { type: Array, default: () => [] },
    marketRate: { type: Number, required: true },
    marketBuyRate: { type: Number, required: true },
    marketSellRate: { type: Number, required: true },
    rateRange: { type: Object, required: true },
    quickPhrases: { type: Array, default: () => [] },
});

const isEdit = computed(() => Boolean(props.listing?.id));
const step = ref(1);

const form = useForm({
    direction: props.listing?.direction ?? 'sell_usdt',
    price_type: props.listing?.price_type ?? 'floating',
    fixed_rate: props.listing?.fixed_rate ?? props.marketRate,
    margin_percent: props.listing?.margin_percent ?? 0,
    total_usdt: props.listing?.total_usdt ?? 1000,
    min_limit_kzt: props.listing?.min_limit_kzt ?? 5000,
    max_limit_kzt: props.listing?.max_limit_kzt ?? 100000,
    payment_methods: props.listing?.payment_methods?.map((bank) => bank.code) ?? ['kaspi', 'halyk'],
    payment_term: props.listing?.payment_term ?? '15_min',
    conditions_text: props.listing?.conditions_text ?? '',
    sort_order: props.listing?.sort_order ?? 0,
    publish: false,
});

const finalRate = computed(() => {
    if (form.price_type === 'fixed') {
        return Number(form.fixed_rate) || 0;
    }

    const base = form.direction === 'sell_usdt' ? props.marketBuyRate : props.marketSellRate;
    const margin = Number(form.margin_percent) || 0;

    if (form.direction === 'sell_usdt') {
        return base * (1 + margin / 100);
    }

    return base * (1 - margin / 100);
});

const maxAdvertKzt = computed(() => finalRate.value * Number(form.total_usdt || 0));

function togglePaymentMethod(code) {
    const methods = [...form.payment_methods];
    const index = methods.indexOf(code);

    if (index >= 0) {
        methods.splice(index, 1);
    } else if (methods.length < 5) {
        methods.push(code);
    }

    form.payment_methods = methods;
}

function appendPhrase(phrase) {
    const current = form.conditions_text?.trim() ?? '';
    form.conditions_text = current ? `${current} ${phrase}` : phrase;
}

function nextStep() {
    if (step.value < 3) {
        step.value += 1;
    }
}

function prevStep() {
    if (step.value > 1) {
        step.value -= 1;
    }
}

function submit(publish) {
    form.publish = publish;
    const options = { preserveScroll: true };

    if (isEdit.value) {
        form.put(route('admin.listings.update', props.listing.id), options);
        return;
    }

    form.post(route('admin.listings.store'), options);
}

watch(
    () => form.price_type,
    (value) => {
        if (value === 'fixed' && !form.fixed_rate) {
            form.fixed_rate = props.marketRate;
        }
    },
);
</script>

<template>
    <Head :title="isEdit ? 'Редактировать объявление' : 'Создать объявление'" />

    <AdminLayout>
        <template #title>{{ isEdit ? 'Редактировать объявление' : 'Создание объявления' }}</template>

        <AdminPage>
            <AdminBackLink :href="route('admin.listings.index')" />

            <div class="listing-form-card">
                <h1 class="listing-form-card__title">
                    {{ isEdit ? 'Редактировать объявление' : 'Создать объявление' }}
                </h1>

                <div class="listing-form-steps">
                    <div class="listing-form-step" :class="{ 'listing-form-step--active': step === 1, 'listing-form-step--done': step > 1 }">
                        <span>1</span>
                        Цена
                    </div>
                    <div class="listing-form-step" :class="{ 'listing-form-step--active': step === 2, 'listing-form-step--done': step > 2 }">
                        <span>2</span>
                        Сумма
                    </div>
                    <div class="listing-form-step" :class="{ 'listing-form-step--active': step === 3 }">
                        <span>3</span>
                        Условия
                    </div>
                </div>

                <section v-show="step === 1" class="listing-form-section">
                    <label class="listing-form-label">ТИП ОБЪЯВЛЕНИЯ</label>
                    <div class="listing-form-direction-tabs">
                        <button
                            type="button"
                            class="listing-form-direction-tabs__btn"
                            :class="form.direction === 'sell_usdt'
                                ? 'listing-form-direction-tabs__btn--buy-active'
                                : 'listing-form-direction-tabs__btn--inactive'"
                            @click="form.direction = 'sell_usdt'"
                        >
                            Купить USDT
                        </button>
                        <button
                            type="button"
                            class="listing-form-direction-tabs__btn"
                            :class="form.direction === 'buy_usdt'
                                ? 'listing-form-direction-tabs__btn--sell-active'
                                : 'listing-form-direction-tabs__btn--inactive'"
                            @click="form.direction = 'buy_usdt'"
                        >
                            Продать USDT
                        </button>
                    </div>

                    <label class="listing-form-label">ТИП ЦЕНЫ</label>
                    <div class="listing-form-toggle">
                        <button
                            type="button"
                            :class="{ 'is-active': form.price_type === 'fixed' }"
                            @click="form.price_type = 'fixed'"
                        >
                            Фиксированная
                        </button>
                        <button
                            type="button"
                            :class="{ 'is-active': form.price_type === 'floating' }"
                            @click="form.price_type = 'floating'"
                        >
                            Плавающая
                        </button>
                    </div>

                    <div v-if="form.price_type === 'floating'" class="listing-form-floating">
                        <div class="listing-form-floating__item">
                            <span>Курс KASE</span>
                            <strong>{{ formatRate(marketRate) }} KZT за 1 USDT</strong>
                        </div>
                        <div class="listing-form-floating__item">
                            <span>Маржа, %</span>
                            <input v-model="form.margin_percent" type="number" step="0.01" class="listing-form-input" />
                        </div>
                        <div class="listing-form-floating__equals">=</div>
                        <div class="listing-form-floating__item">
                            <span>Итоговая цена</span>
                            <strong>{{ formatRate(finalRate) }} KZT</strong>
                            <small>{{ formatRate(marketRate) }} × (1 + {{ Number(form.margin_percent || 0).toFixed(2) }}%)</small>
                        </div>
                    </div>

                    <div v-else class="listing-form-fixed">
                        <label class="listing-form-label">Цена, KZT за 1 USDT</label>
                        <input v-model="form.fixed_rate" type="number" step="0.01" class="listing-form-input listing-form-input--large" />
                        <p class="listing-form-hint">
                            Допустимый диапазон: {{ formatRate(rateRange.min) }} - {{ formatRate(rateRange.max) }} KZT
                        </p>
                    </div>
                </section>

                <section v-show="step === 2" class="listing-form-section">
                    <label class="listing-form-label">Общая сумма USDT</label>
                    <input v-model="form.total_usdt" type="number" step="0.01" class="listing-form-input" />

                    <label class="listing-form-label">Минимальный лимит ордера в KZT</label>
                    <input v-model="form.min_limit_kzt" type="number" step="1" class="listing-form-input" />

                    <label class="listing-form-label">Максимальный лимит ордера в KZT</label>
                    <input v-model="form.max_limit_kzt" type="number" step="1" class="listing-form-input" />
                    <p class="listing-form-hint">Не больше объёма объявления: {{ formatKzt(maxAdvertKzt) }} KZT</p>

                    <label class="listing-form-label">СПОСОБ ОПЛАТЫ</label>
                    <div class="listing-form-chips">
                        <button
                            v-for="bank in banks"
                            :key="bank.code"
                            type="button"
                            class="listing-form-chip"
                            :class="{ 'listing-form-chip--active': form.payment_methods.includes(bank.code) }"
                            @click="togglePaymentMethod(bank.code)"
                        >
                            {{ bank.name }}
                        </button>
                    </div>
                    <p class="listing-form-hint">Можно добавить до 5 методов оплаты</p>
                    <p v-if="form.errors.payment_methods" class="listing-form-error">{{ form.errors.payment_methods }}</p>

                    <label class="listing-form-label">СРОК ОПЛАТЫ</label>
                    <div class="listing-form-chips">
                        <button
                            v-for="term in paymentTerms"
                            :key="term.value"
                            type="button"
                            class="listing-form-chip"
                            :class="{ 'listing-form-chip--active': form.payment_term === term.value }"
                            @click="form.payment_term = term.value"
                        >
                            {{ term.label }}
                        </button>
                    </div>
                </section>

                <section v-show="step === 3" class="listing-form-section">
                    <h2 class="listing-form-section__title">Условия сделки</h2>
                    <p class="listing-form-hint">Пользователь прочитает этот текст перед оплатой</p>
                    <textarea
                        v-model="form.conditions_text"
                        rows="5"
                        maxlength="500"
                        class="listing-form-textarea"
                        placeholder="Оплата принимается только с личного счёта..."
                    />
                    <p class="listing-form-counter">{{ (form.conditions_text || '').length }}/500</p>

                    <label class="listing-form-label">БЫСТРЫЕ ФРАЗЫ</label>
                    <div class="listing-form-chips">
                        <button
                            v-for="phrase in quickPhrases"
                            :key="phrase"
                            type="button"
                            class="listing-form-chip"
                            @click="appendPhrase(phrase)"
                        >
                            {{ phrase }}
                        </button>
                    </div>

                    <div v-if="form.conditions_text" class="listing-form-preview">
                        <span class="material-symbols-outlined" aria-hidden="true">info</span>
                        <p>{{ form.conditions_text }}</p>
                    </div>
                </section>

                <footer class="listing-form-footer">
                    <button v-if="step > 1" type="button" class="listing-form-footer__secondary" @click="prevStep">
                        Назад
                    </button>
                    <div class="listing-form-footer__spacer" />
                    <button
                        v-if="step < 3"
                        type="button"
                        class="listing-form-footer__primary"
                        @click="nextStep"
                    >
                        Продолжить
                    </button>
                    <button
                        v-else
                        type="button"
                        class="listing-form-footer__primary"
                        :disabled="form.processing"
                        @click="submit(true)"
                    >
                        Опубликовать
                    </button>
                </footer>
            </div>
        </AdminPage>
    </AdminLayout>
</template>

<style scoped>
.listing-form-card {
    max-width: 920px;
    margin: 0 auto;
    background: #fff;
    border-radius: 20px;
    border: 1px solid #edf2f7;
    padding: 28px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.05);
}

.listing-form-card__title {
    margin: 0 0 24px;
    text-align: center;
    font-size: 28px;
}

.listing-form-steps {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 28px;
}

.listing-form-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    color: #94a3b8;
    font-size: 13px;
}

.listing-form-step span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 999px;
    background: #f1f5f9;
    font-weight: 700;
}

.listing-form-step--active {
    color: #2563eb;
}

.listing-form-step--active span,
.listing-form-step--done span {
    background: #2563eb;
    color: #fff;
}

.listing-form-step--done {
    color: #16a34a;
}

.listing-form-step--done span {
    background: #16a34a;
}

.listing-form-section {
    display: grid;
    gap: 16px;
}

.listing-form-section__title {
    margin: 0;
    font-size: 20px;
}

.listing-form-label {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: #94a3b8;
}

.listing-form-toggle {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    padding: 4px;
    border-radius: 14px;
    background: #f8fafc;
}

.listing-form-toggle button {
    border: 0;
    border-radius: 10px;
    padding: 12px;
    background: transparent;
    font-weight: 700;
    cursor: pointer;
}

.listing-form-toggle button.is-active {
    background: #ef4444;
    color: #fff;
}

.listing-form-direction-tabs {
    display: flex;
    gap: 4px;
    padding: 4px;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
}

.listing-form-direction-tabs__btn {
    flex: 1;
    min-height: 44px;
    border: 2px solid transparent;
    border-radius: 12px;
    padding: 12px 8px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    transition: background-color 0.15s, color 0.15s, transform 0.15s;
}

.listing-form-direction-tabs__btn:active:not(:disabled) {
    transform: scale(0.97);
}

.listing-form-direction-tabs__btn--inactive {
    background: transparent;
    color: #64748b;
}

.listing-form-direction-tabs__btn--inactive:hover {
    background: #fff;
    color: #0f172a;
}

.listing-form-direction-tabs__btn--buy-active {
    background: #16a34a;
    color: #fff;
}

.listing-form-direction-tabs__btn--sell-active {
    background: #dc2626;
    color: #fff;
}

.listing-form-floating {
    display: grid;
    grid-template-columns: 1fr auto 1fr auto 1.2fr;
    gap: 12px;
    align-items: center;
}

.listing-form-floating__item,
.listing-form-fixed {
    padding: 16px;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
}

.listing-form-floating__item span,
.listing-form-fixed .listing-form-label {
    display: block;
    margin-bottom: 8px;
}

.listing-form-floating__item small {
    display: block;
    margin-top: 6px;
    color: #94a3b8;
}

.listing-form-floating__equals {
    font-size: 24px;
    color: #94a3b8;
    text-align: center;
}

.listing-form-input,
.listing-form-textarea {
    width: 100%;
    border: 1px solid #dbe3ee;
    border-radius: 12px;
    padding: 12px 14px;
    font: inherit;
}

.listing-form-input--large {
    font-size: 28px;
    font-weight: 700;
    text-align: center;
}

.listing-form-hint,
.listing-form-counter {
    margin: 0;
    color: #94a3b8;
    font-size: 13px;
}

.listing-form-error {
    margin: 0;
    color: #dc2626;
    font-size: 13px;
}

.listing-form-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.listing-form-chip {
    border: 1px solid #dbe3ee;
    background: #fff;
    border-radius: 999px;
    padding: 8px 14px;
    cursor: pointer;
}

.listing-form-chip--active {
    border-color: #2563eb;
    color: #2563eb;
    background: #eff6ff;
}

.listing-form-preview {
    display: flex;
    gap: 10px;
    padding: 14px;
    border-radius: 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.listing-form-footer {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 28px;
}

.listing-form-footer__spacer {
    flex: 1;
}

.listing-form-footer__primary,
.listing-form-footer__secondary {
    border: 0;
    border-radius: 12px;
    padding: 12px 20px;
    font-weight: 700;
    cursor: pointer;
}

.listing-form-footer__primary {
    background: #ef4444;
    color: #fff;
}

.listing-form-footer__secondary {
    background: #f8fafc;
    border: 1px solid #dbe3ee;
}

@media (max-width: 768px) {
    .listing-form-floating {
        grid-template-columns: 1fr;
    }

    .listing-form-floating__equals {
        display: none;
    }
}
</style>
