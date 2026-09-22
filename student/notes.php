<?php
/**
 * University Student Voice & Video Notes Hub
 * Shows recorded audio and video notes from university instructors for enrolled courses.
 */

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';
require_once BASE_PATH . '/includes/functions/activity.php';
require_once BASE_PATH . '/includes/functions/voice_video_notes.php';

secure_page(ROLE_STUDENT);

$user   = current_user();
$pdo    = getDBConnection();
$userId = (int)$user['id'];

if (is_secondary_student($userId)) {
    redirect('student/secondary-dashboard.php');
}

log_user_activity($userId, 'student_view_notes', 'Student opened Voice & Video Notes hub');

$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$studentRow = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId  = $studentRow ? (int)$studentRow['id'] : 0;

if (!$studentId) {
    set_flash('error', 'Student profile not found.');
    redirect('student/dashboard.php');
}

// Filters
$filterType   = $_GET['type'] ?? '';
$filterCourse = (int)($_GET['course_id'] ?? 0);
$filterSearch = trim($_GET['q'] ?? '');

$filters = [];
if (in_array($filterType, ['voice', 'video'])) $filters['media_type'] = $filterType;
if ($filterCourse > 0) $filters['course_id'] = $filterCourse;
if (!empty($filterSearch)) $filters['search'] = $filterSearch;

$notes = get_student_voice_video_notes($studentId, $filters);
$activeCourse = get_student_active_course($studentId);

