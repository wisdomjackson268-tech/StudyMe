<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_TEACHER);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$user = current_user();
$pdo  = getDBConnection();
$uid  = $user['id'];
$lid  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;

$stmt = $pdo->prepare("
    SELECT l.*, cs.course_id, c.teacher_id
    FROM lessons l
    JOIN course_sections cs ON l.section_id = cs.id
    JOIN courses c ON cs.course_id = c.id
    WHERE l.id = ? LIMIT 1
");
$stmt->execute([$lid]);
$lesson = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lesson) {
    set_flash('error', 'Lesson not found.');
    redirect('teacher/lessons.php');
}

if ($lesson['teacher_id'] != $tid && current_user_role() !== ROLE_ADMIN) {
    set_flash('error', 'ACCESS DENIED: You cannot edit a lesson belonging to another teacher.');
    redirect('teacher/lessons.php');
}

if (is_post()) {
    $title         = trim($_POST['title'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $content       = trim($_POST['content'] ?? '');
    $videoUrl      = trim($_POST['video_url'] ?? '');
    $videoDuration = (int)($_POST['video_duration'] ?? 600);
    $isFree        = isset($_POST['is_free']) ? 1 : 0;
    $status        = $_POST['status'] ?? 'published';

    if (empty($title)) {
        set_flash('error', 'Lesson title is required.');
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE lessons
                SET title = ?, description = ?, content = ?, video_url = ?, video_duration = ?, is_free = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $content, $videoUrl, $videoDuration, $isFree, $status, $lid]);

            set_flash('success', 'Lesson updated successfully!');
            redirect('teacher/lessons.php?course_id=' . $lesson['course_id']);
        } catch (Exception $e) {
            set_flash('error', 'Update error: ' . $e->getMessage());
        }
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-pencil-square text-primary me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0">Edit Lesson: <?= e($lesson['title']) ?></h2>
    </div>
    <a href="<?= url('teacher/lessons.php?course_id=' . $lesson['course_id']) ?>" class="btn btn-outline-secondary rounded-pill px-4">
        <i class="bi bi-arrow-left me-1"></i> Back to Lessons
    </a>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 max-w-2xl">
    <form method="POST">
        <div class="mb-3">
            <label class="form-label fw-bold">Lesson Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control rounded-3" value="<?= e($lesson['title']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Video URL</label>
            <input type="url" name="video_url" class="form-control rounded-3" value="<?= e($lesson['video_url']) ?>">
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label fw-bold">Video Duration (Seconds)</label>
                <input type="number" name="video_duration" class="form-control rounded-3" value="<?= (int)$lesson['video_duration'] ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Status</label>
                <select name="status" class="form-select rounded-3">
                    <option value="published" <?= $lesson['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="draft" <?= $lesson['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Lesson Content &amp; Lecture Notes</label>
            <textarea name="content" class="form-control rounded-3" rows="5"><?= e($lesson['content']) ?></textarea>
        </div>

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="is_free" id="isFree" value="1" <?= $lesson['is_free'] ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="isFree">Allow as Free Preview Lesson</label>
        </div>

        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold py-2">
            <i class="bi bi-save me-1"></i> Update Lesson
        </button>
    </form>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
