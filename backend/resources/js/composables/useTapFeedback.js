import { ref } from 'vue';

/**
 * Brief visual + haptic feedback for tap actions (mobile-friendly).
 */
export function useTapFeedback(durationMs = 180) {
    const tapping = ref(false);

    function wrap(handler) {
        return async (...args) => {
            tapping.value = true;

            if (typeof navigator !== 'undefined' && navigator.vibrate) {
                navigator.vibrate(12);
            }

            try {
                return await handler(...args);
            } finally {
                window.setTimeout(() => {
                    tapping.value = false;
                }, durationMs);
            }
        };
    }

    return { tapping, wrap };
}
