<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_TEACHER);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$user = current_user();
$pdo  = getDBConnection();
$uid  = $user['id'];

$stmt = $pdo->prepare("SELECT id, assigned_course_id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;
$assignedCourseId = (int)($teacher['assigned_course_id'] ?? 0);

$courses = [];
if ($tid) {
    $stmt = $pdo->prepare("
        SELECT c.*, cat.name AS category_name, cat.slug AS category_slug,
               (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'active') AS student_count,
               (SELECT COUNT(*) FROM lessons l JOIN course_sections cs ON l.section_id = cs.id WHERE cs.course_id = c.id) AS lesson_count,
               (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.id) AS task_count,
               (SELECT COUNT(*) FROM quizzes q WHERE q.course_id = c.id) AS quiz_count,
               (SELECT COUNT(*) FROM resources r WHERE r.course_id = c.id) AS resource_count
        FROM courses c
        LEFT JOIN categories cat ON c.category_id = cat.id
        WHERE c.teacher_id = ? OR c.id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$tid, $assignedCourseId]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-journal-code text-primary me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0">My Teaching Courses</h2>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= url('teacher/select-course.php') ?>" class="btn btn-outline-primary rounded-pill px-4 fw-bold shadow-sm" data-feedback="click">
            <i class="bi bi-collection-play me-1"></i> Select Existing Course
        </a>
        <a href="<?= url('teacher/create-course.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-feedback="click">
            <i class="bi bi-plus-circle-fill me-1"></i> Create New Course
        </a>
    </div>
</div>

<?php if (!empty($courses)): ?>
    <div class="row g-4 mb-5">
        <?php foreach ($courses as $c): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 d-flex flex-column">
                    <div class="position-relative">
                        <?php $tThumb = function_exists('get_course_thumbnail_url') ? get_course_thumbnail_url($c['thumbnail'] ?? '', $c['category_slug'] ?? 'technology') : ($c['thumbnail'] ?? 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=500&q=80'); ?>
                        <img src="<?= e($tThumb) ?>"
                             class="card-img-top" style="height: 180px; object-fit: cover;" alt="<?= e($c['title']) ?>"
                             onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=500&q=80';">
                        <span class="badge <?= $c['status'] === 'published' ? 'bg-success' : 'bg-warning text-dark' ?> position-absolute top-0 end-0 m-3 rounded-pill px-3 py-2">
                            <?= ucfirst($c['status']) ?>
                        </span>
                    </div>

                    <div class="card-body p-4 d-flex flex-column flex-grow-1">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 small">
                                <?= e($c['category_name'] ?: 'General') ?>
                            </span>
                            <?php if (!empty($c['academic_level'])): ?>
                                <span class="badge bg-dark bg-opacity-10 text-dark rounded-pill px-2 py-1 small">
                                    <i class="bi bi-mortarboard me-1"></i><?= e($c['academic_level']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <h5 class="fw-bold mb-2 text-dark"><?= e($c['title']) ?></h5>
                        <p class="text-muted small mb-3 flex-grow-1"><?= e(substr($c['short_description'] ?? '', 0, 100)) ?>...</p>

                        <div class="row g-2 text-center p-3 bg-light rounded-3 mb-3 border border-subtle small">
                            <div class="col-4">
                                <div class="fw-bold text-main"><?= (int)$c['student_count'] ?></div>
                                <div class="text-muted" style="font-size:0.75rem;">Students</div>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold text-main"><?= (int)$c['lesson_count'] ?></div>
                                <div class="text-muted" style="font-size:0.75rem;">Lessons</div>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold text-main"><?= (int)$c['quiz_count'] ?></div>
                                <div class="text-muted" style="font-size:0.75rem;">Quizzes</div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 pt-2 border-top">
                            <a href="<?= url('courses/details.php?slug=' . urlencode($c['slug'])) ?>" class="btn btn-sm btn-outline-secondary rounded-pill flex-grow-1" target="_blank">
                                <i class="bi bi-eye me-1"></i> Preview
                            </a>
                            <a href="<?= url('teacher/edit-course.php?id=' . $c['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill flex-grow-1">
                                <i class="bi bi-pencil-square me-1"></i> Edit
                            </a>
                            <a href="<?= url('teacher/lessons.php?course_id=' . $c['id']) ?>" class="btn btn-sm btn-outline-info rounded-pill flex-grow-1">
                                <i class="bi bi-play-btn me-1"></i> Lessons
                            </a>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <a href="<?= url('teacher/tasks.php?course_id=' . $c['id']) ?>" class="btn btn-sm btn-light border rounded-pill flex-grow-1">
                                <i class="bi bi-list-task me-1"></i> Tasks (<?= (int)$c['task_count'] ?>)
                            </a>
                            <a href="<?= url('teacher/quizzes.php?course_id=' . $c['id']) ?>" class="btn btn-sm btn-light border rounded-pill flex-grow-1">
                                <i class="bi bi-patch-question me-1"></i> Quizzes (<?= (int)$c['quiz_count'] ?>)
                            </a>
                            <a href="<?= url('teacher/students.php?course_id=' . $c['id']) ?>" class="btn btn-sm btn-light border rounded-pill flex-grow-1">
                                <i class="bi bi-people me-1"></i> Students (<?= (int)$c['student_count'] ?>)
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4 bg-white">
        <i class="bi bi-journal-plus text-primary display-3 mb-3"></i>
        <h4 class="fw-bold">No Teaching Courses Assigned Yet</h4>
        <p class="text-muted max-w-md mx-auto mb-4">
            To start instructing students and managing curriculum on StudyMe, you can either select an existing course from our academic catalog or create a custom course with your preferred University Level and syllabus.
        </p>
        <div class="d-flex justify-content-center flex-wrap gap-3">
            <a href="<?= url('teacher/select-course.php') ?>" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-bold">
                <i class="bi bi-collection-play me-1"></i> Select an Existing Course
            </a>
            <a href="<?= url('teacher/create-course.php') ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm">
                <i class="bi bi-plus-circle-fill me-1"></i> Create a New Course
            </a>
        </div>
    </div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
