class Toast {
    constructor() {
        this.container = null;
        this.init();
    }

    init() {
        if (!this.container) {
            this.container = $("<div>")
                .attr("id", "toast-container")
                .addClass(
                    "fixed top-5 right-5 z-[10000] flex flex-col gap-[10px]",
                );
            $("body").append(this.container);

            this.container
                .off("click.toast")
                .on("click.toast", "[data-toast-close]", (e) => {
                    e.preventDefault();
                    const toastId = $(e.currentTarget).attr("data-toast-close");
                    this.close(toastId);
                });
        }
    }

    show(message, type = "info", duration = 5000) {
        const types = {
            success: { tone: "success", icon: "✓" },
            error: { tone: "error", icon: "✗" },
            warning: { tone: "warning", icon: "⚠" },
            info: { tone: "info", icon: "ℹ" },
        };

        const config = types[type] || types.info;
        const toastId =
            "toast-" +
            Date.now() +
            "-" +
            Math.random().toString(36).substr(2, 9);

        const toast = $("<div>")
            .attr("id", toastId)
            .attr("data-tone", config.tone)
            .addClass(
                "theme-toast px-6 py-4 rounded-lg flex items-center gap-3 min-w-[300px] max-w-[500px] transform transition-all duration-300 opacity-0 translate-x-full",
            ).html(`
                <span class="text-xl font-bold">${config.icon}</span>
                <span class="flex-1 text-sm font-medium">${message}</span>
                <button type="button" class="theme-toast-close font-bold text-lg leading-none" data-toast-close="${toastId}">&times;</button>
            `);

        this.container.append(toast);

        setTimeout(() => {
            toast.removeClass("opacity-0 translate-x-full");
        }, 10);

        if (duration > 0) {
            setTimeout(() => {
                this.close(toastId);
            }, duration);
        }

        return toastId;
    }

    close(toastId) {
        const toast = $(`#${toastId}`);
        if (toast.length) {
            toast.addClass("opacity-0 translate-x-full");
            setTimeout(() => {
                toast.remove();
            }, 300);
        }
    }

    success(message, duration) {
        return this.show(message, "success", duration);
    }

    error(message, duration) {
        return this.show(message, "error", duration);
    }

    warning(message, duration) {
        return this.show(message, "warning", duration);
    }

    info(message, duration) {
        return this.show(message, "info", duration);
    }
}

if (typeof window !== "undefined") {
    window.toast = window.toast || new Toast();
}

export default Toast;
