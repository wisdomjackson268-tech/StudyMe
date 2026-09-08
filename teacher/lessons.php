<?php
/**
 * StudyMe AI Platform — Teacher Lessons Manager
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

$lessons = [];
if ($tid) {
    $params = [$tid];
    $sql = "
        SELECT l.*, c.title AS course_title, cs.title AS section_title
        FROM lessons l
        JOIN course_sections cs ON l.section_id = cs.id
        JOIN courses c ON cs.course_id = c.id
        WHERE c.teacher_id = ?
    ";
    if ($courseFilter > 0) {
        $sql .= " AND c.id = ?";
        $params[] = $courseFilter;
    }
    $sql .= " ORDER BY c.id DESC, cs.sort_order ASC, l.sort_order ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-play-btn-fill text-primary me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0">Course Lessons</h2>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('teacher/create-lesson.php' . ($courseFilter ? '?course_id=' . $courseFilter : '')) ?>" class="btn btn-primary rounded-pill px-4 fw-bold">
            <i class="bi bi-plus-circle me-1"></i> Add New Lesson
        </a>
    </div>
</div>

<?php if (!empty($lessons)): ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Lesson Title</th>
                        <th>Course &amp; Section</th>
                        <th>Duration</th>
                        <th>Preview</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lessons as $l): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-main"><?= e($l['title']) ?></div>
                                <small class="text-muted"><?= e(substr(strip_tags($l['description'] ?? ''), 0, 60)) ?>...</small>
                            </td>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 mb-1 d-inline-block"><?= e($l['course_title']) ?></span>
                                <div class="small text-muted"><?= e($l['section_title']) ?></div>
                            </td>
                            <td class="small text-muted"><i class="bi bi-clock me-1"></i><?= (int)($l['video_duration'] / 60) ?> mins</td>
                            <td>
                                <?php if ($l['is_free']): ?>
                                    <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1">Free Preview</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-muted rounded-pill px-3 py-1">Locked</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $l['status'] === 'published' ? 'bg-success' : 'bg-secondary' ?> rounded-pill">
                                    <?= ucfirst($l['status']) ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <a href="<?= url('teacher/edit-lesson.php?id=' . $l['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    <i class="bi bi-pencil-square me-1"></i> Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4">
        <i class="bi bi-play-btn text-muted display-3 mb-3"></i>
        <h4 class="fw-bold">No Lessons Created Yet</h4>
        <p class="text-muted max-w-md mx-auto mb-4">Add lesson modules to your courses to deliver video lectures and downloadable resources.</p>
        <a href="<?= url('teacher/create-lesson.php' . ($courseFilter ? '?course_id=' . $courseFilter : '')) ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-bold align-self-center">Add First Lesson</a>
    </div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
