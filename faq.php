<?php

require_once __DIR__ . '/config/main.php';
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">

        <div class="text-center max-w-700 mx-auto mb-5 animate-fade-in">
            <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex mb-3">
                <i class="bi bi-question-diamond-fill fs-1"></i>
            </div>
            <h1 class="display-5 fw-bold mb-2">Frequently Asked Questions</h1>
            <p class="lead text-muted">Everything you need to know about StudyMe, AI tutors, courses, verified certificates, and teaching.</p>
        </div>

        <div class="row justify-content-center mb-5">
            <div class="col-lg-8">
                <div class="input-group input-group-lg shadow-sm rounded-4 overflow-hidden mb-4">
                    <span class="input-group-text bg-white border-0 ps-4 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="faqSearch" class="form-control border-0 py-3" placeholder="Search questions (e.g. AI Tutor, certificates, enroll, teacher)...">
                </div>

                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <button class="btn btn-outline-primary btn-sm rounded-pill active faq-filter-btn" data-filter="all">All FAQs</button>
                    <button class="btn btn-outline-primary btn-sm rounded-pill faq-filter-btn" data-filter="general">General</button>
                    <button class="btn btn-outline-primary btn-sm rounded-pill faq-filter-btn" data-filter="ai">AI Learning</button>
                    <button class="btn btn-outline-primary btn-sm rounded-pill faq-filter-btn" data-filter="courses">Courses &amp; Quizzes</button>
                    <button class="btn btn-outline-primary btn-sm rounded-pill faq-filter-btn" data-filter="teachers">Teaching</button>
                    <button class="btn btn-outline-primary btn-sm rounded-pill faq-filter-btn" data-filter="certificates">Certificates</button>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="accordion custom-accordion" id="faqAccordion">

                    <div class="accordion-item faq-item" data-category="general">
                        <h2 class="accordion-header" id="headingGen1">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGen1" aria-expanded="true" aria-controls="collapseGen1">
                                <i class="bi bi-mortarboard me-2 text-primary"></i> What is StudyMe?
                            </button>
                        </h2>
                        <div id="collapseGen1" class="accordion-collapse collapse show" aria-labelledby="headingGen1" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary lh-lg">
                                StudyMe is an advanced <strong>AI-Powered Learning Platform</strong> combining expert instructor video courses with real-time 24/7 AI tutoring. Whether you are preparing for secondary school examinations (WAEC/JAMB), studying university curricula, or learning tech skills (Full Stack Development, Python, AI), StudyMe adapts to your pace and ensures complete concept mastery.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item faq-item" data-category="general">
                        <h2 class="accordion-header" id="headingGen2">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGen2" aria-expanded="false" aria-controls="collapseGen2">
                                <i class="bi bi-person-check me-2 text-primary"></i> How do I create an account?
                            </button>
                        </h2>
                        <div id="collapseGen2" class="accordion-collapse collapse" aria-labelledby="headingGen2" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary lh-lg">
                                Simply click <a href="<?= url('auth/register.php') ?>" class="fw-bold">Get Started / Register</a> in the top navigation or footer. Choose whether you are registering as a Student or applying as an Instructor. Complete the form to instantly access your dashboard.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item faq-item" data-category="ai">
                        <h2 class="accordion-header" id="headingAI1">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAI1" aria-expanded="false" aria-controls="collapseAI1">
                                <i class="bi bi-robot me-2 text-warning"></i> How does the StudyMe AI Tutor work?
                            </button>
                        </h2>
                        <div id="collapseAI1" class="accordion-collapse collapse" aria-labelledby="headingAI1" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary lh-lg">
                                The AI Tutor is accessible inside every lesson viewer and from the floating AI assistant widget. It can break down difficult concepts with simple analogies, walk you through code step-by-step using Socratic questioning, summarize lengthy readings, and generate practice questions tailored to the exact topic you are studying.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item faq-item" data-category="ai">
                        <h2 class="accordion-header" id="headingAI2">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAI2" aria-expanded="false" aria-controls="collapseAI2">
                                <i class="bi bi-stars me-2 text-warning"></i> Can the AI generate quizzes from my course materials?
                            </button>
                        </h2>
                        <div id="collapseAI2" class="accordion-collapse collapse" aria-labelledby="headingAI2" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary lh-lg">
                                Yes! In the AI Learning module, you can ask the AI Tutor to generate diagnostic multiple-choice or short-answer quizzes for any syllabus topic, helping you pinpoint knowledge gaps before real exams.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item faq-item" data-category="courses">
                        <h2 class="accordion-header" id="headingCourse1">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCourse1" aria-expanded="false" aria-controls="collapseCourse1">
                                <i class="bi bi-play-circle me-2 text-info"></i> How do I enroll in and watch courses?
                            </button>
                        </h2>
                        <div id="collapseCourse1" class="accordion-collapse collapse" aria-labelledby="headingCourse1" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary lh-lg">
                                Head over to the <a href="<?= url('courses/index.php') ?>" class="fw-bold">Course Catalog</a>. Select any course to view its curriculum syllabus, instructor qualifications, and learning outcomes. Click <strong>Enroll Now</strong> or <strong>Start Learning</strong> to open the lesson viewer.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item faq-item" data-category="courses">
                        <h2 class="accordion-header" id="headingCourse2">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCourse2" aria-expanded="false" aria-controls="collapseCourse2">
                                <i class="bi bi-clock-history me-2 text-info"></i> Are courses self-paced?
                            </button>
                        </h2>
                        <div id="collapseCourse2" class="accordion-collapse collapse" aria-labelledby="headingCourse2" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary lh-lg">
                                Yes. All courses are 100% self-paced with 24/7 on-demand access on desktop, tablet, and mobile browsers. Your progress is automatically synced to the database as you complete lessons and quizzes.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item faq-item" data-category="teachers">
                        <h2 class="accordion-header" id="headingTeach1">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTeach1" aria-expanded="false" aria-controls="collapseTeach1">
                                <i class="bi bi-person-workspace me-2 text-success"></i> How do I become an instructor on StudyMe?
                            </button>
                        </h2>
                        <div id="collapseTeach1" class="accordion-collapse collapse" aria-labelledby="headingTeach1" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary lh-lg">
                                Visit <a href="<?= url('auth/teacher-register.php') ?>" class="fw-bold">Become an Instructor</a>. Submit your qualifications and specialization. Once approved by the administration team, you gain access to the Teacher Dashboard to create courses, upload video/PDF resources, author quizzes, and mentor enrolled students.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item faq-item" data-category="teachers">
                        <h2 class="accordion-header" id="headingTeach2">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTeach2" aria-expanded="false" aria-controls="collapseTeach2">
                                <i class="bi bi-file-earmark-arrow-up me-2 text-success"></i> What types of resources can teachers upload?
                            </button>
                        </h2>
                        <div id="collapseTeach2" class="accordion-collapse collapse" aria-labelledby="headingTeach2" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary lh-lg">
                                Teachers can upload MP4/WebM lesson videos, PDFs, Microsoft Word (.doc, .docx) documents, PowerPoint (.ppt, .pptx) presentations, code files, and lecture notes.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item faq-item" data-category="certificates">
                        <h2 class="accordion-header" id="headingCert1">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCert1" aria-expanded="false" aria-controls="collapseCert1">
                                <i class="bi bi-award me-2 text-warning"></i> How do I earn and verify a StudyMe Certificate?
                            </button>
                        </h2>
                        <div id="collapseCert1" class="accordion-collapse collapse" aria-labelledby="headingCert1" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary lh-lg">
                                When your course progress reaches 100% and you pass the required quizzes, a digital certificate is automatically generated with a unique certificate ID. Anyone can verify its authenticity anytime using our <a href="<?= url('certificates/verify.php') ?>" class="fw-bold">Certificate Verification Portal</a>.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mt-5 text-center">
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex mb-3 fs-3">
                        <i class="bi bi-headset"></i>
                    </div>
                    <h3 class="fw-bold mb-2">Still have questions?</h3>
                    <p class="text-muted mb-4 max-w-600 mx-auto">Can't find the answer you're looking for? Our friendly support team is always available to assist you.</p>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <a href="<?= url('contact.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold" data-feedback="click">
                            <i class="bi bi-envelope-fill me-2"></i> Contact Support
                        </a>
                        <a href="<?= url('help.php') ?>" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-feedback="click">
                            <i class="bi bi-life-preserver me-2"></i> Visit Help Center
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("faqSearch");
    const filterButtons = document.querySelectorAll(".faq-filter-btn");
    const faqItems = document.querySelectorAll(".faq-item");

    // Filter Buttons
    filterButtons.forEach(btn => {
        btn.addEventListener("click", () => {
            filterButtons.forEach(b => b.classList.remove("active"));
            btn.classList.add("active");
            const filter = btn.dataset.filter;

            faqItems.forEach(item => {
                const cat = item.dataset.category;
                if (filter === "all" || cat === filter) {
                    item.style.display = "";
                } else {
                    item.style.display = "none";
                }
            });
        });
    });

    // Real-time Search
    if (searchInput) {
        searchInput.addEventListener("input", (e) => {
            const query = e.target.value.toLowerCase().trim();
            faqItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(query)) {
                    item.style.display = "";
                } else {
                    item.style.display = "none";
                }
            });
        });
    }
});
</script>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
