<?php
/**
 * University Teacher Voice & Video Notes Management Hub
 */

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/activity.php';
require_once BASE_PATH . '/includes/functions/voice_video_notes.php';

secure_page(ROLE_TEACHER);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$user = current_user();
$pdo  = getDBConnection();
$uid  = (int)$user['id'];

$stmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;

if (!$tid) {
    set_flash('error', 'Teacher profile not found.');
    redirect('teacher/dashboard.php');
}

// Filter parameters
$filterType    = $_GET['type'] ?? '';
$filterCourse  = (int)($_GET['course_id'] ?? 0);
$filterStatus  = $_GET['status'] ?? '';
$filterSearch  = trim($_GET['q'] ?? '');

$filters = [];
if (in_array($filterType, ['voice', 'video'])) $filters['media_type'] = $filterType;
if ($filterCourse > 0) $filters['course_id'] = $filterCourse;
if (in_array($filterStatus, ['published', 'draft', 'archived'])) $filters['status'] = $filterStatus;
if (!empty($filterSearch)) $filters['search'] = $filterSearch;

$stats = get_teacher_notes_stats($tid);
$notes = get_teacher_voice_video_notes($tid, $filters);
$assignedCourses = get_teacher_assigned_courses($tid);

$pageTitle = 'Voice & Video Notes — Teacher Hub';
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="container-fluid px-0 px-md-3 py-3">
    <!-- Breadcrumb & Top Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= url('teacher/dashboard.php') ?>" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Voice &amp; Video Notes</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold text-main mb-1 d-flex align-items-center gap-2">
                <span class="d-inline-flex p-2 rounded-3 bg-primary bg-opacity-10 text-primary shadow-sm">
                    <lord-icon src="https://cdn.lordicon.com/bkhmabup.json" trigger="hover" colors="primary:#2563eb,secondary:#3b82f6" style="width:32px;height:32px;"></lord-icon>
                </span>
                Voice &amp; Video Notes
            </h1>
            <p class="text-muted small mb-0">Record, organize, and manage multimedia explanations for your enrolled university students.</p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="<?= url('teacher/record-voice-note.php') ?>" class="btn btn-danger rounded-pill px-4 py-2 fw-bold d-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-mic-fill"></i> Record Voice Note
            </a>
            <a href="<?= url('teacher/record-video-note.php') ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-bold d-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-camera-video-fill"></i> Record Video Note
            </a>
        </div>
    </div>

    <!-- Stats Overview Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="vvn-stat-box">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem;">Total Notes</span>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill p-1 px-2"><i class="bi bi-collection-play"></i></span>
                </div>
                <div class="h3 fw-bold text-main mb-0"><?= (int)($stats['total_notes'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="vvn-stat-box border-danger border-opacity-25">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem;">Voice Notes</span>
                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill p-1 px-2"><i class="bi bi-mic-fill"></i></span>
                </div>
                <div class="h3 fw-bold text-danger mb-0 d-flex align-items-center gap-1">
                    <?= (int)($stats['total_voice'] ?? 0) ?>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="vvn-stat-box border-primary border-opacity-25">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem;">Video Notes</span>
                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill p-1 px-2"><i class="bi bi-camera-video-fill"></i></span>
                </div>
                <div class="h3 fw-bold text-primary mb-0 d-flex align-items-center gap-1">
                    <?= (int)($stats['total_video'] ?? 0) ?>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="vvn-stat-box border-success border-opacity-25">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem;">Published</span>
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill p-1 px-2"><i class="bi bi-check-circle-fill"></i></span>
                </div>
                <div class="h3 fw-bold text-success mb-0"><?= (int)($stats['total_published'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="vvn-stat-box border-warning border-opacity-25">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem;">Drafts</span>
                    <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill p-1 px-2"><i class="bi bi-journal-bookmark"></i></span>
                </div>
                <div class="h3 fw-bold text-warning mb-0"><?= (int)($stats['total_drafts'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="vvn-stat-box border-info border-opacity-25">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem;">Total Time</span>
                    <span class="badge bg-info bg-opacity-10 text-info rounded-pill p-1 px-2"><i class="bi bi-clock-history"></i></span>
                </div>
                <div class="h4 fw-bold text-info mb-0 font-monospace"><?= format_note_duration($stats['total_duration_seconds'] ?? 0) ?></div>
            </div>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-body">
        <div class="card-body p-3">
            <form method="GET" action="<?= url('teacher/notes.php') ?>" class="row g-2 align-items-center">
                <!-- Search Input -->
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-pill"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" value="<?= e($filterSearch) ?>" class="form-control bg-light border-start-0 rounded-end-pill" placeholder="Search by title or keyword...">
                    </div>
                </div>

                <!-- Media Type Dropdown -->
                <div class="col-6 col-md-2">
                    <select name="type" class="form-select rounded-pill" onchange="this.form.submit()">
                        <option value="">All Media Types</option>
                        <option value="voice" <?= $filterType === 'voice' ? 'selected' : '' ?>>🎙️ Voice Notes</option>
                        <option value="video" <?= $filterType === 'video' ? 'selected' : '' ?>>🎥 Video Notes</option>
                    </select>
                </div>

                <!-- Course Dropdown -->
                <div class="col-6 col-md-3">
                    <select name="course_id" class="form-select rounded-pill" onchange="this.form.submit()">
                        <option value="0">All My Courses</option>
                        <?php foreach ($assignedCourses as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $filterCourse === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= e($c['title']) ?> (<?= e($c['code'] ?: 'Univ') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status Dropdown -->
                <div class="col-6 col-md-2">
                    <select name="status" class="form-select rounded-pill" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="published" <?= $filterStatus === 'published' ? 'selected' : '' ?>>Published</option>
                        <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : '' ?>>Draft</option>
                    </select>
                </div>

                <!-- Action / Reset -->
                <div class="col-6 col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-dark rounded-pill w-100" title="Apply Filter">
                        <i class="bi bi-funnel-fill"></i>
                    </button>
                    <?php if (!empty($filterType) || !empty($filterCourse) || !empty($filterStatus) || !empty($filterSearch)): ?>
                        <a href="<?= url('teacher/notes.php') ?>" class="btn btn-outline-secondary rounded-pill" title="Reset Filters">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Notes List / Grid -->
    <?php if (empty($notes)): ?>
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-body">
            <div class="py-5">
                <div class="d-inline-flex p-4 rounded-circle bg-light text-muted mb-3">
                    <i class="bi bi-mic-mute fs-1 opacity-50"></i>
                </div>
                <h4 class="fw-bold text-main mb-2">No Voice or Video Notes Found</h4>
                <p class="text-muted small mb-4" style="max-width: 480px; margin: 0 auto;">
                    <?= (!empty($filterType) || !empty($filterCourse) || !empty($filterSearch)) ? 'No notes match your current filter criteria. Try resetting your search filters.' : 'You have not recorded any voice or video notes yet. Start recording audio explanations or video lectures for your students.' ?>
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="<?= url('teacher/record-voice-note.php') ?>" class="btn btn-danger rounded-pill px-4 py-2 fw-semibold">
                        <i class="bi bi-mic-fill me-1"></i> Record First Voice Note
                    </a>
                    <a href="<?= url('teacher/record-video-note.php') ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold">
                        <i class="bi bi-camera-video-fill me-1"></i> Record First Video Note
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4" id="notesListContainer">
            <?php foreach ($notes as $n): 
                $isVoice = ($n['media_type'] === 'voice');
                $mediaUrl = url(ltrim($n['file_path'], '/'));
            ?>
                <div class="col-md-6 col-xl-4 note-card-item" id="noteCard_<?= (int)$n['id'] ?>">
                    <div class="vvn-card h-100">
                        <!-- Card Header / Media Banner -->
                        <div class="vvn-card-header <?= $isVoice ? 'bg-danger bg-opacity-10' : 'bg-primary bg-opacity-10' ?>">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge rounded-pill <?= $isVoice ? 'bg-danger' : 'bg-primary' ?> px-3 py-1 fw-bold d-flex align-items-center gap-1 shadow-sm">
                                    <i class="bi <?= $isVoice ? 'bi-mic-fill' : 'bi-camera-video-fill' ?>"></i>
                                    <?= $isVoice ? 'VOICE NOTE' : 'VIDEO NOTE' ?>
                                </span>
                                <div class="d-flex align-items-center gap-1">
                                    <?php if ($n['status'] === 'published'): ?>
                                        <span class="badge bg-success bg-opacity-25 text-success rounded-pill px-2 py-1 small fw-semibold">Published</span>
                                    <?php elseif ($n['status'] === 'draft'): ?>
                                        <span class="badge bg-warning bg-opacity-25 text-warning rounded-pill px-2 py-1 small fw-semibold">Draft</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-25 text-muted rounded-pill px-2 py-1 small fw-semibold">Archived</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Course and Lesson Tags -->
                            <div class="text-truncate small fw-bold text-main" title="<?= e($n['course_title']) ?>">
                                <i class="bi bi-mortarboard-fill me-1 text-muted"></i> <?= e($n['course_title']) ?>
                            </div>
                            <?php if (!empty($n['lesson_title'])): ?>
                                <div class="text-truncate small text-muted" title="<?= e($n['lesson_title']) ?>">
                                    <i class="bi bi-journal-text me-1"></i> <?= e($n['lesson_title']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Card Body -->
                        <div class="vvn-card-body">
                            <div>
                                <h5 class="fw-bold text-main mb-2 text-truncate-2" style="min-height: 2.8rem;"><?= e($n['title']) ?></h5>
                                <?php if (!empty($n['description'])): ?>
                                    <p class="text-muted small mb-3 text-truncate-2" style="font-size: 0.85rem;"><?= e($n['description']) ?></p>
                                <?php else: ?>
                                    <p class="text-muted small mb-3 fst-italic" style="font-size: 0.85rem;">No additional description provided.</p>
                                <?php endif; ?>

                                <!-- Quick Player Component in Card -->
                                <div class="vvn-player-box">
                                    <?php if ($isVoice): ?>
                                        <audio controls preload="none" class="w-100" style="height: 38px;">
                                            <source src="<?= e($mediaUrl) ?>" type="<?= e($n['mime_type']) ?>">
                                            Your browser does not support audio playback.
                                        </audio>
                                    <?php else: ?>
                                        <div class="position-relative rounded-2 overflow-hidden bg-black d-flex align-items-center justify-content-center shadow-sm" style="height: 120px;">
                                            <video preload="metadata" class="w-100 h-100 object-fit-cover opacity-75">
                                                <source src="<?= e($mediaUrl) ?>" type="<?= e($n['mime_type']) ?>">
                                            </video>
                                            <button type="button" class="btn btn-primary rounded-circle position-absolute shadow btn-preview-note" 
                                                    data-id="<?= (int)$n['id'] ?>"
                                                    data-title="<?= e($n['title']) ?>"
                                                    data-url="<?= e($mediaUrl) ?>"
                                                    data-type="<?= e($n['media_type']) ?>"
                                                    data-mime="<?= e($n['mime_type']) ?>"
                                                    style="width: 44px; height: 44px;">
                                                <i class="bi bi-play-fill fs-5"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Card Meta & Footer Actions -->
                            <div>
                                <div class="d-flex align-items-center justify-content-between small text-muted mb-3 border-top pt-2">
                                    <span class="font-monospace fw-bold">
                                        <i class="bi bi-clock me-1"></i><?= format_note_duration($n['duration_seconds']) ?>
                                    </span>
                                    <span><?= date('M d, Y', strtotime($n['created_at'])) ?></span>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill flex-grow-1 fw-semibold btn-preview-note"
                                            data-id="<?= (int)$n['id'] ?>"
                                            data-title="<?= e($n['title']) ?>"
                                            data-url="<?= e($mediaUrl) ?>"
                                            data-type="<?= e($n['media_type']) ?>"
                                            data-mime="<?= e($n['mime_type']) ?>">
                                        <i class="bi bi-eye-fill me-1"></i> Preview
                                    </button>

                                    <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 btn-edit-note"
                                            data-id="<?= (int)$n['id'] ?>"
                                            data-title="<?= e($n['title']) ?>"
                                            data-description="<?= e($n['description']) ?>"
                                            data-course="<?= (int)$n['course_id'] ?>"
                                            data-lesson="<?= (int)($n['lesson_id'] ?? 0) ?>"
                                            data-status="<?= e($n['status']) ?>"
                                            title="Edit Metadata">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>

                                    <button type="button" class="btn btn-sm btn-light border text-danger rounded-pill px-3 btn-delete-note"
                                            data-id="<?= (int)$n['id'] ?>"
                                            data-title="<?= e($n['title']) ?>"
                                            title="Delete Note">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Interactive Full Media Preview -->
<div class="modal fade" id="mediaPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-main text-truncate" id="modalPreviewTitle">Media Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div id="modalPlayerHost" class="rounded-4 overflow-hidden bg-black d-flex align-items-center justify-content-center mb-3" style="min-height: 240px;">
                    <!-- Injected dynamically -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Edit Note Metadata -->
<div class="modal fade" id="editNoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom p-4">
                <h5 class="modal-title fw-bold text-main d-flex align-items-center gap-2">
                    <i class="bi bi-pencil-square text-primary"></i> Edit Note Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="editNoteForm" onsubmit="return false;">
                    <input type="hidden" id="editNoteId" name="note_id">

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" id="editNoteTitle" name="title" required maxlength="255">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Course</label>
                        <select class="form-select rounded-3" id="editCourseSelect" name="course_id">
                            <?php foreach ($assignedCourses as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= e($c['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Topic / Lesson</label>
                        <select class="form-select rounded-3" id="editLessonSelect" name="lesson_id">
                            <option value="">-- General Course Note --</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Status</label>
                        <select class="form-select rounded-3" id="editNoteStatus" name="status">
                            <option value="published">Published (Visible to Students)</option>
                            <option value="draft">Draft (Hidden from Students)</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Description</label>
                        <textarea class="form-control rounded-3" id="editNoteDescription" name="description" rows="3"></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-2">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" id="btnSaveEdit">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Delete Confirmation -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content rounded-4 border-0 shadow text-center p-4">
            <div class="d-inline-flex p-3 rounded-circle bg-danger bg-opacity-10 text-danger mb-3 mx-auto">
                <i class="bi bi-trash3-fill fs-3"></i>
            </div>
            <h5 class="fw-bold text-main mb-1">Delete Note?</h5>
            <p class="text-muted small mb-4">Are you sure you want to permanently delete <strong id="deleteNoteTitleLabel"></strong>? This will remove the audio/video file from the server.</p>
            <input type="hidden" id="deleteTargetId">
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-danger rounded-pill fw-bold py-2" id="btnConfirmDelete">Yes, Delete Note</button>
                <button type="button" class="btn btn-light rounded-pill py-2" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const previewModalEl = document.getElementById('mediaPreviewModal');
    const previewModal = new bootstrap.Modal(previewModalEl);
    const modalPlayerHost = document.getElementById('modalPlayerHost');
    const modalPreviewTitle = document.getElementById('modalPreviewTitle');

    const editModalEl = document.getElementById('editNoteModal');
    const editModal = new bootstrap.Modal(editModalEl);
    const editCourseSelect = document.getElementById('editCourseSelect');
    const editLessonSelect = document.getElementById('editLessonSelect');

    const deleteModalEl = document.getElementById('deleteConfirmModal');
    const deleteModal = new bootstrap.Modal(deleteModalEl);

    // Stop audio/video when preview modal closes
    previewModalEl.addEventListener('hidden.bs.modal', () => {
        modalPlayerHost.innerHTML = '';
    });

    // Preview Button Click
    document.querySelectorAll('.btn-preview-note').forEach(btn => {
        btn.addEventListener('click', () => {
            const title = btn.getAttribute('data-title');
            const url = btn.getAttribute('data-url');
            const type = btn.getAttribute('data-type');
            const mime = btn.getAttribute('data-mime');

            modalPreviewTitle.innerText = title;

            if (type === 'voice') {
                modalPlayerHost.innerHTML = `
                    <div class="p-4 w-100 bg-dark rounded-4 text-center">
                        <i class="bi bi-soundwave fs-1 text-danger mb-3 d-block"></i>
                        <audio controls autoplay class="w-100 shadow-none">
                            <source src="${url}" type="${mime}">
                        </audio>
                    </div>
                `;
            } else {
                modalPlayerHost.innerHTML = `
                    <video controls autoplay playsinline class="w-100 h-100 rounded-4" style="max-height: 480px;">
                        <source src="${url}" type="${mime}">
                    </video>
                `;
            }

            previewModal.show();
        });
    });

    // Edit Lesson loader helper
    async function loadEditLessons(courseId, selectedLessonId = 0) {
        editLessonSelect.innerHTML = '<option value="">Loading lessons...</option>';
        try {
            const res = await fetch(`<?= url('api/voice-video-notes.php') ?>?action=get_lessons&course_id=${encodeURIComponent(courseId)}`);
            const data = await res.json();
            let html = '<option value="">-- General Course Note --</option>';
            if (data.success && Array.isArray(data.lessons)) {
                data.lessons.forEach(l => {
                    const sec = l.section_title ? `${l.section_title} &bull; ` : '';
                    const isSel = (parseInt(l.id) === parseInt(selectedLessonId)) ? 'selected' : '';
                    html += `<option value="${l.id}" ${isSel}>${sec}${l.title}</option>`;
                });
            }
            editLessonSelect.innerHTML = html;
        } catch (e) {
            editLessonSelect.innerHTML = '<option value="">-- General Course Note --</option>';
        }
    }

    if (editCourseSelect) {
        editCourseSelect.addEventListener('change', () => {
            loadEditLessons(editCourseSelect.value, 0);
        });
    }

    // Edit Button Click
    document.querySelectorAll('.btn-edit-note').forEach(btn => {
        btn.addEventListener('click', () => {
            const noteId = btn.getAttribute('data-id');
            const title = btn.getAttribute('data-title');
            const desc = btn.getAttribute('data-description');
            const courseId = btn.getAttribute('data-course');
            const lessonId = btn.getAttribute('data-lesson');
            const status = btn.getAttribute('data-status');

            document.getElementById('editNoteId').value = noteId;
            document.getElementById('editNoteTitle').value = title;
            document.getElementById('editNoteDescription').value = desc;
            document.getElementById('editNoteStatus').value = status;
            editCourseSelect.value = courseId;

            loadEditLessons(courseId, lessonId);
            editModal.show();
        });
    });

    // Save Edit Form
    document.getElementById('btnSaveEdit').addEventListener('click', async () => {
        const btn = document.getElementById('btnSaveEdit');
        const form = document.getElementById('editNoteForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        btn.disabled = true;
        btn.innerText = 'Saving...';

        const formData = new FormData(form);
        formData.append('action', 'update_note');

        try {
            const res = await fetch('<?= url('api/voice-video-notes.php') ?>', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                editModal.hide();
                window.location.reload();
            } else {
                alert(data.message || 'Error updating note.');
                btn.disabled = false;
                btn.innerText = 'Save Changes';
            }
        } catch (e) {
            alert('Request failed: ' + e.message);
            btn.disabled = false;
            btn.innerText = 'Save Changes';
        }
    });

    // Delete Button Click
    document.querySelectorAll('.btn-delete-note').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            const title = btn.getAttribute('data-title');
            document.getElementById('deleteTargetId').value = id;
            document.getElementById('deleteNoteTitleLabel').innerText = `"${title}"`;
            deleteModal.show();
        });
    });

    // Confirm Delete
    document.getElementById('btnConfirmDelete').addEventListener('click', async () => {
        const btn = document.getElementById('btnConfirmDelete');
        const noteId = document.getElementById('deleteTargetId').value;
        if (!noteId) return;

        btn.disabled = true;
        btn.innerText = 'Deleting...';

        const formData = new FormData();
        formData.append('action', 'delete_note');
        formData.append('note_id', noteId);

        try {
            const res = await fetch('<?= url('api/voice-video-notes.php') ?>', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                deleteModal.hide();
                const card = document.getElementById('noteCard_' + noteId);
                if (card) {
                    card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.9)';
                    setTimeout(() => card.remove(), 300);
                } else {
                    window.location.reload();
                }
            } else {
                alert(data.message || 'Error deleting note.');
                btn.disabled = false;
                btn.innerText = 'Yes, Delete Note';
            }
        } catch (e) {
            alert('Request failed: ' + e.message);
            btn.disabled = false;
            btn.innerText = 'Yes, Delete Note';
        }
    });
});
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
