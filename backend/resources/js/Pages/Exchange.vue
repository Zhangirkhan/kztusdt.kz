<script setup>
import ExchangeLayout from '@/Layouts/ExchangeLayout.vue';
import AppIcon from '@/shared/ui/icon/AppIcon.vue';
import BankLogo from '@/shared/ui/bank-logo/BankLogo.vue';
import FlashBanner from '@/shared/ui/flash-banner/FlashBanner.vue';
import { formatKzt, formatPercent, formatRate, formatUsdt } from '@/utils/formatNumber';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    rates: Object,
    feePercent: Number,
    canTrade: Boolean,
    balance: Object,
    limits: Object,
    orders: Array,
    cards: { type: Array, default: () => [] },
    buyListings: { type: Array, default: () => [] },
    sellListings: { type: Array, default: () => [] },
    selectedListing: { type: Object, default: null },
    initialDirection: { type: String, default: 'buy' },
    paymentTermLabels: { type: Object, default: () => ({}) },
    companyRequisites: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const mode = ref(props.initialDirection === 'sell' ? 'sell' : 'buy');
const pickedListingId = ref(props.selectedListing?.id ?? null);
const buyInput = ref('kzt');
const bankPickerExpanded = ref(false);
const copiedField = ref(null);
const exchangeModeStorageKey = 'exchange.mode';

const form = useForm({
    direction: mode.value,
    listing_id: pickedListingId.value,
    kzt_amount: '',
    usdt_amount: '',
    card_id: '',
    payout_type: null,
    payment_bank_code: '',
});

const catalogListings = computed(() => (mode.value === 'buy' ? props.buyListings : props.sellListings));

const activeListing = computed(() => {
    if (pickedListingId.value === null) {
        return null;
    }

    return catalogListings.value.find((listing) => listing.id === pickedListingId.value) ?? props.selectedListing;
});

const activeLimits = computed(() => {
    if (activeListing.value) {
        return {
            min_buy_kzt: activeListing.value.min_limit_kzt,
            max_buy_kzt: activeListing.value.max_limit_kzt,
            min_sell_usdt: props.limits.min_sell_usdt,
            max_sell_usdt: Math.min(
                props.limits.max_sell_usdt,
                activeListing.value.remaining_usdt,
            ),
        };
    }

    return props.limits;
});

const activeRate = computed(() => {
    if (activeListing.value) {
        return activeListing.value.rate;
    }

    return mode.value === 'buy' ? props.rates.buy : props.rates.sell;
});

const selectedCard = computed(() => {
    if (form.card_id === null || form.card_id === '') {
        return null;
    }

    return props.cards.find((card) => card.id === Number(form.card_id)) ?? null;
});

const availablePayoutTypes = computed(() => selectedCard.value?.available_payout_types ?? []);

const statusLabels = {
    created: 'Создана',
    awaiting_kzt_payment: 'Ожидает оплату KZT',
    payment_proof_uploaded: 'Скрин загружен',
    pending_admin_confirmation: 'На подтверждении',
    kzt_sent: 'KZT отправлены',
    kzt_received: 'KZT получены',
    crypto_sending: 'Отправка крипты',
    crypto_sent: 'Крипта отправлена',
    completed: 'Выполнена',
    cancelled: 'Отменена',
    failed: 'Ошибка',
    dispute: 'Спор',
    manual_review: 'Ручная проверка',
};

function orderStatusBadgeClass(status) {
    if (status === 'completed') {
        return 'status-badge--success';
    }

    if (['cancelled', 'failed'].includes(status)) {
        return 'status-badge--error';
    }

    return 'status-badge--pending';
}

function roundUsdtBound(value) {
    return Math.round((Number(value) || 0) * 100) / 100;
}

