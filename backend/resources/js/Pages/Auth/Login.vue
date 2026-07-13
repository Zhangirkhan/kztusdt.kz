<script setup>
import AppLogo from '@/Components/AppLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import AdminPwaInstallBanner from '@/widgets/admin-shell/ui/AdminPwaInstallBanner.vue';
import { localizedPath } from '@/utils/localizedPath';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    signedInAsClient: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showClientPwaHint = ref(false);
const { t } = useI18n();

onMounted(() => {
    showClientPwaHint.value =
        window.matchMedia('(display-mode: standalone)').matches
        && window.location.pathname.startsWith('/admin');
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head :title="t('admin.auth.loginTitle')" />

    <div class="app-frame">
        <div class="app-shell page-enter flex min-h-dvh flex-col px-margin-page py-stack-section">
            <div class="mx-auto w-full max-w-md">
            <div class="mb-8 text-center">
                <AppLogo show-wordmark class="mx-auto" />
                <h1 class="mt-6 text-headline-xl text-on-surface">{{ t('admin.auth.loginHeading') }}</h1>
                <p class="mt-2 text-body-sm text-text-muted">
                    {{ t('admin.auth.loginSubtitle') }}
                </p>
            </div>

            <div v-if="showClientPwaHint" class="card mb-4 border border-amber-500/30 bg-amber-500/10 text-sm text-on-surface">
                {{ t('admin.auth.clientPwaHintPrefix') }}
                <strong>kztusdt.kz/admin/login</strong>
                {{ t('admin.auth.clientPwaHintMiddle') }}
                <strong>admin.kztusdt.kz</strong>.
            </div>

            <div v-if="signedInAsClient" class="card mb-4 border border-outline-variant/40 text-sm text-text-muted">
                {{ t('admin.auth.signedInAsClient') }}
            </div>

            <div v-if="status" class="card mb-4 border border-accent/30 text-sm text-accent">
                {{ status }}
            </div>

            <form class="card space-y-4" @submit.prevent="submit">
                <div>
                    <label for="email" class="mb-2 block text-label-caps uppercase text-text-dim">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="input-field"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="admin@kztusdt.kz"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                    <label for="password" class="mb-2 block text-label-caps uppercase text-text-dim">{{ t('admin.auth.passwordLabel') }}</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="input-field"
                        required
                        autocomplete="current-password"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <label class="flex items-center gap-2 text-sm text-text-muted">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    {{ t('admin.auth.rememberMe') }}
                </label>

                <div class="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-sm text-text-dim hover:text-accent"
                    >
                        {{ t('admin.auth.forgotPassword') }}
                    </Link>

                    <button type="submit" class="btn-primary sm:ms-auto" :disabled="form.processing">
                        {{ form.processing ? t('admin.auth.signingIn') : t('admin.auth.signIn') }}
                    </button>
                </div>
            </form>

            <p class="mt-6 text-center text-body-sm text-text-dim">
                {{ t('admin.auth.clientsPrefix') }}
                <Link :href="localizedPath('/auth/phone')" class="text-accent hover:underline">{{ t('admin.auth.clientsPhoneLogin') }}</Link>
            </p>
            </div>
        </div>

        <AdminPwaInstallBanner />
    </div>
</template>
