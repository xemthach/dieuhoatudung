// All comparison controls share one request and state-update contract.
let pending = false;

window.updateComparison = async (url, body) => {
    if (pending) return null;
    pending = true;
    window.dispatchEvent(new CustomEvent('compare:busy', { detail: true }));
    try {
        const response = await window.csrfFetch(url, { method: 'POST', body, reloadOnExpired: false });
        const data = await response.json();
        if (!response.ok || !data.success || !Array.isArray(data.items)) {
            throw new Error(data.message || 'Không thể cập nhật so sánh. Vui lòng thử lại.');
        }
        window.dispatchEvent(new CustomEvent('compare:updated', { detail: data.items }));
        return data;
    } finally {
        pending = false;
        window.dispatchEvent(new CustomEvent('compare:busy', { detail: false }));
    }
};
