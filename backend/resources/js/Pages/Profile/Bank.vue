<script setup>
import ExchangeLayout from '@/widgets/exchange-shell/ui/ExchangeLayout.vue';
import ProfileSettingsShell from '@/widgets/profile-settings-shell/ui/ProfileSettingsShell.vue';
import FlashBanner from '@/shared/ui/flash-banner/FlashBanner.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    profile: Object,
});

const page = usePage();

const form = useForm({
    bank_name: props.profile.bank_name ?? '',
    bank_holder: props.profile.bank_holder ?? '',
    bank_account: props.profile.bank_account ?? '',
});

const hasRequisites = () => Boolean(props.profile.bank_name && props.profile.bank_account);

function submit() {
    form.patch(route('profile.bank.update'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Банковские реквизиты" />

    <ExchangeLayout>
        <template #title>Банковские реквизиты</template>

        <ProfileSettingsShell>
            <FlashBanner v-if="page.props.flash?.success" :message="page.props.flash.success" tone="success" />

            <div v-if="hasRequisites()" class="card mb-4">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <span class="flex items-center gap-2 font-semibold">
                        <span class="material-symbols-outlined text-accent">account_balance</span>
                        {{ profile.bank_name }}
                    </span>
                    <span class="verified-badge">Сохранено</span>
                </div>
                <p class="text-sm text-text-muted">{{ profile.bank_account }}</p>
                <p class="mt-1 text-sm text-text-dim">{{ profile.bank_holder }}</p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <section class="card space-y-4">
                    <p class="text-label-caps uppercase text-text-dim">
                        {{ hasRequisites() ? 'Изменить реквизиты' : 'Добавить реквизиты' }}
                    </p>

                    <div>
                        <label class="mb-2 block text-label-caps uppercase text-text-dim">Банк</label>
                        <input v-model="form.bank_name" class="input-field" required maxlength="255" placeholder="Kaspi Bank" />
                        <p v-if="form.errors.bank_name" class="mt-2 text-sm text-error">{{ form.errors.bank_name }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-label-caps uppercase text-text-dim">Получатель</label>
                        <input v-model="form.bank_holder" class="input-field" required maxlength="255" placeholder="ФИО получателя" />
                        <p v-if="form.errors.bank_holder" class="mt-2 text-sm text-error">{{ form.errors.bank_holder }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-label-caps uppercase text-text-dim">IBAN / счёт</label>
                        <input v-model="form.bank_account" class="input-field" required maxlength="255" placeholder="KZ..." />
                        <p v-if="form.errors.bank_account" class="mt-2 text-sm text-error">{{ form.errors.bank_account }}</p>
                    </div>
                </section>

                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Сохранение…' : 'Сохранить' }}
                </button>
            </form>

            <p class="mt-4 text-sm text-text-muted">
                Реквизиты используются для выплат при продаже USDT. Убедитесь, что данные совпадают с KYC.
            </p>
        </ProfileSettingsShell>
    </ExchangeLayout>
</template>
