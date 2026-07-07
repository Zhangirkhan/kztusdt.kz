import { buildProfileMenuItems } from '@/entities/profile/model/menu';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useProfileMenu() {
    const page = usePage();

    const canUseWallet = computed(() => page.props.auth?.user?.can_use_wallet ?? false);

    const languageLabel = computed(() => {
        const options = page.props.locale?.options ?? [];
        const current = page.props.locale?.current ?? 'ru';

        return options.find((item) => item.code === current)?.label ?? 'Русский';
    });

    const menuItems = computed(() => buildProfileMenuItems(languageLabel.value, canUseWallet.value));

    return { menuItems, languageLabel, canUseWallet };
}
