function initInfinitePagination(root) {
    if (!root) return;

    const sentinel = root.querySelector("[data-infinite-pagination-sentinel]");
    const button = root.querySelector("[data-infinite-pagination-button]");

    if (!sentinel || !button || !("IntersectionObserver" in window)) {
        return;
    }

    let pending = false;

    const maybeLoadMore = () => {
        if (root.dataset.hasMore !== "true") return;
        if (pending || button.disabled) return;

        pending = true;
        button.click();
    };

    const observer = new IntersectionObserver(
        (entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                maybeLoadMore();
            }
        },
        {
            rootMargin: "320px 0px 320px 0px",
        },
    );

    observer.observe(sentinel);

    const disabledObserver = new MutationObserver(() => {
        if (!button.disabled) {
            pending = false;
        }
    });

    disabledObserver.observe(button, {
        attributes: true,
        attributeFilter: ["disabled"],
    });

    button.addEventListener("click", () => {
        pending = true;
    });
}

window.initInfinitePagination = initInfinitePagination;
