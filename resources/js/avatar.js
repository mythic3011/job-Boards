let pendingChange = null;
let avatarSyncScheduled = false;

function getAvatarFallbackElement(img) {
    if (!img) return null;

    const fallbackId = img.getAttribute("data-avatar-fallback-id");
    if (!fallbackId) return null;

    return document.getElementById(fallbackId);
}

function showAvatarImage(img) {
    if (!img) return;

    const fallback = getAvatarFallbackElement(img);

    img.classList.remove("hidden");
    if (fallback) fallback.classList.add("hidden");
}

function showAvatarFallbackForImage(img) {
    if (!img) return;

    const fallback = getAvatarFallbackElement(img);

    img.classList.add("hidden");
    if (fallback) fallback.classList.remove("hidden");
}

function showAvatarFallback(inputId) {
    if (!inputId) return;
    const fallback = document.getElementById(`${inputId}-fallback-avatar`);
    const image = document.getElementById(`${inputId}-current-image`);

    if (image) image.classList.add("hidden");
    if (fallback) fallback.classList.remove("hidden");
}

function handleAvatarSelect(input) {
    const file = input.files[0];

    if (!file) return;

    const allowedTypes = [
        "image/jpeg",
        "image/jpg",
        "image/png",
        "image/webp",
        "image/gif",
    ];
    if (!allowedTypes.includes(file.type.toLowerCase())) {
        showToast(
            "Please select a valid image file (JPG, PNG, WebP, or GIF)",
            "error",
        );
        input.value = "";
        return;
    }

    if (file.size > 2097152) {
        showToast("File size must be less than 2MB", "error");
        input.value = "";
        return;
    }

    showAvatarPreview(file, input);
}

function showAvatarPreview(file, input) {
    const reader = new FileReader();
    reader.onload = (e) => {
        const container = document.getElementById(`${input.id}-container`);
        const current = document.getElementById(`${input.id}-current-avatar`);
        const actions = document.getElementById(`${input.id}-actions`);

        if (container && current) {
            let preview = document.getElementById(`${input.id}-preview-avatar`);
            if (!preview) {
                preview = document.createElement("img");
                preview.id = `${input.id}-preview-avatar`;
                preview.alt = "Preview";
                preview.className =
                    "w-full h-full object-cover absolute inset-0 rounded-full avatar-preview avatar-preview-ready";
                container.appendChild(preview);
            }

            preview.src = e.target.result;
            preview.classList.add(
                "avatar-preview-ready",
                "avatar-preview-layer-base",
            );
            preview.classList.remove("avatar-preview-layer-top");
            container.classList.add("avatar-container-previewing");
            current.classList.add("avatar-current-dimmed");
            if (actions) {
                actions.classList.remove("opacity-0");
                actions.classList.add("opacity-100");
            }
            pendingChange = {
                inputId: input.id,
                file,
                dataUrl: e.target.result,
            };
            showToast("Photo ready to save", "info");
        }
    };

    reader.onerror = () => {
        showToast("Error reading file. Please try again.", "error");
        input.value = "";
    };

    reader.readAsDataURL(file);
}

function confirmAvatarChange(inputId) {
    if (!pendingChange || pendingChange.inputId !== inputId)
        return showToast("No changes to save", "warning");

    const $preview = document.getElementById(`${inputId}-preview-avatar`);
    const $current = document.getElementById(`${inputId}-current-avatar`);
    const $actions = document.getElementById(`${inputId}-actions`);
    const $success = document.getElementById(`${inputId}-success-indicator`);

    if ($preview && $current) {
        $preview.classList.add("avatar-preview-layer-top");
        $preview.classList.remove("avatar-preview-layer-base");
        $current.classList.remove("avatar-current-dimmed");
    }

    if ($actions) {
        $actions.classList.add("opacity-0");
        $actions.classList.remove("opacity-100");
    }

    if ($success) {
        $success.classList.remove("hidden");
        $success.classList.add("flex");
    }

    pendingChange = null;
    showToast(
        "Profile photo updated! Remember to save your changes.",
        "success",
    );
}

function cancelAvatarChange(inputId) {
    const $input = document.getElementById(inputId);
    const $preview = document.getElementById(`${inputId}-preview-avatar`);
    const $current = document.getElementById(`${inputId}-current-avatar`);
    const $actions = document.getElementById(`${inputId}-actions`);

    if ($input) $input.value = "";
    if ($preview) $preview.remove();
    if ($current) $current.classList.remove("avatar-current-dimmed");
    const $container = document.getElementById(`${inputId}-container`);
    if ($container) $container.classList.remove("avatar-container-previewing");
    if ($actions) {
        $actions.classList.add("opacity-0");
        $actions.classList.remove("opacity-100");
    }

    pendingChange = null;
    showToast("Changes cancelled", "info");
}

