const STORAGE_KEYS = {
    preference: "jobs-board.theme.preference",
    accent: "jobs-board.theme.accent",
};

const VALID_PREFERENCES = ["system", "light", "dark"];
const VALID_ACCENTS = ["indigo", "graphite", "forest"];

const MODE_LABELS = {
    system: "System",
    light: "Light",
    dark: "Dark",
};

const ACCENT_LABELS = {
    indigo: "Default",
    graphite: "Graphite",
    forest: "Forest",
};

function getRoot() {
    return document.documentElement;
}

function safeStorageGet(key) {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function safeStorageSet(key, value) {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Ignore storage failures and continue with in-memory state.
    }
}

function normalizePreference(value) {
    return VALID_PREFERENCES.includes(value) ? value : "system";
}

function normalizeAccent(value) {
    return VALID_ACCENTS.includes(value) ? value : "indigo";
}

function resolveMode(preference) {
    if (preference !== "system") {
        return preference;
    }

    return window.matchMedia?.("(prefers-color-scheme: dark)").matches
        ? "dark"
        : "light";
}

function getState() {
    const root = getRoot();
    const preference = normalizePreference(root.dataset.themePreference);
    const accent = normalizeAccent(root.dataset.themeAccent);

    return {
        preference,
        accent,
        mode: resolveMode(preference),
    };
}

function syncThemeSwitcher(state = getState()) {
    document
        .querySelectorAll("[data-theme-preference-option]")
        .forEach((button) => {
            const active =
                button.dataset.themePreferenceOption === state.preference;
            button.dataset.active = active ? "true" : "false";
            button.setAttribute("aria-pressed", active ? "true" : "false");
        });

    document.querySelectorAll("[data-theme-accent-option]").forEach((button) => {
        const active = button.dataset.themeAccentOption === state.accent;
        button.dataset.active = active ? "true" : "false";
        button.setAttribute("aria-pressed", active ? "true" : "false");
    });

    document
        .querySelectorAll("[data-theme-current-summary]")
        .forEach((summary) => {
            summary.textContent = `${MODE_LABELS[state.preference]} · ${ACCENT_LABELS[state.accent]}`;
        });
}

function applyThemeState(nextState, { persist = true, announce = true } = {}) {
    const preference = normalizePreference(nextState.preference);
    const accent = normalizeAccent(nextState.accent);
    const mode = resolveMode(preference);
    const root = getRoot();

    root.dataset.themePreference = preference;
    root.dataset.themeMode = mode;
    root.dataset.themeAccent = accent;
    root.style.colorScheme = mode;

    if (persist) {
        safeStorageSet(STORAGE_KEYS.preference, preference);
        safeStorageSet(STORAGE_KEYS.accent, accent);
    }

    const state = { preference, accent, mode };
    syncThemeSwitcher(state);

    if (announce) {
        window.dispatchEvent(new CustomEvent("theme:changed", { detail: state }));
    }
}

function bootstrapStoredTheme() {
    const state = getState();
    const preference = normalizePreference(
        safeStorageGet(STORAGE_KEYS.preference) ?? state.preference,
    );
    const accent = normalizeAccent(
        safeStorageGet(STORAGE_KEYS.accent) ?? state.accent,
    );

    applyThemeState({ preference, accent }, { persist: false, announce: false });
}

function bindThemeControls() {
    if (window.__themeControlsBound) {
        syncThemeSwitcher();
        return;
    }

    document.addEventListener("click", (event) => {
        const preferenceButton = event.target.closest(
            "[data-theme-preference-option]",
        );
        if (preferenceButton) {
            applyThemeState({
                preference: preferenceButton.dataset.themePreferenceOption,
                accent: getState().accent,
            });
            return;
        }

        const accentButton = event.target.closest("[data-theme-accent-option]");
        if (accentButton) {
            applyThemeState({
                preference: getState().preference,
                accent: accentButton.dataset.themeAccentOption,
            });
        }
    });

    const mediaQuery = window.matchMedia?.("(prefers-color-scheme: dark)");
    if (mediaQuery) {
        const handleSystemThemeChange = () => {
            if (getState().preference === "system") {
                applyThemeState(getState(), { persist: false });
            }
        };

        if (typeof mediaQuery.addEventListener === "function") {
            mediaQuery.addEventListener("change", handleSystemThemeChange);
        } else if (typeof mediaQuery.addListener === "function") {
            mediaQuery.addListener(handleSystemThemeChange);
        }
    }

    window.__themeControlsBound = true;
    syncThemeSwitcher();
}

function initThemeController() {
    bootstrapStoredTheme();
    bindThemeControls();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initThemeController, {
        once: true,
    });
} else {
    initThemeController();
}
