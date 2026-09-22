<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';
require_once BASE_PATH . '/includes/functions/activity.php';

secure_page(ROLE_STUDENT);

$user   = current_user();
$userId = (int)$user['id'];
$pdo    = getDBConnection();

$studentStmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$studentStmt->execute([$userId]);
$studentId = (int)$studentStmt->fetchColumn();

log_user_activity($userId, 'accessed_secondary_lessons', 'Student accessed Secondary School Lessons & Study Portal');

// Handle Lesson Completion Toggle (AJAX)
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'toggle_lesson_completion') {
    header('Content-Type: application/json; charset=utf-8');
    $topicId    = !empty($_POST['topic_id']) ? (int)$_POST['topic_id'] : null;
    $materialId = !empty($_POST['material_id']) ? (int)$_POST['material_id'] : null;

    if ($studentId && ($topicId || $materialId)) {
        try {
            $chk = $pdo->prepare("SELECT id FROM secondary_lesson_progress WHERE student_id = ? AND (material_id = ? OR (topic_id = ? AND material_id IS NULL)) LIMIT 1");
            $chk->execute([$studentId, $materialId ?: 0, $topicId ?: 0]);
            $progId = (int)$chk->fetchColumn();

            if ($progId > 0) {
                $pdo->prepare("DELETE FROM secondary_lesson_progress WHERE id = ?")->execute([$progId]);
                echo json_encode(['success' => true, 'completed' => false]);
                exit;
            } else {
                $pdo->prepare("INSERT INTO secondary_lesson_progress (student_id, topic_id, material_id, completed, completed_at) VALUES (?, ?, ?, 1, NOW())")
                    ->execute([$studentId, $topicId, $materialId]);
                log_user_activity($userId, 'completed_lesson_topic', "Marked secondary lesson topic as completed");
                echo json_encode(['success' => true, 'completed' => true]);
                exit;
            }
        } catch (Exception $e) {
            error_log("Error toggling lesson progress: " . $e->getMessage());
        }
    }
    echo json_encode(['success' => false]);
    exit;
}

// Filters
$selectedSubjectSlug = strtolower(trim($_GET['subject'] ?? 'mathematics'));
$selectedClassLevel  = trim($_GET['level'] ?? '');
$activeMaterialId    = !empty($_GET['material_id']) ? (int)$_GET['material_id'] : null;
$activeTopicId       = !empty($_GET['topic_id']) ? (int)$_GET['topic_id'] : null;

// Get all subjects
$allSubjects = get_secondary_subjects('active');
$currentSubject = get_secondary_subject_by_slug($selectedSubjectSlug) ?: ($allSubjects[0] ?? null);
$currentSubjectId = $currentSubject ? (int)$currentSubject['id'] : 0;

// Get topics for this subject
$topicsList = get_secondary_topics($currentSubjectId);

// Get materials/lessons for this subject
$materialsList = get_secondary_materials($currentSubjectId);

// Get student's completed lessons set
$completedMaterialIds = [];
$completedTopicIds = [];
if ($studentId) {
    $compStmt = $pdo->prepare("SELECT topic_id, material_id FROM secondary_lesson_progress WHERE student_id = ? AND completed = 1");
    $compStmt->execute([$studentId]);
    foreach ($compStmt->fetchAll(PDO::FETCH_ASSOC) as $cr) {
        if ($cr['material_id']) $completedMaterialIds[] = (int)$cr['material_id'];
        if ($cr['topic_id']) $completedTopicIds[] = (int)$cr['topic_id'];
    }
}

// Active Material / Lesson for reader
$activeMaterial = null;
if ($activeMaterialId) {
    foreach ($materialsList as $m) {
        if ((int)$m['id'] === $activeMaterialId) {
            $activeMaterial = $m;
            break;
        }
    }
} elseif (!empty($materialsList)) {
    $activeMaterial = $materialsList[0];
    $activeMaterialId = (int)$activeMaterial['id'];
}

// Overall progress stats
$progressStats = get_secondary_lesson_progress_stats($studentId);