// Get all enrolled university courses for the student
$stmtCourses = $pdo->prepare("
    SELECT c.id, c.title, c.code, u.full_name as teacher_name
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN teachers t ON t.id = e.teacher_id OR t.id = c.teacher_id
    LEFT JOIN users u ON u.id = t.user_id
    WHERE e.student_id = ? AND e.status = 'active'
    ORDER BY c.title ASC
");
$stmtCourses->execute([$studentId]);
$enrolledCourses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Instructor Voice & Video Notes';
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="container-fluid px-0 px-md-3 py-3">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= url('student/dashboard.php') ?>" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Voice &amp; Video Notes</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold text-main mb-1 d-flex align-items-center gap-2">
                <span class="d-inline-flex p-2 rounded-3 bg-danger bg-opacity-10 text-danger shadow-sm">
                    <lord-icon src="https://cdn.lordicon.com/bkhmabup.json" trigger="hover" colors="primary:#ef4444,secondary:#dc2626" style="width:32px;height:32px;"></lord-icon>
                </span>
                Instructor Voice &amp; Video Notes
            </h1>
            <p class="text-muted small mb-0">Listen to audio summaries and watch quick video explanations recorded directly by your university course lecturers.</p>
        </div>

        <?php if ($activeCourse): ?>
            <div class="d-flex align-items-center gap-2 bg-body p-2 px-3 rounded-pill shadow-sm border">
                <span class="badge bg-primary rounded-pill"><i class="bi bi-mortarboard-fill me-1"></i> Current Course</span>
                <span class="small fw-bold text-main text-truncate" style="max-width: 200px;"><?= e($activeCourse['title']) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Filters Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-body">
        <div class="card-body p-3">
            <form method="GET" action="<?= url('student/notes.php') ?>" class="row g-2 align-items-center">
                <!-- Search -->
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-pill"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" value="<?= e($filterSearch) ?>" class="form-control bg-light border-start-0 rounded-end-pill" placeholder="Search lecturer notes, topics, keywords...">
                    </div>
                </div>

                <!-- Media Type Dropdown -->
                <div class="col-6 col-md-3">
                    <select name="type" class="form-select rounded-pill" onchange="this.form.submit()">
                        <option value="">All Media (Voice &amp; Video)</option>
                        <option value="voice" <?= $filterType === 'voice' ? 'selected' : '' ?>>🎙️ Voice Notes Only</option>
                        <option value="video" <?= $filterType === 'video' ? 'selected' : '' ?>>🎥 Video Notes Only</option>
                    </select>
                </div>

                <!-- Enrolled Course Filter -->
                <div class="col-6 col-md-3">
                    <select name="course_id" class="form-select rounded-pill" onchange="this.form.submit()">
                        <option value="0">All My Enrolled Courses</option>
                        <?php foreach ($enrolledCourses as $ec): ?>
                            <option value="<?= (int)$ec['id'] ?>" <?= $filterCourse === (int)$ec['id'] ? 'selected' : '' ?>>
                                <?= e($ec['title']) ?> (<?= e($ec['code'] ?: 'Univ') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Submit / Clear -->
                <div class="col-12 col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-primary rounded-pill w-100" title="Apply Filter">
                        <i class="bi bi-funnel-fill"></i>
                    </button>
                    <?php if (!empty($filterType) || !empty($filterCourse) || !empty($filterSearch)): ?>
                        <a href="<?= url('student/notes.php') ?>" class="btn btn-outline-secondary rounded-pill" title="Clear Filters">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Notes Feed -->
    <?php if (empty($notes)): ?>
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-body">
            <div class="py-5">
                <div class="d-inline-flex p-4 rounded-circle bg-light text-muted mb-3">
                    <i class="bi bi-headphones fs-1 opacity-50"></i>
                </div>
                <h4 class="fw-bold text-main mb-2">No Voice or Video Notes Available</h4>
                <p class="text-muted small mb-0" style="max-width: 480px; margin: 0 auto;">
                    <?= (!empty($filterType) || !empty($filterCourse) || !empty($filterSearch)) ? 'No notes matched your search filters. Try clearing some filters.' : 'Your course instructor has not published any voice or video notes yet. Check back regularly for audio lecture tips and video walk-throughs!' ?>
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($notes as $n): 
                $isVoice = ($n['media_type'] === 'voice');
                $mediaUrl = url(ltrim($n['file_path'], '/'));
                $avatarUrl = function_exists('get_avatar_url') ? get_avatar_url($n['teacher_avatar'] ?? null, $n['teacher_name'] ?? 'Instructor') : ($n['teacher_avatar'] ?? '');
            ?>
                <div class="col-lg-6">
                    <div class="vvn-card h-100">
                        <!-- Card Header -->
                        <div class="vvn-card-header d-flex align-items-center justify-content-between <?= $isVoice ? 'bg-danger bg-opacity-10' : 'bg-primary bg-opacity-10' ?>">
                            <div class="d-flex align-items-center gap-2">
                                <div class="profile-avatar-box" style="width: 44px; height: 44px;">
                                    <img src="<?= e($avatarUrl) ?>" alt="Teacher" class="border border-2 border-white shadow-sm" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($n['teacher_name'] ?? 'Lecturer') ?>&background=4f46e5&color=ffffff&bold=true';">
                                    <span class="profile-avatar-badge <?= $isVoice ? 'bg-danger' : 'bg-primary' ?> text-white" style="width: 14px; height: 14px; font-size: 7px;">
                                        <i class="bi <?= $isVoice ? 'bi-mic-fill' : 'bi-camera-video-fill' ?>"></i>
                                    </span>
                                </div>
                                <div>
                                    <div class="fw-bold text-main small mb-0"><?= e($n['teacher_name'] ?? 'Instructor') ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        <i class="bi bi-mortarboard me-1"></i> <?= e($n['course_title']) ?>
                                    </div>
                                </div>
                            </div>

                            <span class="badge rounded-pill <?= $isVoice ? 'bg-danger' : 'bg-primary' ?> px-3 py-1 fw-bold d-flex align-items-center gap-1 shadow-sm">
                                <i class="bi <?= $isVoice ? 'bi-mic-fill' : 'bi-camera-video-fill' ?>"></i>
                                <?= $isVoice ? 'VOICE NOTE' : 'VIDEO NOTE' ?>
                            </span>
                        </div>

                        <!-- Card Body -->
                        <div class="vvn-card-body">
                            <div>
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                    <h5 class="fw-bold text-main mb-0"><?= e($n['title']) ?></h5>
                                    <span class="badge bg-light text-dark font-monospace border rounded-pill px-2 py-1 shadow-sm">
                                        <i class="bi bi-clock me-1"></i><?= format_note_duration($n['duration_seconds']) ?>
                                    </span>
                                </div>

                                <?php if (!empty($n['lesson_title'])): ?>
                                    <div class="mb-3">
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill small">
                                            <i class="bi bi-journal-bookmark me-1"></i> Topic: <?= e($n['lesson_title']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($n['description'])): ?>
                                    <p class="text-muted small mb-3" style="line-height: 1.6;"><?= nl2br(e($n['description'])) ?></p>
                                <?php endif; ?>

                                <!-- Custom HTML5 Media Player -->
                                <div class="vvn-player-box shadow-sm">
                                    <?php if ($isVoice): ?>
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="small fw-bold text-danger d-flex align-items-center gap-1">
                                                <i class="bi bi-soundwave fs-5"></i> Audio Playback
                                            </span>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-secondary vvn-speed-btn btn-speed-toggle active" data-target="audio_<?= (int)$n['id'] ?>" data-speed="1">1x</button>
                                                <button type="button" class="btn btn-outline-secondary vvn-speed-btn btn-speed-toggle" data-target="audio_<?= (int)$n['id'] ?>" data-speed="1.25">1.25x</button>
                                                <button type="button" class="btn btn-outline-secondary vvn-speed-btn btn-speed-toggle" data-target="audio_<?= (int)$n['id'] ?>" data-speed="1.5">1.5x</button>
                                            </div>
                                        </div>
                                        <audio id="audio_<?= (int)$n['id'] ?>" controls class="w-100 rounded-3 shadow-none" style="height: 44px;">
                                            <source src="<?= e($mediaUrl) ?>" type="<?= e($n['mime_type']) ?>">
                                            Your browser does not support audio playback.
                                        </audio>
                                    <?php else: ?>
                                        <div class="rounded-3 overflow-hidden bg-black position-relative shadow-sm" style="aspect-ratio: 16/9;">
                                            <video id="video_<?= (int)$n['id'] ?>" controls playsinline class="w-100 h-100 object-fit-contain">
                                                <source src="<?= e($mediaUrl) ?>" type="<?= e($n['mime_type']) ?>">
                                                Your browser does not support video playback.
                                            </video>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Footer Date / Action -->
                            <div class="d-flex align-items-center justify-content-between border-top pt-3 small text-muted">
                                <span><i class="bi bi-calendar-event me-1"></i> Posted <?= date('M d, Y', strtotime($n['created_at'])) ?></span>
                                <a href="<?= e($mediaUrl) ?>" download class="btn btn-sm btn-link text-decoration-none text-muted fw-semibold p-0">
                                    <i class="bi bi-download me-1"></i> Download File
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Playback speed toggles
    document.querySelectorAll('.btn-speed-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const speed = parseFloat(btn.getAttribute('data-speed'));
            const mediaEl = document.getElementById(targetId);
            if (mediaEl) {
                mediaEl.playbackRate = speed;
                
                // Active state
                btn.parentElement.querySelectorAll('.btn-speed-toggle').forEach(b => b.classList.remove('btn-danger', 'text-white', 'btn-primary'));
                btn.classList.add('btn-danger', 'text-white');
            }
        });
    });
});
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
