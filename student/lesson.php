<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/lessons.php';
require_once BASE_PATH . '/includes/functions/courses.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';
require_once BASE_PATH . '/includes/functions/activity.php';
require_once BASE_PATH . '/includes/functions/discussions.php';

require_login();
$user   = current_user();
$userId = (int)$user['id'];
$role   = current_user_role();
$pdo    = getDBConnection();

$lessonId = (int)($_GET['id'] ?? 0);
$lesson = get_lesson_by_id($lessonId);
if (!$lesson) {
    redirect($role === ROLE_TEACHER ? 'teacher/courses.php' : 'student/my-courses.php');
}

$studentId = 0;
if ($role === ROLE_STUDENT) {
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    $studentId = $student ? (int)$student['id'] : 0;
}

$enrollment = $studentId ? get_enrollment($studentId, $lesson['course_id']) : null;

log_user_activity($userId, 'lesson_view', 'Viewed lesson: ' . ($lesson['title'] ?? 'Unknown'), $lesson['course_id'], $lessonId);

if ($role === ROLE_STUDENT && !(defined('FREE_TESTING_MODE') && FREE_TESTING_MODE)) {
    $activeCourse = get_student_active_course($studentId);
    if ($activeCourse && (int)$activeCourse['course_id'] !== (int)$lesson['course_id']) {
        set_flash('error', 'Lesson Locked: You are enrolled in "' . htmlspecialchars($activeCourse['course_title']) . '". Students are restricted to ONE active course at a time.');
        redirect('student/my-courses.php');
    }
}

if (!$enrollment && empty($lesson['is_free']) && $role === ROLE_STUDENT) {
    if (defined('FREE_TESTING_MODE') && FREE_TESTING_MODE) {

        if ($studentId) {
            $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status, progress, enrolled_at) VALUES (?, ?, 'active', 0.00, NOW()) ON DUPLICATE KEY UPDATE status = 'active'")
                ->execute([$studentId, $lesson['course_id']]);
            $enrollment = get_enrollment($studentId, $lesson['course_id']);
        }
    } else {
        set_flash('error', 'Lesson Locked: You must have an active enrollment in this course to access full lessons.');
        redirect('payments/checkout.php?course_id=' . $lesson['course_id']);
    }
}

$isCompleted = false;
if ($enrollment) {
    $stmt = $pdo->prepare("SELECT completed FROM lesson_progress WHERE enrollment_id = ? AND lesson_id = ? LIMIT 1");
    $stmt->execute([$enrollment['id'], $lessonId]);
    $prog = $stmt->fetch(PDO::FETCH_ASSOC);
    $isCompleted = (bool)($prog['completed'] ?? false);
}

if (is_post() && isset($_POST['mark_complete']) && $enrollment) {
    complete_lesson_and_update_progress($enrollment['id'], $lessonId, $lesson['course_id']);
    set_flash('success', 'Lesson marked as complete! Your course progress has been updated.');
    redirect('student/lesson.php?id=' . $lessonId);
}

if (is_post() && isset($_POST['ask_question'])) {
    $qTopic   = trim($_POST['question_topic'] ?? '');
    $qContent = trim($_POST['question_content'] ?? '');

    $result = post_lesson_question($lessonId, $userId, $qContent, $qTopic);
    if ($result['success']) {
        set_flash('success', $result['message']);
    } else {
        set_flash('error', $result['message']);
    }
    redirect('student/lesson.php?id=' . $lessonId . '#qa-section');
}

if (is_post() && isset($_POST['post_reply'])) {
    $questionId = (int)($_POST['question_id'] ?? 0);
    $replyText  = trim($_POST['reply_content'] ?? '');
    $isInstructor = ($role === ROLE_TEACHER || $role === ROLE_ADMIN);

    $result = post_lesson_question_reply($questionId, $userId, $replyText, $isInstructor);
    if ($result['success']) {
        set_flash('success', $result['message']);
    } else {
        set_flash('error', $result['message']);
    }
    redirect('student/lesson.php?id=' . $lessonId . '#question-' . $questionId);
}

