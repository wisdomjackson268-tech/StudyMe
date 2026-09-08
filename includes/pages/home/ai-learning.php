<section class="section-padding" id="ai-learning">
    <div class="container">
        <div class="ai-section">
            <div class="row align-items-center g-5">
                <!-- Left: Copy & Highlights -->
                <div class="col-lg-6">
                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold mb-3">
                        <i class="bi bi-cpu-fill me-1"></i> Next-Gen Education Technology
                    </span>
                    <h2 class="display-5 fw-bold text-white mb-4">
                        Your Personal AI Learning Companion
                    </h2>
                    <p class="lead text-white-50 mb-4">
                        StudyMe helps learners understand difficult topics, ask instant questions, summarize complex lectures, and practice concepts whenever you need assistance.
                    </p>

                    <div class="d-flex flex-column gap-3 mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-white bg-opacity-10 p-2 rounded-circle text-warning">
                                <i class="bi bi-chat-left-dots-fill fs-5"></i>
                            </div>
                            <div>
                                <h6 class="text-white mb-0 fw-bold">Instant Question Answering</h6>
                                <small class="text-white-50">Stuck on a concept? Get clear, step-by-step explanations 24/7.</small>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-white bg-opacity-10 p-2 rounded-circle text-warning">
                                <i class="bi bi-lightning-charge-fill fs-5"></i>
                            </div>
                            <div>
                                <h6 class="text-white mb-0 fw-bold">Smart Lesson Summaries</h6>
                                <small class="text-white-50">Generate quick review notes and key takeaways from any video lesson.</small>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-white bg-opacity-10 p-2 rounded-circle text-warning">
                                <i class="bi bi-journal-check fs-5"></i>
                            </div>
                            <div>
                                <h6 class="text-white mb-0 fw-bold">Personalized Quiz Practice</h6>
                                <small class="text-white-50">Auto-generate practice questions tailored to your knowledge gaps.</small>
                            </div>
                        </div>
                    </div>

                    <a href="<?= function_exists('url') ? url('auth/register.php') : 'auth/register.php' ?>" class="btn btn-warning text-dark rounded-pill px-4 py-3 fw-bold" data-feedback="success">
                        Try AI Tutor Free <i class="bi bi-arrow-right ms-2"></i>
                    </a>
                </div>

                <!-- Right: Interactive Chat UI Mockup -->
                <div class="col-lg-6">
                    <div class="ai-chat-card">
                        <div class="chat-header">
                            <div class="chat-user">
                                <div class="chat-avatar">
                                    <i class="bi bi-robot"></i>
                                </div>
                                <div>
                                    <h6 class="text-white mb-0 fw-bold">StudyMe AI Tutor</h6>
                                    <small class="text-success">&bull; Active &amp; Ready to Help</small>
                                </div>
                            </div>
                            <span class="badge bg-secondary bg-opacity-50 text-white-50 rounded-pill px-3 py-1">Model v2.4</span>
                        </div>

                        <div class="chat-body">
                            <!-- Student Bubble -->
                            <div class="chat-bubble chat-bubble-user">
                                Can you explain how JavaScript Async/Await works in simple terms?
                            </div>

                            <!-- AI Reply Bubble -->
                            <div class="chat-bubble chat-bubble-ai">
                                <div class="fw-bold mb-1 text-warning"><i class="bi bi-stars me-1"></i> AI Explanation:</div>
                                Think of <code>async/await</code> like ordering coffee:
                                <ul class="mb-1 ps-3 mt-2">
                                    <li><code>async</code> means your function returns a Promise.</li>
                                    <li><code>await</code> pauses execution until the coffee (data) is ready, without freezing the rest of your app!</li>
                                </ul>
                            </div>

                            <!-- Student Reply Bubble -->
                            <div class="chat-bubble chat-bubble-user">
                                That makes total sense! Can you give me a code example?
                            </div>

                            <!-- Typing Indicator Mockup -->
                            <div class="d-flex align-items-center gap-2 text-white-50 fs-7 pt-2">
                                <div class="spinner-grow spinner-grow-sm text-warning" role="status"></div>
                                <span>AI Tutor is generating example code...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
