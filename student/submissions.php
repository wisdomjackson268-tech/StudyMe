<?php
/**
 * StudyMe AI Platform — Student Submissions Handler
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/uploads.php';

secure_page(ROLE_STUDENT);

$assignmentId = (int)($_GET['assignment_id'] ?? $_POST['assignment_id'] ?? 0);
$user = current_user();
$pdo = getDBConnection();
$userId = $user['id'];

// Resolve student ID
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId = $student ? (int)$student['id'] : 0;

$assignment = null;
if ($assignmentId) {
    $stmt = $pdo->prepare("
        SELECT a.*, c.title AS course_title, c.id AS course_id
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        WHERE a.id = ? LIMIT 1
    ");
    $stmt->execute([$assignmentId]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($assignment && current_user_role() === ROLE_STUDENT) {
        $stmtEn = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ? AND status = 'active' LIMIT 1");
        $stmtEn->execute([$studentId, $assignment['course_id']]);
        if (!$stmtEn->fetch()) {
            set_flash('error', 'Assignment Locked: You can only submit tasks for your active enrolled course.');
            redirect('student/dashboard.php');
        }
    }
}

// Handle assignment submission
$errors = [];
if (is_post() && isset($_POST['submit_solution']) && $assignment && $studentId) {
    $submissionText = trim($_POST['submission_text'] ?? '');
    $attachmentPath = null;

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $docRes = upload_document($_FILES['attachment'], $studentId);
        if ($docRes['success']) {
            $attachmentPath = $docRes['relative_path'];
        } else {
            $errors[] = 'Attachment upload failed: ' . $docRes['error'];
        }
    }

    if (empty($submissionText) && empty($attachmentPath)) {
        $errors[] = 'Please provide written text or attach a file solution.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO assignment_submissions (assignment_id, student_id, submission_text, attachment, status, submitted_at)
            VALUES (?, ?, ?, ?, 'submitted', NOW())
            ON DUPLICATE KEY UPDATE submission_text = VALUES(submission_text), attachment = VALUES(attachment), status = 'submitted', submitted_at = NOW()
        ");
        $stmt->execute([$assignmentId, $studentId, $submissionText, $attachmentPath]);

        set_flash('success', 'Assignment solution submitted successfully!');
        redirect('student/assignments.php');
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="mb-4">
    <a href="<?= url('student/assignments.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill mb-3">
        <i class="bi bi-arrow-left me-1"></i> Back to Assignments
    </a>
    <h1 class="h3 fw-bold mb-1"><i class="bi bi-upload text-primary me-2"></i> Submit Assignment Solution</h1>
    <?php if ($assignment): ?>
        <p class="text-muted mb-0"><?= e($assignment['course_title']) ?> &bull; <strong><?= e($assignment['title']) ?></strong></p>
    <?php endif; ?>
</div>

<?php if ($assignment): ?>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-5">
                <div class="bg-light p-4 rounded-3 mb-4 border">
                    <h5 class="fw-bold mb-2">Assignment Brief</h5>
                    <p class="text-secondary small mb-0 lh-lg"><?= e($assignment['description'] ?: 'No additional notes.') ?></p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger rounded-3 p-3 mb-4">
                        <ul class="mb-0 small ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="<?= url('student/submissions.php') ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="assignment_id" value="<?= $assignmentId ?>">
                    <input type="hidden" name="submit_solution" value="1">

                    <div class="mb-4">
                        <label for="submissionText" class="form-label fw-semibold small">Written Solution / Code Notes</label>
                        <textarea name="submission_text" id="submissionText" rows="6" class="form-control rounded-3" placeholder="Paste code, written answers, or explanations here..."></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="attachment" class="form-label fw-semibold small">Attach Solution File (PDF, DOC, DOCX, TXT)</label>
                        <input type="file" name="attachment" id="attachment" class="form-control py-2 rounded-3">
                        <div class="form-text">Max file size: 50 MB.</div>
                    </div>

                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm" data-feedback="success">
                        <i class="bi bi-send-fill me-2"></i> Submit Assignment
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
        <i class="bi bi-file-earmark-x text-muted fs-1 mb-2"></i>
        <h4 class="fw-bold">Assignment Not Found</h4>
        <a href="<?= url('student/assignments.php') ?>" class="btn btn-primary rounded-pill px-4 mt-3">View Assignments</a>
    </div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
