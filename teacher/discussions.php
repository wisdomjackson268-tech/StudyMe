<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/discussions.php';

secure_page(ROLE_TEACHER);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$user   = current_user();
$userId = (int)$user['id'];
$pdo    = getDBConnection();

$stmt = $pdo->prepare("SELECT id, user_id, assigned_course_id, assigned_category_id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;

$statusFilter = $_GET['status'] ?? null;
if (!in_array($statusFilter, ['open', 'answered', 'closed'])) {
    $statusFilter = null;
}

$scope = $_GET['scope'] ?? 'my_courses';
if (!in_array($scope, ['my_courses', 'all'])) {
    $scope = 'my_courses';
}

if (is_post() && isset($_POST['post_instructor_reply'])) {
    $questionId = (int)($_POST['question_id'] ?? 0);
    $replyText  = trim($_POST['reply_content'] ?? '');

    $res = post_lesson_question_reply($questionId, $userId, $replyText, true);
    if ($res['success']) {
        set_flash('success', 'Your instructor reply has been posted! The student has been notified.');
    } else {
        set_flash('error', $res['message']);
    }

    $redirectUrl = 'teacher/discussions.php?scope=' . urlencode($scope);
    if ($statusFilter) $redirectUrl .= '&status=' . urlencode($statusFilter);
    $redirectUrl .= '#question-' . $questionId;
    redirect($redirectUrl);
}

$questions = get_teacher_course_questions($tid ?: $userId, $statusFilter, $scope);

$allScopeQuestions = get_teacher_course_questions($tid ?: $userId, null, $scope);
$totalQuestionsCount = count($allScopeQuestions);
$unansweredCount = 0;
$answeredCount   = 0;
foreach ($allScopeQuestions as $aq) {
    if (($aq['status'] ?? '') === 'open') $unansweredCount++;
    if (($aq['status'] ?? '') === 'answered') $answeredCount++;
}

$totalPlatformQuestions = (int)$pdo->query("SELECT COUNT(*) FROM lesson_questions")->fetchColumn();

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small">
            <i class="bi bi-chat-quote-fill text-primary me-1"></i> Instructor Suite
        </p>
        <h1 class="h3 fw-bold mb-1">Student Q&amp;A &amp; Lesson Discussions</h1>
        <p class="text-muted small mb-0">Review and reply directly to academic questions asked by enrolled students on course lessons.</p>
    </div>

    <div class="btn-group bg-light p-1 rounded-pill border" role="group">
        <a href="<?= url('teacher/discussions.php?scope=my_courses' . ($statusFilter ? '&status=' . $statusFilter : '')) ?>" class="btn btn-sm <?= $scope === 'my_courses' ? 'btn-primary shadow-sm' : 'btn-light text-muted' ?> rounded-pill px-3 fw-bold">
            <i class="bi bi-person-check me-1"></i> My Courses
        </a>
        <a href="<?= url('teacher/discussions.php?scope=all' . ($statusFilter ? '&status=' . $statusFilter : '')) ?>" class="btn btn-sm <?= $scope === 'all' ? 'btn-primary shadow-sm' : 'btn-light text-muted' ?> rounded-pill px-3 fw-bold">
            <i class="bi bi-globe2 me-1"></i> All StudyMe (<?= $totalPlatformQuestions ?>)
        </a>
    </div>
</div>

<div class="d-flex gap-2 flex-wrap mb-4">
    <a href="<?= url('teacher/discussions.php?scope=' . $scope) ?>" class="btn btn-sm <?= !$statusFilter ? 'btn-dark' : 'btn-outline-secondary' ?> rounded-pill px-3 fw-bold">
        All Questions (<?= $totalQuestionsCount ?>)
    </a>
    <a href="<?= url('teacher/discussions.php?scope=' . $scope . '&status=open') ?>" class="btn btn-sm <?= $statusFilter === 'open' ? 'btn-warning text-dark' : 'btn-outline-warning text-dark' ?> rounded-pill px-3 fw-bold">
        <i class="bi bi-hourglass-split me-1"></i> Awaiting Reply (<?= $unansweredCount ?>)
    </a>
    <a href="<?= url('teacher/discussions.php?scope=' . $scope . '&status=answered') ?>" class="btn btn-sm <?= $statusFilter === 'answered' ? 'btn-success' : 'btn-outline-success' ?> rounded-pill px-3 fw-bold">
        <i class="bi bi-check-circle-fill me-1"></i> Answered (<?= $answeredCount ?>)
    </a>
</div>

<?php if (!empty($questions)): ?>
    <div class="d-flex flex-column gap-4 mb-5">
        <?php foreach ($questions as $q): ?>
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-card" id="question-<?= (int)$q['id'] ?>">

                <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-3 border-bottom pb-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 fw-bold small">
                                <?= e($q['course_title']) ?>
                            </span>
                            <span class="text-muted small">&bull;</span>
                            <a href="<?= url('student/lesson.php?id=' . (int)$q['lesson_id'] . '#question-' . (int)$q['id']) ?>" class="badge bg-secondary bg-opacity-10 text-secondary text-decoration-none rounded-pill px-2 py-1 small hover-primary" target="_blank">
                                <i class="bi bi-play-circle me-1"></i><?= e($q['lesson_title']) ?>
                            </a>
                        </div>
                        <small class="text-muted">
                            Asked by <strong><?= e($q['first_name'] . ' ' . $q['last_name']) ?></strong> (<?= e($q['email']) ?>) &bull; <?= date('M d, Y \a\t g:i A', strtotime($q['created_at'])) ?>
                        </small>
                    </div>

                    <span class="badge <?= $q['status'] === 'answered' ? 'bg-success' : 'bg-warning text-dark' ?> rounded-pill px-3 py-2 font-monospace">
                        <i class="bi bi-<?= $q['status'] === 'answered' ? 'check-circle-fill' : 'hourglass-split' ?> me-1"></i>
                        <?= $q['status'] === 'answered' ? 'Answered' : 'Needs Response' ?>
                    </span>
                </div>

                <?php if (!empty($q['title'])): ?>
                    <h5 class="fw-bold text-main mb-2"><?= e($q['title']) ?></h5>
                <?php endif; ?>
                <div class="p-3 bg-light rounded-3 mb-3 text-secondary lh-base fs-6 border border-subtle">
                    <?= nl2br(e($q['question'])) ?>
                </div>

                <?php if (!empty($q['replies'])): ?>
                    <h6 class="fw-bold text-main mb-3 small text-uppercase">
                        <i class="bi bi-chat-left-dots-fill text-primary me-1"></i> Discussion Thread (<?= count($q['replies']) ?>)
                    </h6>
                    <div class="d-flex flex-column gap-3 mb-4 ps-md-3 border-start border-3 border-primary">
                        <?php foreach ($q['replies'] as $rep): ?>
                            <div class="p-3 rounded-3 <?= $rep['is_instructor_reply'] ? 'bg-primary bg-opacity-10 border border-primary border-opacity-25' : 'bg-light' ?>">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle overflow-hidden bg-primary text-white fw-bold d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.75rem;">
                                            <?php if (!empty($rep['avatar'])): ?>
                                                <img src="<?= e($rep['avatar']) ?>" alt="Avatar" style="width:100%; height:100%; object-fit:cover;">
                                            <?php else: ?>
                                                <?= strtoupper(substr($rep['first_name'] ?? 'U', 0, 1)) ?>
                                            <?php endif; ?>
                                        </div>
                                        <strong class="small text-main">
                                            <?= e($rep['first_name'] . ' ' . $rep['last_name']) ?>
                                        </strong>
                                        <?php if ($rep['is_instructor_reply']): ?>
                                            <span class="badge bg-warning text-dark rounded-pill fw-bold small px-2 py-0" style="font-size: 0.7rem;">
                                                <i class="bi bi-patch-check-fill text-dark me-1"></i>Instructor Response
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted" style="font-size: 0.72rem;"><?= date('M d, g:i A', strtotime($rep['created_at'])) ?></small>
                                </div>
                                <div class="small text-secondary lh-base mt-2"><?= nl2br(e($rep['reply'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="pt-3 border-top mt-2">
                    <form method="POST" action="<?= url('teacher/discussions.php?scope=' . urlencode($scope) . ($statusFilter ? '&status=' . urlencode($statusFilter) : '')) ?>">
                        <input type="hidden" name="post_instructor_reply" value="1">
                        <input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>">

                        <label class="form-label fw-bold small text-main mb-2">
                            <i class="bi bi-reply-fill text-primary me-1"></i> Reply as Instructor
                        </label>
                        <div class="d-flex gap-2">
                            <textarea name="reply_content" class="form-control rounded-3" rows="2" placeholder="Write a helpful, structured explanation for the student..." required></textarea>
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold align-self-end flex-shrink-0" data-feedback="click">
                                <i class="bi bi-send-fill me-1"></i> Post Reply
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4 bg-card">
        <i class="bi bi-chat-check-fill text-success display-3 mb-3"></i>
        <h4 class="fw-bold text-main mb-1">No Questions in this View</h4>
        <p class="text-muted mb-3">
            <?= $scope === 'my_courses' ? 'There are no questions posted under your specific assigned courses. Click below to view all questions across StudyMe.' : 'There are no student questions currently matching the selected filter.' ?>
        </p>
        <?php if ($scope === 'my_courses'): ?>
            <div>
                <a href="<?= url('teacher/discussions.php?scope=all') ?>" class="btn btn-primary rounded-pill px-4 fw-bold">
                    <i class="bi bi-globe2 me-1"></i> View All Platform Questions (<?= $totalPlatformQuestions ?>)
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