function resetAvatarState(input) {
    const $preview = document.getElementById(`${input.id}-preview-avatar`);
    const $current = document.getElementById(`${input.id}-current-avatar`);
    const $actions = document.getElementById(`${input.id}-actions`);
    const $success = document.getElementById(`${input.id}-success-indicator`);
    const $container = document.getElementById(`${input.id}-container`);

    if ($preview) $preview.remove();
    if ($current) $current.classList.remove("avatar-current-dimmed");
    if ($container) $container.classList.remove("avatar-container-previewing");
    if ($actions) {
        $actions.classList.add("opacity-0");
        $actions.classList.remove("opacity-100");
    }
    if ($success) {
        $success.classList.add("hidden");
        $success.classList.remove("flex");
    }
}

function removeCurrentAvatar(inputId) {
    if (!confirm("Are you sure you want to remove your profile photo?")) return;
    const form = document.createElement("form");
    form.method = "POST";
    form.action = "/profile/image";
    form.classList.add("hidden");
    const methodInput = document.createElement("input");
    methodInput.type = "hidden";
    methodInput.name = "_method";
    methodInput.value = "DELETE";
    const tokenMeta = document.querySelector('meta[name="csrf-token"]');
    const tokenInput = document.createElement("input");
    tokenInput.type = "hidden";
    tokenInput.name = "_token";
    tokenInput.value = tokenMeta ? tokenMeta.getAttribute("content") : "";
    form.appendChild(methodInput);
    form.appendChild(tokenInput);
    document.body.appendChild(form);
    showToast("Removing profile photo...", "info");
    form.submit();
}

// Legacy compatibility
const handleFileSelect = handleAvatarSelect,
    clearFilePreview = cancelAvatarChange,
    changeImage = (inputId) => document.getElementById(inputId)?.click();

function showToast(message, type = "info") {
    if (window.toast) {
        window.toast.show(message, type);
    } else {
        console.log(`Toast (${type}): ${message}`);
    }
}

function syncExistingAvatarImage(img) {
    if (!img?.complete) return;

    if (img.naturalWidth > 0) {
        showAvatarImage(img);
        return;
    }

    showAvatarFallbackForImage(img);
}

function syncAllAvatarImages() {
    document.querySelectorAll("[data-avatar-image]").forEach(syncExistingAvatarImage);
}

function scheduleAvatarSync() {
    if (avatarSyncScheduled) return;

    avatarSyncScheduled = true;
    requestAnimationFrame(() => {
        avatarSyncScheduled = false;
        syncAllAvatarImages();
    });
}

function initLivewireAvatarSync() {
    document.addEventListener("livewire:init", () => {
        scheduleAvatarSync();

        const livewire = window.Livewire;
        if (!livewire?.hook) return;

        // Livewire morphs can reinsert avatar images with "hidden" class
        // while browsers skip firing "load" for cached URLs.
        livewire.hook("morph.updated", () => {
            scheduleAvatarSync();
        });
    });

    document.addEventListener("livewire:navigated", () => {
        scheduleAvatarSync();
    });
}

// Delegated event listeners — replaces inline on* handlers
document.addEventListener("change", (e) => {
    const input = e.target.closest("[data-avatar-input]");
    if (input) handleAvatarSelect(input);
});

document.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-avatar-action]");
    if (!btn) return;
    const action = btn.dataset.avatarAction;
    const inputId = btn.dataset.avatarTarget;
    if (action === "confirm") confirmAvatarChange(inputId);
    else if (action === "cancel") cancelAvatarChange(inputId);
    else if (action === "remove") removeCurrentAvatar(inputId);
});

// Capture image load errors so broken/missing avatars fall back to initials.
document.addEventListener(
    "load",
    (e) => {
        const img = e.target.closest?.("[data-avatar-image]");
        if (!img) return;
        showAvatarImage(img);
    },
    true,
);

document.addEventListener(
    "error",
    (e) => {
        const img = e.target.closest?.("[data-avatar-image]");
        if (!img) return;
        showAvatarFallbackForImage(img);
        showAvatarFallback(img.dataset.avatarInputId);
    },
    true,
);

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", scheduleAvatarSync, { once: true });
} else {
    scheduleAvatarSync();
}

initLivewireAvatarSync();
