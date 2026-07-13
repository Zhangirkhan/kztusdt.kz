<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import AdminPagination from '@/shared/ui/admin/AdminPagination.vue';
import { formatPercent } from '@/utils/formatNumber';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps({
    subscriptions: Object,
    search: String,
    foundUsers: Array,
    plans: Array,
    subscriptionPlans: Array,
});

const page = usePage();
const { t } = useI18n();
const searchInput = ref(props.search ?? '');
const showEditModal = ref(false);
const showCreateModal = ref(false);
const showGrantModal = ref(false);
const showCancelModal = ref(false);
const editingPlan = ref(null);
const cancelSubId = ref(null);

const grantForm = useForm({
    user_id: null,
    subscription_plan_id: props.subscriptionPlans[0]?.id ?? null,
    months: 1,
    comment: '',
});

const editForm = useForm({
    name: '',
    fee_percent: 0,
    timing: '',
    description: '',
    is_default: false,
    is_subscription: false,
    is_active: true,
    sort_order: 0,
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

const defaultPlan = computed(() => props.plans.find((plan) => plan.is_default));
const subscriptionPlanOptions = computed(() => props.subscriptionPlans ?? []);

const selectedGrantUser = computed(() => props.foundUsers?.find((u) => u.id === grantForm.user_id));

const subscriptionColumns = computed(() => [
    { title: t('admin.subscriptions.userSubscriptions.columns.client'), key: 'user', dataIndex: 'user' },
    { title: t('admin.subscriptions.userSubscriptions.columns.plan'), key: 'plan', dataIndex: 'plan' },
    { title: t('admin.subscriptions.userSubscriptions.columns.period'), key: 'period' },
    { title: t('admin.subscriptions.userSubscriptions.columns.status'), key: 'status', width: 110 },
    { title: '', key: 'actions', width: 120, align: 'right' },
]);

function openEdit(plan) {
    editingPlan.value = plan;
    editForm.name = plan.name;
    editForm.fee_percent = plan.fee_percent;
    editForm.timing = plan.timing ?? '';
    editForm.description = plan.description ?? '';
    editForm.is_default = plan.is_default;
    editForm.is_subscription = plan.is_subscription;
    editForm.is_active = plan.is_active;
    editForm.sort_order = plan.sort_order;
    editForm.clearErrors();
    showEditModal.value = true;
}

function closeEdit() {
    showEditModal.value = false;
    editingPlan.value = null;
}

function savePlan() {
    if (!editingPlan.value) {
        return;
    }

    editForm.patch(route('admin.subscriptions.plans.update', editingPlan.value.id), {
        preserveScroll: true,
        onSuccess: () => closeEdit(),
    });
}

function openCreate() {
    newPlanForm.reset();
    newPlanForm.sort_order = (props.plans?.length ?? 0) + 1;
    newPlanForm.is_subscription = true;
    newPlanForm.is_active = true;
    newPlanForm.fee_percent = 0.1;
    showCreateModal.value = true;
}

function createPlan() {
    newPlanForm.post(route('admin.subscriptions.plans.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            newPlanForm.reset();
        },
    });
}

function openGrant() {
    grantForm.reset();
    grantForm.subscription_plan_id = props.subscriptionPlans[0]?.id ?? null;
    grantForm.months = 1;
    searchInput.value = props.search ?? '';
    showGrantModal.value = true;
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
        onSuccess: () => {
            showGrantModal.value = false;
            grantForm.reset();
        },
    });
}

function confirmCancelSubscription() {
    if (!cancelSubId.value) {
        return;
    }

    router.post(route('admin.subscriptions.cancel', cancelSubId.value), {}, {
        preserveScroll: true,
        onSuccess: () => {
            cancelSubId.value = null;
            showCancelModal.value = false;
        },
    });
}

function openCancelSubscription(id) {
    cancelSubId.value = id;
    showCancelModal.value = true;
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('ru-RU') : t('admin.shared.empty');
}

