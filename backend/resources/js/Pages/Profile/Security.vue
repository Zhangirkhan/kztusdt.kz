<script setup>
import ExchangeLayout from '@/widgets/exchange-shell/ui/ExchangeLayout.vue';
import ProfileSettingsShell from '@/widgets/profile-settings-shell/ui/ProfileSettingsShell.vue';
import { useBiometricAuth } from '@/composables/useBiometricAuth';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const page = usePage();
const { supported, checkAvailability, registerBiometric } = useBiometricAuth();

const available = ref(false);
const busy = ref(false);
const message = ref('');

const userPhone = computed(() => page.props.auth?.user?.phone ?? '');

onMounted(async () => {
    if (!supported || !userPhone.value) {
        return;
    }

    try {
        available.value = await checkAvailability(userPhone.value);
    } catch {
        available.value = false;
    }
});

async function enableBiometric() {
    busy.value = true;
    message.value = '';

    try {
        await registerBiometric();
        message.value = 'Биометрический вход включён на этом устройстве.';
    } catch (error) {
        message.value = error?.message ?? 'Не удалось включить биометрию.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Head title="Безопасность" />

    <ExchangeLayout>
        <template #title>Безопасность</template>

        <ProfileSettingsShell>
        <section class="card mb-4">
            <div class="flex items-start gap-3">
                <span class="settings-item__icon">
                    <span class="material-symbols-outlined">smartphone</span>
                </span>
                <div>
                    <p class="font-semibold text-on-surface">Подтверждение телефона</p>
                    <p class="mt-1 text-sm text-text-muted">Вход и операции подтверждаются через WhatsApp-код.</p>
                    <Link :href="route('auth.phone')" class="mt-3 inline-block text-sm font-semibold text-accent">Проверить номер →</Link>
                </div>
            </div>
        </section>

        <section class="card mb-4">
            <div class="flex items-start gap-3">
                <span class="settings-item__icon">
                    <span class="material-symbols-outlined">fingerprint</span>
                </span>
                <div class="flex-1">
                    <p class="font-semibold text-on-surface">Face ID / отпечаток</p>
                    <p class="mt-1 text-sm text-text-muted">
                        Быстрый вход на этом устройстве после первой авторизации по номеру телефона.
                    </p>

                    <button
                        v-if="supported && available"
                        type="button"
                        class="btn-secondary mt-4"
                        :disabled="busy"
                        @click="enableBiometric"
                    >
                        {{ busy ? 'Настройка…' : 'Включить биометрию' }}
                    </button>
                    <p v-else-if="!supported" class="mt-3 text-sm text-text-dim">Биометрия недоступна в этом браузере.</p>
                    <p v-else class="mt-3 text-sm text-text-dim">Сначала войдите по номеру телефона на этом устройстве.</p>

                    <p v-if="message" class="mt-3 text-sm" :class="message.includes('включён') ? 'text-accent' : 'text-error'">
                        {{ message }}
                    </p>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="flex items-start gap-3">
                <span class="settings-item__icon">
                    <span class="material-symbols-outlined">verified_user</span>
                </span>
                <div>
                    <p class="font-semibold text-on-surface">KYC / верификация</p>
                    <p class="mt-1 text-sm text-text-muted">Доступ к кошельку и обмену после проверки документов.</p>
                    <Link :href="route('kyc')" class="mt-3 inline-block text-sm font-semibold text-accent">Перейти к KYC →</Link>
                </div>
            </div>
        </section>
        </ProfileSettingsShell>
    </ExchangeLayout>
</template>
