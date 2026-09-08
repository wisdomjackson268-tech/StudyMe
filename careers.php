<?php
/**
 * StudyMe AI Platform — Careers & Opportunities
 */
require_once __DIR__ . '/config/main.php';
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <!-- Header -->
        <div class="text-center max-w-700 mx-auto mb-5 animate-fade-in">
            <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex mb-3">
                <i class="bi bi-briefcase-fill fs-1"></i>
            </div>
            <h1 class="display-5 fw-bold mb-2">Build the Future of AI Education</h1>
            <p class="lead text-muted">Join our mission to empower millions of students and educators with cutting-edge AI learning tools.</p>
        </div>

        <!-- Values Grid -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center">
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex mx-auto mb-3 fs-3">
                        <i class="bi bi-stars"></i>
                    </div>
                    <h5 class="fw-bold mb-2">AI Innovation First</h5>
                    <p class="text-muted small mb-0">We push the boundaries of Large Language Models and cognitive science to personalize learning at unprecedented scale.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center">
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle d-inline-flex mx-auto mb-3 fs-3">
                        <i class="bi bi-globe-americas"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Remote-First Culture</h5>
                    <p class="text-muted small mb-0">Work from anywhere with flexible hours, competitive compensation, and high-trust, asynchronous collaboration.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center">
                    <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-circle d-inline-flex mx-auto mb-3 fs-3">
                        <i class="bi bi-heart-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Massive Social Impact</h5>
                    <p class="text-muted small mb-0">Every line of code and curriculum module you create directly improves educational accessibility across Africa and beyond.</p>
                </div>
            </div>
        </div>

        <!-- Open Roles List -->
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-5">
            <h3 class="fw-bold mb-4">Current Open Positions</h3>
            
            <div class="list-group list-group-flush">
                <!-- Role 1 -->
                <div class="list-group-item px-0 py-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h5 class="fw-bold mb-0">Senior AI / LLM Prompt Engineer</h5>
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill small">Full-Time</span>
                        </div>
                        <p class="text-muted small mb-0">Engineering &bull; Remote &bull; Lagos / Global</p>
                        <p class="text-secondary small mt-2 mb-0">Design, optimize, and evaluate Socratic tutoring prompts and multi-turn educational agents for STEM subjects.</p>
                    </div>
                    <a href="<?= url('contact.php?subject=' . urlencode('Application: Senior AI / LLM Prompt Engineer')) ?>" class="btn btn-outline-primary rounded-pill px-4 fw-bold text-nowrap">
                        Apply Now &rarr;
                    </a>
                </div>

                <!-- Role 2 -->
                <div class="list-group-item px-0 py-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h5 class="fw-bold mb-0">Senior Full-Stack PHP / MySQL Engineer</h5>
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill small">Full-Time</span>
                        </div>
                        <p class="text-muted small mb-0">Engineering &bull; Remote &bull; Lagos / Global</p>
                        <p class="text-secondary small mt-2 mb-0">Scale the core StudyMe platform, video streaming pipelines, quiz evaluation engines, and teacher dashboards.</p>
                    </div>
                    <a href="<?= url('contact.php?subject=' . urlencode('Application: Senior Full-Stack Engineer')) ?>" class="btn btn-outline-primary rounded-pill px-4 fw-bold text-nowrap">
                        Apply Now &rarr;
                    </a>
                </div>

                <!-- Role 3 -->
                <div class="list-group-item px-0 py-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h5 class="fw-bold mb-0">Curriculum &amp; Pedagogical Lead (STEM / Tech)</h5>
                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill small">Contract / Full-Time</span>
                        </div>
                        <p class="text-muted small mb-0">Education &bull; Hybrid / Remote &bull; Lagos, Nigeria</p>
                        <p class="text-secondary small mt-2 mb-0">Curate standard syllabi for secondary examination prep (WAEC/JAMB) and university computer science programs.</p>
                    </div>
                    <a href="<?= url('contact.php?subject=' . urlencode('Application: Curriculum Lead')) ?>" class="btn btn-outline-primary rounded-pill px-4 fw-bold text-nowrap">
                        Apply Now &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Instructor CTA -->
        <div class="card border-0 bg-primary text-white rounded-4 p-4 p-md-5 text-center shadow-lg">
            <h2 class="fw-bold mb-3">Want to Teach Your Own Courses?</h2>
            <p class="lead text-white-50 max-w-700 mx-auto mb-4">
                You don't have to be a full-time employee to share your knowledge. Apply as an independent instructor on StudyMe.
            </p>
            <a href="<?= url('auth/teacher-register.php') ?>" class="btn btn-warning btn-lg rounded-pill px-5 fw-bold shadow" data-feedback="click">
                <i class="bi bi-person-badge-fill me-2"></i> Apply to Teach on StudyMe
            </a>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