function planTypeLabel(plan) {
    if (plan.is_default) {
        return t('admin.subscriptions.plans.types.base');
    }

    if (plan.is_subscription) {
        return t('admin.subscriptions.plans.types.subscription');
    }

    return t('admin.subscriptions.plans.types.tariff');
}

function planTypeColor(plan) {
    if (plan.is_default) {
        return 'default';
    }

    if (plan.is_subscription) {
        return 'blue';
    }

    return 'purple';
}

function statusColor(status) {
    if (status === 'active') {
        return 'success';
    }

    return 'default';
}
</script>

<template>
    <Head :title="t('admin.subscriptions.title')" />

    <AdminLayout>
        <template #title>{{ t('admin.subscriptions.title') }}</template>

        <AdminPage>
            <a-alert
                v-if="page.props.errors?.plan"
                type="error"
                show-icon
                :message="page.props.errors.plan"
                class="admin-ant-block"
            />

            <a-card :title="t('admin.subscriptions.plans.cardTitle')" class="admin-ant-card">
                <template #extra>
                    <a-space>
                        <a-typography-text type="secondary">
                            {{ t('admin.subscriptions.plans.defaultPlan', { name: defaultPlan?.name ?? t('admin.shared.empty'), fee: defaultPlan ? formatPercent(defaultPlan.fee_percent) : t('admin.shared.empty') }) }}
                        </a-typography-text>
                        <a-button type="primary" @click="openCreate">{{ t('admin.subscriptions.plans.newPlan') }}</a-button>
                    </a-space>
                </template>

                <a-space direction="vertical" :size="12" style="width: 100%">
                    <a-card v-for="plan in plans" :key="plan.id" size="small">
                        <div class="admin-ant-plan-row">
                            <div>
                                <a-space wrap :size="8">
                                    <a-typography-text strong>{{ plan.name }}</a-typography-text>
                                    <a-tag :color="planTypeColor(plan)">{{ planTypeLabel(plan) }}</a-tag>
                                    <a-tag color="processing">{{ formatPercent(plan.fee_percent) }}%</a-tag>
                                    <a-tag v-if="!plan.is_active" color="error">{{ t('admin.subscriptions.plans.disabled') }}</a-tag>
                                </a-space>
                                <div class="admin-ant-meta">
                                    {{ t('admin.subscriptions.plans.meta', { code: plan.code, sortOrder: plan.sort_order }) }}
                                </div>
                                <div v-if="plan.timing" class="admin-ant-desc">{{ plan.timing }}</div>
                                <div v-if="plan.description" class="admin-ant-desc admin-ant-desc--muted">
                                    {{ plan.description }}
                                </div>
                            </div>
                            <a-button type="primary" ghost size="small" @click="openEdit(plan)">
                                {{ t('admin.subscriptions.plans.edit') }}
                            </a-button>
                        </div>
                    </a-card>
                </a-space>
            </a-card>

            <a-card :title="t('admin.subscriptions.userSubscriptions.cardTitle')" class="admin-ant-card">
                <template #extra>
                    <a-button type="primary" @click="openGrant">{{ t('admin.subscriptions.userSubscriptions.grant') }}</a-button>
                </template>

                <a-table
                    :columns="subscriptionColumns"
                    :data-source="subscriptions.data"
                    :pagination="false"
                    row-key="id"
                    size="middle"
                >
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'user'">
                            <div>
                                <a-typography-text strong>
                                    {{ record.user?.name ?? t('admin.shared.empty') }}
                                </a-typography-text>
                                <div class="admin-ant-meta">
                                    {{ record.user?.phone ?? record.user?.email ?? t('admin.shared.empty') }}
                                </div>
                            </div>
                        </template>

                        <template v-else-if="column.key === 'plan'">
                            <div>
                                {{ record.plan?.name ?? t('admin.shared.empty') }}
                                <a-tag color="processing" style="margin-left: 4px">
                                    {{ record.plan ? formatPercent(record.plan.fee_percent) : t('admin.shared.empty') }}%
                                </a-tag>
                            </div>
                            <div v-if="record.comment" class="admin-ant-meta">{{ record.comment }}</div>
                        </template>

                        <template v-else-if="column.key === 'period'">
                            <div>{{ formatDate(record.starts_at) }} — {{ formatDate(record.expires_at) }}</div>
                            <div class="admin-ant-meta">{{ t('admin.subscriptions.userSubscriptions.grantedBy', { name: record.granted_by?.name ?? t('admin.shared.empty') }) }}</div>
                        </template>

                        <template v-else-if="column.key === 'status'">
                            <a-tag :color="statusColor(record.status)">{{ record.status }}</a-tag>
                        </template>

                        <template v-else-if="column.key === 'actions'">
                            <a-button
                                v-if="record.status === 'active'"
                                danger
                                size="small"
                                @click="openCancelSubscription(record.id)"
                            >
                                {{ t('admin.subscriptions.userSubscriptions.cancel') }}
                            </a-button>
                        </template>
                    </template>

                    <template #emptyText>
                        <a-empty :description="t('admin.subscriptions.userSubscriptions.empty')" />
                    </template>
                </a-table>

                <AdminPagination :pagination="subscriptions" />
            </a-card>

            <!-- Edit plan -->
            <a-modal
                v-model:open="showEditModal"
                :title="editingPlan ? t('admin.subscriptions.modals.edit.title', { name: editingPlan.name }) : t('admin.subscriptions.modals.edit.titleFallback')"
                :ok-text="t('admin.shared.actions.save')"
                :cancel-text="t('admin.shared.actions.cancel')"
                :confirm-loading="editForm.processing"
                width="640px"
                destroy-on-close
                @ok="savePlan"
            >
                <a-form layout="vertical">
                    <a-row :gutter="16">
                        <a-col :xs="24" :md="12">
                            <a-form-item :label="t('admin.subscriptions.modals.edit.name')" required>
                                <a-input v-model:value="editForm.name" />
                            </a-form-item>
                        </a-col>
                        <a-col :xs="24" :md="12">
                            <a-form-item :label="t('admin.subscriptions.modals.edit.fee')" required>
                                <a-input-number
                                    v-model:value="editForm.fee_percent"
                                    :min="0"
                                    :max="100"
                                    :step="0.01"
                                    style="width: 100%"
                                />
                            </a-form-item>
                        </a-col>
                        <a-col :xs="24" :md="12">
                            <a-form-item :label="t('admin.subscriptions.modals.edit.timing')">
                                <a-input v-model:value="editForm.timing" />
                            </a-form-item>
                        </a-col>
                        <a-col :xs="24" :md="12">
                            <a-form-item :label="t('admin.subscriptions.modals.edit.sortOrder')">
                                <a-input-number v-model:value="editForm.sort_order" :min="0" style="width: 100%" />
                            </a-form-item>
                        </a-col>
                        <a-col :span="24">
                            <a-form-item :label="t('admin.subscriptions.modals.edit.description')">
                                <a-textarea v-model:value="editForm.description" :rows="2" />
                            </a-form-item>
                        </a-col>
                        <a-col :span="24">
                            <a-space wrap>
                                <a-checkbox v-if="editingPlan && !editingPlan.is_default" v-model:checked="editForm.is_default">
                                    {{ t('admin.subscriptions.modals.edit.isDefault') }}
                                </a-checkbox>
                                <a-checkbox v-if="editingPlan && !editingPlan.is_default" v-model:checked="editForm.is_subscription">
                                    {{ t('admin.subscriptions.modals.edit.isSubscription') }}
                                </a-checkbox>
                                <a-checkbox v-model:checked="editForm.is_active" :disabled="editingPlan?.is_default">
                                    {{ t('admin.subscriptions.modals.edit.isActive') }}
                                </a-checkbox>
                            </a-space>
                        </a-col>
                    </a-row>
                </a-form>
            </a-modal>

            <!-- New plan -->
            <a-modal
                v-model:open="showCreateModal"
                :title="t('admin.subscriptions.modals.create.title')"
                :ok-text="t('admin.shared.actions.create')"
                :cancel-text="t('admin.shared.actions.cancel')"
                :confirm-loading="newPlanForm.processing"
                width="640px"
                destroy-on-close
                @ok="createPlan"
            >
                <a-form layout="vertical">
                    <a-row :gutter="16">
                        <a-col :xs="24" :md="12">
                            <a-form-item :label="t('admin.subscriptions.modals.create.code')" required :validate-status="newPlanForm.errors.code ? 'error' : ''" :help="newPlanForm.errors.code">
                                <a-input v-model:value="newPlanForm.code" placeholder="premium" />
                            </a-form-item>
                        </a-col>
                        <a-col :xs="24" :md="12">
                            <a-form-item :label="t('admin.subscriptions.modals.create.name')" required>
                                <a-input v-model:value="newPlanForm.name" :placeholder="t('admin.subscriptions.modals.create.namePlaceholder')" />
                            </a-form-item>
                        </a-col>
                        <a-col :xs="24" :md="12">
                            <a-form-item :label="t('admin.subscriptions.modals.create.fee')" required>
                                <a-input-number
                                    v-model:value="newPlanForm.fee_percent"
                                    :min="0"
                                    :max="100"
                                    :step="0.01"
                                    style="width: 100%"
                                />
                            </a-form-item>
                        </a-col>
                        <a-col :xs="24" :md="12">
                            <a-form-item :label="t('admin.subscriptions.modals.create.sortOrder')">
                                <a-input-number v-model:value="newPlanForm.sort_order" :min="0" style="width: 100%" />
                            </a-form-item>
                        </a-col>
                        <a-col :span="24">
                            <a-form-item :label="t('admin.subscriptions.modals.create.timing')">
                                <a-input v-model:value="newPlanForm.timing" :placeholder="t('admin.subscriptions.modals.create.timingPlaceholder')" />
                            </a-form-item>
                        </a-col>
                        <a-col :span="24">
                            <a-form-item :label="t('admin.subscriptions.modals.create.description')">
                                <a-textarea v-model:value="newPlanForm.description" :rows="2" />
                            </a-form-item>
                        </a-col>
                        <a-col :span="24">
                            <a-space>
                                <a-checkbox v-model:checked="newPlanForm.is_subscription">{{ t('admin.subscriptions.modals.create.isSubscription') }}</a-checkbox>
                                <a-checkbox v-model:checked="newPlanForm.is_active">{{ t('admin.subscriptions.modals.create.isActive') }}</a-checkbox>
                            </a-space>
                        </a-col>
                    </a-row>
                </a-form>
            </a-modal>

            <!-- Grant subscription -->
            <a-modal
                v-model:open="showGrantModal"
                :title="t('admin.subscriptions.modals.grant.title')"
                :ok-text="t('admin.shared.actions.confirm')"
                :cancel-text="t('admin.shared.actions.cancel')"
                :confirm-loading="grantForm.processing"
                :ok-button-props="{ disabled: !grantForm.user_id }"
                width="640px"
                destroy-on-close
                @ok="grant"
            >
                <a-space direction="vertical" :size="16" style="width: 100%">
                    <a-input-search
                        v-model:value="searchInput"
                        :placeholder="t('admin.subscriptions.modals.grant.searchPlaceholder')"
                        :enter-button="t('admin.shared.actions.find')"
                        @search="doSearch"
                    />

                    <a-space v-if="foundUsers.length" direction="vertical" :size="8" style="width: 100%">
                        <a-button
                            v-for="user in foundUsers"
                            :key="user.id"
                            block
                            :type="grantForm.user_id === user.id ? 'primary' : 'default'"
                            class="admin-ant-user-pick"
                            @click="selectUser(user)"
                        >
                            #{{ user.id }} · {{ user.name }} · {{ user.phone ?? user.email }}
                        </a-button>
                    </a-space>

                    <a-alert v-else type="info" :message="t('admin.subscriptions.modals.grant.searchHint')" show-icon />

                    <template v-if="grantForm.user_id">
                        <a-alert
                            type="success"
                            :message="t('admin.subscriptions.modals.grant.selected', { name: selectedGrantUser?.name ?? t('admin.shared.empty'), contact: selectedGrantUser?.phone ?? selectedGrantUser?.email ?? t('admin.shared.empty') })"
                            show-icon
                        />

                        <a-form layout="vertical">
                            <a-row :gutter="16">
                                <a-col :xs="24" :md="12">
                                    <a-form-item
                                        :label="t('admin.subscriptions.modals.grant.subscriptionPlan')"
                                        :validate-status="grantForm.errors.subscription_plan_id ? 'error' : ''"
                                        :help="grantForm.errors.subscription_plan_id"
                                    >
                                        <a-select v-model:value="grantForm.subscription_plan_id">
                                            <a-select-option
                                                v-for="plan in subscriptionPlanOptions"
                                                :key="plan.id"
                                                :value="plan.id"
                                            >
                                                {{ plan.name }} ({{ formatPercent(plan.fee_percent) }}%)
                                            </a-select-option>
                                        </a-select>
                                    </a-form-item>
                                </a-col>
                                <a-col :xs="24" :md="12">
                                    <a-form-item
                                        :label="t('admin.subscriptions.modals.grant.months')"
                                        :validate-status="grantForm.errors.months ? 'error' : ''"
                                        :help="grantForm.errors.months"
                                    >
                                        <a-input-number v-model:value="grantForm.months" :min="1" :max="36" style="width: 100%" />
                                    </a-form-item>
                                </a-col>
                                <a-col :span="24">
                                    <a-form-item :label="t('admin.subscriptions.modals.grant.comment')">
                                        <a-input v-model:value="grantForm.comment" :placeholder="t('admin.subscriptions.modals.grant.commentPlaceholder')" />
                                    </a-form-item>
                                </a-col>
                            </a-row>
                            <a-typography-text v-if="grantForm.errors.user_id" type="danger">
                                {{ grantForm.errors.user_id }}
                            </a-typography-text>
                        </a-form>
                    </template>
                </a-space>
            </a-modal>

            <!-- Cancel subscription -->
            <a-modal
                v-model:open="showCancelModal"
                :title="t('admin.subscriptions.modals.cancel.title')"
                :ok-text="t('admin.shared.actions.confirm')"
                :cancel-text="t('admin.shared.actions.cancel')"
                ok-type="danger"
                @ok="confirmCancelSubscription"
                @cancel="cancelSubId = null"
            >
                <a-typography-text>{{ t('admin.subscriptions.modals.cancel.confirm') }}</a-typography-text>
            </a-modal>
        </AdminPage>
    </AdminLayout>
</template>

<style scoped>
.admin-ant-card :deep(.ant-card-head-title) {
    font-weight: 600;
}

.admin-ant-plan-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.admin-ant-meta {
    margin-top: 4px;
    font-size: 12px;
    color: rgba(0, 0, 0, 0.45);
}

.admin-ant-desc {
    margin-top: 6px;
    font-size: 13px;
    color: rgba(0, 0, 0, 0.65);
}

.admin-ant-desc--muted {
    color: rgba(0, 0, 0, 0.45);
}

.admin-ant-user-pick {
    text-align: left;
    height: auto;
    padding: 8px 12px;
}
</style>
