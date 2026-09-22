<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';
require_once BASE_PATH . '/includes/functions/activity.php';

secure_page(ROLE_STUDENT);

$user   = current_user();
$userId = (int)$user['id'];
$pdo    = getDBConnection();

log_user_activity($userId, 'viewed_secondary_subjects', 'Student explored Secondary Subjects Directory');

$search = trim($_GET['search'] ?? '');
$subjects = get_secondary_subjects('active');

if (!empty($search)) {
    $searchLower = strtolower($search);
    $subjects = array_filter($subjects, function($s) use ($searchLower) {
        return strpos(strtolower($s['name']), $searchLower) !== false ||
               strpos(strtolower($s['code']), $searchLower) !== false ||
               strpos(strtolower($s['description']), $searchLower) !== false;
    });
}

// Check if a specific subject was selected to view topics & materials
$selectedSlug = trim($_GET['subject'] ?? '');
$selectedSubject = null;
$selectedTopics = [];
$selectedMaterials = [];

if (!empty($selectedSlug)) {
    $selectedSubject = get_secondary_subject_by_slug($selectedSlug);
    if ($selectedSubject) {
        $selectedTopics = get_secondary_topics($selectedSubject['id']);
        $selectedMaterials = get_secondary_materials($selectedSubject['id']);
    }
}

$pageTitle = 'Secondary School Subjects | StudyMe';
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="<?= url('student/secondary-dashboard.php') ?>" class="text-decoration-none text-muted small">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
            <span class="text-muted small">&bull;</span>
            <span class="text-muted small">Academic Curriculum</span>
        </div>
        <h2 class="fw-bold mb-0 text-dark">
            <i class="bi bi-journals text-primary me-2"></i> Secondary School Subjects
        </h2>
    </div>

    <!-- Search input -->
    <form method="GET" action="<?= url('student/secondary-subjects.php') ?>" class="d-flex gap-2">
        <div class="input-group">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" name="search" class="form-control border-start-0" placeholder="Filter subjects..." value="<?= e($search) ?>">
            <button type="submit" class="btn btn-primary px-3 fw-bold">Search</button>
        </div>
        <?php if (!empty($search)): ?>
            <a href="<?= url('student/secondary-subjects.php') ?>" class="btn btn-outline-secondary">Reset</a>
        <?php endif; ?>
    </form>
</div>

