<?php
/**
 * StudyMe AI Platform — About StudyMe Page
 */
require_once __DIR__ . '/config/main.php';
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <!-- Header -->
        <div class="text-center max-w-700 mx-auto mb-5 animate-fade-in">
            <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex mb-3">
                <i class="bi bi-mortarboard-fill fs-1"></i>
            </div>
            <h1 class="display-5 fw-bold mb-2">About StudyMe</h1>
            <p class="lead text-muted">The premier <strong>AI-Powered Learning Platform</strong> empowering students and teachers with modern digital education.</p>
        </div>

        <!-- Vision and Mission Cards -->
        <div class="row g-4 mb-5">
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 p-md-5">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle fs-3">
                            <i class="bi bi-eye-fill"></i>
                        </div>
                        <h3 class="fw-bold mb-0 text-primary">Our Vision</h3>
                    </div>
                    <p class="text-secondary lh-lg mb-0">
                        To redefine modern education by delivering intelligent, personalized learning support to every student globally. We believe education should adapt dynamically to each individual's pace, learning style, and goals, removing traditional barriers to academic and professional mastery.
                    </p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 p-md-5">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle fs-3">
                            <i class="bi bi-shield-fill-check"></i>
                        </div>
                        <h3 class="fw-bold mb-0 text-success">Our Mission</h3>
                    </div>
                    <p class="text-secondary lh-lg mb-0">
                        To build a trusted, interactive AI-powered learning ecosystem curated by certified instructors. We equip secondary, university, and tech students with intuitive concept breakdowns, automated practice evaluations, and verifiable credentials that accelerate real-world career growth.
                    </p>
                </div>
            </div>
        </div>

        <!-- What StudyMe Offers -->
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-5">
            <div class="text-center max-w-700 mx-auto mb-5">
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold mb-2">Platform Highlights</span>
                <h2 class="fw-bold">What StudyMe Offers</h2>
                <p class="text-muted">A seamless blend of human pedagogical expertise and artificial intelligence.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="p-4 rounded-4 bg-light-subtle h-100 border border-light">
                        <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-circle d-inline-flex mb-3 fs-3">
                            <i class="bi bi-robot"></i>
                        </div>
                        <h5 class="fw-bold mb-2">24/7 AI Tutor</h5>
                        <p class="text-muted small mb-0">Instant conceptual explanations, Socratic code guidance, customized practice quizzes, and lesson summaries tailored to your syllabus.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-4 rounded-4 bg-light-subtle h-100 border border-light">
                        <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex mb-3 fs-3">
                            <i class="bi bi-person-video3"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Certified Instructors</h5>
                        <p class="text-muted small mb-0">Learn from seasoned educators and industry professionals who structure curriculum, review student assignments, and mentor learners.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-4 rounded-4 bg-light-subtle h-100 border border-light">
                        <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle d-inline-flex mb-3 fs-3">
                            <i class="bi bi-patch-check-fill"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Verifiable Certificates</h5>
                        <p class="text-muted small mb-0">Earn tamper-proof digital certificates upon course completion with unique verification codes that can be shared on LinkedIn and portfolios.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Call to Action -->
        <div class="card border-0 bg-primary text-white rounded-4 p-4 p-md-5 text-center shadow-lg">
            <h2 class="fw-bold mb-3">Ready to Experience AI-Powered Learning?</h2>
            <p class="lead text-white-50 max-w-700 mx-auto mb-4">
                Join thousands of students and educators transforming their study habits on StudyMe today.
            </p>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="<?= url('courses/index.php') ?>" class="btn btn-warning btn-lg rounded-pill px-4 fw-bold shadow-sm" data-feedback="click">
                    <i class="bi bi-compass me-2"></i> Explore Courses
                </a>
                <a href="<?= url('auth/teacher-register.php') ?>" class="btn btn-outline-light btn-lg rounded-pill px-4 fw-bold" data-feedback="click">
                    <i class="bi bi-person-badge me-2"></i> Teach on StudyMe
                </a>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
