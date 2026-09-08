<?php
/**
 * StudyMe AI Platform — Teacher Enrolled Students Manager
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_TEACHER);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$user = current_user();
$pdo  = getDBConnection();
$uid  = $user['id'];
$courseFilter = (int)($_GET['course_id'] ?? 0);

// Get teacher ID
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;

$students = [];
if ($tid) {
    $params = [$tid];
    $sql = "
        SELECT e.*, c.title AS course_title,
               u.first_name, u.last_name, u.email, u.avatar
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        JOIN students s ON e.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE c.teacher_id = ? AND e.status = 'active'
    ";
    if ($courseFilter > 0) {
        $sql .= " AND e.course_id = ?";
        $params[] = $courseFilter;
    }
    $sql .= " ORDER BY e.enrolled_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-people-fill text-warning me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0">Enrolled Students</h2>
    </div>
</div>

<?php if (!empty($students)): ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Student</th>
                        <th>Enrolled Course</th>
                        <th>Enrolled Date</th>
                        <th>Course Progress</th>
                        <th class="text-end pe-4">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    <?php if (!empty($s['avatar'])): ?>
                                        <img src="<?= e($s['avatar']) ?>" class="rounded-circle border" style="width:40px; height:40px; object-fit:cover;" alt="Student">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                                            <?= strtoupper(substr($s['first_name'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-bold text-main"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></div>
                                        <small class="text-muted"><?= e($s['email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1"><?= e($s['course_title']) ?></span>
                            </td>
                            <td class="small text-muted">
                                <i class="bi bi-calendar-check me-1"></i><?= date('M d, Y', strtotime($s['enrolled_at'])) ?>
                            </td>
                            <td style="width: 200px;">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height:6px; border-radius:10px;">
                                        <div class="progress-bar bg-success" style="width: <?= (float)$s['progress'] ?>%;"></div>
                                    </div>
                                    <span class="small fw-bold text-main"><?= (int)$s['progress'] ?>%</span>
                                </div>
                            </td>
                            <td class="text-end pe-4">
                                <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1">Active Learner</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4">
        <i class="bi bi-people text-muted display-3 mb-3"></i>
        <h4 class="fw-bold">No Students Enrolled Yet</h4>
        <p class="text-muted max-w-md mx-auto mb-0">Students who enroll in your courses will appear here with real-time course progress tracking.</p>
    </div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
