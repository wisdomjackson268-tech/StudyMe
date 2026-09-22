<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();
$errors = [];
$successMsg = '';

// Handle Subject / Topic Actions
if (is_post()) {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'create_subject') {
        $name        = trim($_POST['name'] ?? '');
        $slug        = trim($_POST['slug'] ?? '');
        $code        = strtoupper(trim($_POST['code'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        $icon        = trim($_POST['icon'] ?? 'bi-book-half');
        $color       = trim($_POST['color'] ?? '#2563EB');
        $classLevel  = trim($_POST['class_level'] ?? 'Senior Secondary (SS1 - SS3)');

        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        }

        if (empty($name) || empty($code)) {
            $errors[] = 'Subject Name and Code are required.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO secondary_subjects (name, slug, code, description, icon, color, class_level, status, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 1)
                ");
                $stmt->execute([$name, $slug, $code, $description, $icon, $color, $classLevel]);
                $successMsg = 'Secondary Subject added successfully!';
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'create_topic') {
        $subjectId   = (int)($_POST['subject_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $term        = trim($_POST['term'] ?? 'First Term');
        $classLevel  = trim($_POST['class_level'] ?? 'SS1');
        $description = trim($_POST['description'] ?? '');
        $slug        = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));

        if ($subjectId <= 0 || empty($title)) {
            $errors[] = 'Please select a subject and specify a topic title.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO secondary_topics (subject_id, title, slug, description, term, class_level, sort_order, status)
                    VALUES (?, ?, ?, ?, ?, ?, 1, 'active')
                ");
                $stmt->execute([$subjectId, $title, $slug, $description, $term, $classLevel]);
                $successMsg = 'Curriculum Topic added successfully!';
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_subject') {
        $subId = (int)($_POST['subject_id'] ?? 0);
        if ($subId > 0) {
            $pdo->prepare("DELETE FROM secondary_subjects WHERE id = ?")->execute([$subId]);
            $successMsg = 'Subject deleted.';
        }
    } elseif ($action === 'delete_topic') {
        $topId = (int)($_POST['topic_id'] ?? 0);
        if ($topId > 0) {
            $pdo->prepare("DELETE FROM secondary_topics WHERE id = ?")->execute([$topId]);
            $successMsg = 'Topic deleted.';
        }
    }
}

$subjects = get_secondary_subjects(null);
$topics = get_secondary_topics(null, null);

$pageTitle = 'Manage Secondary Subjects & Topics | Admin';
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small">
            <i class="bi bi-book-half text-success me-1"></i> Admin Command Center
        </p>
        <h2 class="fw-bold mb-0 text-dark">Secondary Subjects &amp; Curriculum Topics</h2>
    </div>

    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#newSubjectModal">
            <i class="bi bi-plus-lg me-1"></i> Add Subject
        </button>
        <button type="button" class="btn btn-outline-primary rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#newTopicModal">
            <i class="bi bi-plus-lg me-1"></i> Add Topic
        </button>
    </div>
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

<!-- SUBJECTS TABLE -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5 bg-white border">
    <div class="card-header bg-white p-3 p-md-4 border-bottom">
        <h5 class="fw-bold text-dark mb-0">Active Secondary School Subjects (<?= count($subjects) ?>)</h5>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Code &amp; Subject</th>
                    <th>Slug</th>
                    <th>Topics</th>
                    <th>Questions</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subjects as $s): ?>
                <tr>
                    <td class="ps-4">
                        <span class="badge bg-dark font-monospace me-1"><?= e($s['code']) ?></span>
                        <strong class="text-dark"><?= e($s['name']) ?></strong>
                    </td>
                    <td class="font-monospace text-muted"><?= e($s['slug']) ?></td>
                    <td><span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3"><?= (int)$s['topic_count'] ?> Topics</span></td>
                    <td><span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3"><?= (int)$s['question_count'] ?> Qs</span></td>
                    <td>
                        <span class="badge <?= $s['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?> rounded-pill px-2 py-1">
                            <?= ucfirst(e($s['status'])) ?>
                        </span>
                    </td>
                    <td class="text-end pe-4">
                        <form method="POST" action="<?= url('admin/secondary-subjects.php') ?>" class="d-inline" onsubmit="return confirm('Delete subject and all related topics?');">
                            <input type="hidden" name="action" value="delete_subject">
                            <input type="hidden" name="subject_id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-1">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- TOPICS TABLE -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5 bg-white border">
    <div class="card-header bg-white p-3 p-md-4 border-bottom">
        <h5 class="fw-bold text-dark mb-0">Curriculum Syllabus Topics (<?= count($topics) ?>)</h5>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Subject</th>
                    <th>Topic Title</th>
                    <th>Term / Level</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topics as $t): ?>
                <tr>
                    <td class="ps-4 fw-bold text-primary"><?= e($t['subject_name']) ?></td>
                    <td class="fw-semibold text-dark"><?= e($t['title']) ?></td>
                    <td><span class="badge bg-light text-dark border"><?= e($t['class_level']) ?> &bull; <?= e($t['term']) ?></span></td>
                    <td class="text-end pe-4">
                        <form method="POST" action="<?= url('admin/secondary-subjects.php') ?>" class="d-inline" onsubmit="return confirm('Delete this topic?');">
                            <input type="hidden" name="action" value="delete_topic">
                            <input type="hidden" name="topic_id" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-1">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD SUBJECT MODAL -->
<div class="modal fade" id="newSubjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-light border-bottom p-4">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-plus-circle text-primary me-2"></i> Add Secondary Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= url('admin/secondary-subjects.php') ?>">
                <input type="hidden" name="action" value="create_subject">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Subject Name:</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Further Mathematics" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Subject Code (3-4 Letters):</label>
                        <input type="text" name="code" class="form-control font-monospace text-uppercase" placeholder="e.g. FMT" maxlength="10" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Description:</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Overview of curriculum coverage..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Bootstrap Icon Class:</label>
                        <input type="text" name="icon" class="form-control" value="bi-book-half">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Theme Color Hex:</label>
                        <input type="color" name="color" class="form-control form-control-color" value="#2563EB">
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-3">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Save Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ADD TOPIC MODAL -->
<div class="modal fade" id="newTopicModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-light border-bottom p-4">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-plus-circle text-primary me-2"></i> Add Curriculum Topic</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= url('admin/secondary-subjects.php') ?>">
                <input type="hidden" name="action" value="create_topic">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Subject:</label>
                        <select name="subject_id" class="form-select" required>
                            <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Topic Title:</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Indices and Logarithmic Equations" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Class Level:</label>
                            <select name="class_level" class="form-select">
                                <option value="SS1">SS1</option>
                                <option value="SS2">SS2</option>
                                <option value="SS3">SS3</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Academic Term:</label>
                            <select name="term" class="form-select">
                                <option value="First Term">First Term</option>
                                <option value="Second Term">Second Term</option>
                                <option value="Third Term">Third Term</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Description / Syllabus Notes:</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief outline of subtopics..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-3">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Save Topic</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
