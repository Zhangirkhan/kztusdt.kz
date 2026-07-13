import { onMounted, onUnmounted, ref } from 'vue';

export const ADMIN_SHELL_BREAKPOINT = 992;
export const ADMIN_CONTENT_BREAKPOINT = 768;

/**
 * Reactive viewport flags for admin UI.
 * @param {'shell'|'content'} mode shell = drawer/sider (992), content = tables/filters (768)
 */
export function useAdminBreakpoint(mode = 'content') {
    const breakpoint = mode === 'shell' ? ADMIN_SHELL_BREAKPOINT : ADMIN_CONTENT_BREAKPOINT;
    const isMobile = ref(
        typeof window !== 'undefined' ? window.innerWidth < breakpoint : false,
    );

    function updateViewport() {
        if (typeof window === 'undefined') {
            return;
        }

        isMobile.value = window.innerWidth < breakpoint;
    }

    onMounted(() => {
        updateViewport();
        window.addEventListener('resize', updateViewport);
    });

    onUnmounted(() => {
        window.removeEventListener('resize', updateViewport);
    });

    return { isMobile, breakpoint };
}
