<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    profile: Object,
    roles: Array,
});
</script>

<template>
    <Head title="Аккаунт" />

    <AdminLayout>
        <template #title>Аккаунт</template>

        <div class="mx-auto max-w-lg space-y-6">
            <section class="card space-y-2">
                <p class="text-label-caps uppercase text-text-dim">Сотрудник</p>
                <p class="text-headline-md">{{ profile.name || '—' }}</p>
                <p class="text-body-sm text-text-muted">{{ profile.phone || '—' }}</p>
                <p v-if="profile.email" class="text-body-sm text-text-muted">{{ profile.email }}</p>
                <p v-if="roles.length" class="pt-2 text-xs text-text-dim">
                    Роли: {{ roles.join(', ') }}
                </p>
            </section>

            <section class="card">
                <p class="mb-3 text-label-caps uppercase text-text-dim">Язык интерфейса</p>
                <LocaleSwitcher show-label />
            </section>

            <section class="card space-y-3">
                <Link
                    v-if="$page.props.auth.canAccessPwa"
                    href="/profile"
                    class="btn-secondary block text-center no-underline"
                >
                    Полный профиль в приложении
                </Link>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="btn-secondary block w-full text-center text-red-400 no-underline"
                >
                    Выйти
                </Link>
            </section>
        </div>
    </AdminLayout>
</template>
