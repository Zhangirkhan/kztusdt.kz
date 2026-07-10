<script setup>
import { formatPercent, formatUsdt } from '@/utils/formatNumber';
import { clampDecimalAmount, formatAmountForInput, maxWithdrawableAmount } from '@/utils/amountInput';
import {
    clearWalletAddressMask,
    formatWalletAddress,
    isWalletAddressComplete,
    isWalletAddressValid,
    walletAddressError,
    walletAddressHint,
    walletAddressMaxLength,
    walletAddressPlaceholder,
} from '@/utils/walletAddressMask';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    balance: {
        type: Object,
        required: true,
    },
    networks: {
        type: Array,
        default: () => [],
    },
    withdraw: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const addressTouched = ref(false);

const form = useForm({
    network: props.networks[0]?.code ?? 'BEP20',
    to_address: '',
    amount: '',
});

const currentNetwork = computed(
    () => props.networks.find((n) => n.code === form.network) || props.networks[0] || {},
);

const addressFormat = computed(() => currentNetwork.value.address_format || 'evm');

const isTron = computed(() => addressFormat.value === 'tron');

const addressPlaceholder = computed(() => walletAddressPlaceholder(addressFormat.value));

const addressHint = computed(() => walletAddressHint(addressFormat.value));

const addressMaxLength = computed(() => walletAddressMaxLength(addressFormat.value));

const maxAmount = computed(() => maxWithdrawableAmount(
    props.balance.available,
    props.withdraw.feePercent,
    props.withdraw.networkFee,
));

const localAddressError = computed(() => {
    if (!addressTouched.value && !form.errors.to_address) {
        return null;
    }

    return walletAddressError(form.to_address, addressFormat.value);
});

const addressFieldError = computed(() => form.errors.to_address || localAddressError.value);

const statusLabels = {
    created: 'Создана',
    awaiting_telegram_confirmation: 'Ждёт подтверждения',
    pending_review: 'На проверке СБ',
    approved: 'Одобрена, в очереди',
    sending: 'Отправляется',
    sent: 'Отправлена, ждём сеть',
    completed: 'Выполнена',
    cancelled: 'Отменена',
    failed: 'Ошибка',
    rejected: 'Отклонена',
};

const preview = computed(() => {
    const amount = parseFloat(form.amount) || 0;
    const fee = (amount * props.withdraw.feePercent) / 100;
    const network = parseFloat(props.withdraw.networkFee);

    return {
        fee: formatUsdt(fee, 4),
        network: formatUsdt(network, 4),
        total: formatUsdt(amount + fee + network, 4),
    };
});

watch(
    () => form.network,
    (code) => {
        const net = props.networks.find((n) => n.code === code);
        const format = net?.address_format || 'evm';
        form.to_address = clearWalletAddressMask(format);
        addressTouched.value = false;
        form.clearErrors('to_address', 'network');
    },
);

function onAddressInput(event) {
    addressTouched.value = true;
    const next = formatWalletAddress(event.target.value, addressFormat.value);
    form.to_address = next;
    event.target.value = next;
    form.clearErrors('to_address');
}

function onAmountInput(event) {
    const next = clampDecimalAmount(event.target.value, maxAmount.value, { maxDecimals: 2 });
    form.amount = next;
    event.target.value = next;
}

function setMaxAmount() {
    form.amount = formatAmountForInput(maxAmount.value, 2);
    form.clearErrors('amount');
}

function submit() {
    addressTouched.value = true;
    form.to_address = formatWalletAddress(form.to_address, addressFormat.value);
    form.amount = clampDecimalAmount(form.amount, maxAmount.value, { maxDecimals: 2 });

    if (!isWalletAddressValid(form.to_address, addressFormat.value)) {
        return;
    }

    form.post(route('withdraw.store'), {
        preserveScroll: true,
        onSuccess: () => {
            addressTouched.value = false;
            form.reset('to_address', 'amount');
        },
    });
}

function cancelWithdrawal(id) {
    if (confirm('Отменить заявку на вывод?')) {
        router.post(route('withdraw.cancel', id), {}, { preserveScroll: true });
    }
}

function short(value) {
    return value ? `${value.slice(0, 8)}…${value.slice(-6)}` : '—';
}

