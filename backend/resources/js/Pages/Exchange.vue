<script setup>
import ExchangeLayout from '@/Layouts/ExchangeLayout.vue';
import { formatDecimal, formatKzt, formatPercent, formatRate, formatUsdt } from '@/utils/formatNumber';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    rates: Object,
    feePercent: Number,
    canTrade: Boolean,
    balance: Object,
    limits: Object,
    orders: Array,
});

const page = usePage();
const mode = ref('buy');
const buyInput = ref('kzt'); // kzt | usdt

const form = useForm({
    direction: 'buy',
    kzt_amount: '50000',
    usdt_amount: '',
    bank_name: '',
    recipient_name: '',
    recipient_account: '',
});

const statusLabels = {
    created: 'Создана',
    awaiting_kzt_payment: 'Ожидает оплату KZT',
    payment_proof_uploaded: 'Скрин загружен',
    pending_admin_confirmation: 'На подтверждении',
    kzt_received: 'KZT получены',
    crypto_sending: 'Отправка крипты',
    crypto_sent: 'Крипта отправлена',
    completed: 'Выполнена',
    cancelled: 'Отменена',
    failed: 'Ошибка',
    dispute: 'Спор',
    manual_review: 'Ручная проверка',
};

const statusColors = {
    completed: 'text-accent',
    cancelled: 'text-text-dim',
    failed: 'text-red-400',
};

const rate = computed(() => (mode.value === 'buy' ? props.rates.buy : props.rates.sell));

const preview = computed(() => {
    const fee = props.feePercent / 100;

    if (mode.value === 'buy') {
        if (buyInput.value === 'kzt') {
            const kzt = parseFloat(form.kzt_amount) || 0;
            const gross = kzt / props.rates.buy;
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
            give: formatKzt(gross * props.rates.buy) + ' ₸',
            receive: formatUsdt(net, 2) + ' USDT',
        };
    }

    const gross = parseFloat(form.usdt_amount) || 0;
    const feeAmount = gross * fee;
    const kzt = (gross - feeAmount) * props.rates.sell;

    return {
        fee: formatUsdt(feeAmount, 4) + ' USDT',
        give: formatUsdt(gross, 2) + ' USDT',
        receive: formatKzt(kzt) + ' ₸',
    };
});

function setMode(value) {
    mode.value = value;
    form.direction = value;
    form.clearErrors();
}

function submit() {
    if (mode.value === 'buy' && buyInput.value === 'kzt') {
        form.usdt_amount = '';
    }
    if (mode.value === 'buy' && buyInput.value === 'usdt') {
        form.kzt_amount = '';
    }

    form.post(route('exchange.orders.store'), { preserveScroll: true });
}