const sellUsdtBounds = computed(() => {
    const rate = Number(activeRate.value);
    const available = Number.parseFloat(props.balance?.available ?? '0');
    const configMin = Number.parseFloat(props.limits?.min_sell_usdt ?? '0');
    const configMax = Number.parseFloat(props.limits?.max_sell_usdt ?? '0');

    if (!rate || rate <= 0) {
        return { min: 0, max: 0 };
    }

    const feeFactor = 1 - props.feePercent / 100;
    let min = configMin;
    let max = configMax;

    if (activeListing.value && feeFactor > 0) {
        const listingMinGross = Number(activeListing.value.min_limit_kzt) / (rate * feeFactor);
        const listingMaxGross = Number(activeListing.value.max_limit_kzt) / (rate * feeFactor);

        min = Math.max(min, listingMinGross);
        max = Math.min(max, listingMaxGross, Number(activeListing.value.remaining_usdt));
    } else if (activeListing.value) {
        max = Math.min(max, Number(activeListing.value.remaining_usdt));
    }

    max = Math.min(max, available);

    return {
        min: roundUsdtBound(Math.max(0, min)),
        max: roundUsdtBound(Math.max(0, max)),
    };
});

const sellUsdtMin = computed(() => sellUsdtBounds.value.min);

const sellUsdtMax = computed(() => sellUsdtBounds.value.max);

const buyUsdtBounds = computed(() => {
    const rate = Number(activeRate.value);

    if (!rate || rate <= 0) {
        return { min: 0, max: 0 };
    }

    const feeFactor = 1 - props.feePercent / 100;
    const grossMin = Number(activeLimits.value.min_buy_kzt) / rate;
    const grossMax = Number(activeLimits.value.max_buy_kzt) / rate;

    return {
        min: Math.max(0, grossMin * feeFactor),
        max: Math.max(0, grossMax * feeFactor),
    };
});

const submitLabel = computed(() => (mode.value === 'buy' ? 'Купить USDT →' : 'Продать USDT →'));

const selectedPaymentBank = computed(
    () => listingPaymentBanks.value.find((bank) => bank.code === form.payment_bank_code) ?? null,
);

const recentOrders = computed(() => (Array.isArray(props.orders) ? props.orders.slice(0, 5) : []));

const historyLink = computed(() => route('wallet.history', { section: 'exchange', filter: mode.value }));

const companyRequisiteRows = computed(() =>
    [
        { key: 'bank', label: 'Название банка', value: props.companyRequisites.bank_name },
        { key: 'account', label: 'Номер счёта', value: props.companyRequisites.recipient_account },
    ].filter((row) => row.value),
);

const canSubmitSell = computed(() => {
    if (mode.value !== 'sell') {
        return true;
    }

    return Boolean(
        props.cards.length > 0
        && form.card_id
        && form.payout_type
        && availablePayoutTypes.value.includes(form.payout_type)
        && sellUsdtMax.value >= sellUsdtMin.value
        && sellUsdtMax.value > 0,
    );
});

const listingPaymentBanks = computed(() => activeListing.value?.payment_methods ?? []);

const canSubmitBuy = computed(() => {
    if (mode.value !== 'buy' || listingPaymentBanks.value.length === 0) {
        return true;
    }

    return Boolean(form.payment_bank_code);
});

function syncPaymentBankForListing(listing) {
    const banks = listing?.payment_methods ?? [];

    if (banks.length === 0) {
        form.payment_bank_code = '';
        return;
    }

    if (!banks.some((bank) => bank.code === form.payment_bank_code)) {
        form.payment_bank_code = banks[0].code;
    }
}

function selectPaymentBank(code) {
    form.payment_bank_code = code;
    form.clearErrors('payment_bank_code');
    bankPickerExpanded.value = false;
}

function syncPayoutTypeForCard(card) {
    const types = card?.available_payout_types ?? [];

    if (types.includes('phone')) {
        form.payout_type = 'phone';
        return;
    }

    if (types.includes('iban')) {
        form.payout_type = 'iban';
        return;
    }

    form.payout_type = null;
}

