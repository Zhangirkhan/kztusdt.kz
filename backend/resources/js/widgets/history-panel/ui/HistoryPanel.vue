<script setup>
import { historyStatusFilters, historySubTabs } from '@/entities/history/model/constants';
import { groupHistoryItems, countHistoryByStatus } from '@/entities/history/lib/groupHistoryItems';
import {
    historyIconName,
    historyIconTone,
    historyStatusBadgeClass,
    historyStatusLabel,
} from '@/entities/history/lib/presentHistoryItem';
import { formatTime } from '@/shared/lib/format/date';
import { useHistoryFilters } from '@/features/history-filters/model/useHistoryFilters';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    section: { type: String, default: 'wallet' },
    filter: { type: String, default: 'all' },
    status: { type: String, default: 'all' },
    search: { type: String, default: '' },
    items: { type: Array, default: () => [] },
});
const { t } = useI18n();

const subTabs = computed(() => historySubTabs(props.section));
const statusFilters = computed(() => historyStatusFilters());
const groupedItems = computed(() => groupHistoryItems(props.items));
const completedCount = computed(() => countHistoryByStatus(props.items, 'COMPLETED'));
const pendingCount = computed(() => countHistoryByStatus(props.items, 'PENDING'));

const { setSection, setFilter, setStatus, setSearch } = useHistoryFilters(() => ({
    section: props.section,
    filter: props.filter,
    status: props.status,
    search: props.search,
}));
</script>

<template>
    <div class="history-stats">
        <div class="history-stat">
            <span class="history-stat__value">{{ items.length }}</span>
            <span class="history-stat__label">{{ t('history.panel.operations') }}</span>
        </div>
        <div class="history-stat history-stat--success">
            <span class="history-stat__value">{{ completedCount }}</span>
            <span class="history-stat__label">{{ t('history.panel.completed') }}</span>
        </div>
        <div class="history-stat history-stat--pending">
            <span class="history-stat__value">{{ pendingCount }}</span>
            <span class="history-stat__label">{{ t('history.panel.pending') }}</span>
        </div>
    </div>

    <div class="segment-tabs segment-tabs--history mb-3">
        <button type="button" class="segment-tab" :class="{ 'segment-tab--active': section === 'wallet' }" @click="setSection('wallet')">
            <span class="material-symbols-outlined text-base">account_balance_wallet</span>
            {{ t('history.panel.section.wallet') }}
        </button>
        <button type="button" class="segment-tab" :class="{ 'segment-tab--active': section === 'exchange' }" @click="setSection('exchange')">
            <span class="material-symbols-outlined text-base">currency_exchange</span>
            {{ t('history.panel.section.exchange') }}
        </button>
    </div>

    <div class="history-sub-tabs">
        <button
            v-for="tab in subTabs"
            :key="tab.id"
            type="button"
            class="history-sub-tab"
            :class="[
                `history-sub-tab--${tab.tone}`,
                { 'history-sub-tab--active': filter === tab.id || (filter === 'all' && tab.id === 'all') },
            ]"
            @click="setFilter(tab.id)"
        >
            <span class="material-symbols-outlined text-base">{{ tab.icon }}</span>
            {{ tab.label }}
        </button>
    </div>

    <div class="history-toolbar">
        <div class="history-search">
            <span class="material-symbols-outlined history-search__icon">search</span>
            <input type="search" class="history-search__input" :placeholder="t('history.panel.searchPlaceholder')" :value="search" @input="setSearch" />
        </div>
        <div class="history-filter-chips">
            <button
                v-for="chip in statusFilters"
                :key="chip.id"
                type="button"
                class="history-filter-chip"
                :class="{ 'history-filter-chip--active': status === chip.id }"
                @click="setStatus(chip.id)"
            >
                {{ chip.label }}
            </button>
        </div>
    </div>

    <div v-if="groupedItems.length === 0" class="history-empty">
        <span class="material-symbols-outlined text-4xl text-text-dim">history</span>
        <p class="history-empty__title">{{ t('history.panel.emptyTitle') }}</p>
        <p class="history-empty__text">{{ t('history.panel.emptyText') }}</p>
    </div>

    <section v-for="[group, groupItems] in groupedItems" :key="group" class="mb-4">
        <div class="history-group-label">{{ group }}</div>
        <div class="space-y-2">
            <component
                :is="item.href ? Link : 'div'"
                v-for="item in groupItems"
                :key="item.id"
                :href="item.href || undefined"
                class="history-item history-item--clickable"
            >
                <div class="history-item__icon" :class="`history-item__icon--${historyIconTone(item)}`">
                    <span class="material-symbols-outlined">{{ historyIconName(item) }}</span>
                </div>
                <div class="history-item__info">
                    <div class="history-item__amount">{{ item.amount }}</div>
                    <div class="history-item__source">{{ item.title }}</div>
                    <div v-if="item.subtitle" class="text-xs text-text-dim">{{ item.subtitle }}</div>
                </div>
                <div class="history-item__right">
                    <span class="status-badge" :class="historyStatusBadgeClass(item.status)">
                        {{ historyStatusLabel(item.status) }}
                    </span>
                    <div class="history-item__time">{{ formatTime(item.created_at) }}</div>
                </div>
            </component>
        </div>
    </section>
</template>
