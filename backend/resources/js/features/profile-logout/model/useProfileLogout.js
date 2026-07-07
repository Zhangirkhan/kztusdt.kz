import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

export function useProfileLogout() {
    const { t } = useI18n();

    function logout() {
        if (!window.confirm(t('profile.logoutConfirm'))) {
            return;
        }

        router.post(route('logout'));
    }

    return { logout };
}
