<?php
/**
 * StudyMe AI Platform — Teacher Create Lesson Page
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_TEACHER);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$user = current_user();
$pdo  = getDBConnection();
$uid  = $user['id'];
$preSelectedCourse = (int)($_GET['course_id'] ?? 0);

// Get teacher ID
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;

// Fetch teacher's courses
$stmt = $pdo->prepare("SELECT id, title FROM courses WHERE teacher_id = ? OR id = (SELECT assigned_course_id FROM teachers WHERE id = ?) ORDER BY title ASC");
$stmt->execute([$tid, $tid]);
$myCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($myCourses)) {
    set_flash('error', 'You must create a course before adding lessons.');
    redirect('teacher/create-course.php');
}

if (is_post()) {
    $courseId      = (int)($_POST['course_id'] ?? 0);
    $sectionTitle  = trim($_POST['section_title'] ?? 'Module 1: Introduction');
    $title         = trim($_POST['title'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $content       = trim($_POST['content'] ?? '');
    $videoUrl      = trim($_POST['video_url'] ?? '');
    $videoDuration = (int)($_POST['video_duration'] ?? 600); // seconds
    $isFree        = isset($_POST['is_free']) ? 1 : 0;
    $status        = $_POST['status'] ?? 'published';

    // Verify course ownership
    $stmtCheck = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ? LIMIT 1");
    $stmtCheck->execute([$courseId, $tid]);

    if (!$stmtCheck->fetch()) {
        set_flash('error', 'ACCESS DENIED: Course does not belong to you.');
    } elseif (empty($title)) {
        set_flash('error', 'Lesson title is required.');
    } else {
        try {
            $pdo->beginTransaction();

            // Find or create course section
            $stmtSec = $pdo->prepare("SELECT id FROM course_sections WHERE course_id = ? AND title = ? LIMIT 1");
            $stmtSec->execute([$courseId, $sectionTitle]);
            $sec = $stmtSec->fetch(PDO::FETCH_ASSOC);

            if ($sec) {
                $sectionId = $sec['id'];
            } else {
                $stmtSecIns = $pdo->prepare("INSERT INTO course_sections (course_id, title, sort_order, created_at) VALUES (?, ?, 1, NOW())");
                $stmtSecIns->execute([$courseId, $sectionTitle]);
                $sectionId = $pdo->lastInsertId();
            }

            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-')) . '-' . time();

            $stmtLesson = $pdo->prepare("
                INSERT INTO lessons (section_id, title, slug, description, content, video_url, video_duration, is_free, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtLesson->execute([$sectionId, $title, $slug, $description, $content, $videoUrl, $videoDuration, $isFree, $status]);

            $pdo->commit();
            set_flash('success', 'Lesson created successfully!');
            redirect('teacher/lessons.php?course_id=' . $courseId);
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'Failed to create lesson: ' . $e->getMessage());
        }
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-plus-circle text-primary me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0">Add New Lesson</h2>
    </div>
    <a href="<?= url('teacher/lessons.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">
        <i class="bi bi-arrow-left me-1"></i> Cancel
    </a>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 max-w-2xl">
    <form method="POST">
        <div class="mb-3">
            <label class="form-label fw-bold">Select Course <span class="text-danger">*</span></label>
            <select name="course_id" class="form-select rounded-3" required>
                <option value="">-- Select Owned Course --</option>
                <?php foreach ($myCourses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $preSelectedCourse === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Section / Module Name</label>
            <input type="text" name="section_title" class="form-control rounded-3" value="Module 1: Introduction" placeholder="e.g. Section 1: Getting Started">
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Lesson Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control rounded-3" placeholder="e.g. 1.1 Overview &amp; Architecture Setup" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Video URL (YouTube / Vimeo / MP4)</label>
            <input type="url" name="video_url" class="form-control rounded-3" placeholder="https://www.youtube.com/watch?v=...">
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label fw-bold">Video Duration (Seconds)</label>
                <input type="number" name="video_duration" class="form-control rounded-3" value="600" min="1">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Status</label>
                <select name="status" class="form-select rounded-3">
                    <option value="published">Published</option>
                    <option value="draft">Draft</option>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Lesson Description &amp; Content</label>
            <textarea name="content" class="form-control rounded-3" rows="5" placeholder="Written lecture notes, code snippets, or study material..."></textarea>
        </div>

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="is_free" id="isFree" value="1">
            <label class="form-check-label fw-semibold" for="isFree">Allow as Free Preview Lesson</label>
        </div>

        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold py-2">
            <i class="bi bi-check-circle me-1"></i> Save Lesson
        </button>
    </form>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
