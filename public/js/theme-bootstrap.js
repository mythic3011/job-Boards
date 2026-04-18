(function () {
    var root = document.documentElement;
    var preferenceKey = "jobs-board.theme.preference";
    var accentKey = "jobs-board.theme.accent";
    var validPreferences = { system: true, light: true, dark: true };
    var validAccents = { indigo: true, graphite: true, forest: true };
    var preference = "system";
    var accent = "indigo";

    try {
        var storedPreference = window.localStorage.getItem(preferenceKey);
        var storedAccent = window.localStorage.getItem(accentKey);

        if (storedPreference && validPreferences[storedPreference]) {
            preference = storedPreference;
        }

        if (storedAccent && validAccents[storedAccent]) {
            accent = storedAccent;
        }
    } catch (error) {
        // Continue with defaults when storage is unavailable.
    }

    var mediaQuery = window.matchMedia
        ? window.matchMedia("(prefers-color-scheme: dark)")
        : null;
    var mode =
        preference === "system"
            ? mediaQuery && mediaQuery.matches
                ? "dark"
                : "light"
            : preference;

    root.dataset.themePreference = preference;
    root.dataset.themeMode = mode;
    root.dataset.themeAccent = accent;
    root.style.colorScheme = mode;
})();
