@if(request()->is('admin/products*'))
    <style>
        .ai-status-pulse {
            animation: ai-status-pulse 1.2s ease-in-out infinite;
        }

        @keyframes ai-status-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .55; }
        }

        .ai-status-progress {
            height: 3px;
            margin-top: 4px;
            overflow: hidden;
            border-radius: 999px;
            background: rgba(59, 130, 246, .18);
        }

        .ai-status-progress > span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: rgb(37, 99, 235);
            transition: width .25s ease;
        }

        .ai-inline-retry {
            margin-left: .35rem;
            border: 0;
            background: transparent;
            color: rgb(217, 119, 6);
            cursor: pointer;
            font-size: .72rem;
            font-weight: 600;
        }

        .ai-inline-retry:disabled {
            cursor: wait;
            opacity: .6;
        }
    </style>
    <script>
        (() => {
            if (window.ProductAiStatusPoller) {
                return;
            }

            const endpoint = @json(route('admin.products.ai-status'));
            const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const activeStatuses = new Set(['queued', 'processing', 'retrying', 'stuck']);
            const terminalStatuses = new Set(['completed', 'completed_verified', 'completed_with_warnings', 'needs_review', 'failed', 'blocked', 'cancelled', 'not_generated']);
            let timer = null;
            let inflight = false;

            const visibleIds = () => Array.from(document.querySelectorAll('[data-ai-field="ai_status"][data-ai-product-id]'))
                .map((cell) => cell.getAttribute('data-ai-product-id'))
                .filter(Boolean)
                .filter((id, index, ids) => ids.indexOf(id) === index);

            const textColor = (status) => {
                if (['completed', 'completed_verified', 'completed_with_warnings'].includes(status)) return 'success';
                if (['queued', 'processing', 'retrying'].includes(status)) return 'info';
                if (status === 'needs_review') return 'warning';
                if (['failed', 'blocked', 'stuck', 'cancelled'].includes(status)) return 'danger';
                return 'gray';
            };

            const badgeClass = (tone) => ({
                success: 'background:#dcfce7;color:#15803d;border:1px solid #bbf7d0',
                info: 'background:#dbeafe;color:#2563eb;border:1px solid #bfdbfe',
                warning: 'background:#fef3c7;color:#d97706;border:1px solid #fde68a',
                danger: 'background:#fee2e2;color:#dc2626;border:1px solid #fecaca',
                gray: 'background:#f3f4f6;color:#4b5563;border:1px solid #e5e7eb',
            }[tone] || 'background:#f3f4f6;color:#4b5563;border:1px solid #e5e7eb');

            const setButtonLoading = (loading) => {
                document.querySelectorAll('[data-ai-refresh-button]').forEach((button) => {
                    button.toggleAttribute('disabled', loading);
                    button.setAttribute('aria-busy', loading ? 'true' : 'false');
                    const label = button.querySelector('.fi-btn-label') || button;
                    label.textContent = loading ? 'Refreshing...' : 'Refresh AI Status';
                });
            };

            const renderQueue = (health) => {
                const widget = document.querySelector('[data-ai-queue-widget]');
                if (! widget || ! health) return;

                const label = widget.querySelector('.fi-btn-label') || widget;
                const online = !!health.worker_online;
                label.textContent = online
                    ? `AI Queue: Online | P ${health.pending_jobs} | Run ${health.processing_jobs} | Fail ${health.failed_jobs}`
                    : `AI Queue: Offline | P ${health.pending_jobs} | Run ${health.processing_jobs} | Fail ${health.failed_jobs}`;
                widget.dataset.aiQueueOnline = online ? '1' : '0';
                widget.title = online ? 'AI queue worker online' : 'AI queue worker offline';
                widget.style.borderColor = online ? 'rgb(187, 247, 208)' : 'rgb(254, 202, 202)';
                widget.style.backgroundColor = online ? 'rgb(240, 253, 244)' : 'rgb(254, 242, 242)';
                widget.style.color = online ? 'rgb(22, 101, 52)' : 'rgb(185, 28, 28)';
            };

            const updateCell = (productId, field, html, title = '') => {
                document.querySelectorAll(`[data-ai-product-id="${productId}"][data-ai-field="${field}"]`).forEach((cell) => {
                    cell.innerHTML = html;
                    if (title) {
                        cell.setAttribute('title', title);
                    } else {
                        cell.removeAttribute('title');
                    }
                });
            };

            const renderStatus = (product) => {
                const tone = textColor(product.ai_status);
                const pulse = activeStatuses.has(product.ai_status) ? ' ai-status-pulse' : '';
                const failedTitle = [product.failed_reason, product.last_error_message].filter(Boolean).join('\n');
                const retry = ['failed', 'stuck', 'cancelled'].includes(product.ai_status)
                    ? `<button class="ai-inline-retry" type="button" data-ai-retry-url="${product.retry_url}" data-ai-retry-product="${product.id}">Retry</button>`
                    : '';
                const progress = product.progress_percent !== null && product.progress_percent !== undefined
                    ? `<div class="ai-status-progress"><span style="width:${Math.max(0, Math.min(100, product.progress_percent))}%"></span></div>`
                    : '';

                updateCell(product.id, 'ai_status',
                    `<span class="${pulse}" style="display:inline-flex;align-items:center;border-radius:6px;padding:2px 6px;font-size:12px;font-weight:500;${badgeClass(tone)}">${product.ai_status_label || product.ai_status}</span>${retry}${progress}`,
                    failedTitle
                );
                updateCell(product.id, 'seo_score',
                    `<span style="display:inline-flex;border-radius:6px;padding:2px 6px;font-size:12px;font-weight:500;${badgeClass(product.seo_score >= 85 ? 'success' : product.seo_score >= 70 ? 'info' : product.seo_score > 0 ? 'warning' : 'gray')}">${product.seo_score}</span>`
                );
                updateCell(product.id, 'last_ai_run', product.last_ai_run || '-');
                updateCell(product.id, 'warnings_count',
                    `<span style="display:inline-flex;border-radius:6px;padding:2px 6px;font-size:12px;font-weight:500;${badgeClass(product.warnings_count > 0 ? 'warning' : 'success')}">${product.warnings_count}</span>`,
                    product.warnings_count > 0 ? 'Click AI details để xem warnings' : ''
                );
            };

            const bindInlineRetry = () => {
                document.querySelectorAll('[data-ai-retry-url]:not([data-ai-bound])').forEach((button) => {
                    button.dataset.aiBound = '1';
                    button.addEventListener('click', async (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        button.disabled = true;
                        button.textContent = 'Retrying...';

                        try {
                            await fetch(button.dataset.aiRetryUrl, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrf(),
                                },
                            });
                        } finally {
                            await window.ProductAiStatusPoller.refreshNow();
                        }
                    });
                });
            };

            const schedule = (shouldContinue) => {
                clearTimeout(timer);
                timer = null;
                if (shouldContinue) {
                    timer = setTimeout(() => window.ProductAiStatusPoller.refreshNow(), 5000);
                }
            };

            window.ProductAiStatusPoller = {
                refreshNow: async () => {
                    if (inflight) return;
                    const ids = visibleIds();
                    if (!ids.length) {
                        schedule(false);
                        return;
                    }

                    inflight = true;
                    setButtonLoading(true);

                    try {
                        const url = new URL(endpoint, window.location.origin);
                        url.searchParams.set('ids', ids.join(','));
                        const response = await fetch(url, {
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json' },
                        });

                        if (!response.ok) return;
                        const data = await response.json();
                        (data.products || []).forEach(renderStatus);
                        renderQueue(data.queue_health);
                        bindInlineRetry();

                        const hasActiveRows = (data.products || []).some((product) => activeStatuses.has(product.ai_status));
                        schedule(hasActiveRows || !!data.auto_refresh?.should_continue);
                    } finally {
                        inflight = false;
                        setButtonLoading(false);
                    }
                },
                stop: () => schedule(false),
            };

            document.addEventListener('click', (event) => {
                if (event.target.closest('[data-ai-refresh-button], [data-ai-queue-widget]')) {
                    event.preventDefault();
                    window.ProductAiStatusPoller.refreshNow();
                }
            });

            document.addEventListener('DOMContentLoaded', () => window.ProductAiStatusPoller.refreshNow());
            document.addEventListener('livewire:navigated', () => window.ProductAiStatusPoller.refreshNow());
            setTimeout(() => window.ProductAiStatusPoller.refreshNow(), 800);
        })();
    </script>
@endif
