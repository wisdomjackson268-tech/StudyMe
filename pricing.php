<?php
/**
 * StudyMe AI Platform — Official Pricing Overview
 */
require_once __DIR__ . '/config/main.php';

$rates = get_official_pricing_rates();

include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <!-- Header -->
        <div class="text-center max-w-700 mx-auto mb-5">
            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-bold text-uppercase mb-3">
                Official Platform Rates
            </span>
            <h1 class="display-5 fw-bold mb-2">Transparent, Simple Pricing</h1>
            <p class="lead text-muted">
                Each selected course or subject is priced according to its academic category. Access is granted strictly per course.
            </p>
        </div>

        <!-- Plans Grid -->
        <div class="row g-4 justify-content-center">
            <!-- Secondary / WAEC / NECO -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 d-flex flex-column hover-lift">
                    <div class="p-3 bg-info bg-opacity-10 text-info rounded-3 d-inline-flex mb-3" style="width: fit-content;">
                        <i class="bi bi-backpack-fill fs-3"></i>
                    </div>
                    <h4 class="fw-bold mb-1">Secondary / WAEC / NECO</h4>
                    <p class="text-muted small mb-3">For SSCE, WAEC, NECO &amp; JAMB subject preparation.</p>
                    <div class="my-3">
                        <span class="fs-2 fw-bold text-main">₦<?= number_format($rates['secondary'], 0) ?></span>
                        <span class="text-muted small">/ per subject</span>
                    </div>
                    <ul class="list-unstyled small text-muted d-flex flex-column gap-2 mb-4">
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> WAEC, NECO &amp; Secondary Subjects</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> 24/7 AI Homework Assistant</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> Interactive Topic Quizzes</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> Access to selected course ONLY</li>
                    </ul>
                    <a href="<?= url('courses/index.php') ?>" class="btn btn-outline-primary rounded-pill w-100 py-3 fw-bold mt-auto" data-feedback="click">
                        Browse Subjects <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- University Courses -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 d-flex flex-column hover-lift">
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3 d-inline-flex mb-3" style="width: fit-content;">
                        <i class="bi bi-mortarboard-fill fs-3"></i>
                    </div>
                    <h4 class="fw-bold mb-1">University</h4>
                    <p class="text-muted small mb-3">For higher education concepts, degree modules &amp; projects.</p>
                    <div class="my-3">
                        <span class="fs-2 fw-bold text-main">₦<?= number_format($rates['university'], 0) ?></span>
                        <span class="text-muted small">/ per course</span>
                    </div>
                    <ul class="list-unstyled small text-muted d-flex flex-column gap-2 mb-4">
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> Undergraduate &amp; Degree Subjects</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> AI Research Companion</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> Verified Digital Certificates</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> Access to selected course ONLY</li>
                    </ul>
                    <a href="<?= url('courses/index.php') ?>" class="btn btn-outline-primary rounded-pill w-100 py-3 fw-bold mt-auto" data-feedback="click">
                        Browse Courses <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- Technology Courses (Best Value) -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 d-flex flex-column hover-lift border-primary" style="border: 2px solid var(--primary-color) !important;">
                    <div class="badge bg-primary text-white rounded-pill px-3 py-1 mb-3 align-self-start fw-bold small">High Demand</div>
                    <h4 class="fw-bold mb-1">Technology</h4>
                    <p class="text-muted small mb-3">Coding bootcamps, AI, Data Science &amp; Cybersecurity.</p>
                    <div class="my-3">
                        <span class="fs-2 fw-bold text-main">₦<?= number_format($rates['tech'], 0) ?></span>
                        <span class="text-muted small">/ per course</span>
                    </div>
                    <ul class="list-unstyled small text-muted d-flex flex-column gap-2 mb-4">
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> Python, Web Dev, AI, Security, etc.</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> AI Code Debugger &amp; Assistant</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> Capstone Projects &amp; Quizzes</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> Access to selected course ONLY</li>
                    </ul>
                    <a href="<?= url('courses/index.php') ?>" class="btn btn-primary rounded-pill w-100 py-3 fw-bold mt-auto shadow" data-feedback="click">
                        Explore Tech Courses <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- Teacher Plan -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 d-flex flex-column hover-lift">
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-3 d-inline-flex mb-3" style="width: fit-content;">
                        <i class="bi bi-person-badge-fill fs-3"></i>
                    </div>
                    <h4 class="fw-bold mb-1">Instructor Suite</h4>
                    <p class="text-muted small mb-3">Tools for teachers to create, publish &amp; manage courses.</p>
                    <div class="my-3">
                        <span class="fs-2 fw-bold text-main">₦<?= number_format($rates['teacher'], 0) ?></span>
                        <span class="text-muted small">/ access</span>
                    </div>
                    <ul class="list-unstyled small text-muted d-flex flex-column gap-2 mb-4">
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> Unlimited Course Publishing</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> Interactive Quiz &amp; Task Builder</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> PDF &amp; Resource Upload Manager</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> Student Analytics &amp; Roster</li>
                    </ul>
                    <a href="<?= url('auth/teacher-register.php') ?>" class="btn btn-outline-success rounded-pill w-100 py-3 fw-bold mt-auto" data-feedback="click">
                        Join as Instructor <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
