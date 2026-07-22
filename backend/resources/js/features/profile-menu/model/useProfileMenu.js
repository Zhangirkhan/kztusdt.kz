import { buildProfileMenuItems } from '@/entities/profile/model/menu';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

export function useProfileMenu() {
    const page = usePage();
    const { t } = useI18n();

    const canUseWallet = computed(() => page.props.auth?.user?.can_use_wallet ?? false);

    const locale = computed(() => page.props.locale?.current ?? 'ru');

    const menuItems = computed(() =>
        buildProfileMenuItems(canUseWallet.value, locale.value).map((item) => ({
            ...item,
            label: item.labelKey ? t(item.labelKey) : item.label,
        })),
    );

    return { menuItems, canUseWallet };
}
