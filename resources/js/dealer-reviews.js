document.addEventListener('submit', async event => {
    const form = event.target.closest('[data-dealer-review-form]');
    if (!form || typeof window.csrfFetch !== 'function') return;
    event.preventDefault();
    if (form.dataset.pending === 'true' || form.dataset.completed === 'true') return;
    form.dataset.pending = 'true';
    const button = form.querySelector('button[type="submit"]');
    const error = form.querySelector('[data-review-error]');
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    error.hidden = true;
    form.querySelectorAll('[data-field-error]').forEach(element => { element.textContent = ''; });
    try {
        const response = await window.csrfFetch(form.action, { method: 'POST', body: new FormData(form), reloadOnExpired: false });
        const data = await response.json();
        if (!response.ok) {
            Object.entries(data.errors || {}).forEach(([field, messages]) => {
                const element = [...form.querySelectorAll('[data-field-error]')].find(item => item.dataset.fieldError === field);
                if (element) element.textContent = messages.join(' ');
            });
            throw new Error(response.status === 429 ? 'Bạn đã gửi quá nhiều đánh giá. Vui lòng thử lại sau.' : 'Chưa thể gửi đánh giá. Vui lòng kiểm tra thông tin và thử lại.');
        }
        const success = form.closest('[data-dealer-review-panel]').querySelector('[data-review-success]');
        success.textContent = data.message;
        success.hidden = false;
        form.dataset.completed = 'true';
        form.remove();
    } catch (failure) {
        error.textContent = failure.message || 'Không thể kết nối. Vui lòng thử lại.';
        error.hidden = false;
    } finally {
        form.dataset.pending = 'false';
        button.disabled = false;
        button.setAttribute('aria-busy', 'false');
    }
});
