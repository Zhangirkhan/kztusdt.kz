import { onMounted, ref, watch } from 'vue';

const STORAGE_KEY = 'kztusdt.theme';

export function getStoredTheme() {
    if (typeof window === 'undefined') {
        return 'light';
    }

    const stored = localStorage.getItem(STORAGE_KEY);

    if (stored === 'dark') {
        return 'dark';
    }

    // Legacy "system" and missing value → light (white) by default.
    return 'light';
}

const preference = ref(typeof window !== 'undefined' ? getStoredTheme() : 'light');
const isDark = ref(preference.value === 'dark');

function applyClientFavicons(dark) {
    const favicon = document.querySelector('link[rel="icon"][data-theme-favicon="ico"]');
    const png = document.querySelector('link[rel="icon"][data-theme-favicon="png"]');

    if (favicon) {
        favicon.setAttribute('href', dark ? '/favicon-dark.ico' : '/favicon.ico');
    }

    if (png) {
        png.setAttribute('href', dark ? '/icons/icon-32-dark.png' : '/icons/icon-32.png');
    }
}

function isAdminSurface() {
    return document.documentElement.dataset.adminSurface === 'true';
}

function applyResolvedTheme(resolved) {
    if (isAdminSurface()) {
        document.documentElement.classList.remove('dark');
        document.documentElement.dataset.theme = 'light';
        return;
    }

    const dark = resolved === 'dark';
    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';

    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
        meta.setAttribute('content', dark ? '#0f172a' : '#ffffff');
    }

    applyClientFavicons(dark);
}

export function applyThemePreference(theme) {
    const resolved = theme === 'dark' ? 'dark' : 'light';
    preference.value = resolved;
    isDark.value = resolved === 'dark';
    applyResolvedTheme(resolved);

    return resolved;
}

export function setThemePreference(theme) {
    const resolved = theme === 'dark' ? 'dark' : 'light';
    localStorage.setItem(STORAGE_KEY, resolved);

    return applyThemePreference(resolved);
}

export function useTheme() {
    function setDark(enabled) {
        setThemePreference(enabled ? 'dark' : 'light');
    }

    onMounted(() => {
        if (isAdminSurface()) {
            return;
        }

        applyThemePreference(getStoredTheme());
    });

    watch(preference, (next) => {
        isDark.value = next === 'dark';
    });

    return {
        preference,
        isDark,
        setDark,
        setPreference: setThemePreference,
    };
}
