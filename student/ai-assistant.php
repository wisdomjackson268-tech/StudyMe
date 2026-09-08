<?php
/**
 * StudyMe AI Platform — Student AI Tutor Assistant Page
 */
require_once dirname(__DIR__) . '/config/main.php';
secure_page(ROLE_STUDENT);

$user = current_user();
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-robot text-warning me-2"></i> Dedicated AI Tutor Assistant</h1>
        <p class="text-muted mb-0">Ask questions, request concept summaries, or generate practice quizzes anytime.</p>
    </div>
    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill fw-bold">
        <i class="bi bi-circle-fill me-1 small"></i> AI Engine Active
    </span>
</div>

<div class="row g-4">
    <!-- Chat Screen Column -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden" style="background: #0F172A; color: #F8FAFC;">
            <div class="p-3 border-bottom border-secondary border-opacity-25 bg-dark d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 bg-warning bg-opacity-20 text-warning rounded-circle d-flex align-items-center justify-content-center" style="width:38px; height:38px;">
                        <i class="bi bi-robot fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-white">StudyMe AI Companion</h6>
                        <small class="text-white-50">Socratic Tutoring &bull; Interactive Explanations</small>
                    </div>
                </div>
                <button type="button" id="clearChatBtn" class="btn btn-outline-secondary btn-sm rounded-pill text-white-50">Clear Chat</button>
            </div>

            <div class="card-body p-4 overflow-y-auto d-flex flex-column" id="pageAiChatLog" style="min-height: 400px; max-height: 520px;">
                <div class="chat-bubble chat-bubble-ai p-3 rounded-4 bg-secondary bg-opacity-20 mb-3 text-white">
                    <div class="fw-bold text-warning mb-1"><i class="bi bi-stars me-1"></i> Hello, <?= e($user['first_name']) ?>!</div>
                    I am your 24/7 StudyMe AI Tutor. What subject or lesson would you like to explore today?
                </div>
            </div>

            <div class="p-3 border-top border-secondary border-opacity-25 bg-dark">
                <form id="pageAiForm" class="d-flex gap-2">
                    <input type="text" id="pageAiInput" class="form-control rounded-pill px-4 bg-dark text-white border-secondary border-opacity-50" placeholder="Type a concept, formula, or code problem..." required autocomplete="off">
                    <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold" data-feedback="click">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- AI Presets & Prompt Templates Column -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-lightning-charge-fill text-warning me-2"></i> Quick AI Presets</h5>
            <div class="d-flex flex-column gap-2">
                <button type="button" class="btn btn-outline-primary text-start rounded-3 p-3 prompt-preset-btn">
                    <div class="fw-bold small mb-1"><i class="bi bi-card-text me-1"></i> Summarize a Topic</div>
                    <div class="text-muted small">"Summarize key concepts in Full Stack Web Development."</div>
                </button>

                <button type="button" class="btn btn-outline-warning text-start rounded-3 p-3 prompt-preset-btn">
                    <div class="fw-bold small mb-1"><i class="bi bi-question-circle me-1"></i> Generate Practice Quiz</div>
                    <div class="text-muted small">"Create 3 multiple-choice quiz questions on Python functions."</div>
                </button>

                <button type="button" class="btn btn-outline-success text-start rounded-3 p-3 prompt-preset-btn">
                    <div class="fw-bold small mb-1"><i class="bi bi-code-slash me-1"></i> Code Socratic Debugger</div>
                    <div class="text-muted small">"Help me find the logical bug in my JavaScript loop."</div>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const pageForm = document.getElementById("pageAiForm");
    const pageInput = document.getElementById("pageAiInput");
    const pageLog = document.getElementById("pageAiChatLog");
    const clearBtn = document.getElementById("clearChatBtn");
    const presetBtns = document.querySelectorAll(".prompt-preset-btn");

    if (pageForm && pageInput && pageLog) {
        pageForm.addEventListener("submit", (e) => {
            e.preventDefault();
            const text = pageInput.value.trim();
            if (!text) return;

            // Append User Message
            const userBubble = document.createElement("div");
            userBubble.className = "chat-bubble p-3 rounded-4 bg-primary text-white ms-auto mb-3 max-w-80";
            userBubble.textContent = text;
            pageLog.appendChild(userBubble);

            pageInput.value = "";
            pageLog.scrollTop = pageLog.scrollHeight;

            // Append Branded AI Thinking Indicator
            const loadingBubble = StudyMeLoader.createAiThinkingIndicator();
            pageLog.appendChild(loadingBubble);
            pageLog.scrollTop = pageLog.scrollHeight;

            const startTime = Date.now();
            const minDelay = 650; // Minimum display time to prevent unnatural quick flashing

            function handleResponse(replyText) {
                const elapsedTime = Date.now() - startTime;
                const remainingTime = Math.max(0, minDelay - elapsedTime);

                setTimeout(() => {
                    loadingBubble.remove();
                    const aiBubble = document.createElement("div");
                    aiBubble.className = "chat-bubble p-3 rounded-4 bg-secondary bg-opacity-20 text-white me-auto mb-3 lh-base";
                    aiBubble.innerHTML = '<div class="fw-bold text-warning mb-1"><i class="bi bi-robot me-1"></i> AI Tutor</div>' + replyText;
                    pageLog.appendChild(aiBubble);
                    pageLog.scrollTop = pageLog.scrollHeight;
                }, remainingTime);
            }

            function handleError() {
                const elapsedTime = Date.now() - startTime;
                const remainingTime = Math.max(0, minDelay - elapsedTime);

                setTimeout(() => {
                    loadingBubble.remove();
                    // Append structured AI Error Bubble with Retry Callback
                    const errBubble = StudyMeLoader.createAiErrorBubble(
                        "AI Tutor connection temporarily busy. Please try again.",
                        () => {
                            // Retry callback: resubmit prompt text
                            pageInput.value = text;
                            pageForm.dispatchEvent(new Event("submit"));
                        }
                    );
                    pageLog.appendChild(errBubble);
                    pageLog.scrollTop = pageLog.scrollHeight;
                }, remainingTime);
            }

            // Fetch AI Response from backend API
            fetch("<?= url('api/ai/index.php') ?>", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ prompt: text })
            })
            .then(res => {
                if (!res.ok) throw new Error("HTTP error");
                return res.json();
            })
            .then(data => {
                const reply = data.reply || "I am processing your query. Could you clarify the specific topic?";
                handleResponse(reply);
            })
            .catch(() => {
                handleError();
            });
        });

        if (clearBtn) {
            clearBtn.addEventListener("click", () => {
                pageLog.innerHTML = '<div class="chat-bubble p-3 rounded-4 bg-secondary bg-opacity-20 mb-3 text-white"><div class="fw-bold text-warning mb-1"><i class="bi bi-stars me-1"></i> Chat Cleared</div>Ask me any new study question!</div>';
            });
        }

        presetBtns.forEach(btn => {
            btn.addEventListener("click", () => {
                const sampleText = btn.querySelector(".text-muted").textContent.replace(/"/g, "");
                pageInput.value = sampleText;
                pageInput.focus();
            });
        });
    }
});
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
