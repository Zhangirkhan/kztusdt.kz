import { computed, onMounted, onUnmounted, ref, unref, watch } from 'vue';

export function useOrderCountdown(deadlineSource) {
    const remainingMs = ref(0);
    let timer = null;

    function resolveDeadline() {
        const value = unref(deadlineSource);

        if (!value) {
            return null;
        }

        const parsed = new Date(value);

        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    function tick() {
        const deadline = resolveDeadline();

        if (!deadline) {
            remainingMs.value = 0;
            return;
        }

        remainingMs.value = Math.max(0, deadline.getTime() - Date.now());
    }

    const formatted = computed(() => {
        const totalSec = Math.floor(remainingMs.value / 1000);
        const min = Math.floor(totalSec / 60);
        const sec = totalSec % 60;

        return `${String(min).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
    });

    const expired = computed(() => {
        const deadline = resolveDeadline();

        return deadline !== null && remainingMs.value <= 0;
    });

    const active = computed(() => resolveDeadline() !== null);

    onMounted(() => {
        tick();
        timer = window.setInterval(tick, 1000);
    });

    onUnmounted(() => {
        if (timer !== null) {
            window.clearInterval(timer);
        }
    });

    watch(
        () => unref(deadlineSource),
        () => tick(),
    );

    return {
        formatted,
        expired,
        active,
        remainingMs,
    };
}
