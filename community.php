<?php

require_once __DIR__ . '/config/main.php';
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">

        <div class="text-center max-w-700 mx-auto mb-5 animate-fade-in">
            <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex mb-3">
                <i class="bi bi-people-fill fs-1"></i>
            </div>
            <h1 class="display-5 fw-bold mb-2">StudyMe Community Hub</h1>
            <p class="lead text-muted">A collaborative space for ambitious students, passionate teachers, and AI learning enthusiasts.</p>
        </div>

        <div class="row g-4 mb-5">

            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 hover-lift">
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3 d-inline-flex mb-3 fs-3">
                        <i class="bi bi-chat-left-text-fill"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Study Groups &amp; Circles</h4>
                    <p class="text-muted small mb-4">Connect with classmates taking the same courses, form study cohorts, share lecture notes, and tackle challenging projects together.</p>
                    <a href="<?= url('courses/index.php') ?>" class="btn btn-outline-primary btn-sm rounded-pill mt-auto fw-bold">Explore Active Courses</a>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 hover-lift">
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-3 d-inline-flex mb-3 fs-3">
                        <i class="bi bi-person-video3"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Educator Network</h4>
                    <p class="text-muted small mb-4">Exchange teaching methodologies, review curriculum best practices, and collaborate on cutting-edge AI study templates.</p>
                    <a href="<?= url('teachers.php') ?>" class="btn btn-outline-success btn-sm rounded-pill mt-auto fw-bold">Meet Our Teachers</a>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 hover-lift">
                    <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-3 d-inline-flex mb-3 fs-3">
                        <i class="bi bi-cpu-fill"></i>
                    </div>
                    <h4 class="fw-bold mb-2">AI Innovation Forum</h4>
                    <p class="text-muted small mb-4">Discover the latest pedagogical breakthroughs in machine learning, prompt engineering for students, and cognitive study methods.</p>
                    <a href="<?= url('ai-learning.php') ?>" class="btn btn-outline-warning btn-sm rounded-pill mt-auto fw-bold text-dark">Explore AI Learning</a>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-5">
            <h3 class="fw-bold mb-4"><i class="bi bi-shield-check text-primary me-2"></i> Community Guidelines</h3>
            <div class="row g-4 text-secondary">
                <div class="col-md-6">
                    <div class="d-flex gap-3">
                        <div class="fs-4 text-primary"><i class="bi bi-check2-circle"></i></div>
                        <div>
                            <h6 class="fw-bold text-main">Mutual Respect &amp; Inclusivity</h6>
                            <p class="small mb-0">Treat all students and instructors with kindness and constructive empathy, regardless of background or skill level.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex gap-3">
                        <div class="fs-4 text-primary"><i class="bi bi-check2-circle"></i></div>
                        <div>
                            <h6 class="fw-bold text-main">Academic Integrity</h6>
                            <p class="small mb-0">Use AI Tutors as a pedagogical learning guide to master concepts, rather than to submit plagiarized assignments.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex gap-3">
                        <div class="fs-4 text-primary"><i class="bi bi-check2-circle"></i></div>
                        <div>
                            <h6 class="fw-bold text-main">Constructive Collaboration</h6>
                            <p class="small mb-0">Help fellow learners by explaining concepts clearly, sharing helpful resources, and encouraging perseverance.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex gap-3">
                        <div class="fs-4 text-primary"><i class="bi bi-check2-circle"></i></div>
                        <div>
                            <h6 class="fw-bold text-main">Safe &amp; Clean Environment</h6>
                            <p class="small mb-0">Spam, commercial solicitations, harassment, and unsafe links are strictly prohibited and result in immediate account suspension.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 bg-primary text-white rounded-4 p-4 p-md-5 text-center shadow-lg">
            <h2 class="fw-bold mb-3">Join the Official StudyMe Community</h2>
            <p class="lead text-white-50 max-w-700 mx-auto mb-4">
                Connect with thousands of students and teachers across Nigeria and worldwide.
            </p>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="<?= url('auth/register.php') ?>" class="btn btn-warning btn-lg rounded-pill px-5 fw-bold shadow" data-feedback="click">
                    <i class="bi bi-person-plus-fill me-2"></i> Join Free Today
                </a>
                <a href="<?= url('contact.php') ?>" class="btn btn-outline-light btn-lg rounded-pill px-4 fw-bold" data-feedback="click">
                    <i class="bi bi-envelope-fill me-2"></i> Contact Community Team
                </a>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