const selectedPayoutLabel = computed(() => {
    if (!selectedCard.value || !form.payout_type) {
        return null;
    }

    if (form.payout_type === 'phone') {
        return selectedCard.value.phone_masked ?? selectedCard.value.phone;
    }

    return selectedCard.value.iban_masked ?? selectedCard.value.iban;
});

const selectedPayoutKindLabel = computed(() => {
    if (form.payout_type === 'phone') {
        return 'Телефон';
    }

    if (form.payout_type === 'iban') {
        return 'IBAN';
    }

    return null;
});

watch(
    () => form.card_id,
    () => {
        syncPayoutTypeForCard(selectedCard.value);
        form.clearErrors(['card_id', 'payout_type']);
    },
);

const preview = computed(() => {
    const fee = props.feePercent / 100;
    const rateValue = Number(activeRate.value);

    if (mode.value === 'buy') {
        if (buyInput.value === 'kzt') {
            const kzt = parseFloat(form.kzt_amount) || 0;
            const gross = kzt / rateValue;
            const feeAmount = gross * fee;

            return {
                fee: formatUsdt(feeAmount, 4) + ' USDT',
                give: formatKzt(kzt) + ' ₸',
                receive: formatUsdt(gross - feeAmount, 2) + ' USDT',
            };
        }

        const net = parseFloat(form.usdt_amount) || 0;
        const gross = net / (1 - fee);

        return {
            fee: formatUsdt(gross - net, 4) + ' USDT',
            give: formatKzt(gross * rateValue) + ' ₸',
            receive: formatUsdt(net, 2) + ' USDT',
        };
    }

    const gross = parseFloat(form.usdt_amount) || 0;
    const feeAmount = gross * fee;
    const kzt = (gross - feeAmount) * rateValue;

    return {
        fee: formatUsdt(feeAmount, 4) + ' USDT',
        give: formatUsdt(gross, 2) + ' USDT',
        receive: formatKzt(kzt) + ' ₸',
    };
});

const activeListingMarginMeta = computed(() => {
    if (!activeListing.value) {
        return null;
    }

    return listingMarginMeta(activeListing.value);
});

function listingMarginMeta(listing) {
    if (listing.price_type !== 'floating' || listing.margin_percent === null) {
        return null;
    }

    const margin = Number(listing.margin_percent);
    if (!Number.isFinite(margin) || margin === 0) {
        return null;
    }

    const baseRate = listing.market_rate ?? (mode.value === 'buy' ? props.rates.buy : props.rates.sell);

    return {
        baseRate,
        badge: `${margin >= 0 ? '+' : ''}${formatPercent(margin)}%`,
    };
}

function formatKztInput(value) {
    return String(Math.round(Number(value) || 0));
}

function formatUsdtInput(value) {
    return String(roundUsdtBound(value));
}

function defaultSellUsdtAmount() {
    if (sellUsdtMax.value < sellUsdtMin.value || sellUsdtMin.value <= 0) {
        return '';
    }

    return formatUsdtInput(sellUsdtMin.value);
}

function applyDefaultAmounts() {
    form.kzt_amount = formatKztInput(activeLimits.value.min_buy_kzt);

    if (mode.value === 'sell') {
        form.usdt_amount = defaultSellUsdtAmount();
    } else {
        form.usdt_amount = formatUsdtInput(buyUsdtBounds.value.min);
    }
}

function ensureSellUsdtDefault() {
    if (mode.value !== 'sell' || !activeListing.value) {
        return;
    }

    const amount = defaultSellUsdtAmount();

    if (amount === '') {
        form.usdt_amount = '';
        return;
    }

    const current = Number.parseFloat(form.usdt_amount);

    if (form.usdt_amount === '' || Number.isNaN(current) || current <= 0) {
        form.usdt_amount = amount;
    }
}

function openListing(listing) {
    pickedListingId.value = listing.id;
    form.listing_id = listing.id;
    syncPaymentBankForListing(listing);
    form.clearErrors();
    applyDefaultAmounts();
    ensureSellUsdtDefault();
}

