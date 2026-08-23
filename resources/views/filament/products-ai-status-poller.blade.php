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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.35rem;
            height: 1.35rem;
            margin-left: .25rem;
            border: 1px solid rgba(217, 119, 6, .22);
            border-radius: 999px;
            background: rgba(251, 191, 36, .08);
            color: rgb(180, 83, 9);
            cursor: pointer;
            font-size: .78rem;
            font-weight: 600;
            line-height: 1;
        }

        .ai-inline-retry:disabled {
            cursor: wait;
            opacity: .6;
        }

        .ai-status-dot {
            width: .42rem;
            height: .42rem;
            margin-right: .35rem;
            border-radius: 999px;
            background: currentColor;
            opacity: .8;
        }

        [data-ai-refresh-button][aria-busy="true"] svg {
            animation: ai-status-spin .8s linear infinite;
        }

        @keyframes ai-status-spin {
            to { transform: rotate(360deg); }
        }

        [data-ai-queue-widget] {
            min-width: auto !important;
        }

        [data-ai-queue-widget] .fi-btn-label::before {
            content: '';
            display: inline-block;
            width: .5rem;
            height: .5rem;
            margin-right: .45rem;
            border-radius: 999px;
            background: currentColor;
            opacity: .85;
        }

        .fi-header {
            row-gap: .75rem;
        }

        .fi-header-heading {
            max-width: 46rem;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            font-size: clamp(1.45rem, 2.1vw, 1.9rem) !important;
            line-height: 1.14 !important;
            letter-spacing: 0 !important;
        }

        .fi-breadcrumbs {
            margin-bottom: .2rem;
            font-size: .8rem;
            opacity: .78;
        }

        .fi-header-actions {
            gap: .5rem !important;
        }

        @media (max-width: 768px) {
            .fi-header-heading {
                max-width: 100%;
                font-size: 1.35rem !important;
            }

            .fi-header-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
    <script>
        (() => {
            if (window.ProductAiStatusPoller) {
                return;
            }

            const endpoint = @json(route('admin.products.ai-status'));
            const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const activeStatuses = new Set(['queued', 'processing', 'validating', 'retrying', 'interrupted']);
            let timer = null;
            let inflight = false;

            const visibleIds = () => Array.from(document.querySelectorAll('[data-ai-field="ai_status"][data-ai-product-id]'))
                .map((cell) => cell.getAttribute('data-ai-product-id'))
                .filter(Boolean)
                .filter((id, index, ids) => ids.indexOf(id) === index);

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
                    button.title = loading ? 'Refreshing AI status...' : 'Refresh AI status';
                });
            };

            const renderQueue = (health) => {
                const widget = document.querySelector('[data-ai-queue-widget]');
                if (! widget || ! health) return;

                const label = widget.querySelector('.fi-btn-label') || widget;
                const disabled = health.desired_state === 'DISABLED';
                const online = !!health.worker_online;
                const failed = Number(health.failed_jobs || 0);
                const pending = Number(health.pending_jobs || 0);
                const processing = Number(health.processing_jobs || 0);
                const warning = online && (pending > 0 || processing > 0);
                label.textContent = disabled ? 'AI worker: Đang tắt' : (online ? 'AI worker: Online' : 'AI worker: Offline');
                widget.dataset.aiQueueOnline = online ? '1' : '0';
                widget.title = [
                    disabled ? 'Worker được operator chủ động tắt' : (online ? 'AI queue worker online' : 'AI queue worker offline'),
                    `Pending: ${pending}`,
                    `Running: ${processing}`,
                    `Failed: ${failed}`,
                ].join('\n');
                widget.style.borderColor = disabled ? 'rgb(229, 231, 235)' : !online ? 'rgb(254, 202, 202)' : warning ? 'rgb(253, 230, 138)' : 'rgb(187, 247, 208)';
                widget.style.backgroundColor = disabled ? 'rgb(249, 250, 251)' : !online ? 'rgb(254, 242, 242)' : warning ? 'rgb(255, 251, 235)' : 'rgb(240, 253, 244)';
                widget.style.color = disabled ? 'rgb(75, 85, 99)' : !online ? 'rgb(185, 28, 28)' : warning ? 'rgb(180, 83, 9)' : 'rgb(22, 101, 52)';
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
                const tone = product.status?.color || 'gray';
                const pulse = activeStatuses.has(product.ai_status) ? ' ai-status-pulse' : '';
                const statusTitle = [product.warning, product.safe_reason, `Cập nhật: ${product.updated_human}`].filter(Boolean).join('\n');
                const retry = product.retry_allowed && product.retry_url
                    ? `<button class="ai-inline-retry" type="button" title="Thử lại" aria-label="Thử lại" data-ai-retry-url="${product.retry_url}" data-ai-retry-product="${product.id}">&#8635;</button>`
                    : '';
                const progress = product.progress
                    ? `<div class="ai-status-progress" title="${product.progress.processed} / ${product.progress.total} sản phẩm"><span style="width:${Math.max(0, Math.min(100, product.progress.percent))}%"></span></div>`
                    : '';

                updateCell(product.id, 'ai_status',
                    `<span class="${pulse}" style="display:inline-flex;align-items:center;border-radius:999px;padding:2px 7px;font-size:12px;font-weight:500;${badgeClass(tone)}"><span class="ai-status-dot"></span>${product.ai_status_label}</span>${retry}${progress}`,
                    statusTitle
                );
                updateCell(product.id, 'seo_score',
                    `<span style="display:inline-flex;border-radius:6px;padding:2px 6px;font-size:12px;font-weight:500;${badgeClass(product.seo_score >= 85 ? 'success' : product.seo_score >= 70 ? 'info' : product.seo_score > 0 ? 'warning' : 'gray')}">${product.seo_score}</span>`
                );
                updateCell(product.id, 'last_ai_run', product.updated_human || '-');
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
                        button.innerHTML = '&#8230;';

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
                    timer = setTimeout(() => window.ProductAiStatusPoller.refreshNow(), 10000);
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
