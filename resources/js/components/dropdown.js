const CLOSED_MENU_CLASSES = [
    "opacity-0",
    "translate-y-1",
    "scale-[0.98]",
    "pointer-events-none",
];

const OPEN_MENU_CLASSES = ["opacity-100", "translate-y-0", "scale-100"];

function getDropdownParts(dropdown) {
    return {
        button: dropdown.querySelector("[data-dropdown-button]"),
        menu: dropdown.querySelector("[data-dropdown-menu]"),
        arrow: dropdown.querySelector("[data-dropdown-arrow]"),
    };
}

function getFocusableItems(menu) {
    return Array.from(menu.querySelectorAll("a[href], button:not([disabled])"));
}

function setDropdownState(dropdown, open) {
    const { button, menu, arrow } = getDropdownParts(dropdown);
    if (!button || !menu) return;

    dropdown.dataset.open = open ? "true" : "false";
    button.setAttribute("aria-expanded", open ? "true" : "false");

    menu.classList.toggle("opacity-0", !open);
    menu.classList.toggle("translate-y-1", !open);
    menu.classList.toggle("scale-[0.98]", !open);
    menu.classList.toggle("pointer-events-none", !open);
    menu.classList.toggle("opacity-100", open);
    menu.classList.toggle("translate-y-0", open);
    menu.classList.toggle("scale-100", open);

    button.classList.toggle("bg-gray-50", open);
    button.classList.toggle("border-gray-200", open);
    button.classList.toggle("text-gray-900", open);

    if (arrow) {
        arrow.classList.toggle("rotate-180", open);
        arrow.classList.toggle("rotate-0", !open);
    }
}

function closeAllDropdowns(exception = null) {
    document.querySelectorAll("[data-dropdown]").forEach((dropdown) => {
        if (dropdown === exception) return;
        setDropdownState(dropdown, false);
    });
}

function focusFirstItem(dropdown) {
    const { menu } = getDropdownParts(dropdown);
    if (!menu) return;

    const firstItem = getFocusableItems(menu)[0];
    if (firstItem) firstItem.focus();
}

function handleMenuKeydown(event, dropdown) {
    const { menu, button } = getDropdownParts(dropdown);
    if (!menu || dropdown.dataset.open !== "true") return;

    const items = getFocusableItems(menu);
    if (!items.length) return;

    const currentIndex = items.indexOf(document.activeElement);

    if (event.key === "Escape") {
        event.preventDefault();
        setDropdownState(dropdown, false);
        button?.focus();
        return;
    }

    if (event.key === "ArrowDown") {
        event.preventDefault();
        const nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
        items[nextIndex].focus();
        return;
    }

    if (event.key === "ArrowUp") {
        event.preventDefault();
        const previousIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
        items[previousIndex].focus();
    }
}

function initDropdown(dropdown) {
    if (dropdown.dataset.dropdownBound === "true") return;

    const { button, menu, arrow } = getDropdownParts(dropdown);
    if (!button || !menu) return;

    if (!menu.id) {
        menu.id = `dropdown-menu-${Math.random().toString(36).slice(2, 11)}`;
    }

    button.setAttribute("aria-controls", menu.id);
    button.setAttribute("aria-expanded", "false");
    button.setAttribute("aria-haspopup", "true");
    dropdown.dataset.open = "false";

    if (arrow) {
        arrow.classList.add("rotate-0");
    }

    CLOSED_MENU_CLASSES.forEach((className) => menu.classList.add(className));
    OPEN_MENU_CLASSES.forEach((className) => menu.classList.remove(className));

    button.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();

        const willOpen = dropdown.dataset.open !== "true";
        closeAllDropdowns(willOpen ? dropdown : null);
        setDropdownState(dropdown, willOpen);

        if (willOpen) {
            requestAnimationFrame(() => focusFirstItem(dropdown));
        }
    });

    button.addEventListener("keydown", (event) => {
        if (event.key !== "ArrowDown" && event.key !== "Enter" && event.key !== " ") {
            return;
        }

        event.preventDefault();
        closeAllDropdowns(dropdown);
        setDropdownState(dropdown, true);
        requestAnimationFrame(() => focusFirstItem(dropdown));
    });

    menu.addEventListener("keydown", (event) => handleMenuKeydown(event, dropdown));

    menu.addEventListener("click", (event) => {
        if (event.target.closest("a, button")) {
            setDropdownState(dropdown, false);
        }
    });

    dropdown.dataset.dropdownBound = "true";
}

function initDropdowns() {
    document.querySelectorAll("[data-dropdown]").forEach(initDropdown);
}

if (!window.__dropdownsGlobalBound) {
    document.addEventListener("click", (event) => {
        if (!event.target.closest("[data-dropdown]")) {
            closeAllDropdowns();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key !== "Escape") return;

        const openDropdown = document.querySelector('[data-dropdown][data-open="true"]');
        if (!openDropdown) return;

        const { button } = getDropdownParts(openDropdown);
        closeAllDropdowns();
        button?.focus();
    });

    window.__dropdownsGlobalBound = true;
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initDropdowns, { once: true });
} else {
    initDropdowns();
}
