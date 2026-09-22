<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();
$errors = [];
$successMsg = '';

// Handle Material creation / deletion
if (is_post()) {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'create_material') {
        $subjectId       = (int)($_POST['subject_id'] ?? 0);
        $title           = trim($_POST['title'] ?? '');
        $contentType     = trim($_POST['content_type'] ?? 'notes');
        $durationMinutes = (int)($_POST['duration_minutes'] ?? 15);
        $contentBody     = trim($_POST['content_body'] ?? '');
        $slug            = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));

        if ($subjectId <= 0 || empty($title) || empty($contentBody)) {
            $errors[] = 'Please select a subject and provide a title and note body.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO secondary_materials (subject_id, title, slug, content_type, content_body, duration_minutes, downloadable, status, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, 1, 'published', 1)
                ");
                $stmt->execute([$subjectId, $title, $slug, $contentType, $contentBody, $durationMinutes]);
                $successMsg = 'Study Material published successfully!';
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_material') {
        $mId = (int)($_POST['material_id'] ?? 0);
        if ($mId > 0) {
            $pdo->prepare("DELETE FROM secondary_materials WHERE id = ?")->execute([$mId]);
            $successMsg = 'Study material deleted.';
        }
    }
}

$materials = get_secondary_materials(null, null);
$subjects = get_secondary_subjects('active');

$pageTitle = 'Manage Secondary Study Materials | Admin';
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small">
            <i class="bi bi-file-earmark-text text-info me-1"></i> Academic Notes Manager
        </p>
        <h2 class="fw-bold mb-0 text-dark">Secondary Study Materials &amp; Notes</h2>
    </div>

    <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#newMaterialModal">
        <i class="bi bi-plus-lg me-1"></i> Add Study Note
    </button>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger rounded-4 mb-4">
    <?php foreach ($errors as $e): ?><div><i class="bi bi-exclamation-circle-fill me-1"></i> <?= e($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($successMsg)): ?>
<div class="alert alert-success rounded-4 mb-4">
    <i class="bi bi-check-circle-fill me-1"></i> <?= e($successMsg) ?>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5 bg-white border">
    <div class="card-header bg-white p-3 p-md-4 border-bottom">
        <h5 class="fw-bold text-dark mb-0">Published Revision Notes &amp; Formula Sheets (<?= count($materials) ?>)</h5>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Subject</th>
                    <th>Note Title</th>
                    <th>Type</th>
                    <th>Read Time</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($materials)): ?>
                    <?php foreach ($materials as $m): ?>
                    <tr>
                        <td class="ps-4 fw-bold text-primary"><?= e($m['subject_name']) ?></td>
                        <td>
                            <strong class="text-dark"><?= e($m['title']) ?></strong>
                            <div class="text-muted small text-truncate" style="max-width: 380px;"><?= e(strip_tags($m['content_body'])) ?></div>
                        </td>
                        <td>
                            <span class="badge bg-warning text-dark rounded-pill px-2 py-1 fw-bold">
                                <?= strtoupper(e(str_replace('_', ' ', $m['content_type']))) ?>
                            </span>
                        </td>
                        <td class="text-muted"><?= (int)$m['duration_minutes'] ?> mins</td>
                        <td class="text-end pe-4">
                            <form method="POST" action="<?= url('admin/secondary-materials.php') ?>" class="d-inline" onsubmit="return confirm('Delete this study material?');">
                                <input type="hidden" name="action" value="delete_material">
                                <input type="hidden" name="material_id" value="<?= $m['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-1">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">No study materials published yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD MATERIAL MODAL -->
<div class="modal fade" id="newMaterialModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-light border-bottom p-4">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-plus-circle text-primary me-2"></i> Publish Study Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= url('admin/secondary-materials.php') ?>">
                <input type="hidden" name="action" value="create_material">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Select Subject:</label>
                            <select name="subject_id" class="form-select" required>
                                <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Content Type:</label>
                            <select name="content_type" class="form-select" required>
                                <option value="notes">Revision Notes</option>
                                <option value="summary">Topic Summary</option>
                                <option value="formula_sheet">Formula Sheet</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Est. Read Time (mins):</label>
                            <input type="number" name="duration_minutes" class="form-control" value="15" min="1">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Material Title:</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Physics Formula Sheet: Mechanics & Optics" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Content Body (Markdown / Text):</label>
                        <textarea name="content_body" class="form-control font-monospace" rows="10" placeholder="Type key formulas, definitions, and syllabus notes..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-3">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Publish Note</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
