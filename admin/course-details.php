<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();
$cid = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT c.*, cat.name AS category_name,
           CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
           u.email AS teacher_email, u.phone AS teacher_phone,
           (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'active') AS student_count,
           (SELECT COUNT(*) FROM lessons l JOIN course_sections cs ON l.section_id = cs.id WHERE cs.course_id = c.id) AS lesson_count,
           (SELECT COUNT(*) FROM lessons l JOIN course_sections cs ON l.section_id = cs.id WHERE cs.course_id = c.id AND l.video_url IS NOT NULL AND l.video_url != '') AS video_count,
           (SELECT COUNT(*) FROM resources r WHERE r.course_id = c.id) AS document_count,
           (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.id) AS task_count,
           (SELECT COUNT(*) FROM quizzes q WHERE q.course_id = c.id) AS quiz_count
    FROM courses c
    JOIN teachers t ON c.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    LEFT JOIN categories cat ON c.category_id = cat.id
    WHERE c.id = ? LIMIT 1
");
$stmt->execute([$cid]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    set_flash('error', 'Course not found.');
    redirect('admin/courses.php');
}

$stmtStudents = $pdo->prepare("
    SELECT e.*, u.first_name, u.last_name, u.email, u.avatar
    FROM enrollments e
    JOIN students s ON e.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE e.course_id = ? AND e.status = 'active'
    ORDER BY e.enrolled_at DESC
");
$stmtStudents->execute([$cid]);
$enrolledStudents = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-eye-fill text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Course Details: <?= e($course['title']) ?></h2>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('admin/courses.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="bi bi-arrow-left me-1"></i> Back to Courses
        </a>
        <a href="<?= url('admin/edit-course.php?id=' . $cid) ?>" class="btn btn-primary rounded-pill px-4 fw-bold">
            <i class="bi bi-pencil-square me-1"></i> Edit Course
        </a>
    </div>
</div>

<div class="row g-4 mb-4">

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <img src="<?= e($course['thumbnail'] ?: 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=400&q=80') ?>"
                     class="rounded-3 border" style="width: 120px; height: 90px; object-fit: cover;" alt="Thumbnail">
                <div>
                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 mb-1"><?= e($course['category_name'] ?: 'General') ?></span>
                    <h3 class="fw-bold text-main mb-1"><?= e($course['title']) ?></h3>
                    <div class="small text-muted">
                        Status: <span class="badge <?= $course['status'] === 'published' ? 'bg-success' : 'bg-warning text-dark' ?> rounded-pill me-2"><?= ucfirst($course['status']) ?></span>
                        Level: <span class="text-capitalize text-main fw-semibold"><?= str_replace('_', ' ', $course['level']) ?></span>
                    </div>
                </div>
            </div>

            <h5 class="fw-bold mb-2">Short Summary</h5>
            <p class="text-secondary mb-4"><?= e($course['short_description']) ?></p>

            <h5 class="fw-bold mb-2">Course Description</h5>
            <p class="text-secondary lh-lg mb-0" style="white-space: pre-line;"><?= e($course['description']) ?></p>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white p-4 border-0">
                <h5 class="fw-bold mb-0"><i class="bi bi-people-fill text-primary me-2"></i>Enrolled Students (<?= count($enrolledStudents) ?>)</h5>
            </div>
            <?php if (!empty($enrolledStudents)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Student</th>
                                <th>Enrolled Date</th>
                                <th>Progress</th>
                                <th class="text-end pe-4">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrolledStudents as $s): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
                                                <?= strtoupper(substr($s['first_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-main small"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></div>
                                                <small class="text-muted" style="font-size:0.75rem;"><?= e($s['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="small text-muted"><?= date('M d, Y', strtotime($s['enrolled_at'])) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2" style="width: 140px;">
                                            <div class="progress flex-grow-1" style="height:6px;">
                                                <div class="progress-bar bg-success" style="width: <?= (float)$s['progress'] ?>%;"></div>
                                            </div>
                                            <span class="small fw-bold"><?= (int)$s['progress'] ?>%</span>
                                        </div>
                                    </td>
                                    <td class="text-end pe-4">
                                        <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1">Active</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-4 text-center text-muted">No students currently enrolled in this course.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">

        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-person-badge-fill text-warning me-2"></i>Course Owner</h5>
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="rounded-circle bg-warning bg-opacity-10 text-warning fw-bold fs-3 d-flex align-items-center justify-content-center" style="width:54px; height:54px;">
                    <?= strtoupper(substr($course['teacher_name'], 0, 1)) ?>
                </div>
                <div>
                    <h6 class="fw-bold mb-0 text-main"><?= e($course['teacher_name']) ?></h6>
                    <small class="text-muted"><?= e($course['teacher_email']) ?></small>
                </div>
            </div>
            <div class="small text-muted">
                <i class="bi bi-telephone me-1"></i> Phone: <?= e($course['teacher_phone'] ?: 'N/A') ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-bar-chart-fill text-info me-2"></i>Course Metrics</h5>
            <div class="d-flex flex-column gap-3">
                <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                    <span class="text-muted small"><i class="bi bi-people me-2 text-primary"></i>Enrolled Students</span>
                    <span class="fw-bold text-main"><?= (int)$course['student_count'] ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                    <span class="text-muted small"><i class="bi bi-play-btn me-2 text-info"></i>Total Lessons</span>
                    <span class="fw-bold text-main"><?= (int)$course['lesson_count'] ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                    <span class="text-muted small"><i class="bi bi-camera-video me-2 text-danger"></i>Video Lessons</span>
                    <span class="fw-bold text-main"><?= (int)$course['video_count'] ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                    <span class="text-muted small"><i class="bi bi-file-earmark-pdf me-2 text-warning"></i>PDF Documents</span>
                    <span class="fw-bold text-main"><?= (int)$course['document_count'] ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                    <span class="text-muted small"><i class="bi bi-list-task me-2 text-primary"></i>Created Tasks</span>
                    <span class="fw-bold text-main"><?= (int)$course['task_count'] ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small"><i class="bi bi-patch-question me-2 text-success"></i>Quizzes</span>
                    <span class="fw-bold text-main"><?= (int)$course['quiz_count'] ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
