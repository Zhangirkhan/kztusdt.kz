<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatPercent } from '@/utils/formatNumber';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    subscriptions: Object,
    search: String,
    foundUsers: Array,
    plans: Array,
    subscriptionPlans: Array,
});

const page = usePage();
const searchInput = ref(props.search ?? '');
const editingPlanId = ref(null);

const grantForm = useForm({
    user_id: null,
    subscription_plan_id: props.subscriptionPlans[0]?.id ?? null,
    months: 1,
    comment: '',
});

const newPlanForm = useForm({
    code: '',
    name: '',
    fee_percent: 0.1,
    timing: '',
    description: '',
    is_subscription: true,
    is_active: true,
    sort_order: (props.plans?.length ?? 0) + 1,
});

const editForms = ref({});

const defaultPlan = computed(() => props.plans.find((plan) => plan.is_default));
const subscriptionPlanOptions = computed(() => props.subscriptionPlans ?? []);

function planEditForm(plan) {
    if (!editForms.value[plan.id]) {
        editForms.value[plan.id] = useForm({
            name: plan.name,
            fee_percent: plan.fee_percent,
            timing: plan.timing ?? '',
            description: plan.description ?? '',
            is_default: plan.is_default,
            is_subscription: plan.is_subscription,
            is_active: plan.is_active,
            sort_order: plan.sort_order,
        });
    }

    return editForms.value[plan.id];
}

function startEdit(plan) {
    editingPlanId.value = plan.id;
    const form = planEditForm(plan);
    form.name = plan.name;
    form.fee_percent = plan.fee_percent;
    form.timing = plan.timing ?? '';
    form.description = plan.description ?? '';
    form.is_default = plan.is_default;
    form.is_subscription = plan.is_subscription;
    form.is_active = plan.is_active;
    form.sort_order = plan.sort_order;
}

function cancelEdit() {
    editingPlanId.value = null;
}

function savePlan(plan) {
    planEditForm(plan).patch(route('admin.subscriptions.plans.update', plan.id), {
        preserveScroll: true,
        onSuccess: () => {
            editingPlanId.value = null;
        },
    });
}

function createPlan() {
    newPlanForm.post(route('admin.subscriptions.plans.store'), {
        preserveScroll: true,
        onSuccess: () => newPlanForm.reset(),
    });
}

function doSearch() {
    router.get('/admin/subscriptions', { search: searchInput.value }, { preserveState: true });
}

function selectUser(user) {
    grantForm.user_id = user.id;
}

function grant() {
    grantForm.post(route('admin.subscriptions.store'), {
        preserveScroll: true,
        onSuccess: () => grantForm.reset('comment'),
    });
}

function cancelSubscription(id) {
    if (confirm('Отменить подписку?')) {
        router.post(route('admin.subscriptions.cancel', id), {}, { preserveScroll: true });
    }
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('ru-RU') : '—';
}

function planTypeLabel(plan) {
    if (plan.is_default) {
        return 'Базовый';
    }

    if (plan.is_subscription) {
        return 'Подписка';
    }

    return 'Тариф';
}
</script>

