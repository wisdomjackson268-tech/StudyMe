<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';
require_once BASE_PATH . '/includes/functions/activity.php';

secure_page(ROLE_STUDENT);

$user   = current_user();
$userId = (int)$user['id'];
$pdo    = getDBConnection();

log_user_activity($userId, 'viewed_study_materials', 'Student browsed Secondary Study Materials');

$selectedId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$subjectFilter = strtolower(trim($_GET['subject'] ?? ''));
$typeFilter = strtolower(trim($_GET['type'] ?? ''));

// Fetch single material if ID is given
$singleMaterial = null;
if ($selectedId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, s.name AS subject_name, s.slug AS subject_slug, s.icon AS subject_icon, s.color AS subject_color
            FROM secondary_materials m
            JOIN secondary_subjects s ON m.subject_id = s.id
            WHERE m.id = ? AND m.status = 'published'
            LIMIT 1
        ");
        $stmt->execute([$selectedId]);
        $singleMaterial = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Fetch all materials list
$where = ["m.status = 'published'"];
$params = [];

if (!empty($subjectFilter)) {
    $where[] = "s.slug = ?";
    $params[] = $subjectFilter;
}
if (!empty($typeFilter)) {
    $where[] = "m.content_type = ?";
    $params[] = $typeFilter;
}

$sql = "
    SELECT m.*, s.name AS subject_name, s.slug AS subject_slug, s.icon AS subject_icon, s.color AS subject_color
    FROM secondary_materials m
    JOIN secondary_subjects s ON m.subject_id = s.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY m.sort_order ASC, m.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

$subjects = get_secondary_subjects('active');

$pageTitle = 'Secondary Study Materials & Formula Sheets | StudyMe';
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="<?= url('student/secondary-dashboard.php') ?>" class="text-decoration-none text-muted small">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
            <span class="text-muted small">&bull;</span>
            <span class="text-muted small">Academic Library</span>
        </div>
        <h2 class="fw-bold mb-0 text-dark">
            <i class="bi bi-file-earmark-text-fill text-warning me-2"></i> Secondary Study Materials &amp; Notes
        </h2>
    </div>
</div>

<?php if ($singleMaterial): ?>
    <!-- DETAILED STUDY MATERIAL VIEWER -->
    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-5 bg-white border">
        <div class="d-flex align-items-center justify-content-between pb-3 mb-4 border-bottom flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded-4" style="background: <?= e($singleMaterial['subject_color'] ?? '#2563EB') ?>15; color: <?= e($singleMaterial['subject_color'] ?? '#2563EB') ?>; font-size: 1.75rem;">
                    <i class="bi <?= e($singleMaterial['subject_icon'] ?? 'bi-book-half') ?>"></i>
                </div>
                <div>
                    <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold mb-1">
                        <?= strtoupper(e(str_replace('_', ' ', $singleMaterial['content_type']))) ?>
                    </span>
                    <h3 class="fw-bold text-dark mb-1"><?= e($singleMaterial['title']) ?></h3>
                    <small class="text-muted"><?= e($singleMaterial['subject_name']) ?> &bull; <?= $singleMaterial['duration_minutes'] ?> mins estimated read</small>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-3" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print / Save PDF
                </button>
                <a href="<?= url('student/secondary-materials.php') ?>" class="btn btn-primary rounded-pill px-3">
                    Back to Library
                </a>
            </div>
        </div>

        <!-- Material Markdown Render Area -->
        <div class="material-content p-2 text-dark lh-lg" style="font-size: 1.05rem;">
            <?= nl2br(e($singleMaterial['content_body'])) ?>
        </div>

        <div class="pt-4 mt-5 border-top d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Have questions about this material? Consult your AI Tutor.
            </div>
            <a href="<?= url('student/secondary-ai-tutor.php?preset=' . urlencode("Explain key concepts from: " . $singleMaterial['title'])) ?>" class="btn btn-warning rounded-pill px-4 fw-bold text-dark">
                <i class="bi bi-robot me-1"></i> Discuss this Note with AI Tutor
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- FILTER CONTROLS -->
<div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white border">
    <form method="GET" action="<?= url('student/secondary-materials.php') ?>" class="row g-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label small fw-bold text-muted">Filter by Subject:</label>
            <select name="subject" class="form-select rounded-3">
                <option value="">All Subjects</option>
                <?php foreach ($subjects as $s): ?>
                <option value="<?= e($s['slug']) ?>" <?= $subjectFilter === $s['slug'] ? 'selected' : '' ?>>
                    <?= e($s['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-5">
            <label class="form-label small fw-bold text-muted">Material Type:</label>
            <select name="type" class="form-select rounded-3">
                <option value="">All Material Types</option>
                <option value="notes" <?= $typeFilter === 'notes' ? 'selected' : '' ?>>Revision Notes</option>
                <option value="summary" <?= $typeFilter === 'summary' ? 'selected' : '' ?>>Topic Summary</option>
                <option value="formula_sheet" <?= $typeFilter === 'formula_sheet' ? 'selected' : '' ?>>Formula Sheets</option>
            </select>
        </div>

        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100 rounded-3 fw-bold">Filter</button>
        </div>
    </form>
</div>

<!-- MATERIALS GRID -->
<div class="row g-4 mb-5">
    <?php if (!empty($materials)): ?>
        <?php foreach ($materials as $m): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 d-flex flex-column justify-content-between border-top border-4" style="border-top-color: <?= e($m['subject_color'] ?? '#2563EB') ?> !important;">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold small">
                            <?= strtoupper(e(str_replace('_', ' ', $m['content_type']))) ?>
                        </span>
                        <small class="text-muted"><i class="bi bi-clock me-1"></i> <?= $m['duration_minutes'] ?> mins</small>
                    </div>

                    <h5 class="fw-bold text-dark mb-2"><?= e($m['title']) ?></h5>
                    <p class="text-muted small mb-3 text-truncate-2">
                        <?= e(substr(strip_tags($m['content_body']), 0, 120)) ?>...
                    </p>
                </div>

                <div class="pt-3 border-top d-flex align-items-center justify-content-between">
                    <span class="small fw-semibold text-primary"><?= e($m['subject_name']) ?></span>
                    <a href="<?= url('student/secondary-materials.php?id=' . $m['id']) ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">
                        Read Material <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12 text-center py-5 text-muted">
            <i class="bi bi-file-earmark-x fs-1 d-block mb-2 text-muted"></i>
            No study materials published matching this filter.
            <div class="mt-3">
                <a href="<?= url('student/secondary-materials.php') ?>" class="btn btn-outline-primary rounded-pill px-4">
                    Clear Filters
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
