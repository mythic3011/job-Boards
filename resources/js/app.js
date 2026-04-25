// jQuery-based app initialization with minimal code
import "./bootstrap";
import "./components/toast";
import "./components/dropdown";
import "./components/back-to-top";
import "./components/theme";
import "./components/infinite-pagination";
import "./components/ui-feedback";
import "./register";
import "./avatar";
import "./profile";
import Alpine from "@alpinejs/csp";

// Initialize everything with jQuery
$(() => {
    // Start Alpine.js
    window.Alpine = Alpine;
    Alpine.start();

    // UX: clear server-side validation styling as user edits (login/register/auth forms)
    $(document).on(
        "input change",
        "input.theme-input-error, textarea.theme-input-error, select.theme-input-error",
        function () {
            const $field = $(this);
            $field.removeClass("theme-input-error");
            const $wrapper = $field.closest("div");
            const $error = $wrapper
                .nextAll("p.theme-error-text, p.theme-install-error-text")
                .first();
            if ($error.length) {
                $error.fadeOut(150);
            }
        },
    );

    // Livewire initialization handler
    $(document).on("livewire:init", () => {
        console.log(
            "Livewire initialized!",
            window.Livewire.all().length,
            "components",
        );
        window.Livewire.all().forEach((comp, i) =>
            console.log(`Component ${i}:`, comp.name, comp.id),
        );
    });
});
