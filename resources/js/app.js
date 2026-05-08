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
