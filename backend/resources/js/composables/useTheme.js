import { onMounted, ref, watch } from 'vue';

const STORAGE_KEY = 'kztusdt.theme';
const THEMES = ['light', 'dark'];

function applyResolvedTheme(resolved) {
    const isDark = resolved === 'dark';
    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.dataset.theme = isDark ? 'dark' : 'light';

    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
        meta.setAttribute('content', isDark ? '#0f172a' : '#ffffff');
    }
}

export function getStoredTheme() {
    const stored = localStorage.getItem(STORAGE_KEY);

    if (stored === 'dark') {
        return 'dark';
    }

    // Legacy "system" and missing value → light (white) by default.
    return 'light';
}

export function applyThemePreference(theme) {
    const resolved = theme === 'dark' ? 'dark' : 'light';
    applyResolvedTheme(resolved);

    return resolved;
}

export function setThemePreference(theme) {
    const resolved = theme === 'dark' ? 'dark' : 'light';
    localStorage.setItem(STORAGE_KEY, resolved);
    applyThemePreference(resolved);

    return resolved;
}

export function useTheme() {
    const preference = ref(getStoredTheme());

    const isDark = ref(preference.value === 'dark');

    function setDark(enabled) {
        isDark.value = enabled;
        preference.value = setThemePreference(enabled ? 'dark' : 'light');
    }

    onMounted(() => {
        applyThemePreference(preference.value);
        isDark.value = preference.value === 'dark';
    });

    watch(preference, (next) => {
        isDark.value = next === 'dark';
        applyThemePreference(next);
    });

    return {
        preference,
        isDark,
        setDark,
        setPreference: setThemePreference,
    };
}
