<?php
/**
 * StudyMe AI Platform — Teacher Category & Instructor Suite Program
 * Details educator benefits, course creation, student telemetry, and registration workflow (@ ₦5,000).
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/pricing.php';

$rates = get_official_pricing_rates();
$teacherPrice = $rates['teacher'] ?? 5000.00;

$pageTitle = 'Teacher Suite & Instructor Program — StudyMe';
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        
        <!-- Breadcrumbs -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="<?= url('index.php') ?>" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= url('courses/index.php') ?>" class="text-decoration-none">Courses</a></li>
                <li class="breadcrumb-item active" aria-current="page">Teacher</li>
            </ol>
        </nav>

        <!-- Hero Section -->
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-5 position-relative overflow-hidden" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); color:#fff;">
            <div class="row align-items-center g-4 position-relative" style="z-index: 2;">
                <div class="col-lg-8">
                    <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold text-uppercase small mb-3">
                        <i class="bi bi-person-workspace me-1"></i> Educator &amp; Instructor Program
                    </span>
                    <h1 class="display-5 fw-bold text-white mb-2">Teach, Publish &amp; Inspire on StudyMe</h1>
                    <p class="text-white-50 lead fs-6 mb-4 max-w-600">
                        Join our network of verified educators. Build modern digital curriculums, deliver video lessons, generate auto-graded quizzes, and track student analytics with 24/7 AI tutor co-pilot.
                    </p>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <a href="<?= url('auth/teacher-register.php') ?>" class="btn btn-warning btn-lg rounded-pill px-5 fw-bold text-dark shadow">
                            Become a Teacher &rarr;
                        </a>
                        <span class="badge bg-white bg-opacity-15 text-white rounded-pill px-3 py-2 fs-6 border border-white border-opacity-20">
                            Instructor License: ₦<?= number_format($teacherPrice, 0) ?>
                        </span>
                    </div>
                </div>
                <div class="col-lg-4 text-center d-none d-lg-block">
                    <div class="p-4 bg-white bg-opacity-10 rounded-4 border border-white border-opacity-20 text-start">
                        <div class="fs-1 text-warning mb-2"><i class="bi bi-shield-check"></i></div>
                        <h5 class="fw-bold text-white mb-2">Verified Instructor Status</h5>
                        <p class="text-white-50 small mb-0">Publish accredited courses across Technology, Secondary School, and University faculties.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Instructor Features Grid -->
        <div class="text-center max-w-700 mx-auto mb-5">
            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 fw-bold text-uppercase small mb-2">
                What You Receive
            </span>
            <h2 class="fw-bold mb-2">Everything You Need to Teach Online</h2>
            <p class="text-muted">StudyMe equips teachers with state-of-the-art authoring tools, assessment engines, and audience outreach.</p>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4" style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e2e8f0) !important;">
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3 d-inline-flex mb-3" style="width: 50px; height: 50px; align-items: center; justify-content: center;">
                        <i class="bi bi-collection-play-fill fs-4"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Course &amp; Lesson Creation</h5>
                    <p class="text-muted small mb-0">Create structured syllabus modules, organize video lessons, write markdown summaries, and attach downloadable PDF study guides.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4" style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e2e8f0) !important;">
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-3 d-inline-flex mb-3" style="width: 50px; height: 50px; align-items: center; justify-content: center;">
                        <i class="bi bi-patch-question-fill fs-4"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Auto-Graded Quizzes</h5>
                    <p class="text-muted small mb-0">Build rich multiple-choice assessment banks with automated scoring, custom passing thresholds, and solution explanations.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4" style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e2e8f0) !important;">
                    <div class="p-3 bg-info bg-opacity-10 text-info rounded-3 d-inline-flex mb-3" style="width: 50px; height: 50px; align-items: center; justify-content: center;">
                        <i class="bi bi-graph-up-arrow fs-4"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Student Telemetry &amp; Grading</h5>
                    <p class="text-muted small mb-0">Monitor student syllabus completion rates in real time, review homework submissions, and issue feedback directly.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4" style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e2e8f0) !important;">
                    <div class="p-3 bg-warning bg-opacity-10 text-warning-emphasis rounded-3 d-inline-flex mb-3" style="width: 50px; height: 50px; align-items: center; justify-content: center;">
                        <i class="bi bi-robot fs-4 text-warning"></i>
                    </div>
                    <h5 class="fw-bold mb-2">AI Teaching Co-Pilot</h5>
                    <p class="text-muted small mb-0">Our 24/7 AI tutor assists your enrolled students with instant explanations for your lesson notes whenever they get stuck.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4" style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e2e8f0) !important;">
                    <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-3 d-inline-flex mb-3" style="width: 50px; height: 50px; align-items: center; justify-content: center;">
                        <i class="bi bi-award-fill fs-4"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Verifiable Certificates</h5>
                    <p class="text-muted small mb-0">Award custom digitally-signed completion certificates bearing your name and StudyMe accreditation verification codes.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4" style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e2e8f0) !important;">
                    <div class="p-3 bg-secondary bg-opacity-10 text-secondary rounded-3 d-inline-flex mb-3" style="width: 50px; height: 50px; align-items: center; justify-content: center;">
                        <i class="bi bi-speedometer2 fs-4"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Dedicated Teacher Dashboard</h5>
                    <p class="text-muted small mb-0">Full command center to manage your active courses, drafts, teaching profile, qualifications, and student questions.</p>
                </div>
            </div>
        </div>

        <!-- Call to Action Banner -->
        <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5 text-center bg-primary text-white">
            <h2 class="fw-bold mb-2 text-white">Ready to Start Teaching on StudyMe?</h2>
            <p class="text-white-50 lead fs-6 max-w-600 mx-auto mb-4">
                Register your educator account today for ₦<?= number_format($teacherPrice, 0) ?> and start building your courses with thousands of active learners.
            </p>
            <div>
                <a href="<?= url('auth/teacher-register.php') ?>" class="btn btn-warning btn-lg rounded-pill px-5 py-3 fw-bold text-dark shadow" data-feedback="success">
                    <i class="bi bi-person-plus-fill me-2"></i> Become a Teacher Now
                </a>
            </div>
        </div>

    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
