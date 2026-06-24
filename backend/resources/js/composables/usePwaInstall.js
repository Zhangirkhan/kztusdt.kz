import { onMounted, onUnmounted, ref } from 'vue';

const DISMISS_KEY = 'pwa-install-dismissed';
const DISMISS_DAYS = 14;

function isStandalone() {
    return (
        window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true
    );
}

function wasDismissedRecently() {
    const dismissedAt = localStorage.getItem(DISMISS_KEY);

    if (!dismissedAt) {
        return false;
    }

    const daysSinceDismiss = (Date.now() - Number(dismissedAt)) / (1000 * 60 * 60 * 24);

    return daysSinceDismiss < DISMISS_DAYS;
}

export function usePwaInstall() {
    const canInstall = ref(false);
    const showIosHint = ref(false);
    let deferredPrompt = null;

    function dismiss() {
        localStorage.setItem(DISMISS_KEY, String(Date.now()));
        canInstall.value = false;
        showIosHint.value = false;
    }

    async function install() {
        if (!deferredPrompt) {
            return false;
        }

        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        deferredPrompt = null;
        canInstall.value = false;

        return outcome === 'accepted';
    }

    function onBeforeInstallPrompt(event) {
        event.preventDefault();

        if (isStandalone() || wasDismissedRecently()) {
            return;
        }

        deferredPrompt = event;
        canInstall.value = true;
    }

    function onAppInstalled() {
        deferredPrompt = null;
        canInstall.value = false;
        showIosHint.value = false;
    }

    onMounted(() => {
        if (isStandalone() || wasDismissedRecently()) {
            return;
        }

        const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;

        if (isIos) {
            showIosHint.value = true;
            return;
        }

        window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);
        window.addEventListener('appinstalled', onAppInstalled);
    });

    onUnmounted(() => {
        window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt);
        window.removeEventListener('appinstalled', onAppInstalled);
    });

    return {
        canInstall,
        showIosHint,
        dismiss,
        install,
    };
}
