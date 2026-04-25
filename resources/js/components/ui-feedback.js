const ALERT_HIDE_DURATION_MS = 300;
const DEFAULT_COPY_FEEDBACK_MS = 2000;

function setCopyFeedbackState(button, copied) {
    const defaultLabel = button.querySelector("[data-copy-default]");
    const successLabel = button.querySelector("[data-copy-success]");

    if (defaultLabel) {
        defaultLabel.classList.toggle("hidden", copied);
    }

    if (successLabel) {
        successLabel.classList.toggle("hidden", !copied);
    }
}

async function writeTextToClipboard(text) {
    if (!text) {
        return false;
    }

    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (_error) {
            // Fall through to the legacy textarea fallback below.
        }
    }

    const textarea = document.createElement("textarea");
    textarea.value = text;
    textarea.setAttribute("readonly", "readonly");
    textarea.style.position = "fixed";
    textarea.style.opacity = "0";
    textarea.style.pointerEvents = "none";

    document.body.appendChild(textarea);
    textarea.select();

    let copied = false;

    try {
        copied = document.execCommand("copy");
    } catch (_error) {
        copied = false;
    }

    document.body.removeChild(textarea);

    return copied;
}

async function handleCopyButton(button) {
    if (button.dataset.copyBusy === "true") {
        return;
    }

    button.dataset.copyBusy = "true";

    const copied = await writeTextToClipboard(button.dataset.copyText ?? "");
    setCopyFeedbackState(button, copied);

    if (copied) {
        const feedbackMs = Number.parseInt(
            button.dataset.copyFeedbackMs ?? `${DEFAULT_COPY_FEEDBACK_MS}`,
            10,
        );

        window.setTimeout(() => {
            setCopyFeedbackState(button, false);
            button.dataset.copyBusy = "false";
        }, Number.isFinite(feedbackMs) ? feedbackMs : DEFAULT_COPY_FEEDBACK_MS);

        return;
    }

    button.dataset.copyBusy = "false";
}

function dismissAlert(alert) {
    if (!alert || alert.dataset.alertDismissing === "true") {
        return;
    }

    alert.dataset.alertDismissing = "true";
    alert.classList.add("opacity-0", "pointer-events-none");

    window.setTimeout(() => {
        alert.hidden = true;
    }, ALERT_HIDE_DURATION_MS);
}

function initAlert(alert) {
    if (alert.dataset.alertInit === "true") {
        return;
    }

    alert.dataset.alertInit = "true";

    const autoDismissMs = Number.parseInt(alert.dataset.autoDismissMs ?? "", 10);
    if (!Number.isFinite(autoDismissMs)) {
        return;
    }

    window.setTimeout(() => {
        dismissAlert(alert);
    }, autoDismissMs);
}

function initInfinitePaginationRoots(root) {
    if (!(root instanceof Element || root instanceof Document)) {
        return;
    }

    const targets = [];

    if (root instanceof Element && root.matches("[data-infinite-pagination]")) {
        targets.push(root);
    }

    root.querySelectorAll?.("[data-infinite-pagination]").forEach((element) => {
        targets.push(element);
    });

    targets.forEach((element) => {
        if (element.dataset.infinitePaginationInit === "true") {
            return;
        }

        element.dataset.infinitePaginationInit = "true";

        if (typeof window.initInfinitePagination === "function") {
            window.initInfinitePagination(element);
        }
    });
}

function initAlerts(root) {
    if (!(root instanceof Element || root instanceof Document)) {
        return;
    }

    if (root instanceof Element && root.matches("[data-alert-surface]")) {
        initAlert(root);
    }

    root.querySelectorAll?.("[data-alert-surface]").forEach(initAlert);
}

function initUiFeedback(root = document) {
    initAlerts(root);
    initInfinitePaginationRoots(root);
}

if (!window.__uiFeedbackClickBound) {
    document.addEventListener("click", (event) => {
        const copyButton = event.target.closest("[data-copy-button]");
        if (copyButton) {
            event.preventDefault();
            void handleCopyButton(copyButton);
            return;
        }

        const dismissButton = event.target.closest("[data-alert-dismiss]");
        if (dismissButton) {
            event.preventDefault();
            dismissAlert(dismissButton.closest("[data-alert-surface]"));
        }
    });

    window.__uiFeedbackClickBound = true;
}

if (!window.__uiFeedbackObserverBound) {
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node instanceof Element) {
                    initUiFeedback(node);
                }
            });
        });
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true,
    });

    window.__uiFeedbackObserverBound = true;
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => initUiFeedback(document), {
        once: true,
    });
} else {
    initUiFeedback(document);
}

window.initUiFeedback = initUiFeedback;
