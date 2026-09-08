<?php
/**
 * StudyMe AI Platform — Help Center
 */
require_once __DIR__ . '/config/main.php';
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <div class="text-center max-w-700 mx-auto mb-5 animate-fade-in">
            <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex mb-3">
                <i class="bi bi-life-preserver fs-1"></i>
            </div>
            <h1 class="display-5 fw-bold mb-2">StudyMe Help Center</h1>
            <p class="lead text-muted">Guides, tutorials, and troubleshooting resources to help you get the most out of StudyMe.</p>

            <div class="input-group input-group-lg shadow-sm rounded-4 overflow-hidden mt-4">
                <span class="input-group-text bg-white border-0 ps-4 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="helpSearch" class="form-control border-0 py-3" placeholder="Search guides, tutorials, or error troubleshooting...">
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 hover-lift help-card">
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3 d-inline-flex mb-3" style="width: fit-content;">
                        <i class="bi bi-person-check-fill fs-3"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Getting Started</h4>
                    <p class="text-muted small mb-3">Account setup, profile editing, password resets, and choosing your learning role.</p>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-4 text-secondary">
                        <li><a href="<?= url('faq.php') ?>#headingGen2" class="text-decoration-none text-secondary hover-primary"><i class="bi bi-chevron-right me-1 text-primary"></i> How to create your free account</a></li>
                        <li><a href="<?= url('auth/forgot-password.php') ?>" class="text-decoration-none text-secondary hover-primary"><i class="bi bi-chevron-right me-1 text-primary"></i> Resetting your password</a></li>
                        <li><a href="<?= url('student/profile.php') ?>" class="text-decoration-none text-secondary hover-primary"><i class="bi bi-chevron-right me-1 text-primary"></i> Updating profile name &amp; avatar</a></li>
                    </ul>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 hover-lift help-card">
                    <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-3 d-inline-flex mb-3" style="width: fit-content;">
                        <i class="bi bi-robot fs-3"></i>
                    </div>
                    <h4 class="fw-bold mb-2">AI Tutor &amp; Tools</h4>
                    <p class="text-muted small mb-3">Master concepts with real-time AI explanations, quiz generation, and Socratic code hints.</p>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-4 text-secondary">
                        <li><a href="<?= url('ai-learning.php') ?>" class="text-decoration-none text-secondary hover-primary"><i class="bi bi-chevron-right me-1 text-warning"></i> Accessing the 24/7 AI Tutor</a></li>
                        <li><a href="<?= url('ai-learning.php') ?>" class="text-decoration-none text-secondary hover-primary"><i class="bi bi-chevron-right me-1 text-warning"></i> Generating custom practice quizzes</a></li>
                        <li><a href="<?= url('ai-learning.php') ?>" class="text-decoration-none text-secondary hover-primary"><i class="bi bi-chevron-right me-1 text-warning"></i> Socratic code troubleshooting</a></li>
                    </ul>
                </div>
            </div>

            <!-- 3. Courses & Lessons -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 hover-lift help-card">
                    <div class="p-3 bg-info bg-opacity-10 text-info rounded-3 d-inline-flex mb-3" style="width: fit-content;">
                        <i class="bi bi-collection-play-fill fs-3"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Courses &amp; Video Lessons</h4>
                    <p class="text-muted small mb-3">Navigating the syllabus, streaming lesson videos, reading PDFs, and tracking progress.</p>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-4 text-secondary">
                        <li><a href="<?= url('courses/index.php') ?>" class="text-decoration-none text-secondary hover-primary"><i class="bi bi-chevron-right me-1 text-info"></i> Enrolling in published courses</a></li>
                        <li><a href="<?= url('courses/index.php') ?>" class="text-decoration-none text-secondary hover-primary"><i class="bi bi-chevron-right me-1 text-info"></i> Filtering by level and subject</a></li>
                        <li><a href="<?= url('student/my-courses.php') ?>" class="text-decoration-none text-secondary hover-primary"><i class="bi bi-chevron-right me-1 text-info"></i> Resuming where you left off</a></li>
                    </ul>
                </div>
            </div>

            <!-- 4. Teacher Tools & Uploads -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 hover-lift help-card">
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-3 d-inline-flex mb-3" style="width: fit-content;">
                        <i class="bi bi-person-badge-fill fs-3"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Teacher &amp; Instructor Toolkit</h4>
                    <p class="text-muted small mb-3">Creating courses, uploading videos/PDFs/DOCX, authoring quizzes, and managing students.</p>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-4 text-secondary">
                        <li><a href="<?= url('auth/teacher-register.php') ?>" class="text-decoration-none text-secondary hover-primary"><i class="bi bi-chevron-right me-1 text-success"></i> Applying as a certified teacher</a></li>
                        <li><a href="<?= url('teacher/create-course.php') ?>" class="text-decoration-none text-secondary hover-primary"><i class="bi bi-chevron-right me-1 text-success"></i> Course creation workflow</a></li>
                        <li><a href="<?= url('teacher/lessons.php') ?>" class="text-decoration-none text-secondary hover-primary"><i class="bi bi-chevron-right me-1 text-success"></i> Uploading lesson resources &amp; videos</a></li>
                    </ul>
                </div>
            </div>

            <!-- 5. Certificates & Quizzes -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 hover-lift help-card">
                    <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-3 d-inline-flex mb-3" style="width: fit-content;">
                        <i class="bi bi-patch-check-fill fs-3"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Certificates &amp; Assessment</h4>
                    <p class="text-muted small mb-3">Taking quizzes, viewing graded results, and generating verifiable digital credentials.</p>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-4 text-secondary">
                        <li><a href="<?= url('certificates/verify.php') ?>" class="text-decoration-none text-secondary hover-primary"><i class="bi bi-chevron-right me-1 text-danger"></i> Online Certificate Verification Portal</a></li>
                        <li><a href="<?= url('student/quizzes.php') ?>" class="text-decoration-none text-secondary hover-primary"><i class="bi bi-chevron-right me-1 text-danger"></i> Passing course assessments</a></li>
                        <li><a href="<?= url('student/certificates.php') ?>" class="text-decoration-none text-secondary hover-primary"><i class="bi bi-chevron-right me-1 text-danger"></i> Downloading PDF certificates</a></li>
                    </ul>
                </div>
            </div>

            <!-- 6. Billing & Access -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 hover-lift help-card">
                    <div class="p-3 bg-secondary bg-opacity-10 text-secondary rounded-3 d-inline-flex mb-3" style="width: fit-content;">
                        <i class="bi bi-credit-card-2-front-fill fs-3"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Billing &amp; Free Access</h4>
                    <p class="text-muted small mb-3">Understanding the current Free Development Mode, subscription tiers, and receipts.</p>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-4 text-secondary">
                        <li><a href="<?= url('pricing.php') ?>" class="text-decoration-none text-secondary hover-primary"><i class="bi bi-chevron-right me-1 text-secondary"></i> 100% Free Development Mode overview</a></li>
                        <li><a href="<?= url('pricing.php') ?>" class="text-decoration-none text-secondary hover-primary"><i class="bi bi-chevron-right me-1 text-secondary"></i> Secondary, University &amp; Tech tiers</a></li>
                        <li><a href="<?= url('payments/history.php') ?>" class="text-decoration-none text-secondary hover-primary"><i class="bi bi-chevron-right me-1 text-secondary"></i> Viewing transaction history</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Contact Support CTA -->
        <div class="card border-0 bg-primary text-white rounded-4 p-4 p-md-5 text-center shadow-lg">
            <h2 class="fw-bold mb-3">Need Personalized Technical Assistance?</h2>
            <p class="lead text-white-50 max-w-700 mx-auto mb-4">
                Our support team and pedagogical counselors are on standby to guide your study journey or resolve issues.
            </p>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="<?= url('contact.php') ?>" class="btn btn-warning btn-lg rounded-pill px-5 fw-bold shadow" data-feedback="click">
                    <i class="bi bi-envelope-fill me-2"></i> Submit a Support Ticket
                </a>
                <a href="<?= url('community.php') ?>" class="btn btn-outline-light btn-lg rounded-pill px-4 fw-bold" data-feedback="click">
                    <i class="bi bi-chat-dots-fill me-2"></i> Ask the Community
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("helpSearch");
    const helpCards = document.querySelectorAll(".help-card");

    if (searchInput) {
        searchInput.addEventListener("input", (e) => {
            const q = e.target.value.toLowerCase().trim();
            helpCards.forEach(card => {
                const text = card.textContent.toLowerCase();
                if (text.includes(q)) {
                    card.parentElement.style.display = "";
                } else {
                    card.parentElement.style.display = "none";
                }
            });
        });
    }
});
</script>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
