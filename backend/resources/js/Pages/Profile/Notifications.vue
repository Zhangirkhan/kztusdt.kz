<script setup>
import ExchangeLayout from '@/widgets/exchange-shell/ui/ExchangeLayout.vue';
import ProfileSettingsShell from '@/widgets/profile-settings-shell/ui/ProfileSettingsShell.vue';
import FlashBanner from '@/shared/ui/flash-banner/FlashBanner.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    profile: Object,
});

const page = usePage();
const prefs = props.profile.notification_preferences ?? { push: true, email: true, sms: true };

const form = useForm({
    push: Boolean(prefs.push),
    email: Boolean(prefs.email),
    sms: Boolean(prefs.sms),
});

function submit() {
    form.patch(route('profile.notifications.update'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Уведомления" />

    <ExchangeLayout>
        <template #title>Уведомления</template>

        <ProfileSettingsShell>
            <FlashBanner v-if="page.props.flash?.success" :message="page.props.flash.success" tone="success" />

            <form class="settings-list" @submit.prevent="submit">
                <label class="settings-item cursor-pointer">
                    <span class="settings-item__icon"><span class="material-symbols-outlined">notifications</span></span>
                    <span class="settings-item__label">Push-уведомления</span>
                    <input v-model="form.push" type="checkbox" class="h-5 w-5 accent-accent" />
                </label>
                <label class="settings-item cursor-pointer">
                    <span class="settings-item__icon"><span class="material-symbols-outlined">mail</span></span>
                    <span class="settings-item__label">Email</span>
                    <input v-model="form.email" type="checkbox" class="h-5 w-5 accent-accent" />
                </label>
                <label class="settings-item cursor-pointer">
                    <span class="settings-item__icon"><span class="material-symbols-outlined">sms</span></span>
                    <span class="settings-item__label">SMS</span>
                    <input v-model="form.sms" type="checkbox" class="h-5 w-5 accent-accent" />
                </label>
                <div class="border-t border-outline-variant/40 bg-surface p-4">
                    <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                        {{ form.processing ? 'Сохранение…' : 'Сохранить' }}
                    </button>
                </div>
            </form>
        </ProfileSettingsShell>
    </ExchangeLayout>
</template>