<?php if ($selectedSubject): ?>
    <!-- SELECTED SUBJECT DEEP DIVE VIEW -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: linear-gradient(135deg, <?= e($selectedSubject['color'] ?? '#1E40AF') ?>15 0%, #FFFFFF 100%); border-left: 6px solid <?= e($selectedSubject['color'] ?? '#1E40AF') ?> !important;">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded-4 text-white" style="background: <?= e($selectedSubject['color'] ?? '#1E40AF') ?>; font-size: 2rem;">
                    <i class="bi <?= e($selectedSubject['icon'] ?? 'bi-book-half') ?>"></i>
                </div>
                <div>
                    <span class="badge bg-dark rounded-pill px-3 py-1 font-monospace mb-1"><?= e($selectedSubject['code']) ?></span>
                    <h3 class="fw-bold text-dark mb-1"><?= e($selectedSubject['name']) ?></h3>
                    <p class="text-muted mb-0" style="max-width: 700px;"><?= e($selectedSubject['description']) ?></p>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="<?= url('student/secondary-practice.php?subject=' . urlencode($selectedSubject['slug'])) ?>" class="btn btn-primary rounded-pill px-4 fw-bold">
                    <i class="bi bi-play-circle-fill me-1"></i> Start Practice CBT
                </a>
                <a href="<?= url('student/secondary-ai-tutor.php?subject=' . urlencode($selectedSubject['name'])) ?>" class="btn btn-warning rounded-pill px-4 fw-bold text-dark">
                    <i class="bi bi-robot me-1"></i> Ask AI Tutor
                </a>
                <a href="<?= url('student/secondary-subjects.php') ?>" class="btn btn-outline-secondary rounded-pill px-3">
                    Back to All Subjects
                </a>
            </div>
        </div>
    </div>

    <!-- TOPICS & STUDY MATERIALS TABS -->
    <div class="row g-4 mb-5">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <h5 class="fw-bold mb-3 text-dark d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-list-nested text-primary me-2"></i> Curriculum Topics (<?= count($selectedTopics) ?>)</span>
                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 small"><?= e($selectedSubject['class_level'] ?? 'SS1 - SS3') ?></span>
                </h5>

                <?php if (!empty($selectedTopics)): ?>
                <div class="accordion" id="topicsAccordion">
                    <?php foreach ($selectedTopics as $idx => $top): ?>
                    <div class="accordion-item border rounded-3 mb-2 overflow-hidden">
                        <h2 class="accordion-header" id="heading<?= $top['id'] ?>">
                            <button class="accordion-button <?= $idx !== 0 ? 'collapsed' : '' ?> fw-semibold py-3" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $top['id'] ?>" aria-expanded="<?= $idx === 0 ? 'true' : 'false' ?>" aria-controls="collapse<?= $top['id'] ?>">
                                <span class="badge bg-secondary bg-opacity-10 text-dark rounded-pill me-2"><?= $idx + 1 ?></span>
                                <?= e($top['title']) ?>
                                <span class="badge bg-info bg-opacity-10 text-info rounded-pill ms-auto me-3 small"><?= e($top['term'] ?? 'Term 1') ?></span>
                            </button>
                        </h2>
                        <div id="collapse<?= $top['id'] ?>" class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>" aria-labelledby="heading<?= $top['id'] ?>" data-bs-parent="#topicsAccordion">
                            <div class="accordion-body bg-light text-muted small">
                                <p class="mb-3"><?= e($top['description'] ?? 'Covers core WAEC and JAMB examination syllabus requirements for this topic.') ?></p>
                                <div class="d-flex gap-2">
                                    <a href="<?= url('student/secondary-practice.php?subject=' . urlencode($selectedSubject['slug']) . '&topic=' . urlencode($top['title'])) ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">
                                        <i class="bi bi-patch-question-fill me-1"></i> Practice This Topic
                                    </a>
                                    <a href="<?= url('student/secondary-ai-tutor.php?preset=' . urlencode("Explain " . $top['title'] . " in " . $selectedSubject['name'] . " with easy step-by-step examples and key formulas.")) ?>" class="btn btn-sm btn-outline-warning text-dark rounded-pill px-3 fw-bold">
                                        <i class="bi bi-robot me-1"></i> Explain with AI
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-journal-x fs-2 d-block mb-2"></i>
                    Topics are being synchronized for this subject curriculum.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <h5 class="fw-bold mb-3 text-dark">
                    <i class="bi bi-file-earmark-text-fill text-warning me-2"></i> Revision Notes &amp; Formulas
                </h5>

                <?php if (!empty($selectedMaterials)): ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($selectedMaterials as $mat): ?>
                    <div class="p-3 border rounded-3 bg-light">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="badge bg-warning text-dark rounded-pill px-2 py-1 small fw-bold">
                                <?= strtoupper(e(str_replace('_', ' ', $mat['content_type']))) ?>
                            </span>
                            <small class="text-muted"><i class="bi bi-clock me-1"></i> <?= $mat['duration_minutes'] ?> mins read</small>
                        </div>
                        <h6 class="fw-bold text-dark mb-1"><?= e($mat['title']) ?></h6>
                        <p class="text-muted small mb-2 text-truncate-2"><?= e(substr(strip_tags($mat['content_body']), 0, 100)) ?>...</p>
                        <a href="<?= url('student/secondary-materials.php?id=' . $mat['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                            <i class="bi bi-eye me-1"></i> Read Note
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-file-earmark-x fs-2 d-block mb-2"></i>
                    No specific revision sheets published for this subject yet.
                    <div class="mt-3">
                        <a href="<?= url('student/secondary-materials.php') ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                            Browse All Study Materials
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php endif; ?>

<!-- ALL SUBJECTS GRID -->
<div class="row g-4">
    <?php if (!empty($subjects)): ?>
        <?php foreach ($subjects as $s): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-4 d-flex flex-column justify-content-between" style="border-top: 4px solid <?= e($s['color'] ?? '#2563EB') ?> !important;">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="sec-icon-circle rounded-3 p-2" style="background: <?= e($s['color'] ?? '#2563EB') ?>15; color: <?= e($s['color'] ?? '#2563EB') ?>; width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                            <i class="bi <?= e($s['icon'] ?? 'bi-book-half') ?>"></i>
                        </div>
                        <span class="badge bg-light text-dark border rounded-pill px-3 py-1 font-monospace fw-bold">
                            <?= e($s['code']) ?>
                        </span>
                    </div>

                    <h5 class="fw-bold text-dark mb-2"><?= e($s['name']) ?></h5>
                    <p class="text-muted small mb-3" style="line-height: 1.5; min-height: 48px;">
                        <?= e($s['description']) ?>
                    </p>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge bg-light text-muted border rounded-pill small">
                            <i class="bi bi-list-ul me-1"></i> <?= (int)$s['topic_count'] ?> Topics
                        </span>
                        <span class="badge bg-light text-muted border rounded-pill small">
                            <i class="bi bi-file-text me-1"></i> <?= (int)$s['material_count'] ?> Notes
                        </span>
                        <span class="badge bg-light text-muted border rounded-pill small">
                            <i class="bi bi-patch-question me-1"></i> <?= (int)$s['question_count'] ?> Questions
                        </span>
                    </div>
                </div>

                <div class="pt-3 border-top d-flex gap-2">
                    <a href="<?= url('student/secondary-subjects.php?subject=' . urlencode($s['slug'])) ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 flex-grow-1">
                        View Topics
                    </a>
                    <a href="<?= url('student/secondary-practice.php?subject=' . urlencode($s['slug'])) ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold flex-grow-1">
                        Practice <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12 text-center py-5 text-muted">
            <i class="bi bi-search fs-1 d-block mb-2"></i>
            No subjects found matching "<?= e($search) ?>".
            <div class="mt-3">
                <a href="<?= url('student/secondary-subjects.php') ?>" class="btn btn-primary rounded-pill px-4">Clear Filter</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
