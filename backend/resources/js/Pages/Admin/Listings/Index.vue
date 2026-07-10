<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import BankLogo from '@/shared/ui/bank-logo/BankLogo.vue';
import { formatKzt, formatRate, formatUsdt } from '@/utils/formatNumber';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    listings: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const deleteTarget = ref(null);
const togglingId = ref(null);

function toggleListing(listing, active) {
    togglingId.value = listing.id;
    router.patch(
        route('admin.listings.toggle', listing.id),
        { active },
        {
            preserveScroll: true,
            onFinish: () => {
                togglingId.value = null;
            },
        },
    );
}

function confirmDelete() {
    if (!deleteTarget.value) {
        return;
    }

    router.delete(route('admin.listings.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleteTarget.value = null;
        },
    });
}
</script>

<template>
    <Head title="Объявления" />

    <AdminLayout>
        <template #title>Объявления</template>

        <AdminPage>
            <div class="admin-listings-toolbar">
                <p class="admin-listings-toolbar__hint">
                    Управляйте курсами, лимитами и сроками оплаты для клиентской страницы обмена.
                </p>
                <Link :href="route('admin.listings.create')" class="admin-listings-create">
                    <span class="material-symbols-outlined" aria-hidden="true">add</span>
                    Создать объявление
                </Link>
            </div>

            <div v-if="listings.length === 0" class="admin-listings-empty">
                <p>Пока нет объявлений.</p>
                <Link :href="route('admin.listings.create')">Создать первое объявление</Link>
            </div>

            <div v-else class="admin-listings-grid">
                <article v-for="listing in listings" :key="listing.id" class="admin-listing-card">
                    <header class="admin-listing-card__header">
                        <div>
                            <h2 class="admin-listing-card__title">{{ listing.title }}</h2>
                            <p class="admin-listing-card__subtitle">{{ listing.subtitle }}</p>
                        </div>
                        <div class="admin-listing-card__toggle">
                            <a-switch
                                :checked="listing.is_active"
                                :loading="togglingId === listing.id"
                                @change="(checked) => toggleListing(listing, checked)"
                            />
                            <span class="admin-listing-card__toggle-label">
                                В рынке
                                <small>{{ listing.is_active ? 'активно' : 'неактивно' }}</small>
                            </span>
                        </div>
                    </header>

                    <p class="admin-listing-card__rate">{{ formatRate(listing.display_rate) }} KZT</p>

                    <div class="admin-listing-card__stats">
                        <div>
                            <span class="admin-listing-card__stat-label">Всего</span>
                            <strong>{{ formatUsdt(listing.total_usdt, 2) }} USDT</strong>
                        </div>
                        <div>
                            <span class="admin-listing-card__stat-label">Остаток</span>
                            <strong>{{ formatUsdt(listing.remaining_usdt, 2) }} USDT</strong>
                        </div>
                        <div class="admin-listing-card__limits">
                            <span class="admin-listing-card__stat-label">Лимиты</span>
                            <strong>
                                {{ formatKzt(listing.min_limit_kzt) }} - {{ formatKzt(listing.max_limit_kzt) }} KZT
                            </strong>
                        </div>
                    </div>

                    <div class="admin-listing-card__banks">
                        <span class="admin-listing-card__stat-label">Оплата</span>
                        <div class="admin-listing-card__bank-chips">
                            <span
                                v-for="bank in listing.payment_methods"
                                :key="bank.code"
                                class="admin-listing-card__bank-chip"
                            >
                                <BankLogo :code="bank.code" size="sm" />
                                {{ bank.name }}
                            </span>
                        </div>
                    </div>

                    <footer class="admin-listing-card__actions">
                        <Link :href="route('admin.listings.edit', listing.id)" class="admin-listing-card__btn">
                            Редактировать
                        </Link>
                        <button type="button" class="admin-listing-card__btn admin-listing-card__btn--danger" @click="deleteTarget = listing">
                            Удалить
                        </button>
                    </footer>
                </article>
            </div>
        </AdminPage>

        <a-modal
            :open="Boolean(deleteTarget)"
            title="Удалить объявление?"
            ok-text="Да, удалить"
            cancel-text="Нет"
            ok-type="danger"
            @ok="confirmDelete"
            @cancel="deleteTarget = null"
        >
            <p v-if="deleteTarget">
                Объявление «{{ deleteTarget.title }}» будет удалено без возможности восстановления.
            </p>
        </a-modal>
    </AdminLayout>
</template>

<style scoped>
.admin-listings-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
}

.admin-listings-toolbar__hint {
    margin: 0;
    color: #64748b;
}

.admin-listings-create {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 10px;
    background: #1677ff;
    color: #fff;
    font-weight: 600;
    text-decoration: none;
}

.admin-listings-empty {
    padding: 48px;
    text-align: center;
    background: #fff;
    border-radius: 16px;
    border: 1px dashed #d9d9d9;
}

.admin-listings-grid {
    display: grid;
    gap: 16px;
}

.admin-listing-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #edf2f7;
    padding: 20px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
}

.admin-listing-card__header {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 12px;
}

.admin-listing-card__title {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
}

.admin-listing-card__subtitle {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 13px;
}

.admin-listing-card__toggle {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
}

.admin-listing-card__toggle-label {
    font-size: 12px;
    color: #16a34a;
    text-align: right;
}

.admin-listing-card__toggle-label small {
    display: block;
    color: #94a3b8;
}

.admin-listing-card__rate {
    margin: 0 0 16px;
    font-size: 28px;
    font-weight: 800;
    color: #2563eb;
}

.admin-listing-card__stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.admin-listing-card__stat-label {
    display: block;
    margin-bottom: 4px;
    color: #94a3b8;
    font-size: 12px;
}

.admin-listing-card__limits {
    grid-column: 1 / -1;
}

.admin-listing-card__banks {
    margin-bottom: 16px;
}

.admin-listing-card__bank-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.admin-listing-card__bank-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    font-size: 12px;
}

.admin-listing-card__actions {
    display: flex;
    gap: 10px;
}

.admin-listing-card__btn {
    padding: 10px 14px;
    border-radius: 10px;
    border: 1px solid #dbe3ee;
    background: #fff;
    font-weight: 600;
    text-decoration: none;
    color: inherit;
}

.admin-listing-card__btn--danger {
    color: #dc2626;
    border-color: #fecaca;
    background: #fff5f5;
}

@media (max-width: 768px) {
    .admin-listings-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .admin-listing-card__stats {
        grid-template-columns: 1fr;
    }
}
</style>
