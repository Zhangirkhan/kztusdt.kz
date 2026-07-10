import { buildProfileMenuItems } from '@/entities/profile/model/menu';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

export function useProfileMenu() {
    const page = usePage();
    const { t } = useI18n();

    const canUseWallet = computed(() => page.props.auth?.user?.can_use_wallet ?? false);

    const languageLabel = computed(() => {
        const options = page.props.locale?.options ?? [];
        const current = page.props.locale?.current ?? 'ru';

        return options.find((item) => item.code === current)?.label ?? t('locale.label');
    });

    const menuItems = computed(() =>
        buildProfileMenuItems(languageLabel.value, canUseWallet.value).map((item) => ({
            ...item,
            label: item.labelKey ? t(item.labelKey) : item.label,
        })),
    );

    return { menuItems, languageLabel, canUseWallet };
}
