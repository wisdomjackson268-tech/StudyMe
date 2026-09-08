/**
 * STUDYME — LANDING PAGE INTERACTIVE JAVASCRIPT
 * Handles scroll progress, glassmorphic navbar scroll state,
 * bidirectional reveal animations, number counters, typewriter, and back-to-top.
 */

document.addEventListener("DOMContentLoaded", () => {
    'use strict';

    /* ─── 1. SCROLL PROGRESS BAR ───────────────────────────────── */
    const progressBar = document.getElementById('lp-progress');
    
    function updateProgressBar() {
        if (!progressBar) return;
        const scrollTop = window.scrollY || document.documentElement.scrollTop;
        const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
        const progress = scrollHeight > 0 ? (scrollTop / scrollHeight) * 100 : 0;
        progressBar.style.width = Math.min(progress, 100) + '%';
    }

    /* ─── 2. GLASSMORPHIC NAVBAR SCROLL HANDLER ───────────────── */
    const navbar = document.getElementById('lpNavbar');
    
    function updateNavbarState() {
        if (!navbar) return;
        if (window.scrollY > 30) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    }

    /* ─── 3. BACK TO TOP BUTTON ────────────────────────────────── */
    const backToTopBtn = document.getElementById('lp-back-top');
    
    function updateBackToTopState() {
        if (!backToTopBtn) return;
        if (window.scrollY > 400) {
            backToTopBtn.classList.add('visible');
        } else {
            backToTopBtn.classList.remove('visible');
        }
    }

    if (backToTopBtn) {
        backToTopBtn.addEventListener('click', (e) => {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    /* ─── 4. GLOBAL SCROLL DISPATCHER ──────────────────────────── */
    function onWindowScroll() {
        updateProgressBar();
        updateNavbarState();
        updateBackToTopState();
    }

    window.addEventListener('scroll', onWindowScroll, { passive: true });
    onWindowScroll(); // Initial execution

    /* ─── 5. REVEAL ANIMATIONS (IntersectionObserver) ───────────── */
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!prefersReducedMotion) {
        const revealElements = document.querySelectorAll('.lp-reveal, .lp-reveal-left, .lp-reveal-right, .lp-reveal-scale');
        
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                } else {
                    // Bidirectional: reset when element scrolls significantly out of view
                    const rect = entry.boundingClientRect;
                    if (rect.top > window.innerHeight || rect.bottom < 0) {
                        entry.target.classList.remove('is-visible');
                    }
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        revealElements.forEach(el => revealObserver.observe(el));
    } else {
        // Show everything immediately if reduced motion is requested
        document.querySelectorAll('.lp-reveal, .lp-reveal-left, .lp-reveal-right, .lp-reveal-scale').forEach(el => {
            el.classList.add('is-visible');
        });
    }

    /* ─── 6. NUMBER COUNTER ANIMATION ───────────────────────────── */
    const counterElements = document.querySelectorAll('[data-target]');
    
    function animateCounter(counterEl) {
        const targetValue = parseInt(counterEl.getAttribute('data-target'), 10) || 0;
        const suffix = counterEl.getAttribute('data-suffix') || '';
        const duration = 1800; // ms
        const startTime = performance.now();

        function step(now) {
            const elapsed = now - startTime;
            const progress = Math.min(elapsed / duration, 1);
            // Ease-out cubic formula
            const easedProgress = 1 - Math.pow(1 - progress, 3);
            const currentValue = Math.floor(easedProgress * targetValue);
            
            counterEl.textContent = currentValue.toLocaleString() + suffix;

            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                counterEl.textContent = targetValue.toLocaleString() + suffix;
            }
        }

        requestAnimationFrame(step);
    }

    const counterObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !entry.target.dataset.counted) {
                entry.target.dataset.counted = 'true';
                animateCounter(entry.target);
            }
        });
    }, { threshold: 0.5 });

    counterElements.forEach(counter => counterObserver.observe(counter));

    /* ─── 7. INTERACTIVE AI CHAT DEMO CHIPS ─────────────────────── */
    const chatInputMock = document.getElementById('lpChatInputMock');
    const aiActionChips = document.querySelectorAll('.lp-ai-chip');

    if (chatInputMock && aiActionChips.length > 0) {
        aiActionChips.forEach(chip => {
            chip.addEventListener('click', () => {
                const text = chip.getAttribute('data-prompt') || chip.textContent.trim();
                chatInputMock.value = text;
                chatInputMock.classList.add('border-primary');
                setTimeout(() => chatInputMock.classList.remove('border-primary'), 1000);
            });
        });
    }
});