<template>
    <Head title="Подписки" />

    <AdminLayout>
        <template #title>Тарифы, комиссии и подписки</template>

        <div v-if="page.props.flash?.success" class="card mb-4 border border-accent/30 text-accent">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props.errors?.plan" class="card mb-4 border border-red-500/30 text-red-400">
            {{ page.props.errors.plan }}
        </div>

        <section class="card mb-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-label-caps uppercase text-text-dim">Тарифы и комиссии</h2>
                    <p class="mt-1 text-sm text-text-muted">
                        Базовый тариф для всех клиентов: {{ defaultPlan?.name ?? '—' }}
                        ({{ defaultPlan ? formatPercent(defaultPlan.fee_percent) : '—' }}%).
                    </p>
                </div>
            </div>

            <div class="space-y-3">
                <article
                    v-for="plan in plans"
                    :key="plan.id"
                    class="rounded-xl border p-4"
                    :class="plan.is_active ? 'border-outline-variant/40 bg-surface-container-low' : 'border-red-500/20 bg-red-500/5 opacity-80'"
                >
                    <div v-if="editingPlanId !== plan.id" class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-on-surface">{{ plan.name }}</h3>
                                <span class="rounded-lg bg-surface-container-high px-2 py-0.5 text-xs font-semibold text-text-dim">
                                    {{ planTypeLabel(plan) }}
                                </span>
                                <span class="rounded-lg bg-accent/15 px-2 py-0.5 text-xs font-semibold text-accent">
                                    {{ formatPercent(plan.fee_percent) }}%
                                </span>
                                <span v-if="!plan.is_active" class="text-xs font-semibold text-red-400">отключён</span>
                            </div>
                            <p class="mt-1 text-xs text-text-dim">код: {{ plan.code }} · порядок: {{ plan.sort_order }}</p>
                            <p v-if="plan.timing" class="mt-2 text-sm text-text-muted">{{ plan.timing }}</p>
                            <p v-if="plan.description" class="mt-1 text-sm text-text-dim">{{ plan.description }}</p>
                        </div>
                        <button
                            class="rounded-lg bg-surface-container-high px-3 py-1 text-xs font-semibold text-accent"
                            @click="startEdit(plan)"
                        >
                            Изменить
                        </button>
                    </div>

                    <form v-else class="space-y-3" @submit.prevent="savePlan(plan)">
                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs uppercase text-text-dim">Название</label>
                                <input v-model="planEditForm(plan).name" class="input-field" required />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs uppercase text-text-dim">Комиссия, %</label>
                                <input v-model.number="planEditForm(plan).fee_percent" type="number" min="0" max="100" step="0.01" class="input-field" required />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs uppercase text-text-dim">Срок обработки</label>
                                <input v-model="planEditForm(plan).timing" class="input-field" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs uppercase text-text-dim">Порядок</label>
                                <input v-model.number="planEditForm(plan).sort_order" type="number" min="0" class="input-field" />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs uppercase text-text-dim">Описание</label>
                            <textarea v-model="planEditForm(plan).description" rows="2" class="input-field" />
                        </div>
                        <div class="flex flex-wrap gap-4 text-sm">
                            <label v-if="!plan.is_default" class="flex items-center gap-2">
                                <input v-model="planEditForm(plan).is_default" type="checkbox" class="rounded" />
                                Базовый тариф
                            </label>
                            <label v-if="!plan.is_default" class="flex items-center gap-2">
                                <input v-model="planEditForm(plan).is_subscription" type="checkbox" class="rounded" />
                                Тариф подписки
                            </label>
                            <label class="flex items-center gap-2">
                                <input v-model="planEditForm(plan).is_active" type="checkbox" class="rounded" :disabled="plan.is_default" />
                                Активен
                            </label>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="btn-primary w-auto px-4" :disabled="planEditForm(plan).processing">Сохранить</button>
                            <button type="button" class="btn-secondary w-auto px-4" @click="cancelEdit">Отмена</button>
                        </div>
                    </form>
                </article>
            </div>
        </section>

        <section class="card mb-6">
            <h2 class="mb-3 text-label-caps uppercase text-text-dim">Новый тариф</h2>
            <form class="grid gap-3 md:grid-cols-2" @submit.prevent="createPlan">
                <div>
                    <label class="mb-1 block text-xs uppercase text-text-dim">Код</label>
                    <input v-model="newPlanForm.code" class="input-field" placeholder="premium" required />
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase text-text-dim">Название</label>
                    <input v-model="newPlanForm.name" class="input-field" placeholder="Премиум" required />
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase text-text-dim">Комиссия, %</label>
                    <input v-model.number="newPlanForm.fee_percent" type="number" min="0" max="100" step="0.01" class="input-field" required />
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase text-text-dim">Порядок</label>
                    <input v-model.number="newPlanForm.sort_order" type="number" min="0" class="input-field" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs uppercase text-text-dim">Срок обработки</label>
                    <input v-model="newPlanForm.timing" class="input-field" placeholder="До 12 часов" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs uppercase text-text-dim">Описание</label>
                    <textarea v-model="newPlanForm.description" rows="2" class="input-field" />
                </div>
                <div class="flex flex-wrap gap-4 text-sm md:col-span-2">
                    <label class="flex items-center gap-2">
                        <input v-model="newPlanForm.is_subscription" type="checkbox" class="rounded" />
                        Тариф подписки
                    </label>
                    <label class="flex items-center gap-2">
                        <input v-model="newPlanForm.is_active" type="checkbox" class="rounded" />
                        Активен
                    </label>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="btn-primary w-auto px-6" :disabled="newPlanForm.processing">Создать тариф</button>
                </div>
                <p v-if="newPlanForm.errors.code" class="text-sm text-red-400 md:col-span-2">{{ newPlanForm.errors.code }}</p>
            </form>
        </section>

        <section class="card mb-6">
            <h2 class="mb-3 text-label-caps uppercase text-text-dim">Выдать / продлить подписку</h2>

            <div class="flex gap-2">
                <input
                    v-model="searchInput"
                    class="input-field"
                    placeholder="Поиск клиента: телефон, имя или email"
                    @keyup.enter="doSearch"
                />
                <button class="rounded-xl bg-surface-container-high px-4 text-sm font-semibold text-accent" @click="doSearch">
                    Найти
                </button>
            </div>

            <div v-if="foundUsers.length" class="mt-3 space-y-2">
                <button
                    v-for="user in foundUsers"
                    :key="user.id"
                    class="block w-full rounded-xl px-4 py-2 text-left text-sm transition"
                    :class="grantForm.user_id === user.id ? 'bg-accent/20 text-accent' : 'bg-surface-container-low text-text-dim'"
                    @click="selectUser(user)"
                >
                    #{{ user.id }} · {{ user.name }} · {{ user.phone ?? user.email }}
                </button>
            </div>

            <div v-if="grantForm.user_id" class="mt-4 flex flex-wrap items-end gap-3">
                <div class="min-w-[12rem] flex-1">
                    <label class="mb-1 block text-xs uppercase text-text-dim">Тариф подписки</label>
                    <select v-model="grantForm.subscription_plan_id" class="input-field">
                        <option v-for="plan in subscriptionPlanOptions" :key="plan.id" :value="plan.id">
                            {{ plan.name }} ({{ formatPercent(plan.fee_percent) }}%)
                        </option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase text-text-dim">Месяцев</label>
                    <input v-model.number="grantForm.months" type="number" min="1" max="36" class="input-field w-24" />
                </div>
                <div class="min-w-[12rem] flex-1">
                    <label class="mb-1 block text-xs uppercase text-text-dim">Комментарий</label>
                    <input v-model="grantForm.comment" class="input-field" placeholder="Например: оплачено вручную" />
                </div>
                <button class="btn-primary w-auto px-6" :disabled="grantForm.processing" @click="grant">Выдать</button>
            </div>
            <p v-if="grantForm.errors.user_id" class="mt-2 text-sm text-red-400">{{ grantForm.errors.user_id }}</p>
            <p v-if="grantForm.errors.subscription_plan_id" class="mt-2 text-sm text-red-400">{{ grantForm.errors.subscription_plan_id }}</p>
            <p v-if="grantForm.errors.months" class="mt-2 text-sm text-red-400">{{ grantForm.errors.months }}</p>
        </section>

        <section>
            <h2 class="mb-3 text-label-caps uppercase text-text-dim">Подписки пользователей</h2>
            <div class="space-y-3">
                <div v-for="subscription in subscriptions.data" :key="subscription.id" class="card">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold">
                                {{ subscription.user?.name ?? '—' }} · {{ subscription.user?.phone ?? subscription.user?.email ?? '—' }}
                            </p>
                            <p class="mt-1 text-body-sm text-text-muted">
                                {{ subscription.plan?.name ?? '—' }}
                                ({{ subscription.plan ? formatPercent(subscription.plan.fee_percent) : '—' }}%)
                                · с {{ formatDate(subscription.starts_at) }} по {{ formatDate(subscription.expires_at) }}
                                · выдал: {{ subscription.granted_by?.name ?? '—' }}
                            </p>
                            <p v-if="subscription.comment" class="mt-1 text-xs text-text-dim">{{ subscription.comment }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                class="text-xs font-semibold uppercase"
                                :class="subscription.status === 'active' ? 'text-accent' : 'text-text-dim'"
                            >
                                {{ subscription.status }}
                            </span>
                            <button
                                v-if="subscription.status === 'active'"
                                class="rounded-lg bg-surface-container-high px-3 py-1 text-xs font-semibold text-red-400"
                                @click="cancelSubscription(subscription.id)"
                            >
                                Отменить
                            </button>
                        </div>
                    </div>
                </div>

                <p v-if="subscriptions.data.length === 0" class="text-center text-text-dim">Подписок пока нет</p>
            </div>
        </section>
    </AdminLayout>
</template>
