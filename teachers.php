<?php

require_once __DIR__ . '/config/main.php';
include BASE_PATH . '/includes/layouts/header.php';

$pdo = getDBConnection();
$teachers = [];
try {
    $stmt = $pdo->query("
        SELECT t.*, u.first_name, u.last_name, u.avatar, u.email
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        WHERE t.status = 'active'
        ORDER BY t.rating DESC
    ");
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching teachers: " . $e->getMessage());
}
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">

        <div class="text-center max-w-700 mx-auto mb-5 animate-fade-in">
            <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle d-inline-flex mb-3">
                <?php include BASE_PATH . '/assets/svg/teacher.svg'; ?>
            </div>
            <h1 class="display-5 fw-bold mb-2">Learn from Leading Minds</h1>
            <p class="lead text-muted">Interact with certified instructors who structure top-tier courses, mentor students, and customize AI learning templates.</p>
        </div>

        <div class="row g-4 justify-content-center mb-5">
            <?php if (!empty($teachers)): ?>
                <?php foreach ($teachers as $t): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden hover-lift">
                            <div class="card-body p-4 text-center">
                                <a href="<?= url('teacher-profile.php?id=' . (int)$t['id']) ?>" class="d-inline-block text-decoration-none">
                                    <img src="<?= e(get_teacher_avatar_url($t['avatar'] ?? '', ($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? ''), $t['id'] ?? 0)) ?>"
                                         alt="StudyMe Certified Instructor <?= e($t['first_name'] . ' ' . $t['last_name']) ?>"
                                         class="rounded-circle mb-3 border border-3 border-light shadow-sm"
                                         style="width: 100px; height: 100px; object-fit: cover;"
                                         onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&q=80';">
                                </a>

                                <h5 class="fw-bold mb-1">
                                    <a href="<?= url('teacher-profile.php?id=' . (int)$t['id']) ?>" class="text-decoration-none text-main hover-primary">
                                        <?= e($t['first_name'] . ' ' . $t['last_name']) ?>
                                    </a>
                                </h5>
                                <div class="badge bg-primary bg-opacity-10 text-primary mb-2 rounded-pill small px-3">
                                    <?= e($t['qualification'] ?? 'Certified Instructor') ?>
                                </div>
                                <p class="text-muted small mb-3" style="min-height: 40px;"><?= e($t['specialization']) ?></p>

                                <div class="d-flex justify-content-center align-items-center gap-3 py-2 border-top border-bottom border-light mb-3">
                                    <div class="text-center">
                                        <div class="fw-bold text-main small"><?= (int)$t['total_courses'] ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;">Courses</div>
                                    </div>
                                    <div class="vr bg-light"></div>
                                    <div class="text-center">
                                        <div class="fw-bold text-main small"><?= number_format($t['rating'], 2) ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><i class="bi bi-star-fill text-warning me-1"></i>Rating</div>
                                    </div>
                                    <div class="vr bg-light"></div>
                                    <div class="text-center">
                                        <div class="fw-bold text-main small"><?= number_format($t['total_students']) ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;">Learners</div>
                                    </div>
                                </div>

                                <p class="text-secondary small line-clamp-3 mb-0 text-start"><?= e($t['bio']) ?></p>
                            </div>
                            <div class="card-footer bg-transparent border-0 px-4 pb-4 d-flex gap-2">
                                <a href="<?= url('teacher-profile.php?id=' . (int)$t['id']) ?>" class="btn btn-primary rounded-pill flex-grow-1 py-2 fw-bold" data-feedback="click">
                                    <i class="bi bi-person-fill me-1"></i> Profile
                                </a>
                                <a href="<?= url('courses/index.php?instructor=' . (int)$t['id']) ?>" class="btn btn-outline-primary rounded-pill px-3 py-2 fw-bold" title="View Courses" data-feedback="click">
                                    <i class="bi bi-collection-play-fill"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-people-fill text-muted" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold mt-3">No instructors currently registered.</h5>
                    <p class="text-muted">Database contains no active profiles. Check seed credentials.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="p-5 bg-primary bg-opacity-5 rounded-4 text-center border border-secondary border-opacity-10 py-5">
            <h3 class="fw-bold mb-2">Are You an Educator?</h3>
            <p class="text-muted max-w-600 mx-auto mb-4">Share your knowledge with thousands of students. Utilize AI tools to draft curriculum templates, generate smart quiz categories, and manage student analytics.</p>
            <a href="<?= url('courses/teacher.php') ?>" class="btn btn-primary rounded-pill px-5 py-3 fw-bold shadow" data-feedback="success">
                Explore Instructor Program &rarr;
            </a>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