function formatDate(value) {
    return new Date(value).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <section class="wallet-section">
        <div v-if="page.props.flash?.success" class="wallet-flash mb-4">
            {{ page.props.flash.success }}
        </div>

        <div v-if="!withdraw.withdrawalsEnabled" class="wallet-warning mb-4">
            <span class="material-symbols-outlined wallet-warning__icon" aria-hidden="true">warning</span>
            <p class="wallet-warning__text">
                Автоматическая отправка временно отключена: заявки принимаются и будут отправлены после включения.
            </p>
        </div>

        <div class="wallet-section-head">
            <p class="wallet-section-head__label">Выберите сеть</p>
        </div>

        <div v-if="networks.length > 1" class="wallet-network-grid">
            <button
                v-for="net in networks"
                :key="net.code"
                type="button"
                class="wallet-network-pill"
                :class="net.code === form.network ? 'wallet-network-pill--active' : 'wallet-network-pill--inactive'"
                @click="form.network = net.code"
            >
                {{ net.code }}
            </button>
        </div>
        <p v-if="form.errors.network" class="mt-2 text-sm text-error">{{ form.errors.network }}</p>

        <label class="wallet-address-label">
            Адрес получателя · {{ currentNetwork.label || currentNetwork.code }}
        </label>
        <p class="mb-2 text-xs text-text-dim">{{ addressHint }}</p>
        <input
            :value="form.to_address"
            class="input-field font-mono text-sm"
            :placeholder="addressPlaceholder"
            :maxlength="addressMaxLength"
            inputmode="text"
            autocomplete="off"
            spellcheck="false"
            :aria-invalid="Boolean(addressFieldError)"
            @input="onAddressInput"
            @blur="addressTouched = true"
        />
        <p
            v-if="form.to_address && !isWalletAddressComplete(form.to_address, addressFormat)"
            class="mt-2 text-xs text-text-dim"
        >
            {{ isTron ? `${form.to_address.length} / 34` : `${form.to_address.length} / 42` }}
        </p>
        <p v-if="addressFieldError" class="mt-2 text-sm text-error">{{ addressFieldError }}</p>

        <label class="wallet-address-label mt-4">
            Сумма USDT
        </label>
        <div class="wallet-amount-row">
            <input
                :value="form.amount"
                type="text"
                class="input-field"
                inputmode="decimal"
                autocomplete="off"
                placeholder="0.00"
                :aria-invalid="Boolean(form.errors.amount)"
                @input="onAmountInput"
            />
            <button
                type="button"
                class="wallet-amount-max"
                :disabled="maxAmount <= 0"
                @click="setMaxAmount"
            >
                MAX
            </button>
        </div>
        <p class="mt-2 text-center text-xs text-text-dim">
            Доступно {{ formatUsdt(balance.available, 2) }} USDT
            <span v-if="parseFloat(balance.locked) > 0">
                · Заблокировано {{ formatUsdt(balance.locked, 2) }} USDT
            </span>
            <br>
            Можно вывести до {{ formatUsdt(maxAmount, 2) }} USDT (с учётом комиссий).
            Минимум {{ formatUsdt(withdraw.minAmount, 2) }} USDT. Все выводы проходят ручную проверку СБ.
        </p>
        <p v-if="form.errors.amount" class="mt-2 text-sm text-error">{{ form.errors.amount }}</p>

        <div class="wallet-withdraw-preview">
            <div class="wallet-withdraw-preview__row wallet-withdraw-preview__row--strong">
                <span>Получатель получит</span>
                <span>{{ formatUsdt(parseFloat(form.amount) || 0, 4) }} USDT</span>
            </div>
            <div class="wallet-withdraw-preview__row">
                <span>Комиссия сервиса ({{ formatPercent(withdraw.feePercent) }}%)</span>
                <span>{{ preview.fee }} USDT</span>
            </div>
            <div class="wallet-withdraw-preview__row">
                <span>Комиссия сети</span>
                <span>{{ preview.network }} USDT</span>
            </div>
            <div class="wallet-withdraw-preview__row wallet-withdraw-preview__row--total">
                <span>Итого к списанию</span>
                <span>{{ preview.total }} USDT</span>
            </div>
        </div>

        <p v-if="form.errors.form" class="text-sm text-error">{{ form.errors.form }}</p>

        <button type="button" class="btn-primary wallet-copy-cta" :disabled="form.processing" @click="submit">
            Создать заявку
        </button>
        <p class="mt-3 text-center text-xs text-text-dim">
            После создания заявка будет проверена службой безопасности.
        </p>
    </section>

    <section v-if="withdraw.withdrawals.length" class="wallet-section wallet-section--card">
        <p class="wallet-section-head__label mb-3">Мои выводы</p>
        <div class="wallet-deposits">
            <div
                v-for="withdrawal in withdraw.withdrawals"
                :key="withdrawal.id"
                class="wallet-deposit-item"
            >
                <div class="wallet-deposit-item__icon wallet-deposit-item__icon--out">
                    <span class="material-symbols-outlined text-lg">north_east</span>
                </div>
                <div class="wallet-deposit-item__info">
                    <p class="wallet-deposit-item__amount">−{{ formatUsdt(withdrawal.amount, 2) }} USDT</p>
                    <p class="wallet-deposit-item__hash">{{ withdrawal.network }} · {{ short(withdrawal.to_address) }}</p>
                    <a
                        v-if="withdrawal.tx_hash && withdrawal.explorer_tx"
                        :href="`${withdrawal.explorer_tx}${withdrawal.tx_hash}`"
                        target="_blank"
                        rel="noopener"
                        class="wallet-deposit-item__hash"
                    >
                        tx: {{ short(withdrawal.tx_hash) }}
                    </a>
                </div>
                <div class="wallet-deposit-item__status">
                    <span
                        class="wallet-deposit-item__badge"
                        :class="{
                            'wallet-deposit-item__badge--success': withdrawal.status === 'completed',
                            'wallet-deposit-item__badge--error': ['cancelled', 'rejected', 'failed'].includes(withdrawal.status),
                        }"
                    >
                        {{ statusLabels[withdrawal.status] ?? withdrawal.status }}
                    </span>
                    <p class="wallet-deposit-item__confirm">{{ formatDate(withdrawal.created_at) }}</p>
                    <button
                        v-if="['awaiting_telegram_confirmation', 'pending_review'].includes(withdrawal.status)"
                        type="button"
                        class="wallet-withdraw-cancel"
                        @click="cancelWithdrawal(withdrawal.id)"
                    >
                        Отменить
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>
