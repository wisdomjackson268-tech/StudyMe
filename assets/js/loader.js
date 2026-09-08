/**
 * StudyMe AI-Powered Learning Platform — Global Preloader & Page Utilities
 * Provides smooth page load masking and programmatic access to show/hide loaders.
 */

const StudyMeLoader = {
    // Hide the main page preloader smoothly
    hide() {
        const preloader = document.getElementById("app-preloader");
        if (preloader) {
            preloader.style.opacity = "0";
            preloader.style.pointerEvents = "none";
            setTimeout(() => {
                preloader.style.visibility = "hidden";
                preloader.style.display = "none";
            }, 350);
        }
    },

    // Show the preloader programmatically (e.g. during form submission or heavy calculations)
    show() {
        const preloader = document.getElementById("app-preloader");
        if (preloader) {
            preloader.style.display = "flex";
            preloader.style.visibility = "visible";
            preloader.style.pointerEvents = "all";
            // Force reflow
            preloader.offsetHeight;
            preloader.style.opacity = "1";
        }
    },

    // Create a beautiful AI Thinking bubble element
    createAiThinkingIndicator() {
        const bubble = document.createElement("div");
        bubble.className = "ai-thinking-bubble mb-3";
        bubble.innerHTML = `
            <div class="ai-thinking-logo">
                <i class="bi bi-robot"></i>
            </div>
            <div class="ai-thinking-text">
                <div class="ai-thinking-label">StudyMe AI</div>
                <div class="ai-thinking-dots" aria-label="Thinking...">
                    <div class="ai-thinking-dot"></div>
                    <div class="ai-thinking-dot"></div>
                    <div class="ai-thinking-dot"></div>
                </div>
            </div>
        `;
        return bubble;
    },

    // Create a professional AI Error bubble element with a [Try Again] action button
    createAiErrorBubble(errorMessage, retryCallback) {
        const bubble = document.createElement("div");
        bubble.className = "ai-error-bubble mb-3";
        
        const messageText = document.createElement("div");
        messageText.className = "small fw-semibold";
        messageText.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i> ${errorMessage || "Connection temporarily busy. Please try again."}`;
        bubble.appendChild(messageText);

        if (retryCallback && typeof retryCallback === "function") {
            const retryBtn = document.createElement("button");
            retryBtn.type = "button";
            retryBtn.className = "btn btn-sm btn-outline-danger rounded-pill px-3 py-1 mt-2 fw-bold align-self-start";
            retryBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Try Again';
            retryBtn.addEventListener("click", () => {
                bubble.remove();
                retryCallback();
            });
            bubble.appendChild(retryBtn);
        }

        return bubble;
    },

    // Toggle button loading overlay
    setButtonLoadingState(btn, isLoading) {
        if (!btn) return;
        if (isLoading) {
            btn.disabled = true;
            btn.dataset.originalHtml = btn.innerHTML;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Loading...`;
        } else {
            btn.disabled = false;
            if (btn.dataset.originalHtml) {
                btn.innerHTML = btn.dataset.originalHtml;
            }
        }
    },

    // Display a loader inside a specific panel container
    showElementLoader(container, message = "Loading content...") {
        if (!container) return;
        // Check if loader already exists
        let loader = container.querySelector(".panel-loader-overlay");
        if (!loader) {
            // Ensure container has relative positioning
            if (window.getComputedStyle(container).position === "static") {
                container.style.position = "relative";
            }
            loader = document.createElement("div");
            loader.className = "panel-loader-overlay";
            loader.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="small text-muted fw-semibold">${message}</div>
                </div>
            `;
            container.appendChild(loader);
        }
        // Force reflow
        loader.offsetHeight;
        loader.classList.add("show");
    },

    // Dismiss the loader inside a specific container
    hideElementLoader(container) {
        if (!container) return;
        const loader = container.querySelector(".panel-loader-overlay");
        if (loader) {
            loader.classList.remove("show");
            setTimeout(() => {
                loader.remove();
            }, 250);
        }
    }
};

// Auto-dismiss preloader when DOM content is loaded
document.addEventListener("DOMContentLoaded", () => {
    // Trigger fadeout immediately
    StudyMeLoader.hide();

    // Safety timeout in case load event was missed or delayed
    setTimeout(() => {
        StudyMeLoader.hide();
    }, 400);

    // Attach button spinner loading state on form submissions (without locking full screen)
    const forms = document.querySelectorAll("form");
    forms.forEach(form => {
        form.addEventListener("submit", (e) => {
            if (form.dataset.ajaxForm) return;
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                const origHtml = submitBtn.innerHTML || submitBtn.value;
                submitBtn.dataset.originalContent = origHtml;
                if (submitBtn.tagName.toLowerCase() === 'button') {
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Please wait...';
                }
            }
        });
    });

    // Handle image fallback smoothly if remote image fails
    const images = document.querySelectorAll("img");
    images.forEach(img => {
        img.addEventListener("error", function() {
            if (this.dataset.fallbackApplied) return;
            this.dataset.fallbackApplied = "true";
            this.src = "data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%22600%22%20height%3D%22400%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20600%20400%22%20preserveAspectRatio%3D%22none%22%3E%3Crect%20width%3D%22600%22%20height%3D%22400%22%20fill%3D%22%236C2BFF%22%20opacity%3D%220.1%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20dominant-baseline%3D%22middle%22%20text-anchor%3D%22middle%22%20font-family%3D%22sans-serif%22%20font-size%3D%2220%22%20fill%3D%22%236C2BFF%22%3EStudyMe%20Course%3C%2Ftext%3E%3C%2Fsvg%3E";
        });
    });
});

// Fallback in case DOMContentLoaded did not trigger
window.addEventListener("load", () => {
    StudyMeLoader.hide();
});

// Quick failsafe check
if (document.readyState === "complete" || document.readyState === "interactive") {
    setTimeout(() => { StudyMeLoader.hide(); }, 100);
}

// Expose to global scope
window.StudyMeLoader = StudyMeLoader;