$pageTitle = 'Secondary Lessons & Curriculum Notes | StudyMe';
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<!-- MathJax for rendering math/chem notations -->
<script>
window.MathJax = {
  tex: {
    inlineMath: [['$', '$'], ['\\(', '\\)']],
    displayMath: [['$$', '$$'], ['\\[', '\\]']]
  },
  svg: { fontCache: 'global' }
};
</script>
<script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= url('student/secondary-dashboard.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Lessons &amp; Syllabus</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-0 text-dark">
            <i class="bi bi-journal-bookmark-fill text-primary me-2"></i>Secondary School Lessons &amp; Study Notes
        </h2>
        <p class="text-muted mb-0 small">Read syllabus notes, master core concepts, and practice topic-specific CBT drills.</p>
    </div>

    <div class="d-flex align-items-center gap-2">
        <div class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-3 py-2">
            <i class="bi bi-check2-all me-1"></i> Completed: <?= (int)$progressStats['completed_count'] ?> / <?= (int)$progressStats['total_materials'] ?> Lessons (<?= (float)$progressStats['percentage'] ?>%)
        </div>
    </div>
</div>

<!-- SUBJECT PILLS SWITCHER -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
        <div class="d-flex gap-2 overflow-auto py-1">
            <?php foreach ($allSubjects as $subj): 
                $isActiveSubj = ($subj['slug'] === $selectedSubjectSlug);
            ?>
                <a href="<?= url('student/secondary-lessons.php?subject=' . urlencode($subj['slug'])) ?>" class="btn btn-sm rounded-pill px-3 py-2 text-nowrap d-flex align-items-center gap-2 <?= $isActiveSubj ? 'btn-primary fw-bold shadow-sm' : 'btn-light text-dark' ?>">
                    <i class="bi <?= e($subj['icon'] ?: 'bi-book-half') ?>"></i>
                    <span><?= e($subj['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- LEFT: SYLLABUS TOPICS & LESSON OUTLINE -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 sticky-top" style="top: 85px;">
            <div class="card-header bg-white p-3 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-list-nested text-primary me-2"></i>Curriculum Topics
                </h6>
                <span class="badge bg-light text-dark border"><?= count($topicsList) ?> Topics</span>
            </div>

            <div class="list-group list-group-flush" style="max-height: 550px; overflow-y: auto;">
                <?php if (!empty($topicsList)): ?>
                    <?php foreach ($topicsList as $idx => $top): 
                        // Find materials under this topic
                        $topMaterials = array_filter($materialsList, fn($m) => (int)$m['topic_id'] === (int)$top['id']);
                        $isTopCompleted = in_array((int)$top['id'], $completedTopicIds, true);
                    ?>
                    <div class="list-group-item p-3 border-bottom bg-light bg-opacity-25">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="badge bg-secondary bg-opacity-10 text-dark rounded-pill px-2 py-0 small font-monospace">
                                <?= e($top['class_level'] ?: 'SS1') ?> &bull; <?= e($top['term'] ?: 'First Term') ?>
                            </span>
                            
                            <!-- Practice Topic Button -->
                            <a href="<?= url('student/secondary-practice.php?exam=waec&subject=' . urlencode($selectedSubjectSlug) . '&topic=' . urlencode($top['title']) . '&count=10') ?>" class="badge bg-warning text-dark text-decoration-none rounded-pill px-2 py-1" title="Practice questions on this topic">
                                <i class="bi bi-play-fill"></i> Practice Topic
                            </a>
                        </div>
                        
                        <h6 class="fw-bold mb-2 text-dark small">
                            <?= $idx + 1 ?>. <?= e($top['title']) ?>
                        </h6>

                        <!-- Topic Lesson Materials Links -->
                        <?php if (!empty($topMaterials)): ?>
                            <div class="d-flex flex-column gap-1 ms-2 ps-2 border-start border-2 border-primary border-opacity-25">
                                <?php foreach ($topMaterials as $tm): 
                                    $isMatActive = ($activeMaterial && (int)$activeMaterial['id'] === (int)$tm['id']);
                                    $isMatDone   = in_array((int)$tm['id'], $completedMaterialIds, true);
                                ?>
                                    <a href="<?= url('student/secondary-lessons.php?subject=' . urlencode($selectedSubjectSlug) . '&material_id=' . (int)$tm['id']) ?>" class="d-flex align-items-center justify-content-between text-decoration-none small py-1 px-2 rounded <?= $isMatActive ? 'bg-primary text-white fw-bold' : 'text-dark hover-bg-light' ?>">
                                        <span class="text-truncate me-2">
                                            <i class="bi <?= $isMatDone ? 'bi-check-circle-fill text-success' : 'bi-file-earmark-text' ?> me-1"></i>
                                            <?= e($tm['title']) ?>
                                        </span>
                                        <span class="badge <?= $isMatActive ? 'bg-white text-primary' : 'bg-light text-muted' ?> rounded-pill px-2 py-0">
                                            <?= (int)$tm['duration_minutes'] ?>m
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="small text-muted ms-2 fst-italic">Standard syllabus module</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-4 text-center text-muted small">No topics registered for this subject yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: LESSON CONTENT READER & PRACTICE CTA -->
    <div class="col-lg-8">
        <?php if ($activeMaterial): 
            $isMaterialCompleted = in_array((int)$activeMaterial['id'], $completedMaterialIds, true);
        ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4 bg-white position-relative" id="lessonReaderCard">
            <!-- Top Controls -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 pb-3 border-bottom">
                <div>
                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 fw-bold text-uppercase mb-1">
                        <?= e($currentSubject['name'] ?? 'Subject') ?> &bull; <?= strtoupper(e($activeMaterial['content_type'])) ?>
                    </span>
                    <h3 class="fw-bold text-dark mb-0"><?= e($activeMaterial['title']) ?></h3>
                </div>

                <div class="d-flex gap-2">
                    <!-- Complete Button -->
                    <button type="button" class="btn btn-sm rounded-pill px-3 fw-bold <?= $isMaterialCompleted ? 'btn-success' : 'btn-outline-success' ?>" id="toggleCompleteBtn" onclick="toggleCompletion(<?= (int)($activeMaterial['topic_id'] ?? 0) ?>, <?= (int)$activeMaterial['id'] ?>)">
                        <i class="bi <?= $isMaterialCompleted ? 'bi-check2-circle' : 'bi-circle' ?> me-1"></i>
                        <span id="completeBtnText"><?= $isMaterialCompleted ? 'Completed' : 'Mark as Completed' ?></span>
                    </button>

                    <!-- Print / PDF -->
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                </div>
            </div>

            <!-- "LEARN -> PRACTICE" BANNER -->
            <div class="card border-0 bg-primary bg-opacity-10 rounded-4 p-3 mb-4 d-flex flex-row align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                        <i class="bi bi-lightning-charge-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">Ready to test your knowledge on this topic?</h6>
                        <small class="text-muted">Launch an instant 10-question practice test directly based on this lesson.</small>
                    </div>
                </div>

                <a href="<?= url('student/secondary-practice.php?exam=waec&subject=' . urlencode($selectedSubjectSlug) . '&topic=' . urlencode($activeMaterial['topic_title'] ?? $activeMaterial['title']) . '&count=10') ?>" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                    <i class="bi bi-play-fill me-1"></i> Practice This Topic
                </a>
            </div>

            <!-- LESSON CONTENT BODY -->
            <div class="lesson-content-body fs-6 lh-lg text-dark">
                <?php 
                // Render markdown or HTML content
                $body = $activeMaterial['content_body'];
                echo nl2br($body);
                ?>
            </div>

            <!-- BOTTOM COMPLETION FOOTER -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-4 mt-5 border-top">
                <button type="button" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" onclick="toggleCompletion(<?= (int)($activeMaterial['topic_id'] ?? 0) ?>, <?= (int)$activeMaterial['id'] ?>)">
                    <i class="bi bi-check-lg me-1"></i> Mark Lesson as Finished
                </button>

                <a href="<?= url('student/secondary-practice.php?exam=waec&subject=' . urlencode($selectedSubjectSlug) . '&count=20') ?>" class="btn btn-warning text-dark fw-bold rounded-pill px-4 shadow-sm">
                    <i class="bi bi-play-circle-fill me-1"></i> Start Full Subject CBT Mock
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
            <i class="bi bi-journal-text fs-1 text-muted d-block mb-3"></i>
            <h4 class="fw-bold text-dark">Select a topic from the curriculum syllabus</h4>
            <p class="text-muted mb-4">Choose any topic on the left to read structured revision notes and formula sheets.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleCompletion(topicId, materialId) {
    const formData = new FormData();
    formData.append('action', 'toggle_lesson_completion');
    formData.append('topic_id', topicId);
    formData.append('material_id', materialId);

    fetch('secondary-lessons.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const btn = document.getElementById('toggleCompleteBtn');
            const txt = document.getElementById('completeBtnText');
            if (data.completed) {
                btn.className = 'btn btn-sm rounded-pill px-3 fw-bold btn-success';
                txt.innerText = 'Completed';
                btn.querySelector('i').className = 'bi bi-check2-circle me-1';
            } else {
                btn.className = 'btn btn-sm rounded-pill px-3 fw-bold btn-outline-success';
                txt.innerText = 'Mark as Completed';
                btn.querySelector('i').className = 'bi bi-circle me-1';
            }
        }
    })
    .catch(err => console.error('Error toggling completion:', err));
}
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