function formatDate(value) {
    return new Date(value).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head title="Обмен" />

    <ExchangeLayout>
        <template #title>Обмен валют</template>

        <div v-if="page.props.flash?.success" class="info-box mb-4">
            {{ page.props.flash.success }}
        </div>

        <div class="mb-stack-element rounded-xl bg-surface-container-low p-4 text-body-sm">
            <div class="flex justify-between py-1">
                <span class="text-text-dim">Покупка USDT</span>
                <span class="font-semibold">{{ formatRate(rates.buy) }} ₸</span>
            </div>
            <div class="flex justify-between py-1">
                <span class="text-text-dim">Продажа USDT</span>
                <span class="font-semibold">{{ formatRate(rates.sell) }} ₸</span>
            </div>
            <p class="mt-1 text-xs text-text-dim">
                <template v-if="rates.updated_at">
                    Курс обновлён: {{ formatDate(rates.updated_at) }}
                    <span v-if="rates.stale" class="text-amber-400"> (источник недоступен, показан последний курс)</span>
                </template>
                <template v-else>Резервный курс (источник недоступен)</template>
            </p>
        </div>

        <div v-if="!canTrade" class="warning-box">
            Для обмена нужно подтвердить телефон и пройти KYC.
            <Link :href="route('kyc')" class="mt-2 block font-semibold text-accent">Пройти KYC →</Link>
        </div>

        <template v-else>
            <div class="buy-sell-tabs mb-stack-element">
                <button
                    class="btn-segment"
                    :class="mode === 'buy' ? 'btn-segment--active' : 'btn-segment--inactive'"
                    @click="setMode('buy')"
                >
                    Купить USDT
                </button>
                <button
                    class="btn-segment"
                    :class="mode === 'sell' ? 'btn-segment--active' : 'btn-segment--inactive'"
                    @click="setMode('sell')"
                >
                    Продать USDT
                </button>
            </div>

            <section class="card space-y-stack-element">
                <template v-if="mode === 'buy'">
                    <div class="flex gap-2 text-xs">
                        <button
                            class="btn-chip"
                            :class="buyInput === 'kzt' ? 'btn-chip--active' : 'btn-chip--inactive'"
                            @click="buyInput = 'kzt'"
                        >
                            Ввожу сумму KZT
                        </button>
                        <button
                            class="btn-chip"
                            :class="buyInput === 'usdt' ? 'btn-chip--active' : 'btn-chip--inactive'"
                            @click="buyInput = 'usdt'"
                        >
                            Хочу получить USDT
                        </button>
                    </div>

                    <div v-if="buyInput === 'kzt'">
                        <label class="mb-2 block text-label-caps uppercase text-text-dim">Сумма KZT</label>
                        <input v-model="form.kzt_amount" type="number" class="input-field" min="0" step="0.01" />
                        <p class="mt-1 text-xs text-text-dim">
                            От {{ formatKzt(limits.min_buy_kzt) }} до {{ formatKzt(limits.max_buy_kzt) }} ₸
                        </p>
                    </div>
                    <div v-else>
                        <label class="mb-2 block text-label-caps uppercase text-text-dim">Сумма USDT к получению</label>
                        <input v-model="form.usdt_amount" type="number" class="input-field" min="0" step="0.01" />
                    </div>
                </template>

                <template v-else>
                    <div>
                        <label class="mb-2 block text-label-caps uppercase text-text-dim">Сумма USDT</label>
                        <input v-model="form.usdt_amount" type="number" class="input-field" min="0" step="0.01" />
                        <p class="mt-1 text-xs text-text-dim">
                            Доступно: {{ formatUsdt(balance.available, 2) }} USDT ·
                            от {{ formatUsdt(limits.min_sell_usdt, 2) }} до {{ formatUsdt(limits.max_sell_usdt, 2) }} USDT
                        </p>
                    </div>

                    <div>
                        <label class="mb-2 block text-label-caps uppercase text-text-dim">Банк для получения KZT</label>
                        <input v-model="form.bank_name" class="input-field" placeholder="Kaspi Bank" />
                    </div>
                    <div>
                        <label class="mb-2 block text-label-caps uppercase text-text-dim">Получатель (ФИО)</label>
                        <input v-model="form.recipient_name" class="input-field" placeholder="Иванов Иван" />
                    </div>
                    <div>
                        <label class="mb-2 block text-label-caps uppercase text-text-dim">Номер карты / счёта / телефона</label>
                        <input v-model="form.recipient_account" class="input-field" placeholder="KZ... / 4400... / +7..." />
                    </div>
                </template>

                <div class="rounded-xl bg-surface-container-low p-4 text-body-sm">
                    <div class="flex justify-between py-1">
                        <span class="text-text-dim">Курс</span>
                        <span>{{ formatRate(rate) }} ₸ / USDT</span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-text-dim">Комиссия ({{ formatPercent(feePercent) }}%)</span>
                        <span>{{ preview.fee }}</span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-text-dim">Вы отдаёте</span>
                        <span>{{ preview.give }}</span>
                    </div>
                    <div class="flex justify-between border-t border-outline-variant/40 py-2 font-semibold">
                        <span>К получению</span>
                        <span class="text-accent">{{ preview.receive }}</span>
                    </div>
                </div>

                <p v-if="form.errors.form" class="text-sm text-red-400">{{ form.errors.form }}</p>
                <p v-for="(error, key) in form.errors" v-show="key !== 'form'" :key="key" class="text-sm text-red-400">
                    {{ error }}
                </p>

                <button class="btn-primary" :disabled="form.processing" @click="submit">
                    Создать заявку
                </button>
                <p class="text-center text-body-sm text-text-dim">KZT операции подтверждаются вручную</p>
            </section>

            <section v-if="orders.length" class="mt-stack-element">
                <h2 class="mb-3 text-label-caps uppercase text-text-dim">Мои заявки</h2>
                <div class="space-y-3">
                    <Link
                        v-for="order in orders"
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
                                <p class="text-xs font-semibold" :class="statusColors[order.status] ?? 'text-amber-400'">
                                    {{ statusLabels[order.status] ?? order.status }}
                                </p>
                                <p class="mt-1 text-xs text-text-dim">{{ formatDate(order.created_at) }}</p>
                            </div>
                        </div>
                    </Link>
                </div>
            </section>
        </template>
    </ExchangeLayout>
</template>
