import { onMounted, onUnmounted, ref } from 'vue';

/**
 * Pull-to-refresh for mobile PWA (touch only).
 *
 * @param {() => Promise<void>|void} onRefresh
 * @param {{ threshold?: number, maxPull?: number }} options
 */
export function usePullToRefresh(onRefresh, options = {}) {
    const threshold = options.threshold ?? 72;
    const maxPull = options.maxPull ?? 120;
    const pullDistance = ref(0);
    const isRefreshing = ref(false);

    let startY = 0;
    let tracking = false;

    function scrollTop() {
        return window.scrollY || document.documentElement.scrollTop || 0;
    }

    function onTouchStart(event) {
        if (isRefreshing.value || scrollTop() > 0) {
            tracking = false;

            return;
        }

        startY = event.touches[0].clientY;
        tracking = true;
    }

    function onTouchMove(event) {
        if (!tracking || isRefreshing.value) {
            return;
        }

        const delta = event.touches[0].clientY - startY;

        if (delta <= 0) {
            pullDistance.value = 0;

            return;
        }

        pullDistance.value = Math.min(delta * 0.45, maxPull);

        if (scrollTop() === 0) {
            event.preventDefault();
        }
    }

    async function onTouchEnd() {
        if (!tracking) {
            return;
        }

        tracking = false;

        if (pullDistance.value < threshold || isRefreshing.value) {
            pullDistance.value = 0;

            return;
        }

        isRefreshing.value = true;
        pullDistance.value = threshold;

        try {
            await onRefresh();
        } finally {
            isRefreshing.value = false;
            pullDistance.value = 0;
        }
    }

    onMounted(() => {
        document.addEventListener('touchstart', onTouchStart, { passive: true });
        document.addEventListener('touchmove', onTouchMove, { passive: false });
        document.addEventListener('touchend', onTouchEnd);
        document.addEventListener('touchcancel', onTouchEnd);
    });

    onUnmounted(() => {
        document.removeEventListener('touchstart', onTouchStart);
        document.removeEventListener('touchmove', onTouchMove);
        document.removeEventListener('touchend', onTouchEnd);
        document.removeEventListener('touchcancel', onTouchEnd);
    });

    return {
        pullDistance,
        isRefreshing,
    };
}
