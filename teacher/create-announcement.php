<?php
/**
 * StudyMe AI Platform — Teacher Create Announcement Page
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/announcements.php';

secure_page(ROLE_TEACHER);
$user   = current_user();
$userId = (int)$user['id'];
$pdo    = getDBConnection();

// Fetch teacher's assigned courses
$stmtTeacher = $pdo->prepare("SELECT id, assigned_course_id FROM teachers WHERE user_id = ? LIMIT 1");
$stmtTeacher->execute([$userId]);
$teacher = $stmtTeacher->fetch(PDO::FETCH_ASSOC);
$teacherId = $teacher ? (int)$teacher['id'] : 0;
$assignedCourseId = (int)($teacher['assigned_course_id'] ?? 0);

// Get all courses owned or assigned to this teacher
$stmtCourses = $pdo->prepare("SELECT id, title FROM courses WHERE teacher_id = ? OR id = ? ORDER BY title ASC");
$stmtCourses->execute([$teacherId, $assignedCourseId]);
$courses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);

if (empty($courses)) {
    // If no course row exists yet, check if subject exists
    if ($assignedCourseId > 0) {
        $stmtSubj = $pdo->prepare("SELECT name AS title FROM subjects WHERE id = ? LIMIT 1");
        $stmtSubj->execute([$assignedCourseId]);
        $sTitle = $stmtSubj->fetchColumn();
        if ($sTitle) {
            $courses[] = ['id' => $assignedCourseId, 'title' => $sTitle];
        }
    }
}

$errors = [];
$title = '';
$message = '';
$selectedCourseId = $courses[0]['id'] ?? 0;
$priority = 'normal';
$status = 'published';

if (is_post()) {
    $title            = trim($_POST['title'] ?? '');
    $message          = trim($_POST['message'] ?? '');
    $selectedCourseId = (int)($_POST['course_id'] ?? 0);
    $priority         = in_array($_POST['priority'] ?? '', ['normal', 'important', 'urgent']) ? $_POST['priority'] : 'normal';
    $status           = in_array($_POST['status'] ?? '', ['published', 'draft']) ? $_POST['status'] : 'published';
    $attachment       = $_FILES['attachment'] ?? null;

    if (empty($title)) {
        $errors[] = 'Announcement title is required.';
    }
    if (empty($message)) {
        $errors[] = 'Announcement message content is required.';
    }
    if ($selectedCourseId <= 0) {
        $errors[] = 'Please select an assigned teaching course.';
    }

    if (empty($errors)) {
        $result = create_announcement_entry(
            $title,
            $message,
            $userId,
            'teacher',
            'course',
            $selectedCourseId,
            $priority,
            $status,
            $attachment
        );

        if ($result['success']) {
            set_flash('success', 'Announcement published successfully to enrolled course students!');
            redirect('teacher/announcements.php');
        } else {
            $errors[] = $result['error'];
        }
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <a href="<?= url('teacher/announcements.php') ?>" class="text-decoration-none small text-muted">
                        &larr; Back to My Announcements
                    </a>
                    <h2 class="fw-bold mb-0 mt-1"><i class="bi bi-megaphone-fill text-warning me-2"></i> Create Announcement</h2>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 bg-white p-4 p-md-5">
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger rounded-3 mb-4">
                        <ul class="mb-0 small ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="<?= url('teacher/create-announcement.php') ?>" method="POST" enctype="multipart/form-data">
                    
                    <!-- Title -->
                    <div class="mb-3">
                        <label for="title" class="form-label fw-bold small">Announcement Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="title" class="form-control form-control-lg rounded-3" placeholder="e.g. New Web Development Project Uploaded" value="<?= e($title) ?>" required>
                    </div>

                    <!-- Target Course (Locked strictly to teacher's assigned courses) -->
                    <div class="mb-3">
                        <label for="course_id" class="form-label fw-bold small">Target Course (Enrolled Students) <span class="text-danger">*</span></label>
                        <select name="course_id" id="course_id" class="form-select py-2 rounded-3" required>
                            <?php if (count($courses) > 1): ?>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= (int)$c['id'] === $selectedCourseId ? 'selected' : '' ?>>
                                        <?= e($c['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php elseif (!empty($courses)): ?>
                                <option value="<?= $courses[0]['id'] ?>" selected>
                                    <?= e($courses[0]['title']) ?> (Your Assigned Course)
                                </option>
                            <?php else: ?>
                                <option value="1">Assigned Teacher Course</option>
                            <?php endif; ?>
                        </select>
                        <div class="form-text small text-muted">
                            <i class="bi bi-shield-lock-fill text-success me-1"></i> Only students enrolled in this course will receive this announcement.
                        </div>
                    </div>

                    <!-- Message Body -->
                    <div class="mb-3">
                        <label for="message" class="form-label fw-bold small">Message Content <span class="text-danger">*</span></label>
                        <textarea name="message" id="message" rows="6" class="form-control rounded-3" placeholder="Type your detailed message, instructions, or updates for students..." required><?= e($message) ?></textarea>
                    </div>

                    <div class="row g-3 mb-4">
                        <!-- Priority -->
                        <div class="col-md-6">
                            <label for="priority" class="form-label fw-bold small">Priority Level</label>
                            <select name="priority" id="priority" class="form-select py-2 rounded-3">
                                <option value="normal" <?= $priority === 'normal' ? 'selected' : '' ?>>Normal Update</option>
                                <option value="important" <?= $priority === 'important' ? 'selected' : '' ?>>Important Notice</option>
                                <option value="urgent" <?= $priority === 'urgent' ? 'selected' : '' ?>>Urgent / Immediate Action</option>
                            </select>
                        </div>

                        <!-- Status -->
                        <div class="col-md-6">
                            <label for="status" class="form-label fw-bold small">Publication Status</label>
                            <select name="status" id="status" class="form-select py-2 rounded-3">
                                <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Publish &amp; Notify Students Now</option>
                                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Save as Draft</option>
                            </select>
                        </div>
                    </div>

                    <!-- Attachment Upload -->
                    <div class="mb-4">
                        <label for="attachment" class="form-label fw-bold small">Attach Resource / File (Optional)</label>
                        <input type="file" name="attachment" id="attachment" class="form-control rounded-3" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,.txt,.zip">
                        <div class="form-text small text-muted">
                            Allowed formats: PDF, Image, Word Document, Zip (Max 15MB).
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                        <a href="<?= url('teacher/announcements.php') ?>" class="btn btn-light rounded-pill px-4">Cancel</a>
                        <button type="submit" class="btn btn-warning rounded-pill px-5 fw-bold text-dark shadow-sm" data-feedback="success">
                            <i class="bi bi-send-fill me-1"></i> Publish Announcement
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
