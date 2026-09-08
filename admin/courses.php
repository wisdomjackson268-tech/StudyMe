<?php
/**
 * StudyMe AI Platform — Admin Course Management (Fixed SQL + Full Actions)
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo     = getDBConnection();
$errors  = [];
$success = '';

// ── POST Actions ─────────────────────────────────────────────
if (is_post()) {
    $action   = trim($_POST['action'] ?? '');
    $courseId = (int)($_POST['course_id'] ?? 0);

    if ($action === 'delete' && $courseId > 0) {
        // Check for active enrollments / payment records
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND status = 'active'");
        $stmt->execute([$courseId]);
        $activeStudents = (int)$stmt->fetchColumn();

        $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE course_id = ? AND status = 'successful'");
        $stmt2->execute([$courseId]);
        $paymentRecords = (int)$stmt2->fetchColumn();

        if ($activeStudents > 0 || $paymentRecords > 0) {
            // Archive instead of delete
            $pdo->prepare("UPDATE courses SET status = 'archived' WHERE id = ?")->execute([$courseId]);
            $success = "Course archived (not deleted) — it has {$activeStudents} active students and {$paymentRecords} payment record(s).";
        } else {
            $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$courseId]);
            $success = 'Course permanently deleted.';
        }
    } elseif ($action === 'publish' && $courseId > 0) {
        $pdo->prepare("UPDATE courses SET status = 'published', published_at = NOW() WHERE id = ?")->execute([$courseId]);
        $success = 'Course published.';
    } elseif ($action === 'unpublish' && $courseId > 0) {
        $pdo->prepare("UPDATE courses SET status = 'draft' WHERE id = ?")->execute([$courseId]);
        $success = 'Course unpublished (set to draft).';
    } elseif ($action === 'archive' && $courseId > 0) {
        $pdo->prepare("UPDATE courses SET status = 'archived' WHERE id = ?")->execute([$courseId]);
        $success = 'Course archived.';
    }
}

// ── Search & Filter ──────────────────────────────────────────
$search       = trim($_GET['search'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterCat    = (int)($_GET['category'] ?? 0);

$whereClauses = [];
$params       = [];

if ($search !== '') {
    $whereClauses[] = "(c.title LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
}
if ($filterStatus !== '') {
    $whereClauses[] = "c.status = ?";
    $params[] = $filterStatus;
}
if ($filterCat > 0) {
    $whereClauses[] = "c.category_id = ?";
    $params[] = $filterCat;
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Fetch all courses with real metrics (uses assignments table for tasks, not resources)
$stmt = $pdo->prepare("
    SELECT c.*, cat.name AS category_name,
           CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
           u.email AS teacher_email,
           (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'active') AS student_count,
           (SELECT COUNT(*) FROM lessons l JOIN course_sections cs ON l.section_id = cs.id WHERE cs.course_id = c.id) AS lesson_count,
           (SELECT COUNT(*) FROM lessons l JOIN course_sections cs ON l.section_id = cs.id WHERE cs.course_id = c.id AND l.video_url IS NOT NULL AND l.video_url != '') AS video_count,
           (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.id) AS task_count,
           (SELECT COUNT(*) FROM quizzes q WHERE q.course_id = c.id) AS quiz_count
    FROM courses c
    JOIN teachers t ON c.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    LEFT JOIN categories cat ON c.category_id = cat.id
    $whereSQL
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Categories for filter dropdown
$categories = $pdo->query("SELECT id, name FROM categories WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Summary stats
$totalAll       = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalPublished = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status='published'")->fetchColumn();
$totalDraft     = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status='draft'")->fetchColumn();
$totalArchived  = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status='archived'")->fetchColumn();

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-shield-check text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Course Moderation &amp; Management</h2>
    </div>
    <a href="<?= url('admin/create-course.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
        <i class="bi bi-plus-circle-fill me-1"></i> Create New Course
    </a>
</div>

<?php if ($success): ?><div class="alert alert-success rounded-3 mb-4"><i class="bi bi-check-circle me-1"></i> <?= e($success) ?></div><?php endif; ?>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon blue"><i class="bi bi-collection-play-fill"></i></div><div><div class="stat-value"><?= $totalAll ?></div><p class="stat-label">Total Courses</p></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon green"><i class="bi bi-broadcast"></i></div><div><div class="stat-value"><?= $totalPublished ?></div><p class="stat-label">Published</p></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon gold"><i class="bi bi-file-earmark-text"></i></div><div><div class="stat-value"><?= $totalDraft ?></div><p class="stat-label">Drafts</p></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon purple"><i class="bi bi-archive-fill"></i></div><div><div class="stat-value"><?= $totalArchived ?></div><p class="stat-label">Archived</p></div></div>
    </div>
</div>

<!-- Search & Filters -->
<div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control rounded-3" placeholder="Search course or teacher…" value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
            <select name="category" class="form-select rounded-3">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $filterCat===$cat['id']?'selected':'' ?>><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select rounded-3">
                <option value="">All Status</option>
                <option value="published" <?= $filterStatus==='published'?'selected':'' ?>>Published</option>
                <option value="draft"     <?= $filterStatus==='draft'    ?'selected':'' ?>>Draft</option>
                <option value="archived"  <?= $filterStatus==='archived' ?'selected':'' ?>>Archived</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary rounded-pill w-100"><i class="bi bi-search me-1"></i> Filter</button>
        </div>
        <div class="col-md-1">
            <a href="<?= url('admin/courses.php') ?>" class="btn btn-outline-secondary rounded-pill w-100"><i class="bi bi-x"></i></a>
        </div>
    </form>
</div>

<!-- Courses Table -->
<?php if (!empty($courses)): ?>
<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.04em;">
                <tr>
                    <th class="ps-4">Course</th>
                    <th>Teacher</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Students</th>
                    <th>Content</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $c): ?>
                <tr>
                    <td class="ps-4">
                            <?php $admThumb = function_exists('get_course_thumbnail_url') ? get_course_thumbnail_url($c['thumbnail'] ?? '', 'technology') : ($c['thumbnail'] ?? 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=200&q=80'); ?>
                            <img src="<?= e($admThumb) ?>"
                                 class="rounded-3 border" style="width:50px;height:38px;object-fit:cover;" alt="Course"
                                 onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=200&q=80';">
                            <div>
                                <div class="fw-bold text-main"><?= e(substr($c['title'],0,40)) ?><?= strlen($c['title'])>40?'…':'' ?></div>
                                <small class="text-muted"><?= date('d M Y', strtotime($c['created_at'])) ?></small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold small"><?= e($c['teacher_name']) ?></div>
                        <div class="text-muted" style="font-size:0.75rem;"><?= e($c['teacher_email']) ?></div>
                    </td>
                    <td><span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2"><?= e($c['category_name'] ?: 'General') ?></span></td>
                    <td class="fw-bold text-success">₦<?= number_format((float)$c['price']) ?></td>
                    <td class="fw-bold"><?= (int)$c['student_count'] ?></td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <span class="badge bg-light text-muted border" title="Lessons"><i class="bi bi-play-btn me-1"></i><?= (int)$c['lesson_count'] ?></span>
                            <span class="badge bg-light text-muted border" title="Videos"><i class="bi bi-camera-video me-1"></i><?= (int)$c['video_count'] ?></span>
                            <span class="badge bg-light text-muted border" title="Quizzes"><i class="bi bi-patch-question me-1"></i><?= (int)$c['quiz_count'] ?></span>
                            <span class="badge bg-light text-muted border" title="Tasks"><i class="bi bi-clipboard me-1"></i><?= (int)$c['task_count'] ?></span>
                        </div>
                    </td>
                    <td>
                        <span class="badge rounded-pill <?= $c['status']==='published' ? 'bg-success' : ($c['status']==='archived' ? 'bg-secondary' : 'bg-warning text-dark') ?>">
                            <?= ucfirst($c['status']) ?>
                        </span>
                    </td>
                    <td class="text-end pe-4">
                        <div class="d-flex justify-content-end gap-1">
                            <a href="<?= url('admin/course-details.php?id='.$c['id']) ?>"
                               class="btn btn-sm btn-outline-primary rounded-pill px-2" title="View Details">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="<?= url('admin/edit-course.php?id='.$c['id']) ?>"
                               class="btn btn-sm btn-outline-secondary rounded-pill px-2" title="Edit">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <!-- Status Toggle -->
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                                <?php if ($c['status'] === 'published'): ?>
                                    <input type="hidden" name="action" value="unpublish">
                                    <button type="submit" class="btn btn-sm btn-outline-warning rounded-pill px-2" title="Unpublish">
                                        <i class="bi bi-pause-circle"></i>
                                    </button>
                                <?php elseif ($c['status'] !== 'archived'): ?>
                                    <input type="hidden" name="action" value="publish">
                                    <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-2" title="Publish">
                                        <i class="bi bi-broadcast"></i>
                                    </button>
                                <?php endif; ?>
                            </form>
                            <!-- Delete with confirmation -->
                            <form method="POST" class="d-inline" id="delForm<?= $c['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Delete/Archive"
                                        onclick="confirmDelete(<?= $c['id'] ?>, '<?= e(addslashes($c['title'])) ?>', <?= (int)$c['student_count'] ?>)">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm rounded-4 p-5 text-center">
    <i class="bi bi-collection-play text-muted display-3 mb-3"></i>
    <h4 class="fw-bold">No Courses Found</h4>
    <p class="text-muted">No courses match your current filters.</p>
    <a href="<?= url('admin/courses.php') ?>" class="btn btn-outline-primary rounded-pill px-4">Clear Filters</a>
</div>
<?php endif; ?>

<script>
function confirmDelete(id, title, students) {
    let msg = '⚠️ Delete Course: "' + title + '"?\n\n';
    if (students > 0) {
        msg += '🚨 This course has ' + students + ' active student(s).\n';
        msg += 'It will be ARCHIVED instead of permanently deleted to protect enrollments and payment records.\n\n';
    } else {
        msg += 'This may affect:\n• All lessons\n• Videos\n• Documents\n• Quizzes\n• Tasks\n• Student enrollments\n\n';
    }
    msg += 'Proceed?';
    if (confirm(msg)) {
        document.getElementById('delForm' + id).submit();
    }
}
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
