<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';
require_once BASE_PATH . '/includes/functions/activity.php';

secure_page(ROLE_STUDENT);

$user   = current_user();
$userId = (int)$user['id'];
$pdo    = getDBConnection();

log_user_activity($userId, 'used_secondary_ai_tutor', 'Student opened Secondary School AI Tutor');

$subjects = get_secondary_subjects('active');
$presetParam = trim($_GET['preset'] ?? '');
$subjectParam = trim($_GET['subject'] ?? 'Mathematics');

$pageTitle = 'Secondary AI Tutor | StudyMe';
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="<?= url('student/secondary-dashboard.php') ?>" class="text-decoration-none text-muted small">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
            <span class="text-muted small">&bull;</span>
            <span class="text-muted small">AI Socratic Engine</span>
        </div>
        <h2 class="fw-bold mb-0 text-dark">
            <i class="bi bi-robot text-warning me-2"></i> Secondary School 24/7 AI Tutor
        </h2>
    </div>
    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill fw-bold">
        <i class="bi bi-circle-fill me-1 small"></i> WAEC / JAMB Socratic Assistant Online
    </span>
</div>

<div class="row g-4 mb-5">

    <!-- MAIN CHAT INTERACTION WINDOW -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden" style="background: #0F172A; color: #F8FAFC;">
            
            <!-- Chat Header -->
            <div class="p-3 bg-dark border-bottom border-secondary border-opacity-25 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 bg-warning bg-opacity-20 text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bi bi-robot fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-white mb-0">StudyMe Secondary Tutor</h6>
                        <small class="text-white-50">Socratic step-by-step guidance &bull; High School Level</small>
                    </div>
                </div>

                <!-- Subject Context Switcher -->
                <div class="d-flex align-items-center gap-2">
                    <label class="text-white-50 small fw-bold d-none d-sm-inline">Subject Context:</label>
                    <select id="aiSubjectSelector" class="form-select form-select-sm bg-dark text-white border-secondary border-opacity-50 rounded-pill px-3" style="width: auto;">
                        <?php foreach ($subjects as $s): ?>
                        <option value="<?= e($s['name']) ?>" <?= strtolower($subjectParam) === strtolower($s['name']) ? 'selected' : '' ?>>
                            <?= e($s['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="clearChatBtn" class="btn btn-outline-secondary btn-sm rounded-pill text-white-50" title="Clear conversation">
                        Clear
                    </button>
                </div>
            </div>

            <!-- Chat Message Stream -->
            <div class="card-body p-4 overflow-y-auto d-flex flex-column" id="secAiChatLog" style="min-height: 420px; max-height: 540px;">
                <div class="chat-bubble chat-bubble-ai p-3 rounded-4 bg-secondary bg-opacity-20 mb-3 text-white">
                    <div class="fw-bold text-warning mb-1">
                        <i class="bi bi-stars me-1"></i> Hello, <?= e($user['first_name']) ?>!
                    </div>
                    I am your StudyMe Secondary School AI Tutor. Ask me any question on Mathematics, English, Physics, Chemistry, Biology, Economics, Government or any WAEC/JAMB subject, and I will break it down step-by-step!
                </div>
            </div>

            <!-- Chat Input Form -->
            <div class="p-3 bg-dark border-top border-secondary border-opacity-25">
                <form id="secAiForm" class="d-flex gap-2">
                    <input type="text" id="secAiInput" class="form-control rounded-pill px-4 bg-dark text-white border-secondary border-opacity-50" placeholder="Ask a formula, exam question, or concept..." value="<?= e($presetParam) ?>" required autocomplete="off">
                    <button type="submit" id="secAiSubmitBtn" class="btn btn-warning rounded-pill px-4 fw-bold text-dark">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </form>
            </div>

        </div>
    </div>

    <!-- PRESETS & STUDY ASSISTANTS -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white border">
            <h5 class="fw-bold text-dark mb-3">
                <i class="bi bi-lightning-charge-fill text-warning me-2"></i> High-Yield Presets
            </h5>
            <div class="d-flex flex-column gap-2">
                <button type="button" class="btn btn-outline-primary text-start rounded-3 p-3 prompt-preset-btn" data-prompt="Solve this step-by-step: 2x^2 - 5x - 3 = 0 using factorization and show all steps clearly for WAEC.">
                    <div class="fw-bold small mb-1"><i class="bi bi-calculator me-1"></i> Step-by-Step Math Solver</div>
                    <div class="text-muted small">"Solve $2x^2 - 5x - 3 = 0$ using factorization..."</div>
                </button>

                <button type="button" class="btn btn-outline-success text-start rounded-3 p-3 prompt-preset-btn" data-prompt="Explain the difference between Monophthongs and Diphthongs in Oral English with 5 common WAEC test words.">
                    <div class="fw-bold small mb-1"><i class="bi bi-chat-quote me-1"></i> Oral English Phonetics</div>
                    <div class="text-muted small">"Explain Monophthongs vs Diphthongs..."</div>
                </button>

                <button type="button" class="btn btn-outline-danger text-start rounded-3 p-3 prompt-preset-btn" data-prompt="Explain Newton's Three Laws of Motion with real-life Nigerian examples for high school physics.">
                    <div class="fw-bold small mb-1"><i class="bi bi-lightning-charge me-1"></i> Physics Law Breakdown</div>
                    <div class="text-muted small">"Explain Newton's Laws with everyday examples..."</div>
                </button>

                <button type="button" class="btn btn-outline-warning text-dark text-start rounded-3 p-3 prompt-preset-btn" data-prompt="Give me 3 practice multiple-choice questions on Stoichiometry and the Mole concept with answer keys.">
                    <div class="fw-bold small mb-1"><i class="bi bi-question-circle me-1"></i> Generate Practice Drill</div>
                    <div class="text-muted small">"Create 3 multiple-choice chemistry questions..."</div>
                </button>
            </div>
        </div>

        <!-- WAEC ESSAY ADVICE WIDGET -->
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-light border">
            <h6 class="fw-bold text-dark mb-2">
                <i class="bi bi-lightbulb-fill text-warning me-1"></i> Tip for Secondary Students:
            </h6>
            <p class="text-muted small mb-0">
                You can copy any past question directly into the prompt box and ask the AI Tutor to provide the marking scheme solution breakdown.
            </p>
        </div>
    </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("secAiForm");
    const input = document.getElementById("secAiInput");
    const log = document.getElementById("secAiChatLog");
    const submitBtn = document.getElementById("secAiSubmitBtn");
    const clearBtn = document.getElementById("clearChatBtn");
    const subjectSelector = document.getElementById("aiSubjectSelector");
    const presetBtns = document.querySelectorAll(".prompt-preset-btn");

    let chatHistory = [];

    // If preset was in query param, trigger automatically
    if (input.value.trim().length > 0) {
        setTimeout(() => {
            form.dispatchEvent(new Event("submit"));
        }, 500);
    }

    presetBtns.forEach(btn => {
        btn.addEventListener("click", () => {
            const prompt = btn.getAttribute("data-prompt");
            if (prompt) {
                input.value = prompt;
                form.dispatchEvent(new Event("submit"));
            }
        });
    });

    clearBtn.addEventListener("click", () => {
        log.innerHTML = `
            <div class="chat-bubble chat-bubble-ai p-3 rounded-4 bg-secondary bg-opacity-20 mb-3 text-white">
                <div class="fw-bold text-warning mb-1"><i class="bi bi-stars me-1"></i> Chat cleared.</div>
                What concept or past question would you like to explore next?
            </div>
        `;
        chatHistory = [];
    });

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        const text = input.value.trim();
        if (!text) return;

        // Render User Bubble
        const userBubble = document.createElement("div");
        userBubble.className = "chat-bubble p-3 rounded-4 bg-primary text-white ms-auto mb-3 max-w-80";
        userBubble.textContent = text;
        log.appendChild(userBubble);

        input.value = "";
        submitBtn.disabled = true;

        // Render Typing Indicator
        const typingBubble = document.createElement("div");
        typingBubble.className = "chat-bubble chat-bubble-ai p-3 rounded-4 bg-secondary bg-opacity-20 mb-3 text-white-50";
        typingBubble.innerHTML = '<i class="bi bi-stars text-warning me-1"></i> AI Tutor is solving and formulating explanation...';
        log.appendChild(typingBubble);
        log.scrollTop = log.scrollHeight;

        const currentSubject = subjectSelector ? subjectSelector.value : "Secondary School Curriculum";
        const secondaryContext = `Student Level: Senior Secondary (SS1 - SS3) / WAEC / JAMB candidate. Current Subject: ${currentSubject}. Provide clear, friendly, high-school level step-by-step explanations with formulas and examples.`;

        try {
            const res = await fetch("<?= url('api/ai/index.php') ?>", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    prompt: text,
                    context: secondaryContext,
                    history: chatHistory
                })
            });

            const data = await res.json();
            typingBubble.remove();

            const aiBubble = document.createElement("div");
            aiBubble.className = "chat-bubble chat-bubble-ai p-3 rounded-4 bg-secondary bg-opacity-20 mb-3 text-white";
            
            const replyText = data.reply || "I encountered an error processing your request. Please try again.";
            aiBubble.innerHTML = `<div class="fw-bold text-warning mb-1"><i class="bi bi-robot me-1"></i> AI Tutor:</div><div>${replyText.replace(/\n/g, '<br>')}</div>`;
            log.appendChild(aiBubble);

            chatHistory.push({ role: "user", parts: [{ text }] });
            chatHistory.push({ role: "model", parts: [{ text: replyText }] });

        } catch (err) {
            typingBubble.remove();
            const errBubble = document.createElement("div");
            errBubble.className = "chat-bubble p-3 rounded-4 bg-danger text-white mb-3";
            errBubble.textContent = "Network error communicating with AI engine. Please check your connection.";
            log.appendChild(errBubble);
        } finally {
            submitBtn.disabled = false;
            log.scrollTop = log.scrollHeight;
        }
    });
});
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
