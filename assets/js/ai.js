
const StudyMeAI = (() => {

    function getApiUrl() {
        const isSubdir = window.location.pathname.startsWith('/StudyMe');
        return isSubdir ? '/StudyMe/api/ai/index.php' : '/api/ai/index.php';
    }

    function formatMarkdown(text) {
        if (!text) return '';

        let html = text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");

        html = html.replace(/```(?:[a-zA-Z0-9_-]+)?\n([\s\S]*?)```/g, (match, code) => {
            return `<pre class="bg-dark text-warning p-3 rounded-3 my-2 border border-secondary border-opacity-25" style="overflow-x:auto; font-family:var(--font-mono, monospace); font-size:0.875rem;"><code>${code.trim()}</code></pre>`;
        });

        html = html.replace(/`([^`]+)`/g, '<code class="bg-dark text-warning px-1 py-0.5 rounded border border-secondary border-opacity-25" style="font-size:0.875em;">$1</code>');

        // Bold **text**
        html = html.replace(/\*\*([^*]+)\*\*/g, '<strong class="text-white">$1</strong>');

        // Italic *text*
        html = html.replace(/\*([^*]+)\*/g, '<em class="text-white-50">$1</em>');

        // Bullet points (* or -)
        html = html.replace(/^(?:[\*\-])\s+(.+)$/gm, '<li class="mb-1 ms-3">$1</li>');
        html = html.replace(/((?:<li class="mb-1 ms-3">.*?<\/li>\s*)+)/g, '<ul class="mb-2 ps-2">$1</ul>');

        // Paragraph line breaks (preserving newlines)
        html = html.replace(/\n\n/g, '<div class="my-2"></div>');
        html = html.replace(/\n/g, '<br>');

        return html;
    }

    // In-memory conversation history for contextual multi-turn chat
    const chatHistory = [];

    async function askGemini(prompt, context = '') {
        const url = getApiUrl();

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    prompt: prompt,
                    context: context,
                    history: chatHistory.slice(-6) // Keep last 6 exchanges for context
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            // Track conversation history
            chatHistory.push({ role: 'user', text: prompt });
            if (data.reply) {
                chatHistory.push({ role: 'model', text: data.reply });
            }

            if (typeof StudyMeFeedback !== 'undefined') {
                StudyMeFeedback.notification();
            }

            return {
                success: data.success !== false,
                title: data.title || (data.success !== false ? "AI Tutor Response" : "AI Notice"),
                text: data.reply || "No response received.",
                html: formatMarkdown(data.reply || "No response received."),
                model: data.model || 'gemini-3.6-flash'
            };
        } catch (error) {
            console.warn('StudyMe AI Network/API error:', error);
            return {
                success: false,
                title: "Connection Notice",
                text: "Unable to reach StudyMe AI Tutor right now. Please check your connection and try again.",
                html: "Unable to reach StudyMe AI Tutor right now. Please check your connection and try again.",
                error: error.message
            };
        }
    }

    return {
        ask: askGemini,
        formatMarkdown: formatMarkdown,
        clearHistory: () => {
            chatHistory.length = 0;
        }
    };
})();

// Attach event listeners for AI drawers and quick prompts
document.addEventListener("DOMContentLoaded", () => {
    const aiForm = document.getElementById("aiQueryForm");
    const aiInput = document.getElementById("aiQueryInput");
    const aiChatLog = document.getElementById("aiChatLog");

    if (aiForm && aiInput && aiChatLog) {
        aiForm.dataset.ajaxForm = "true";
        aiForm.dataset.noLoader = "true";
        const submitBtn = aiForm.querySelector('button[type="submit"]');

        aiForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            e.stopPropagation();

            const text = aiInput.value.trim();
            if (!text) return;

            // Append User Message
            const userMsg = document.createElement("div");
            userMsg.className = "chat-bubble p-3 rounded-4 bg-primary text-white ms-auto mb-3";
            userMsg.style.maxWidth = "85%";
            userMsg.textContent = text;
            aiChatLog.appendChild(userMsg);
            aiInput.value = "";
            aiChatLog.scrollTop = aiChatLog.scrollHeight;

            // Set button to active loading state
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm text-dark" role="status" aria-hidden="true"></span>';
            }

            // Loading Indicator
            let loader;
            if (typeof StudyMeLoader !== 'undefined' && typeof StudyMeLoader.createAiThinkingIndicator === 'function') {
                loader = StudyMeLoader.createAiThinkingIndicator();
            } else {
                loader = document.createElement("div");
                loader.className = "d-flex align-items-center gap-2 text-white-50 small my-2 ai-loading";
                loader.innerHTML = '<div class="spinner-grow spinner-grow-sm text-warning" role="status"></div><span>AI is thinking...</span>';
            }
            aiChatLog.appendChild(loader);
            aiChatLog.scrollTop = aiChatLog.scrollHeight;

            try {
                // Get Context from data attribute
                const context = aiForm.dataset.context || '';
                const res = await StudyMeAI.ask(text, context);

                if (loader && loader.parentNode) {
                    loader.remove();
                }

                // Append AI Message
                const aiMsg = document.createElement("div");
                aiMsg.className = "chat-bubble p-3 rounded-4 bg-secondary bg-opacity-20 text-white me-auto mb-3";
                aiMsg.style.maxWidth = "90%";
                aiMsg.style.lineHeight = "1.6";
                aiMsg.innerHTML = `
                    <div class="fw-bold mb-2 text-warning d-flex align-items-center gap-1">
                        <i class="bi bi-robot"></i> <span>${res.title}</span>
                    </div>
                    <div>${res.html}</div>
                `;
                aiChatLog.appendChild(aiMsg);
                aiChatLog.scrollTop = aiChatLog.scrollHeight;
            } catch (err) {
                if (loader && loader.parentNode) {
                    loader.remove();
                }
                const errMsg = document.createElement("div");
                errMsg.className = "chat-bubble p-3 rounded-4 bg-danger bg-opacity-20 text-white me-auto mb-3";
                errMsg.textContent = "AI Tutor response error. Please try again.";
                aiChatLog.appendChild(errMsg);
                aiChatLog.scrollTop = aiChatLog.scrollHeight;
            } finally {
                // Always restore the submit button to ready state
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-send-fill"></i>';
                }
                aiInput.disabled = false;
                aiInput.focus();
            }
        });
    }
});