function backToCatalog() {
    pickedListingId.value = null;
    form.listing_id = null;
    form.payment_bank_code = '';
    form.clearErrors();
}

function setMode(value) {
    if (mode.value === value) {
        return;
    }

    mode.value = value;
    form.direction = value;
    pickedListingId.value = null;
    form.listing_id = null;
    form.payment_bank_code = '';
    form.clearErrors();

    try {
        if (typeof window !== 'undefined') {
            window.localStorage.setItem(exchangeModeStorageKey, value);
        }
    } catch {
        // ignore
    }

    if (value === 'sell' && !form.card_id && props.cards.length === 1) {
        form.card_id = props.cards[0].id;
        syncPayoutTypeForCard(props.cards[0]);
    }
}

onMounted(() => {
    if (props.selectedListing?.id) {
        pickedListingId.value = props.selectedListing.id;
        mode.value = props.selectedListing.client_direction === 'sell' ? 'sell' : 'buy';
    } else {
        try {
            if (typeof window !== 'undefined') {
                const saved = window.localStorage.getItem(exchangeModeStorageKey);
                if (saved === 'buy' || saved === 'sell') {
                    mode.value = saved;
                }
            }
        } catch {
            // ignore
        }
    }

    form.direction = mode.value;
    form.listing_id = pickedListingId.value;

    if (pickedListingId.value !== null) {
        syncPaymentBankForListing(activeListing.value);
        applyDefaultAmounts();
        ensureSellUsdtDefault();
    } else {
        form.kzt_amount = formatKztInput(props.limits.min_buy_kzt);
        if (mode.value === 'sell') {
            ensureSellUsdtDefault();
        }
    }

    if (mode.value === 'sell' && !form.card_id && props.cards.length === 1) {
        form.card_id = props.cards[0].id;
        syncPayoutTypeForCard(props.cards[0]);
    }
});

watch(pickedListingId, (listingId) => {
    form.listing_id = listingId;

    if (listingId !== null) {
        syncPaymentBankForListing(activeListing.value);
        applyDefaultAmounts();
        ensureSellUsdtDefault();
    } else {
        form.payment_bank_code = '';
    }
});

function formatSellAmountForInput(amount) {
    return String(roundUsdtBound(amount));
}

function clampSellUsdtAmount(raw) {
    if (raw === '') {
        form.usdt_amount = '';
        return;
    }

    const value = Number.parseFloat(raw);

    if (Number.isNaN(value)) {
        form.usdt_amount = raw;
        return;
    }

    if (value < 0) {
        form.usdt_amount = '0';
        return;
    }

    const max = sellUsdtMax.value;
    const min = sellUsdtMin.value;

    if (max >= min && value < min) {
        form.usdt_amount = formatSellAmountForInput(min);
        return;
    }

    if (value > max) {
        form.usdt_amount = formatSellAmountForInput(max);
        return;
    }

    form.usdt_amount = raw;
}

function onSellUsdtInput(event) {
    clampSellUsdtAmount(event.target.value);
}

function selectCard(cardId) {
    form.card_id = cardId;
}

function submit() {
    if (mode.value === 'buy' && buyInput.value === 'kzt') {
        form.usdt_amount = '';
    }
    if (mode.value === 'buy' && buyInput.value === 'usdt') {
        form.kzt_amount = '';
    }

    if (mode.value === 'sell') {
        if (!canSubmitSell.value) {
            return;
        }
        clampSellUsdtAmount(form.usdt_amount);
    }

    form.post(route('exchange.orders.store'), { preserveScroll: true });
}

