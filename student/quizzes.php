<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/quizzes.php';

secure_page(ROLE_STUDENT);

$user = current_user();
$pdo = getDBConnection();
$userId = $user['id'];

$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId = $student ? (int)$student['id'] : 0;

$quizzes = [];
$pastAttempts = [];

if ($studentId) {

    $stmt = $pdo->prepare("
        SELECT q.*, c.title AS course_title,
               (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS question_count,
               (SELECT score FROM quiz_attempts WHERE quiz_id = q.id AND student_id = ? ORDER BY id DESC LIMIT 1) AS last_score,
               (SELECT passed FROM quiz_attempts WHERE quiz_id = q.id AND student_id = ? ORDER BY id DESC LIMIT 1) AS last_passed
        FROM quizzes q
        JOIN courses c ON q.course_id = c.id
        JOIN enrollments e ON e.course_id = c.id
        WHERE e.student_id = ? AND q.status = 'published'
        ORDER BY q.created_at DESC
    ");
    $stmt->execute([$studentId, $studentId, $studentId]);
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT qa.*, q.title AS quiz_title, c.title AS course_title
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        JOIN courses c ON q.course_id = c.id
        WHERE qa.student_id = ?
        ORDER BY qa.started_at DESC
        LIMIT 10
    ");
    $stmt->execute([$studentId]);
    $pastAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-patch-check-fill text-warning me-2"></i> Quizzes &amp; Assessments</h1>
        <p class="text-muted mb-0">Test your conceptual knowledge and earn course progress credits.</p>
    </div>
</div>

<div class="row g-4">

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <h4 class="fw-bold mb-3"><i class="bi bi-card-checklist text-primary me-2"></i> Available Course Quizzes</h4>

            <?php if (!empty($quizzes)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($quizzes as $q): ?>
                        <div class="list-group-item p-3 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                            <div>
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill small px-3 mb-1">
                                    <?= e($q['course_title']) ?>
                                </span>
                                <h5 class="fw-bold mb-1"><?= e($q['title']) ?></h5>
                                <div class="text-muted small">
                                    <i class="bi bi-question-circle me-1"></i> <?= (int)$q['question_count'] ?> Questions
                                    <span class="mx-2">&bull;</span>
                                    <i class="bi bi-clock me-1"></i> <?= (int)($q['time_limit_minutes'] ?? 15) ?> mins
                                    <span class="mx-2">&bull;</span>
                                    Pass Mark: <?= (float)$q['passing_score'] ?>%
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($q['last_score'] !== null): ?>
                                    <span class="badge bg-<?= $q['last_passed'] ? 'success' : 'danger' ?> rounded-pill px-3 py-2">
                                        <?= $q['last_passed'] ? 'Passed' : 'Failed' ?> (<?= number_format($q['last_score'], 0) ?>%)
                                    </span>
                                <?php endif; ?>
                                <a href="<?= url('student/quiz.php?id=' . (int)$q['id']) ?>" class="btn btn-primary rounded-pill px-4 fw-bold text-nowrap" data-feedback="click">
                                    Start Quiz <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-journal-x fs-1"></i>
                    <p class="mt-2">No quizzes available for your enrolled courses yet.</p>
                    <a href="<?= url('courses/index.php') ?>" class="btn btn-outline-primary rounded-pill px-4 btn-sm">Browse Courses</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-primary"></i> Recent Attempts</h5>
            <?php if (!empty($pastAttempts)): ?>
                <div class="list-group list-group-flush small">
                    <?php foreach ($pastAttempts as $att): ?>
                        <div class="list-group-item px-0 py-3 border-bottom">
                            <div class="fw-bold text-main line-clamp-1"><?= e($att['quiz_title']) ?></div>
                            <div class="text-muted small"><?= e($att['course_title']) ?></div>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="badge bg-<?= $att['passed'] ? 'success' : 'danger' ?> rounded-pill">
                                    <?= number_format($att['percentage'], 0) ?>%
                                </span>
                                <span class="text-muted" style="font-size:0.75rem;">
                                    <?= date('M j, g:i a', strtotime($att['started_at'])) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-muted small text-center py-3">No quiz attempts recorded yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
