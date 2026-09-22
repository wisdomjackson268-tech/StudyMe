<?php

require_once dirname(__DIR__) . '/config/main.php';
secure_page(ROLE_STUDENT);

$user = current_user();
$pdo = getDBConnection();
$userId = $user['id'];

$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId = $student ? (int)$student['id'] : 0;

$assignments = [];
if ($studentId) {
    $stmt = $pdo->prepare("
        SELECT a.*, c.title AS course_title,
               (SELECT status FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ? LIMIT 1) AS submission_status,
               (SELECT score FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ? LIMIT 1) AS earned_score
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        JOIN enrollments e ON e.course_id = c.id
        WHERE e.student_id = ? AND a.status = 'published'
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$studentId, $studentId, $studentId]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-file-earmark-text-fill text-primary me-2"></i> Course Assignments</h1>
        <p class="text-muted mb-0">Review project briefs and submit homework assignments to your instructors.</p>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-5">
    <?php if (!empty($assignments)): ?>
        <div class="list-group list-group-flush">
            <?php foreach ($assignments as $a): ?>
                <div class="list-group-item px-0 py-4 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill small px-3 mb-2"><?= e($a['course_title']) ?></span>
                        <h5 class="fw-bold mb-1"><?= e($a['title']) ?></h5>
                        <p class="text-muted small mb-2 lh-base"><?= e($a['description'] ?: 'Complete assignment according to course instructions.') ?></p>
                        <div class="text-muted small">
                            <i class="bi bi-calendar-event me-1"></i> Due Date: <?= $a['due_date'] ? date('M j, Y', strtotime($a['due_date'])) : 'Open Submission' ?>
                            <span class="mx-2">&bull;</span>
                            Max Score: <?= (float)($a['max_score'] ?? 100) ?> pts
                        </div>
                    </div>
                    <div>
                        <?php if ($a['submission_status']): ?>
                            <span class="badge bg-<?= $a['submission_status'] === 'graded' ? 'success' : 'warning' ?> rounded-pill px-3 py-2">
                                <?= ucfirst($a['submission_status']) ?> <?= $a['earned_score'] !== null ? '(' . (float)$a['earned_score'] . ' pts)' : '' ?>
                            </span>
                        <?php else: ?>
                            <a href="<?= url('student/submissions.php?assignment_id=' . (int)$a['id']) ?>" class="btn btn-primary rounded-pill px-4 fw-bold text-nowrap" data-feedback="click">
                                Submit Solution <i class="bi bi-upload ms-1"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-file-earmark-check fs-1"></i>
            <h5 class="fw-bold mt-3">No active assignments</h5>
            <p class="small">Your enrolled courses currently do not have pending homework assignments.</p>
        </div>
    <?php endif; ?>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