function formatDate(value) {
    return new Date(value).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

async function copyText(text, field) {
    if (!text) {
        return;
    }

    try {
        await navigator.clipboard.writeText(text);
        copiedField.value = field;
        setTimeout(() => {
            if (copiedField.value === field) {
                copiedField.value = null;
            }
        }, 2000);
    } catch {
        // clipboard may be unavailable
    }
}
</script>

<template>
    <Head title="Обмен" />

    <ExchangeLayout>
        <template #title>Обмен</template>

        <template #header-actions>
            <Link
                :href="historyLink"
                class="btn-icon wallet-header-btn flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-accent"
                aria-label="История"
            >
                <AppIcon name="history" :size="20" :stroke-width="2" />
            </Link>
        </template>

        <FlashBanner
            v-if="page.props.flash?.success"
            :message="page.props.flash.success"
            :auto-hide-ms="5000"
        />

        <div :class="mode === 'buy' ? 'exchange-mode--buy' : 'exchange-mode--sell'">
            <template v-if="!activeListing">
                <div class="buy-sell-tabs mb-stack-element">
                    <button
                        class="btn-segment"
                        :class="mode === 'buy' ? 'btn-segment--active' : 'btn-segment--inactive'"
                        @click="setMode('buy')"
                    >
                        Купить
                    </button>
                    <button
                        class="btn-segment"
                        :class="mode === 'sell' ? 'btn-segment--active' : 'btn-segment--inactive'"
                        @click="setMode('sell')"
                    >
                        Продать
                    </button>
                </div>

                <div v-if="catalogListings.length === 0" class="exchange-listing-empty">
                    Сейчас нет доступных объявлений для {{ mode === 'buy' ? 'покупки' : 'продажи' }} USDT.
                </div>

                <div v-else class="exchange-listings">
                    <article v-for="listing in catalogListings" :key="listing.id" class="exchange-listing-card">
                        <div class="exchange-listing-card__top">
                            <div class="exchange-listing-card__price">
                                <span class="exchange-listing-card__rate">{{ formatRate(listing.rate) }} KZT</span>
                                <p v-if="listingMarginMeta(listing)" class="exchange-listing-card__margin-line">
                                    <span class="exchange-listing-card__base-rate">
                                        {{ formatRate(listingMarginMeta(listing).baseRate) }}
                                    </span>
                                    <span class="exchange-listing-card__margin">{{ listingMarginMeta(listing).badge }}</span>
                                </p>
                            </div>
                            <span class="exchange-listing-card__term">
                                <span class="material-symbols-outlined text-sm" aria-hidden="true">schedule</span>
                                {{ listing.payment_term_label }}
                            </span>
                        </div>

                        <p class="exchange-listing-card__limits">
                            Лимит: {{ formatKzt(listing.min_limit_kzt) }} - {{ formatKzt(listing.max_limit_kzt) }} KZT
                        </p>

                        <div class="exchange-listing-card__banks">
                            <span
                                v-for="bank in listing.payment_methods"
                                :key="bank.code"
                                class="exchange-listing-card__bank"
                            >
                                <BankLogo :code="bank.code" size="xs" />
                                {{ bank.name }}
                            </span>
                        </div>

                        <button type="button" class="btn-primary exchange-listing-card__action" @click="openListing(listing)">
                            {{ mode === 'buy' ? 'Купить' : 'Продать' }}
                        </button>
                    </article>
                </div>
            </template>

            <template v-else>
                <button type="button" class="exchange-listing-back" @click="backToCatalog">
                    <span class="material-symbols-outlined text-base" aria-hidden="true">chevron_left</span>
                    Все объявления
                </button>

                <section class="card--highlight-exchange mb-stack-element">
                    <p class="text-label-caps uppercase text-white/70">
                        {{ mode === 'buy' ? 'Покупка USDT' : 'Продажа USDT' }}
                    </p>
                    <p class="mt-1 text-headline-md font-bold">
                        {{ formatRate(activeRate) }} ₸
                    </p>
                    <p
                        v-if="activeListingMarginMeta"
                        class="mt-1 flex items-center gap-2 text-body-sm text-white/80"
                    >
                        <span>{{ formatRate(activeListingMarginMeta.baseRate) }} ₸</span>
                        <span class="exchange-listing-card__margin exchange-listing-card__margin--on-dark">
                            {{ activeListingMarginMeta.badge }}
                        </span>
                    </p>
                    <p class="mt-2 text-body-sm text-white/80">
                        Срок оплаты: {{ activeListing.payment_term_label }}
                    </p>
                </section>

                <div v-if="!canTrade" class="warning-box">
                    Для обмена нужно подтвердить телефон и пройти KYC.
                    <Link :href="route('kyc')" class="exchange-accent mt-2 block font-semibold">Пройти KYC →</Link>
                </div>

                <template v-else>
                    <section class="card space-y-stack-element">
                        <template v-if="mode === 'buy'">
                            <div v-if="listingPaymentBanks.length > 0" class="exchange-bank-section">
                                <div>
                                    <p class="exchange-bank-section__title">Банк для оплаты</p>
                                    <p class="exchange-bank-section__hint">
                                        Выберите банк, с которого вы совершите перевод.
                                    </p>
                                </div>

                                <div
                                    v-if="selectedPaymentBank && !bankPickerExpanded"
                                    class="exchange-bank-selected"
                                >
                                    <BankLogo :code="selectedPaymentBank.code" size="sm" />
                                    <div class="min-w-0 flex-1">
                                        <p class="exchange-bank-selected__label">Банк оплаты</p>
                                        <p class="exchange-bank-selected__name">{{ selectedPaymentBank.name }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        class="exchange-bank-selected__change"
                                        @click="bankPickerExpanded = true"
                                    >
                                        Изменить
                                    </button>
                                </div>

                                <div v-else class="card-picker" role="radiogroup" aria-label="Банк для оплаты KZT">
                                    <button
                                        v-for="bank in listingPaymentBanks"
                                        :key="bank.code"
                                        type="button"
                                        role="radio"
                                        class="card-picker__option"
                                        :class="{ 'card-picker__option--active': form.payment_bank_code === bank.code }"
                                        :aria-checked="form.payment_bank_code === bank.code"
                                        @click="selectPaymentBank(bank.code)"
                                    >
                                        <BankLogo :code="bank.code" size="sm" />
                                        <span class="card-picker__body">
                                            <span class="card-picker__title">{{ bank.name }}</span>
                                            <span class="card-picker__subtitle">Перевод с этого банка</span>
                                        </span>
                                    </button>
                                </div>
                                <p v-if="form.errors.payment_bank_code" class="mt-1 text-sm text-red-400">
                                    {{ form.errors.payment_bank_code }}
                                </p>
                            </div>

                            <div class="order-flow__info">
                                <span class="material-symbols-outlined text-base" aria-hidden="true">info</span>
                                <p>Переводите только со своего банковского счёта. Убедитесь, что сумма совпадает с указанной.</p>
                            </div>

                            <div v-if="activeListing.conditions_text" class="order-flow__conditions">
                                <p class="order-flow__conditions-title">Условия сделки</p>
                                <p>{{ activeListing.conditions_text }}</p>
                            </div>

                            <div>
                                <label class="mb-2 block text-label-caps uppercase text-text-dim">Способ ввода</label>
                                <div class="recipient-type-tabs">
                                    <button
                                        type="button"
                                        class="btn-segment"
                                        :class="buyInput === 'kzt' ? 'btn-segment--active' : 'btn-segment--inactive'"
                                        @click="buyInput = 'kzt'"
                                    >
                                        Сумма KZT
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-segment"
                                        :class="buyInput === 'usdt' ? 'btn-segment--active' : 'btn-segment--inactive'"
                                        @click="buyInput = 'usdt'"
                                    >
                                        Получить USDT
                                    </button>
                                </div>
                            </div>

                            <div v-if="buyInput === 'kzt'">
                                <label class="mb-2 block text-label-caps uppercase text-text-dim">Сумма KZT</label>
                                <input v-model="form.kzt_amount" type="number" class="input-field" min="0" step="0.01" />
                                <div class="amount-quick-actions">
                                    <button
                                        type="button"
                                        class="btn-chip btn-chip--inactive"
                                        @click="form.kzt_amount = formatKztInput(activeLimits.min_buy_kzt)"
                                    >
                                        Мин {{ formatKzt(activeLimits.min_buy_kzt) }} ₸
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-chip btn-chip--inactive"
                                        @click="form.kzt_amount = formatKztInput(activeLimits.max_buy_kzt)"
                                    >
                                        Макс {{ formatKzt(activeLimits.max_buy_kzt) }} ₸
                                    </button>
                                </div>
                            </div>
                            <div v-else>
                                <label class="mb-2 block text-label-caps uppercase text-text-dim">Сумма USDT к получению</label>
                                <input v-model="form.usdt_amount" type="number" class="input-field" min="0" step="0.01" />
                                <div class="amount-quick-actions">
                                    <button
                                        type="button"
                                        class="btn-chip btn-chip--inactive"
                                        @click="form.usdt_amount = formatUsdtInput(buyUsdtBounds.min)"
                                    >
                                        Мин {{ formatUsdt(buyUsdtBounds.min, 2) }} USDT
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-chip btn-chip--inactive"
                                        @click="form.usdt_amount = formatUsdtInput(buyUsdtBounds.max)"
                                    >
                                        Макс {{ formatUsdt(buyUsdtBounds.max, 2) }} USDT
                                    </button>
                                </div>
                            </div>
                        </template>

                        <template v-else>
                            <div v-if="activeListing.conditions_text" class="order-flow__conditions">
                                <p class="order-flow__conditions-title">Условия сделки</p>
                                <p>{{ activeListing.conditions_text }}</p>
                            </div>

                            <div>
                                <label class="mb-2 block text-label-caps uppercase text-text-dim">Сумма USDT</label>
                                <input
                                    :value="form.usdt_amount"
                                    type="number"
                                    class="input-field"
                                    :min="sellUsdtMin"
                                    :max="sellUsdtMax"
                                    step="0.01"
                                    :disabled="!canSubmitSell"
                                    @input="onSellUsdtInput"
                                />
                                <p
                                    v-if="sellUsdtMax < sellUsdtMin"
                                    class="mt-2 text-sm text-red-400"
                                >
                                    Недостаточно USDT по объявлению или на балансе для минимальной суммы
                                    {{ formatUsdt(sellUsdtMin, 2) }} USDT.
                                </p>
                                <div class="amount-quick-actions">
                                    <button
                                        type="button"
                                        class="btn-chip btn-chip--inactive"
                                        :disabled="!canSubmitSell"
                                        @click="clampSellUsdtAmount(formatUsdtInput(sellUsdtMin))"
                                    >
                                        Мин {{ formatUsdt(sellUsdtMin, 2) }} USDT
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-chip btn-chip--inactive"
                                        :disabled="!canSubmitSell"
                                        @click="clampSellUsdtAmount(formatUsdtInput(sellUsdtMax))"
                                    >
                                        Макс {{ formatUsdt(sellUsdtMax, 2) }} USDT
                                    </button>
                                </div>
                                <p class="mt-1 text-body-sm text-text-dim">
                                    Доступно: {{ formatUsdt(balance.available, 2) }} USDT
                                </p>
                            </div>

                            <div v-if="cards.length === 0" class="warning-box space-y-2">
                                <p class="font-semibold">Нет сохранённых карт</p>
                                <p class="text-body-sm">
                                    Добавьте карту в профиле, затем вернитесь и выберите её для получения KZT.
                                </p>
                                <Link :href="route('profile.bank')" class="exchange-accent block font-semibold">
                                    Добавить карту в профиле →
                                </Link>
                            </div>

                            <template v-else>
                                <div>
                                    <label class="mb-2 block text-label-caps uppercase text-text-dim">Карта для получения KZT</label>
                                    <div class="card-picker" role="radiogroup" aria-label="Карта для получения KZT">
                                        <button
                                            v-for="card in cards"
                                            :key="card.id"
                                            type="button"
                                            role="radio"
                                            class="card-picker__option"
                                            :class="{ 'card-picker__option--active': Number(form.card_id) === card.id }"
                                            :aria-checked="Number(form.card_id) === card.id"
                                            @click="selectCard(card.id)"
                                        >
                                            <BankLogo :code="card.bank_code" size="sm" />
                                            <span class="card-picker__body">
                                                <span class="card-picker__title">{{ card.label }}</span>
                                                <span class="card-picker__subtitle">
                                                    {{ card.bank_name }}
                                                    <template v-if="card.phone_masked"> · {{ card.phone_masked }}</template>
                                                    <template v-else-if="card.iban_masked"> · {{ card.iban_masked }}</template>
                                                </span>
                                            </span>
                                        </button>
                                    </div>
                                    <p v-if="form.errors.card_id" class="mt-1 text-sm text-red-400">{{ form.errors.card_id }}</p>
                                </div>

                                <div v-if="selectedCard" class="selected-card-panel space-y-1">
                                    <p class="font-semibold">{{ selectedCard.holder_name }}</p>
                                    <p v-if="selectedPayoutKindLabel" class="text-text-dim">
                                        {{ selectedPayoutKindLabel }}:
                                        <span :class="form.payout_type === 'iban' ? 'font-mono' : ''">{{ selectedPayoutLabel }}</span>
                                    </p>
                                    <p v-if="form.errors.payout_type" class="text-sm text-red-400">{{ form.errors.payout_type }}</p>
                                </div>
                            </template>
                        </template>

                        <div class="exchange-preview rounded-xl p-4 text-body-sm">
                            <div class="flex justify-between py-1">
                                <span class="text-label-caps uppercase text-text-dim">Курс</span>
                                <span class="font-medium">{{ formatRate(activeRate) }} ₸ / USDT</span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-label-caps uppercase text-text-dim">Комиссия ({{ formatPercent(feePercent) }}%)</span>
                                <span class="font-medium">{{ preview.fee }}</span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-label-caps uppercase text-text-dim">Вы отдаёте</span>
                                <span class="font-medium">{{ preview.give }}</span>
                            </div>
                            <div class="flex justify-between border-t border-outline-variant/40 py-2 font-semibold">
                                <span class="text-label-caps uppercase">К получению</span>
                                <span class="exchange-accent">{{ preview.receive }}</span>
                            </div>
                        </div>

                        <p v-if="form.errors.form" class="text-sm text-red-400">{{ form.errors.form }}</p>

                        <button
                            class="btn-primary order-flow__cta"
                            :disabled="form.processing || (mode === 'sell' && !canSubmitSell) || (mode === 'buy' && !canSubmitBuy)"
                            @click="submit"
                        >
                            {{ submitLabel }}
                        </button>
                        <p class="text-center text-body-sm text-text-dim">KZT операции подтверждаются вручную</p>
                    </section>
                </template>
            </template>

            <section v-if="recentOrders.length && !activeListing" class="mt-stack-element">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-label-caps uppercase text-text-dim">Последние сделки</h2>
                    <Link :href="historyLink" class="text-sm font-semibold text-accent">Посмотреть все</Link>
                </div>
                <div class="space-y-3">
                    <Link
                        v-for="order in recentOrders"
                        :key="order.id"
                        :href="route('exchange.orders.show', order.id)"
                        class="card block"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-semibold">
                                    №{{ order.id }} · {{ order.direction === 'buy' ? 'Покупка' : 'Продажа' }}
                                </p>
                                <p class="mt-1 text-body-sm text-text-muted">
                                    {{ formatKzt(order.fiat_amount) }} ₸ ·
                                    {{ formatUsdt(order.crypto_amount, 2) }} USDT
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="status-badge" :class="orderStatusBadgeClass(order.status)">
                                    {{ statusLabels[order.status] ?? order.status }}
                                </span>
                                <p class="mt-1 text-body-sm text-text-dim">{{ formatDate(order.created_at) }}</p>
                            </div>
                        </div>
                    </Link>
                </div>
            </section>
        </div>
    </ExchangeLayout>
</template>
