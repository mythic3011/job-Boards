const SHOW_OFFSET = 320;
const prefersReducedMotion = window.matchMedia?.(
    "(prefers-reduced-motion: reduce)",
)?.matches;

function getButton() {
    return document.querySelector("[data-back-to-top]");
}

function setVisible(button, visible) {
    button.setAttribute("data-visible", visible ? "true" : "false");
    button.setAttribute("aria-hidden", visible ? "false" : "true");
    button.tabIndex = visible ? 0 : -1;
}

function syncVisibility(button) {
    setVisible(button, window.scrollY > SHOW_OFFSET);
}

function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: prefersReducedMotion ? "auto" : "smooth",
    });
}

function initBackToTop() {
    const button = getButton();

    if (!button) {
        return;
    }

    if (button.dataset.backToTopBound === "true") {
        syncVisibility(button);
        return;
    }

    let ticking = false;

    const requestSync = () => {
        if (ticking) {
            return;
        }

        ticking = true;

        requestAnimationFrame(() => {
            syncVisibility(button);
            ticking = false;
        });
    };

    button.addEventListener("click", scrollToTop);
    window.addEventListener("scroll", requestSync, { passive: true });
    document.addEventListener("livewire:navigated", requestSync);

    button.dataset.backToTopBound = "true";
    syncVisibility(button);
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initBackToTop, { once: true });
} else {
    initBackToTop();
}
