<?php
/**
 * StudyMe AI Platform — AI Learning Showcase Page
 */
require_once __DIR__ . '/config/main.php';
include BASE_PATH . '/includes/layouts/header.php';
?>

<!-- Custom CSS for the simulated chat -->
<style>
.chat-window {
    background: #0f172a;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
}
.chat-header {
    background: #1e293b;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    padding: 15px 20px;
}
.chat-body {
    padding: 20px;
    height: 380px;
    overflow-y: auto;
}
.chat-bubble {
    padding: 12px 16px;
    border-radius: 14px;
    max-width: 80%;
    margin-bottom: 15px;
    font-size: 0.9rem;
    line-height: 1.5;
}
.chat-bubble.student {
    background: #2563eb;
    color: white;
    margin-left: auto;
    border-bottom-right-radius: 2px;
}
.chat-bubble.ai {
    background: #1e293b;
    color: #cbd5e1;
    margin-right: auto;
    border-bottom-left-radius: 2px;
}
.chat-bubble code {
    background: rgba(0, 0, 0, 0.3);
    color: #f43f5e;
    padding: 2px 6px;
    border-radius: 4px;
}
.chat-input {
    background: #1e293b;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    padding: 15px 20px;
}
</style>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <!-- Hero Section -->
        <div class="row align-items-center g-5 mb-5">
            <div class="col-lg-6">
                <div class="p-3 bg-warning bg-opacity-15 text-warning rounded-circle d-inline-flex mb-3">
                    <i class="bi bi-stars fs-3"></i>
                </div>
                <h1 class="display-4 fw-bold mb-3">Learn Faster &amp; Smarter with Your AI Tutor</h1>
                <p class="lead text-muted mb-4">
                    StudyMe combines elite course content with an advanced personal AI Tutor. Get instant help with homework, explainers for complex concepts, personalized recommendations, and customized quizzes.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="<?= url('auth/register.php') ?>" class="btn btn-primary btn-lg rounded-pill px-4 fw-bold shadow" data-feedback="success">
                        Start Free Trial <i class="bi bi-arrow-right-short ms-1"></i>
                    </a>
                    <a href="#demo-chat" class="btn btn-outline-secondary btn-lg rounded-pill px-4" data-feedback="click">
                        Try Demo Chat
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="p-4 bg-primary bg-opacity-5 rounded-4 border border-secondary border-opacity-10 text-center">
                    <?php include BASE_PATH . '/assets/svg/ai-learning.svg'; ?>
                </div>
            </div>
        </div>

        <!-- Simulated Interactive AI Chat Widget -->
        <div class="row justify-content-center py-5" id="demo-chat">
            <div class="col-lg-8">
                <div class="text-center mb-4">
                    <h3 class="fw-bold mb-2">Experience the AI Study Assistant</h3>
                    <p class="text-muted">Simulate a session with the StudyMe Personal Tutor below.</p>
                </div>

                <div class="chat-window">
                    <div class="chat-header d-flex align-items-center justify-content-between text-white">
                        <div class="d-flex align-items-center gap-3">
                            <div class="p-2 bg-primary bg-opacity-20 text-primary rounded-circle small">
                                <i class="bi bi-robot fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0">StudyMe AI Tutor</h6>
                                <span class="badge bg-success rounded-pill" style="font-size:0.6rem;">Online &amp; Ready</span>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <span class="w-3 h-3 bg-secondary rounded-circle"></span>
                        </div>
                    </div>

                    <div class="chat-body" id="chatBody">
                        <!-- Welcome message -->
                        <div class="chat-bubble ai">
                            Hello! I am your StudyMe AI Tutor. Ask me any question from your lessons, request a summary, or let's generate a quick practice quiz! What are we studying today?
                        </div>
                    </div>

                    <div class="chat-input">
                        <div class="input-group">
                            <select id="simQuery" class="form-select border-0 bg-dark text-white rounded-start-pill py-3 px-3" style="max-width: 40%; font-size: 0.85rem;">
                                <option value="1">Explain "Recursion" in Python</option>
                                <option value="2">Generate a WAEC Chemistry Quiz</option>
                                <option value="3">Summarize "Photosynthesis" process</option>
                                <option value="4">How do database foreign keys work?</option>
                            </select>
                            <button onclick="sendSimulatedMessage()" class="btn btn-primary rounded-end-pill px-4 fw-bold" type="button" data-feedback="click">
                                Ask AI <i class="bi bi-send ms-1"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Core AI Features Cards -->
        <div class="row g-4 py-5 mt-3">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center">
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex mx-auto mb-3">
                        <i class="bi bi-patch-question-fill fs-3"></i>
                    </div>
                    <h5 class="fw-bold">AI Quiz Generation</h5>
                    <p class="text-muted small mb-0">Instant mock quizzes tailored to your syllabus. Tests are evaluated in real-time with detailed concept explanations for mistakes.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center">
                    <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-circle d-inline-flex mx-auto mb-3">
                        <i class="bi bi-file-earmark-text-fill fs-3"></i>
                    </div>
                    <h5 class="fw-bold">Concept Summaries</h5>
                    <p class="text-muted small mb-0">Short on time? Compress long transcripts and textbooks into bullet-point highlights and key study terms instantly.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center">
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle d-inline-flex mx-auto mb-3">
                        <i class="bi bi-graph-up-arrow fs-3"></i>
                    </div>
                    <h5 class="fw-bold">Learning Insights</h5>
                    <p class="text-muted small mb-0">The platform monitors your quiz results and watch metrics to highlight concepts you struggle with and suggest remedial paths.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function sendSimulatedMessage() {
    const chatBody = document.getElementById('chatBody');
    const simQuery = document.getElementById('simQuery');
    const selectedText = simQuery.options[simQuery.selectedIndex].text;
    const value = simQuery.value;

    // 1. Add Student Bubble
    const studentBubble = document.createElement('div');
    studentBubble.className = 'chat-bubble student';
    studentBubble.innerText = selectedText;
    chatBody.appendChild(studentBubble);
    chatBody.scrollTop = chatBody.scrollHeight;

    // 2. Play feedback sound
    if (typeof StudyMeFeedback !== 'undefined') {
        StudyMeFeedback.click();
    }

    // 3. Simulated Typing and AI Response
    setTimeout(() => {
        const aiBubble = document.createElement('div');
        aiBubble.className = 'chat-bubble ai';
        
        let response = '';
        if (value === '1') {
            response = 'Recursion is when a function calls itself. Think of it like a set of Russian nesting dolls. Here is a simple Python example:\n\n<code>def factorial(n):\n  if n == 1: return 1\n  return n * factorial(n-1)</code>\n\nThe <code>n == 1</code> is the base case that stops the recursion.';
        } else if (value === '2') {
            response = 'Great choice! Here is a WAEC-style Chemistry question:\n\n**Question:** Which of the following elements has the highest electronegativity?\n\nA) Sodium\nB) Chlorine\nC) Fluorine\nD) Oxygen\n\n*Reply with A, B, C, or D, and I will check your answer!*';
        } else if (value === '3') {
            response = 'Photosynthesis is the process plants use to turn sunlight, water, and CO2 into oxygen and sugar (energy). Here is the chemical equation:\n\n`6CO2 + 6H2O + Light → C6H12O6 + 6O2`';
        } else {
            response = 'A foreign key is a column in one table that points to the primary key of another table. It enforces referential integrity, ensuring links between records remain valid.';
        }

        aiBubble.innerHTML = response;
        chatBody.appendChild(aiBubble);
        chatBody.scrollTop = chatBody.scrollHeight;

        if (typeof StudyMeFeedback !== 'undefined') {
            StudyMeFeedback.notification();
        }
    }, 1000);
}
</script>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
