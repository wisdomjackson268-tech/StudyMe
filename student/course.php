<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/courses.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';

secure_page(ROLE_STUDENT);

$courseId = (int)($_GET['id'] ?? 0);
$course = get_course_by_id($courseId);
if (!$course) {
    redirect('student/my-courses.php');
}

$user = current_user();
$pdo = getDBConnection();
$userId = $user['id'];

$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId = $student ? (int)$student['id'] : 0;

$stmtEn = $pdo->prepare("SELECT * FROM enrollments WHERE student_id = ? AND course_id = ? AND status = 'active' LIMIT 1");
$stmtEn->execute([$studentId, $courseId]);
$enrollment = $stmtEn->fetch(PDO::FETCH_ASSOC);

if (!$enrollment && current_user_role() !== ROLE_ADMIN) {
    if (defined('FREE_TESTING_MODE') && FREE_TESTING_MODE) {

        if ($studentId) {
            $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status, progress, enrolled_at) VALUES (?, ?, 'active', 0.00, NOW()) ON DUPLICATE KEY UPDATE status = 'active'")
                ->execute([$studentId, $courseId]);
            $stmtEn->execute([$studentId, $courseId]);
            $enrollment = $stmtEn->fetch(PDO::FETCH_ASSOC);
        }
    } else {

        $activeCourse = get_student_active_course($studentId);
        if ($activeCourse) {
            set_flash('error', 'Access Restricted: You are enrolled in "' . htmlspecialchars($activeCourse['course_title']) . '". Students are restricted to ONE active course at a time.');
            redirect('student/my-courses.php');
        } else {
            set_flash('error', 'Course Access Locked: Complete enrollment and payment to access "' . htmlspecialchars($course['title']) . '".');
            redirect('payments/checkout.php?course_id=' . $courseId);
        }
    }
}

$sections = get_course_sections($courseId);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="mb-4">
    <a href="<?= url('student/my-courses.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill mb-3">
        <i class="bi bi-arrow-left me-1"></i> Back to My Courses
    </a>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 mb-2 fw-semibold">
                <?= e($course['category_name'] ?: 'General') ?>
            </span>
            <h1 class="h2 fw-bold mb-1"><?= e($course['title']) ?></h1>
            <p class="text-muted mb-0">Instructor: <strong class="text-dark"><?= e($course['teacher_name']) ?></strong></p>
        </div>
        <?php if ($enrollment): ?>
            <div class="card border-0 bg-light p-3 rounded-4 shadow-sm text-end">
                <div class="small text-muted fw-bold mb-1">Overall Completion</div>
                <div class="fs-4 fw-bold text-success mb-1"><?= number_format($enrollment['progress'], 0) ?>%</div>
                <div class="progress rounded-pill" style="width: 140px; height: 6px;">
                    <div class="progress-bar bg-success rounded-pill" role="progressbar" style="width: <?= (float)$enrollment['progress'] ?>%"></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <h4 class="fw-bold mb-3"><i class="bi bi-list-nested text-primary me-2"></i> Course Curriculum</h4>
            <p class="text-muted small mb-4">Select any lesson below to launch the video player, reading materials, and AI Tutor.</p>

            <?php if (!empty($sections)): ?>
                <div class="accordion custom-accordion" id="syllabusAccordion">
                    <?php foreach ($sections as $sIndex => $sec): ?>
                        <?php $lessons = get_section_lessons($sec['id']); ?>
                        <div class="accordion-item mb-3 border rounded-3 overflow-hidden">
                            <h2 class="accordion-header" id="headingSec<?= $sec['id'] ?>">
                                <button class="accordion-button <?= $sIndex === 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSec<?= $sec['id'] ?>" aria-expanded="<?= $sIndex === 0 ? 'true' : 'false' ?>">
                                    <div class="fw-bold me-auto">
                                        Section <?= $sIndex + 1 ?>: <?= e($sec['title']) ?>
                                    </div>
                                    <span class="badge bg-secondary rounded-pill me-3"><?= count($lessons) ?> Lessons</span>
                                </button>
                            </h2>
                            <div id="collapseSec<?= $sec['id'] ?>" class="accordion-collapse collapse <?= $sIndex === 0 ? 'show' : '' ?>" aria-labelledby="headingSec<?= $sec['id'] ?>" data-bs-parent="#syllabusAccordion">
                                <div class="accordion-body p-0">
                                    <?php if (!empty($lessons)): ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($lessons as $lIndex => $les): ?>
                                                <a href="<?= url('student/lesson.php?id=' . (int)$les['id']) ?>" class="list-group-item list-group-item-action p-3 d-flex align-items-center justify-content-between hover-light">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-circle small">
                                                            <i class="bi bi-play-circle-fill"></i>
                                                        </div>
                                                        <div>
                                                            <div class="fw-semibold text-main"><?= e($les['title']) ?></div>
                                                            <div class="text-muted small"><?= (int)$les['video_duration'] > 0 ? (int)$les['video_duration'] . ' mins' : 'Reading / Video' ?></div>
                                                        </div>
                                                    </div>
                                                    <span class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                        Start <i class="bi bi-arrow-right ms-1"></i>
                                                    </span>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="p-3 text-muted small text-center">No lessons in this section yet.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-journal-x fs-1"></i>
                    <p class="mt-2">No sections created for this course yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <?php $cThumb = function_exists('get_course_thumbnail_url') ? get_course_thumbnail_url($course['thumbnail'], $course['category_slug'] ?? 'technology') : ($course['thumbnail'] ?: 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=400&q=80'); ?>
            <img src="<?= e($cThumb) ?>" class="img-fluid rounded-4 mb-3 w-100" style="max-height: 200px; object-fit: cover;" alt="<?= e($course['title']) ?>" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=400&q=80';">
            <h5 class="fw-bold mb-2">About This Course</h5>
            <p class="text-muted small mb-4"><?= e($course['short_description']) ?></p>

            <div class="d-flex flex-column gap-3 small text-secondary border-top pt-3">
                <div class="d-flex justify-content-between">
                    <span><i class="bi bi-bar-chart me-2 text-primary"></i> Skill Level:</span>
                    <strong class="text-dark" style="text-transform: capitalize;"><?= str_replace('_', ' ', e($course['level'])) ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span><i class="bi bi-translate me-2 text-primary"></i> Language:</span>
                    <strong class="text-dark"><?= e($course['language'] ?? 'English') ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span><i class="bi bi-award me-2 text-primary"></i> Certificate:</span>
                    <strong class="text-success">Included</strong>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 bg-primary text-white text-center">
            <i class="bi bi-robot fs-1 text-warning mb-2"></i>
            <h5 class="fw-bold mb-2">StudyMe AI Tutor Ready</h5>
            <p class="small text-white-50 mb-3">Stuck on any lesson concept? Launch the AI assistant anytime during your study.</p>
            <button class="btn btn-warning rounded-pill fw-bold py-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#aiAssistantDrawer">
                <i class="bi bi-chat-dots-fill me-1"></i> Ask AI Tutor
            </button>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
