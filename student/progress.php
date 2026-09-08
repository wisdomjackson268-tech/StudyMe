<?php
/**
 * StudyMe AI Platform — Student Learning Progress & Metrics
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';

secure_page(ROLE_STUDENT);

$user = current_user();
$pdo = getDBConnection();
$userId = $user['id'];

// Resolve student ID
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId = $student ? (int)$student['id'] : 0;

$enrollments = [];
$completedLessonsCount = 0;
$totalCertificates = 0;

if ($studentId) {
    $stmt = $pdo->prepare("
        SELECT e.*, c.title AS course_title, c.thumbnail, cat.name AS category_name
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        WHERE e.student_id = ?
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute([$studentId]);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT COUNT(lp.id)
        FROM lesson_progress lp
        JOIN enrollments e ON lp.enrollment_id = e.id
        WHERE e.student_id = ? AND lp.completed = 1
    ");
    $stmt->execute([$studentId]);
    $completedLessonsCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $totalCertificates = (int)$stmt->fetchColumn();
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-graph-up-arrow text-primary me-2"></i> Learning Progress &amp; Analytics</h1>
        <p class="text-muted mb-0">Track your completed course milestones, finished lessons, and earned credentials.</p>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-4 mb-5">
    <div class="col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 d-flex align-items-center flex-row gap-3">
            <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle fs-2">
                <i class="bi bi-play-circle-fill"></i>
            </div>
            <div>
                <div class="display-6 fw-bold text-main"><?= count($enrollments) ?></div>
                <div class="text-muted small">Enrolled Courses</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 d-flex align-items-center flex-row gap-3">
            <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle fs-2">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div>
                <div class="display-6 fw-bold text-main"><?= $completedLessonsCount ?></div>
                <div class="text-muted small">Lessons Finished</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 d-flex align-items-center flex-row gap-3">
            <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-circle fs-2">
                <i class="bi bi-award-fill"></i>
            </div>
            <div>
                <div class="display-6 fw-bold text-main"><?= $totalCertificates ?></div>
                <div class="text-muted small">Certificates Earned</div>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Courses Progress List -->
<div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-5">
    <h4 class="fw-bold mb-4"><i class="bi bi-list-task text-primary me-2"></i> Course Completion Breakdown</h4>

    <?php if (!empty($enrollments)): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Course</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrollments as $e): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?= e($e['thumbnail'] ?: 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=100&q=80') ?>" class="rounded-3" style="width: 48px; height: 36px; object-fit: cover;" alt="Course">
                                    <div class="fw-bold text-main line-clamp-1"><?= e($e['course_title']) ?></div>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= e($e['category_name'] ?: 'General') ?></span></td>
                            <td>
                                <span class="badge bg-<?= (float)$e['progress'] >= 100 ? 'success' : 'info' ?> rounded-pill">
                                    <?= (float)$e['progress'] >= 100 ? 'Completed' : 'In Progress' ?>
                                </span>
                            </td>
                            <td style="min-width: 180px;">
                                <div class="d-flex justify-content-between small text-muted mb-1 fw-bold">
                                    <span>Completion</span>
                                    <span><?= number_format($e['progress'], 0) ?>%</span>
                                </div>
                                <div class="progress rounded-pill" style="height: 6px;">
                                    <div class="progress-bar bg-success rounded-pill" role="progressbar" style="width: <?= (float)$e['progress'] ?>%"></div>
                                </div>
                            </td>
                            <td class="text-end">
                                <a href="<?= url('student/course.php?id=' . (int)$e['course_id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill fw-bold">
                                    Continue <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="text-center py-4 text-muted">
            <i class="bi bi-bar-chart-line fs-1"></i>
            <p class="mt-2">No active learning progress recorded yet.</p>
        </div>
    <?php endif; ?>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