$allLessons = [];
$stmt = $pdo->prepare("
    SELECT l.id, l.title, l.video_duration, l.is_free, cs.title AS section_title
    FROM lessons l
    JOIN course_sections cs ON l.section_id = cs.id
    WHERE cs.course_id = ?
    ORDER BY cs.sort_order ASC, l.sort_order ASC, l.id ASC
");
$stmt->execute([$lesson['course_id']]);
$allLessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

$prevLesson = null;
$nextLesson = null;
foreach ($allLessons as $idx => $l) {
    if ((int)$l['id'] === $lessonId) {
        if ($idx > 0) $prevLesson = $allLessons[$idx - 1];
        if ($idx < count($allLessons) - 1) $nextLesson = $allLessons[$idx + 1];
        break;
    }
}

$lessonQuestions = get_lesson_questions($lessonId);
$questionCount = count($lessonQuestions);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="mb-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <a href="<?= url($role === ROLE_TEACHER ? 'teacher/courses.php' : 'student/course.php?id=' . (int)$lesson['course_id']) ?>" class="btn btn-outline-secondary btn-sm rounded-pill">
            <i class="bi bi-arrow-left me-1"></i> <?= $role === ROLE_TEACHER ? 'Back to Course Manager' : 'Back to Course Syllabus' ?>
        </a>
        <div class="d-flex align-items-center gap-2">
            <a href="#qa-section" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                <i class="bi bi-chat-dots-fill me-1"></i> Q&amp;A Discussion (<?= $questionCount ?>)
            </a>
            <button class="btn btn-sm btn-warning text-dark fw-bold rounded-pill px-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#aiAssistantDrawer">
                <i class="bi bi-robot me-1"></i> AI Tutor
            </button>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 fw-semibold small">
                    <?= e($lesson['course_title']) ?>
                </span>
                <span class="text-muted small">&bull;</span>
                <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2 py-1 small">
                    <?= e($lesson['section_title']) ?>
                </span>
            </div>
            <h1 class="h2 fw-bold mb-0 text-main"><?= e($lesson['title']) ?></h1>
        </div>

        <?php if ($role === ROLE_STUDENT && $enrollment): ?>
            <form method="POST" action="<?= url('student/lesson.php?id=' . $lessonId) ?>">
                <input type="hidden" name="mark_complete" value="1">
                <?php if ($isCompleted): ?>
                    <button type="button" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" disabled>
                        <i class="bi bi-check-circle-fill me-1"></i> Lesson Completed
                    </button>
                <?php else: ?>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-feedback="success">
                        <i class="bi bi-check2-circle me-1"></i> Mark as Complete
                    </button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">

    <div class="col-lg-8">

        <?php if (!empty($lesson['video_url'])): ?>
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-4 bg-dark text-white">
                <div class="ratio ratio-16x9">
                    <?php if (strpos($lesson['video_url'], 'youtube.com') !== false || strpos($lesson['video_url'], 'youtu.be') !== false): ?>
                        <?php
                            preg_match('/(?:v=|\/)([a-zA-Z0-9_-]{11})/', $lesson['video_url'], $matches);
                            $ytId = $matches[1] ?? '';
                        ?>
                        <iframe src="https://www.youtube.com/embed/<?= e($ytId) ?>" title="<?= e($lesson['title']) ?>" allowfullscreen></iframe>
                    <?php else: ?>
                        <video controls controlsList="nodownload" poster="https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=800&q=80">
                            <source src="<?= e(url($lesson['video_url'])) ?>" type="video/mp4">
                            Your browser does not support video playback.
                        </video>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($lesson['attachment'])): ?>
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-light border">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-circle fs-3">
                            <i class="bi bi-file-earmark-pdf-fill"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1 text-main">Attached Study Guide / Resource</h6>
                            <span class="text-muted small"><?= e(basename($lesson['attachment'])) ?></span>
                        </div>
                    </div>
                    <a href="<?= e(url($lesson['attachment'])) ?>" target="_blank" download class="btn btn-outline-danger btn-sm rounded-pill px-4 fw-bold">
                        <i class="bi bi-download me-1"></i> Download File
                    </a>
                </div>

                <?php if (pathinfo($lesson['attachment'], PATHINFO_EXTENSION) === 'pdf'): ?>
                    <div class="mt-4 ratio ratio-4x3 border rounded-3 overflow-hidden shadow-sm">
                        <iframe src="<?= e(url($lesson['attachment'])) ?>" title="PDF Document Viewer"></iframe>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4 bg-card">
            <h4 class="fw-bold mb-3 text-main"><i class="bi bi-journal-text text-primary me-2"></i> Lesson Notes &amp; Overview</h4>
            <div class="lesson-body text-secondary lh-lg fs-5">
                <?= !empty($lesson['content']) ? $lesson['content'] : '<p class="text-muted">No additional text notes provided for this video lesson. Use the video and attached resources above to study.</p>' ?>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center gap-3 mb-5">
            <?php if ($prevLesson): ?>
                <a href="<?= url('student/lesson.php?id=' . (int)$prevLesson['id']) ?>" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">
                    <i class="bi bi-arrow-left me-1"></i> Previous Lesson
                </a>
            <?php else: ?>
                <div></div>
            <?php endif; ?>

            <?php if ($nextLesson): ?>
                <a href="<?= url('student/lesson.php?id=' . (int)$nextLesson['id']) ?>" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-feedback="click">
                    Next Lesson <i class="bi bi-arrow-right ms-1"></i>
                </a>
            <?php endif; ?>
        </div>

        <div id="qa-section" class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-5 bg-card">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 pb-3 border-bottom">
                <div>
                    <h4 class="fw-bold mb-1 text-main">
                        <i class="bi bi-question-diamond-fill text-warning me-2"></i>Questions &amp; Discussion
                    </h4>
                    <p class="text-muted small mb-0">Have questions about this lesson? Ask your certified instructor directly below.</p>
                </div>
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-bold">
                    <?= $questionCount ?> <?= $questionCount === 1 ? 'Question' : 'Questions' ?>
                </span>
            </div>

            <div class="bg-light p-4 rounded-4 mb-4 border">
                <h6 class="fw-bold mb-3 text-main">
                    <i class="bi bi-chat-square-dots-fill text-primary me-2"></i>Ask a Question to the Instructor
                </h6>
                <form method="POST" action="<?= url('student/lesson.php?id=' . $lessonId) ?>">
                    <input type="hidden" name="ask_question" value="1">

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted">Topic / Short Summary (Optional)</label>
                        <input type="text" name="question_topic" class="form-control rounded-3" placeholder="e.g. Clarification on line 4 of code example or formula">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted">Your Question <span class="text-danger">*</span></label>
                        <textarea name="question_content" class="form-control rounded-3" rows="3" placeholder="Type your question in detail here. What part of the lesson would you like the teacher to explain further?" required></textarea>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold" data-feedback="click">
                            <i class="bi bi-send-fill me-1"></i> Post Question
                        </button>
                    </div>
                </form>
            </div>

            <?php if (!empty($lessonQuestions)): ?>
                <div class="d-flex flex-column gap-4">
                    <?php foreach ($lessonQuestions as $q): ?>
                        <div class="card border rounded-4 p-4 shadow-sm" id="question-<?= (int)$q['id'] ?>" style="background: var(--bg-surface, #ffffff);">

                            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle overflow-hidden bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                                        <?php if (!empty($q['avatar'])): ?>
                                            <img src="<?= e($q['avatar']) ?>" alt="Avatar" style="width:100%; height:100%; object-fit:cover;" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&q=80';">
                                        <?php else: ?>
                                            <?= strtoupper(substr($q['first_name'] ?? 'S', 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-main">
                                            <?= e($q['first_name'] . ' ' . $q['last_name']) ?>
                                            <?php if (($q['role'] ?? '') === 'teacher'): ?>
                                                <span class="badge bg-warning text-dark rounded-pill ms-1 small">Instructor</span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted"><?= date('M d, Y \a\t g:i A', strtotime($q['created_at'])) ?></small>
                                    </div>
                                </div>

                                <span class="badge <?= $q['status'] === 'answered' ? 'bg-success' : 'bg-warning text-dark' ?> rounded-pill px-3 py-1 font-monospace small">
                                    <i class="bi bi-<?= $q['status'] === 'answered' ? 'check-circle-fill' : 'hourglass-split' ?> me-1"></i>
                                    <?= $q['status'] === 'answered' ? 'Instructor Answered' : 'Awaiting Reply' ?>
                                </span>
                            </div>

                            <?php if (!empty($q['title'])): ?>
                                <h6 class="fw-bold text-main mb-2"><?= e($q['title']) ?></h6>
                            <?php endif; ?>
                            <p class="text-secondary mb-3 lh-base fs-6"><?= nl2br(e($q['question'])) ?></p>

                            <?php if (!empty($q['replies'])): ?>
                                <div class="ps-3 border-start border-3 border-primary my-3 d-flex flex-column gap-3">
                                    <?php foreach ($q['replies'] as $reply): ?>
                                        <div class="p-3 rounded-3 <?= $reply['is_instructor_reply'] ? 'bg-primary bg-opacity-10 border border-primary border-opacity-25' : 'bg-light' ?>">
                                            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded-circle overflow-hidden bg-primary text-white fw-bold d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.75rem;">
                                                        <?php if (!empty($reply['avatar'])): ?>
                                                            <img src="<?= e($reply['avatar']) ?>" alt="Avatar" style="width:100%; height:100%; object-fit:cover;">
                                                        <?php else: ?>
                                                            <?= strtoupper(substr($reply['first_name'] ?? 'U', 0, 1)) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <strong class="small text-main">
                                                        <?= e($reply['first_name'] . ' ' . $reply['last_name']) ?>
                                                    </strong>
                                                    <?php if ($reply['is_instructor_reply']): ?>
                                                        <span class="badge bg-warning text-dark rounded-pill fw-bold small px-2 py-0" style="font-size: 0.7rem;">
                                                            <i class="bi bi-patch-check-fill text-dark me-1"></i>Verified Instructor
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-muted" style="font-size: 0.72rem;"><?= date('M d, g:i A', strtotime($reply['created_at'])) ?></small>
                                            </div>
                                            <div class="small text-secondary lh-base"><?= nl2br(e($reply['reply'])) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="pt-3 border-top mt-2">
                                <form method="POST" action="<?= url('student/lesson.php?id=' . $lessonId) ?>" class="d-flex gap-2 align-items-center">
                                    <input type="hidden" name="post_reply" value="1">
                                    <input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>">
                                    <input type="text" name="reply_content" class="form-control form-control-sm rounded-pill px-3" placeholder="<?= ($role === ROLE_TEACHER || $role === ROLE_ADMIN) ? 'Reply as Instructor...' : 'Write a follow-up reply...' ?>" required>
                                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold flex-shrink-0" data-feedback="click">
                                        Reply
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-chat-heart display-4 mb-2 d-block text-muted opacity-50"></i>
                    <h6 class="fw-bold text-main">No questions posted yet</h6>
                    <p class="small mb-0">Be the first to ask a question about this lesson! Your instructor is here to help.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">

        <div class="card border-0 shadow-sm rounded-4 p-4 bg-primary text-white mb-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%) !important;">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-robot fs-3 text-warning"></i>
                <h5 class="fw-bold mb-0 text-white">AI Lesson Tutor</h5>
            </div>
            <p class="small text-white-50 mb-3">Ask questions specifically about <strong><?= e($lesson['title']) ?></strong>!</p>

            <div class="bg-dark bg-opacity-40 p-3 rounded-3 mb-3 small" style="max-height: 220px; overflow-y: auto;">
                <div class="text-warning fw-bold mb-1"><i class="bi bi-stars me-1"></i> Quick AI Suggestions:</div>
                <ul class="ps-3 mb-0 text-white-50">
                    <li>&ldquo;Explain this lesson in simple terms&rdquo;</li>
                    <li>&ldquo;Give me 3 practice quiz questions&rdquo;</li>
                    <li>&ldquo;Summarize the main takeaways&rdquo;</li>
                </ul>
            </div>

            <button class="btn btn-warning text-dark rounded-pill fw-bold py-2 w-100 shadow-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#aiAssistantDrawer">
                <i class="bi bi-stars me-1"></i> Open Live AI Chat
            </button>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 bg-card mb-4">
            <h6 class="fw-bold mb-3 text-main"><i class="bi bi-person-badge text-primary me-2"></i>Course Instructor</h6>
            <?php if (!empty($lesson['teacher_id']) && !empty($lesson['teacher_name'])): ?>
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle overflow-hidden bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 52px; height: 52px;">
                        <?php if (!empty($lesson['teacher_avatar'])): ?>
                            <img src="<?= e($lesson['teacher_avatar']) ?>" alt="<?= e($lesson['teacher_name']) ?>" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <?= strtoupper(substr($lesson['teacher_name'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-main"><?= e($lesson['teacher_name']) ?></h6>
                        <small class="text-muted d-block"><?= e($lesson['teacher_qualification'] ?: 'Certified Instructor') ?></small>
                        <span class="badge bg-warning bg-opacity-15 text-dark rounded-pill px-2 py-0 small mt-1">
                            <i class="bi bi-star-fill text-warning me-1"></i><?= number_format((float)($lesson['teacher_rating'] ?? 5.0), 1) ?> Rating
                        </span>
                    </div>
                </div>
            <?php else: ?>
                <div class="p-3 bg-light rounded-3 border text-center">
                    <i class="bi bi-person-x text-muted fs-3 mb-1 d-block"></i>
                    <div class="fw-bold small text-main mb-1">No Teacher Currently Assigned</div>
                    <p class="text-muted small mb-0" style="font-size:0.75rem;">This course is in self-paced mode. All video lectures, quiz assessments, and 24/7 AI tutor support are fully active.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 bg-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold mb-0 text-main"><i class="bi bi-list-task text-primary me-2"></i> Course Lessons</h6>
                <span class="badge bg-secondary bg-opacity-10 text-muted rounded-pill small"><?= count($allLessons) ?> Total</span>
            </div>
            <div class="list-group list-group-flush small">
                <?php foreach ($allLessons as $l): ?>
                    <a href="<?= url('student/lesson.php?id=' . (int)$l['id']) ?>" class="list-group-item list-group-item-action p-2 rounded-3 border-0 mb-1 d-flex align-items-center justify-content-between <?= (int)$l['id'] === $lessonId ? 'bg-primary text-white fw-bold' : 'text-secondary' ?>">
                        <div class="d-flex align-items-center gap-2 line-clamp-1">
                            <i class="bi bi-<?= (int)$l['id'] === $lessonId ? 'play-circle-fill' : 'circle' ?> fs-6"></i>
                            <span class="line-clamp-1"><?= e($l['title']) ?></span>
                        </div>
                        <?php if ($l['is_free']): ?>
                            <span class="badge bg-success bg-opacity-25 text-success rounded-pill px-2 py-0" style="font-size:0.65rem;">Free</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
