<script setup>
import ExchangeLayout from '@/Layouts/ExchangeLayout.vue';
import { formatPercent, formatUsdt } from '@/utils/formatNumber';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    balance: Object,
    feePercent: Number,
    networkFee: String,
    minAmount: Number,
    autoLimit: Number,
    withdrawalsEnabled: Boolean,
    networks: { type: Array, default: () => [] },
    withdrawals: Array,
});

const page = usePage();

const form = useForm({
    network: props.networks[0]?.code ?? 'BEP20',
    to_address: '',
    amount: '',
});

const currentNetwork = computed(
    () => props.networks.find((n) => n.code === form.network) || props.networks[0] || {},
);

const isTron = computed(() => currentNetwork.value.address_format === 'tron');

const addressPlaceholder = computed(() => (isTron.value ? 'T...' : '0x...'));

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
    const fee = (amount * props.feePercent) / 100;
    const network = parseFloat(props.networkFee);

    return {
        fee: formatUsdt(fee, 4),
        network: formatUsdt(network, 4),
        total: formatUsdt(amount + fee + network, 4),
    };
});

function submit() {
    form.post(route('withdraw.store'), { preserveScroll: true, onSuccess: () => form.reset() });
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
    <Head title="Вывод USDT" />

    <ExchangeLayout>
        <template #title>Вывод USDT</template>

        <div v-if="page.props.flash?.success" class="card mb-4 border border-accent/30 text-accent">
            {{ page.props.flash.success }}
        </div>

        <section class="card card--highlight mb-stack-element">
            <p class="text-label-caps uppercase text-white/70">Доступно</p>
            <p class="mt-1 text-headline-md font-bold">{{ formatUsdt(balance.available, 2) }} USDT</p>
            <p v-if="parseFloat(balance.locked) > 0" class="mt-1 text-body-sm text-white/80">
                Заблокировано в заявках: {{ formatUsdt(balance.locked, 2) }} USDT
            </p>
        </section>

        <div v-if="!withdrawalsEnabled" class="warning-box mb-stack-element">
            Автоматическая отправка временно отключена: заявки принимаются и будут отправлены после включения.
        </div>

        <section class="card space-y-stack-element">
            <div v-if="networks.length > 1">
                <label class="mb-2 block text-label-caps uppercase text-text-dim">Сеть вывода</label>
                <div class="grid grid-cols-2 gap-2">
                    <button
                        v-for="net in networks"
                        :key="net.code"
                        type="button"
                        class="network-chip"
                        :class="net.code === form.network ? 'network-chip--active' : 'network-chip--inactive'"
                        @click="form.network = net.code"
                    >
                        {{ net.code }}
                    </button>
                </div>
                <p v-if="form.errors.network" class="mt-1 text-sm text-red-400">{{ form.errors.network }}</p>
            </div>

            <div>
                <label class="mb-2 block text-label-caps uppercase text-text-dim">Адрес получателя ({{ currentNetwork.code }})</label>
                <input v-model="form.to_address" class="input-field font-mono text-sm" :placeholder="addressPlaceholder" />
                <p v-if="form.errors.to_address" class="mt-1 text-sm text-red-400">{{ form.errors.to_address }}</p>
            </div>

            <div>
                <label class="mb-2 block text-label-caps uppercase text-text-dim">Сумма USDT</label>
                <input v-model="form.amount" type="number" class="input-field" min="0" step="0.01" />
                <p class="mt-1 text-xs text-text-dim">Минимум {{ formatUsdt(minAmount, 2) }} USDT. Все выводы проходят ручную проверку СБ.</p>
                <p v-if="form.errors.amount" class="mt-1 text-sm text-red-400">{{ form.errors.amount }}</p>
            </div>

            <div class="rounded-xl bg-surface-container-low p-4 text-body-sm">
                <div class="flex justify-between py-1 font-semibold">
                    <span>Получатель получит</span>
                    <span>{{ formatUsdt(parseFloat(form.amount) || 0, 4) }} USDT</span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="text-text-dim">Комиссия сервиса ({{ formatPercent(feePercent) }}%)</span>
                    <span>{{ preview.fee }} USDT</span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="text-text-dim">Комиссия сети</span>
                    <span>{{ preview.network }} USDT</span>
                </div>
                <div class="flex justify-between border-t border-outline-variant/40 py-2 font-semibold">
                    <span>Итого к списанию</span>
                    <span class="text-accent">{{ preview.total }} USDT</span>
                </div>
            </div>

            <p v-if="form.errors.form" class="text-sm text-red-400">{{ form.errors.form }}</p>

            <button class="btn-primary" :disabled="form.processing" @click="submit">Создать заявку</button>
            <p class="text-center text-body-sm text-text-dim">
                После создания заявка будет проверена службой безопасности.
            </p>
        </section>

        <section v-if="withdrawals.length" class="mt-stack-element">
            <h2 class="mb-3 text-label-caps uppercase text-text-dim">Мои выводы</h2>
            <div class="space-y-3">
                <div v-for="withdrawal in withdrawals" :key="withdrawal.id" class="card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-semibold">№{{ withdrawal.id }} · {{ formatUsdt(withdrawal.amount, 2) }} USDT</p>
                            <p class="mt-1 text-xs text-text-dim">{{ withdrawal.network }}</p>
                            <p class="mt-1 font-mono text-xs text-text-muted">{{ short(withdrawal.to_address) }}</p>
                            <a
                                v-if="withdrawal.tx_hash && withdrawal.explorer_tx"
                                :href="`${withdrawal.explorer_tx}${withdrawal.tx_hash}`"
                                target="_blank"
                                class="mt-1 block text-xs text-accent"
                            >
                                tx: {{ short(withdrawal.tx_hash) }}
                            </a>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-semibold"
                               :class="withdrawal.status === 'completed' ? 'text-accent'
                                   : ['cancelled', 'rejected', 'failed'].includes(withdrawal.status) ? 'text-red-400' : 'text-amber-400'">
                                {{ statusLabels[withdrawal.status] ?? withdrawal.status }}
                            </p>
                            <p class="mt-1 text-xs text-text-dim">{{ formatDate(withdrawal.created_at) }}</p>
                            <button
                                v-if="['awaiting_telegram_confirmation', 'pending_review'].includes(withdrawal.status)"
                                class="mt-2 rounded-lg bg-surface-container-high px-3 py-1 text-xs font-semibold text-red-400"
                                @click="cancelWithdrawal(withdrawal.id)"
                            >
                                Отменить
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <Link :href="route('wallet')" class="mt-4 block text-center text-body-sm text-text-dim">← В кошелёк</Link>
    </ExchangeLayout>
</template>
