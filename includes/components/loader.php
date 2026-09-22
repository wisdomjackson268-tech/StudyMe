<?php
?>
<script>
    (function() {
        try {
            var savedTheme = localStorage.getItem("theme");
            if (savedTheme === "dark") {
                document.body.classList.add("dark");
            } else if (savedTheme === "light") {
                document.body.classList.remove("dark");
            }
        } catch (e) {}
    })();
</script>

<style>
    #app-preloader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-color: #ffffff;
        z-index: 999999;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.35s ease;
        opacity: 1;
        visibility: visible;
    }

    body.dark #app-preloader {
        background-color: #0f172a;
    }

    .preloader-content {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 120px;
        height: 120px;
    }

    .preloader-brand-icon {
        font-size: 2.8rem;
        color: #6C2BFF;
        z-index: 10;
        animation: preloader-pulse 1.8s infinite ease-in-out;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    body.dark .preloader-brand-icon {
        color: #a78bfa;
    }

    .preloader-spinner {
        position: absolute;
        width: 90px;
        height: 90px;
        border: 3px solid rgba(108, 43, 255, 0.08);
        border-top: 3px solid #6C2BFF;
        border-radius: 50%;
        animation: preloader-spin 1.1s cubic-bezier(0.5, 0.1, 0.4, 0.9) infinite;
    }

    body.dark .preloader-spinner {
        border: 3px solid rgba(167, 139, 250, 0.08);
        border-top: 3px solid #a78bfa;
    }

    .ai-thinking-bubble {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 1rem 1.25rem;
        background: rgba(108, 43, 255, 0.06);
        border: 1px solid rgba(108, 43, 255, 0.12);
        border-radius: 16px;
        margin-right: auto;
        max-width: 80%;
        color: #1e293b;
        animation: preloader-fadeIn 0.25s ease forwards;
    }

    body.dark .ai-thinking-bubble {
        background: rgba(167, 139, 250, 0.08);
        border: 1px solid rgba(167, 139, 250, 0.15);
        color: #f8fafc;
    }

    .ai-thinking-logo {
        font-size: 1.5rem;
        color: #6C2BFF;
        animation: preloader-pulse 1.5s infinite ease-in-out;
        display: inline-flex;
    }

    body.dark .ai-thinking-logo {
        color: #a78bfa;
    }

    .ai-thinking-text {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .ai-thinking-label {
        font-size: 0.85rem;
        font-weight: 700;
        color: #6C2BFF;
    }

    body.dark .ai-thinking-label {
        color: #a78bfa;
    }

    .ai-thinking-dots {
        display: flex;
        gap: 4px;
        align-items: center;
        height: 10px;
        margin-top: 2px;
    }

    .ai-thinking-dot {
        width: 6px;
        height: 6px;
        background-color: #6C2BFF;
        border-radius: 50%;
        animation: pulse-dot 1.2s infinite ease-in-out both;
    }

    body.dark .ai-thinking-dot {
        background-color: #a78bfa;
    }

    .ai-thinking-dot:nth-child(2) { animation-delay: 0.2s; }
    .ai-thinking-dot:nth-child(3) { animation-delay: 0.4s; }

    .ai-error-bubble {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 1rem 1.25rem;
        background: rgba(239, 68, 68, 0.08);
        border: 1px solid rgba(239, 68, 68, 0.2);
        border-radius: 16px;
        margin-right: auto;
        max-width: 80%;
        color: #b91c1c;
        animation: preloader-fadeIn 0.25s ease forwards;
    }

    body.dark .ai-error-bubble {
        color: #fca5a5;
        background: rgba(239, 68, 68, 0.12);
        border: 1px solid rgba(239, 68, 68, 0.25);
    }

    .panel-loader-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.85);
        z-index: 100;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        border-radius: inherit;
        transition: opacity 0.25s ease;
        opacity: 0;
        pointer-events: none;
    }

    body.dark .panel-loader-overlay {
        background: rgba(15, 23, 42, 0.85);
    }

    .panel-loader-overlay.show {
        opacity: 1;
        pointer-events: all;
    }

    @keyframes preloader-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    @keyframes preloader-pulse {
        0%, 100% { transform: scale(0.92); opacity: 0.85; }
        50% { transform: scale(1.08); opacity: 1; }
    }

    @keyframes pulse-dot {
        0%, 80%, 100% { transform: scale(0.6); opacity: 0.5; }
        40% { transform: scale(1.1); opacity: 1; }
    }

    @keyframes preloader-fadeIn {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div id="app-preloader" aria-label="Loading StudyMe...">
    <div class="preloader-content">
        <div class="preloader-brand-icon">
            <i class="bi bi-mortarboard-fill"></i>
        </div>
        <div class="preloader-spinner"></div>
    </div>
</div>
