let lastTrackedPageSignature = null;

export function trackCurrentPageVisit() {
    const trackerElement = document.querySelector("[data-page-visit-tracker]");

    if (!(trackerElement instanceof HTMLElement)) {
        return;
    }

    const signature = trackerElement.dataset.pageVisitSignature;

    if (!signature || signature === lastTrackedPageSignature) {
        return;
    }

    lastTrackedPageSignature = signature;

    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");

    if (!csrfToken) {
        return;
    }

    window.fetch(trackerElement.dataset.endpoint ?? "/page-visits", {
        method: "POST",
        credentials: "same-origin",
        keepalive: true,
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-TOKEN": csrfToken,
            "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify({
            page_key: trackerElement.dataset.pageKey,
            signature: signature,
        }),
    }).catch(() => {
        lastTrackedPageSignature = null;
    });
}
