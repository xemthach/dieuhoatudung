/**
 * Frontend JS Foundation — Điều Hòa Tủ Đứng
 * Lightweight, vanilla JS. No heavy frameworks.
 */

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);
window.Alpine = Alpine;
Alpine.start();

// ==========================================
// CSRF Token Management & 419 Recovery
// ==========================================

/**
 * Get current CSRF token from meta tag.
 */
window.getCsrfToken = () => {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
};

/**
 * Refresh CSRF token from server (used when session expires).
 * Returns the new token or empty string on failure.
 */
window.refreshCsrfToken = async () => {
    try {
        const res = await fetch('/csrf-token', {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        });
        if (res.ok) {
            const data = await res.json();
            const newToken = data.token || '';
            // Update meta tag
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta && newToken) {
                meta.setAttribute('content', newToken);
            }
            // Update all hidden _token inputs in forms
            document.querySelectorAll('input[name="_token"]').forEach(input => {
                input.value = newToken;
            });
            return newToken;
        }
    } catch (e) {
        console.warn('[CSRF] Failed to refresh token:', e);
    }
    return '';
};

/**
 * CSRF-safe fetch wrapper with automatic 419 recovery.
 *
 * Usage:
 *   const res = await csrfFetch('/api/compare/add', {
 *       method: 'POST',
 *       body: JSON.stringify({ slug: 'xxx' })
 *   });
 *
 * Automatically:
 *   1. Adds X-CSRF-TOKEN header
 *   2. On 419 → refreshes token → retries ONCE
 *   3. On second 419 → prompts user to reload
 */
window.csrfFetch = async (url, options = {}) => {
    const headers = {
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
        ...(options.headers || {})
    };

    // Add Content-Type for JSON body (not FormData)
    if (options.body && typeof options.body === 'string') {
        headers['Content-Type'] = headers['Content-Type'] || 'application/json';
    }

    let res = await fetch(url, { ...options, headers, credentials: 'same-origin' });

    // Handle 419 CSRF token mismatch — refresh and retry once
    if (res.status === 419) {
        console.warn('[CSRF] Token expired, refreshing...');
        const newToken = await refreshCsrfToken();
        if (newToken) {
            headers['X-CSRF-TOKEN'] = newToken;

            // If body is FormData, update _token field
            if (options.body instanceof FormData) {
                options.body.set('_token', newToken);
            }

            res = await fetch(url, { ...options, headers, credentials: 'same-origin' });
        }

        // Still 419 after refresh → session completely dead
        if (res.status === 419) {
            // Show a friendly toast instead of crude alert
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 left-1/2 -translate-x-1/2 z-[99999] rounded-xl bg-surface-800 px-6 py-3 text-sm font-medium text-white shadow-2xl transition-all';
            toast.textContent = 'Phiên làm việc đã hết hạn. Trang sẽ được tải lại...';
            document.body.appendChild(toast);
            setTimeout(() => location.reload(), 1500);
            return res;
        }
    }

    return res;
};


// ==========================================
// Mobile Menu Toggle
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
    const menuBtn = document.querySelector('[aria-label="Mở menu"]');
    const mobileMenu = document.getElementById('mobile-menu');

    if (menuBtn && mobileMenu) {
        menuBtn.addEventListener('click', () => {
            const isHidden = mobileMenu.classList.contains('hidden');
            mobileMenu.classList.toggle('hidden');
            menuBtn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
        });
    }
});

// ==========================================
// Scroll-triggered Fade-in Animation
// ==========================================
if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fade-in-up');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.1, rootMargin: '0px 0px -40px 0px' }
    );

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-animate]').forEach((el) => {
            el.style.opacity = '0';
            observer.observe(el);
        });
    });
}

// ==========================================
// Smooth scroll for anchor links
// ==========================================
document.addEventListener('click', (e) => {
    const link = e.target.closest('a[href^="#"]');
    if (!link) return;

    const targetId = link.getAttribute('href').slice(1);
    const target = document.getElementById(targetId);
    if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});

// ==========================================
// Lazy-load images via native loading="lazy"
// (fallback for older browsers)
// ==========================================
if ('loading' in HTMLImageElement.prototype === false) {
    document.addEventListener('DOMContentLoaded', () => {
        const lazyImages = document.querySelectorAll('img[loading="lazy"]');
        const lazyObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                    }
                    lazyObserver.unobserve(img);
                }
            });
        });
        lazyImages.forEach((img) => lazyObserver.observe(img));
    });
}

// ==========================================
// Price formatting helper (used inline if needed)
// ==========================================
window.formatVND = (number) => {
    return new Intl.NumberFormat('vi-VN').format(number) + '₫';
};
