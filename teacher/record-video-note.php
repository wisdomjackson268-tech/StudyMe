<?php
/**
 * University Teacher Video Note Recording Studio
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

$assignedCourses = get_teacher_assigned_courses($tid);
$preselectedCourseId = (int)($_GET['course_id'] ?? ($teacher['assigned_course_id'] ?? ($assignedCourses[0]['id'] ?? 0)));
$initialLessons = $preselectedCourseId ? get_course_lessons_for_notes($preselectedCourseId, $tid) : [];

$pageTitle = 'Record Video Note — Studio';
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="container-fluid px-0 px-md-3 py-3">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= url('teacher/dashboard.php') ?>" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('teacher/notes.php') ?>" class="text-decoration-none">Voice &amp; Video Notes</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Record Video Note</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold text-main mb-1 d-flex align-items-center gap-2">
                <span class="d-inline-flex p-2 rounded-3 bg-primary bg-opacity-10 text-primary shadow-sm">
                    <lord-icon src="https://cdn.lordicon.com/akqsdstj.json" trigger="hover" colors="primary:#2563eb,secondary:#3b82f6" style="width:32px;height:32px;"></lord-icon>
                </span>
                Video Note Studio
            </h1>
            <p class="text-muted small mb-0">Record webcam explanations, lecture walk-throughs, or video announcements directly from your browser.</p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="<?= url('teacher/record-voice-note.php') ?>" class="btn btn-outline-danger rounded-pill px-3 py-2 fw-semibold d-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-mic-fill"></i> Switch to Voice Studio
            </a>
            <a href="<?= url('teacher/notes.php') ?>" class="btn btn-light rounded-pill px-3 py-2 fw-semibold border shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> View All Notes
            </a>
        </div>
    </div>

    <?php if (empty($assignedCourses)): ?>
        <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 d-flex align-items-start gap-3">
            <i class="bi bi-exclamation-triangle-fill fs-2 text-warning"></i>
            <div>
                <h5 class="fw-bold mb-1">No University Course Assigned</h5>
                <p class="mb-2 text-muted">You need an active university course assigned to your instructor profile before you can publish video notes.</p>
                <a href="<?= url('teacher/select-course.php') ?>" class="btn btn-warning btn-sm fw-bold rounded-pill px-3">
                    <i class="bi bi-collection-play me-1"></i> Select or Claim Course
                </a>
            </div>
        </div>
    <?php else: ?>

    <div class="row g-4">
        <!-- Video Recording Studio Viewport Column -->
        <div class="col-lg-7">
            <div class="vvn-studio-card h-100 bg-body">
                <div class="vvn-studio-header">
                    <div class="d-flex align-items-center justify-content-between position-relative" style="z-index: 2;">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-pill bg-danger d-flex align-items-center gap-1 px-3 py-2 vvn-pulse" id="recordingBadge" style="display: none !important;">
                                <span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true" style="width: 8px; height: 8px;"></span>
                                <span class="fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">REC LIVE</span>
                            </span>
                            <span class="badge rounded-pill bg-secondary d-flex align-items-center gap-1 px-3 py-2" id="idleBadge">
                                <i class="bi bi-camera-video text-muted"></i>
                                <span class="fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">CAMERA STANDBY</span>
                            </span>
                            <span class="badge rounded-pill bg-warning text-dark d-flex align-items-center gap-1 px-3 py-2" id="pausedBadge" style="display: none !important;">
                                <i class="bi bi-pause-fill"></i>
                                <span class="fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">PAUSED</span>
                            </span>
                        </div>
                        <div class="text-white-50 font-monospace fs-5 fw-bold bg-black bg-opacity-40 px-3 py-1 rounded-pill border border-white border-opacity-10" id="timerDisplay">00:00</div>
                    </div>
                </div>

                <div class="card-body p-3 p-md-4 text-center d-flex flex-column justify-content-between">
                    <!-- Live Camera Viewport & Review Container -->
                    <div class="vvn-video-viewport my-2">
                        <!-- Live Video Feed -->
                        <video id="liveVideoPreview" autoplay playsinline muted class="w-100 h-100 object-fit-cover position-absolute top-0 start-0" style="transform: scaleX(-1); display: none;"></video>
                        
                        <!-- Recorded Video Playback (Shown after recording) -->
                        <video id="recordedVideoPreview" controls playsinline class="w-100 h-100 object-fit-contain position-absolute top-0 start-0" style="display: none; background: #000;"></video>

                        <!-- Standby Camera Overlay -->
                        <div id="cameraStandbyOverlay" class="p-4 text-center text-white-50 position-relative" style="z-index: 5;">
                            <div class="d-inline-flex p-3 rounded-circle bg-primary bg-opacity-20 text-primary mb-3">
                                <i class="bi bi-camera-video fs-2"></i>
                            </div>
                            <h6 class="text-white fw-bold mb-1">Webcam Preview Ready</h6>
                            <p class="small mb-3 text-white-50" style="max-width: 320px;">Click below to enable your camera and microphone for live video recording.</p>
                            <button type="button" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold shadow-sm" id="btnInitCamera">
                                <i class="bi bi-camera-fill me-1"></i> Turn On Camera
                            </button>
                        </div>

                        <!-- Top overlay controls (Flip camera / mirror toggle) -->
                        <div id="cameraControlsOverlay" class="position-absolute top-0 end-0 p-3 d-flex gap-2" style="z-index: 10; display: none;">
                            <button type="button" class="btn btn-dark btn-sm bg-opacity-75 text-white rounded-circle shadow" id="btnToggleMirror" title="Toggle Mirror View" style="width: 38px; height: 38px;">
                                <i class="bi bi-symmetry-vertical"></i>
                            </button>
                            <button type="button" class="btn btn-dark btn-sm bg-opacity-75 text-white rounded-circle shadow" id="btnFlipCamera" title="Switch Camera" style="width: 38px; height: 38px;">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Recording Controls -->
                    <div class="vvn-controls-bar">
                        <!-- Record Button -->
                        <button type="button" class="vvn-btn-record" id="btnStartRecord" style="display: none;">
                            <i class="bi bi-record-circle-fill fs-5"></i>
                            <span id="startBtnText">Start Recording</span>
                        </button>

                        <!-- Pause/Resume Button -->
                        <button type="button" class="btn btn-warning btn-lg rounded-pill px-4 py-3 fw-bold align-items-center gap-2 shadow-sm" id="btnPauseRecord" style="display: none;">
                            <i class="bi bi-pause-fill fs-5"></i>
                            <span id="pauseBtnText">Pause</span>
                        </button>

                        <!-- Stop Button -->
                        <button type="button" class="btn btn-dark btn-lg rounded-pill px-4 py-3 fw-bold align-items-center gap-2 shadow-sm" id="btnStopRecord" style="display: none;">
                            <i class="bi bi-stop-fill fs-5"></i>
                            <span>Stop Recording</span>
                        </button>

                        <!-- Discard / Re-record Button -->
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-medium align-items-center gap-1 shadow-sm" id="btnDiscardRecord" style="display: none;">
                            <i class="bi bi-trash3"></i>
                            <span>Discard &amp; Re-record</span>
                        </button>
                    </div>

                    <!-- Video File Fallback Upload -->
                    <div class="mt-4 pt-3 border-top text-center">
                        <span class="small text-muted d-block mb-2">Or upload a pre-recorded video file (MP4, WebM, MOV):</span>
                        <input type="file" id="fallbackVideoInput" accept="video/mp4,video/webm,video/quicktime,video/ogg" class="d-none">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 shadow-sm" onclick="document.getElementById('fallbackVideoInput').click();">
                            <i class="bi bi-cloud-arrow-up me-1"></i> Upload Video File Instead
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Note Details & Publishing Form Column -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-body">
                <div class="card-header bg-transparent p-4 border-bottom">
                    <!-- Instructor Snapshot Pill -->
                    <div class="d-flex align-items-center gap-3 mb-3 p-2 bg-light rounded-4 border">
                        <?php 
                        $tchAvatar = function_exists('get_avatar_url') ? get_avatar_url($user['avatar'] ?? null, $user['first_name'] ?? 'Instructor') : ($user['avatar'] ?? '');
                        ?>
                        <div class="profile-avatar-box" style="width: 46px; height: 46px;">
                            <img src="<?= e($tchAvatar) ?>" alt="Avatar" class="border border-2 border-white shadow-sm" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['first_name'] ?? 'Instructor') ?>&background=4f46e5&color=ffffff&bold=true';">
                            <span class="profile-avatar-badge bg-primary text-white" style="width: 16px; height: 16px; font-size: 8px;">
                                <i class="bi bi-camera-video-fill"></i>
                            </span>
                        </div>
                        <div>
                            <div class="fw-bold text-main small mb-0"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></div>
                            <small class="text-muted" style="font-size: 0.75rem;">University Instructor &bull; <?= e($teacher['specialization'] ?? 'Faculty') ?></small>
                        </div>
                    </div>

                    <h5 class="fw-bold mb-1 text-main d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-play text-primary"></i>
                        Video Details &amp; Publishing
                    </h5>
                    <p class="text-muted small mb-0">Set metadata and designate the university course for this video note.</p>
                </div>
                        <i class="bi bi-file-earmark-play text-primary"></i>
                        Video Details &amp; Publishing
                    </h5>
                    <p class="text-muted small mb-0">Set metadata and designate the university course for this video note.</p>
                </div>

                <div class="card-body p-4">
                    <form id="videoNoteForm" onsubmit="return false;">
                        <input type="hidden" name="media_type" value="video">
                        <input type="hidden" id="noteDurationSeconds" name="duration_seconds" value="0">

                        <!-- University Course Selection -->
                        <div class="mb-3">
                            <label for="courseSelect" class="form-label fw-semibold small text-muted text-uppercase">
                                University Course <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-lg rounded-3" id="courseSelect" name="course_id" required>
                                <?php foreach ($assignedCourses as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= $c['id'] == $preselectedCourseId ? 'selected' : '' ?>>
                                        <?= e($c['title']) ?> (<?= e($c['code'] ?: 'Univ') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">Only students enrolled in this university course can watch this note.</div>
                        </div>

                        <!-- Lesson / Topic Selection (Optional) -->
                        <div class="mb-3">
                            <label for="lessonSelect" class="form-label fw-semibold small text-muted text-uppercase">
                                Associated Topic / Lesson <span class="text-muted">(Optional)</span>
                            </label>
                            <select class="form-select rounded-3" id="lessonSelect" name="lesson_id">
                                <option value="">-- General Course Video Note --</option>
                                <?php foreach ($initialLessons as $ls): ?>
                                    <option value="<?= (int)$ls['id'] ?>">
                                        <?= e($ls['section_title'] ? $ls['section_title'] . ' &bull; ' : '') ?><?= e($ls['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Note Title -->
                        <div class="mb-3">
                            <label for="noteTitle" class="form-label fw-semibold small text-muted text-uppercase">
                                Video Title <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-lg rounded-3" id="noteTitle" name="title" placeholder="e.g. Solution Walkthrough for Assignment 3" required maxlength="255">
                        </div>

                        <!-- Note Description -->
                        <div class="mb-4">
                            <label for="noteDescription" class="form-label fw-semibold small text-muted text-uppercase">
                                Description / Key Takeaways <span class="text-muted">(Optional)</span>
                            </label>
                            <textarea class="form-control rounded-3" id="noteDescription" name="description" rows="4" placeholder="Summarize the core takeaways or provide notes for students watching this video..."></textarea>
                        </div>

                        <!-- Upload Progress Bar -->
                        <div id="uploadProgressContainer" class="mb-4" style="display: none;">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span id="uploadStatusText">Uploading video note...</span>
                                <span id="uploadPercentage">0%</span>
                            </div>
                            <div class="progress rounded-pill" style="height: 8px;">
                                <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary btn-lg rounded-pill fw-bold py-3 shadow-sm d-flex align-items-center justify-content-center gap-2" id="btnPublishNote" disabled>
                                <i class="bi bi-send-fill"></i>
                                <span>Publish Video Note</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary rounded-pill fw-semibold py-2" id="btnDraftNote" disabled>
                                <i class="bi bi-journal-bookmark me-1"></i> Save as Draft
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<!-- Studio Video Recorder JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    let mediaRecorder = null;
    let videoChunks = [];
    let videoBlob = null;
    let recordedDuration = 0;
    let timerInterval = null;
    let mediaStream = null;
    let currentFacingMode = 'user';
    let isMirrored = true;

    // Elements
    const liveVideo = document.getElementById('liveVideoPreview');
    const recordedVideo = document.getElementById('recordedVideoPreview');
    const standbyOverlay = document.getElementById('cameraStandbyOverlay');
    const cameraControls = document.getElementById('cameraControlsOverlay');
    const timerDisplay = document.getElementById('timerDisplay');
    const recordingBadge = document.getElementById('recordingBadge');
    const idleBadge = document.getElementById('idleBadge');
    const pausedBadge = document.getElementById('pausedBadge');
    const btnInit = document.getElementById('btnInitCamera');
    const btnStart = document.getElementById('btnStartRecord');
    const btnPause = document.getElementById('btnPauseRecord');
    const btnStop = document.getElementById('btnStopRecord');
    const btnDiscard = document.getElementById('btnDiscardRecord');
    const btnPublish = document.getElementById('btnPublishNote');
    const btnDraft = document.getElementById('btnDraftNote');
    const btnMirror = document.getElementById('btnToggleMirror');
    const btnFlip = document.getElementById('btnFlipCamera');
    const courseSelect = document.getElementById('courseSelect');
    const lessonSelect = document.getElementById('lessonSelect');
    const fallbackInput = document.getElementById('fallbackVideoInput');
    const progressContainer = document.getElementById('uploadProgressContainer');
    const progressBar = document.getElementById('uploadProgressBar');
    const uploadStatusText = document.getElementById('uploadStatusText');
    const uploadPercentage = document.getElementById('uploadPercentage');

    // Dynamic lesson loader
    if (courseSelect && lessonSelect) {
        courseSelect.addEventListener('change', async () => {
            const courseId = courseSelect.value;
            lessonSelect.innerHTML = '<option value="">Loading lessons...</option>';
            try {
                const res = await fetch(`<?= url('api/voice-video-notes.php') ?>?action=get_lessons&course_id=${encodeURIComponent(courseId)}`);
                const data = await res.json();
                if (data.success && Array.isArray(data.lessons)) {
                    let html = '<option value="">-- General Course Video Note --</option>';
                    data.lessons.forEach(l => {
                        const sec = l.section_title ? `${l.section_title} &bull; ` : '';
                        html += `<option value="${l.id}">${sec}${escapeHtml(l.title)}</option>`;
                    });
                    lessonSelect.innerHTML = html;
                } else {
                    lessonSelect.innerHTML = '<option value="">-- General Course Video Note --</option>';
                }
            } catch (e) {
                console.error(e);
                lessonSelect.innerHTML = '<option value="">-- General Course Video Note --</option>';
            }
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.innerText = text;
        return div.innerHTML;
    }

    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }

    // Initialize Camera Stream
    async function initCamera() {
        try {
            if (mediaStream) {
                mediaStream.getTracks().forEach(t => t.stop());
            }

            const constraints = {
                video: {
                    facingMode: currentFacingMode,
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: true
            };

            mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
            liveVideo.srcObject = mediaStream;
            liveVideo.style.display = 'block';
            recordedVideo.style.display = 'none';
            standbyOverlay.style.display = 'none';
            cameraControls.style.display = 'flex';

            btnStart.style.display = 'inline-flex';
            if (btnInit) btnInit.style.display = 'none';

        } catch (err) {
            console.error("Camera access error:", err);
            alert("Unable to access camera/microphone. Please ensure you have granted browser permissions, or use the file upload fallback.");
        }
    }

    // Toggle mirror mode
    if (btnMirror) {
        btnMirror.addEventListener('click', () => {
            isMirrored = !isMirrored;
            liveVideo.style.transform = isMirrored ? 'scaleX(-1)' : 'scaleX(1)';
        });
    }

    // Switch between front and back camera
    if (btnFlip) {
        btnFlip.addEventListener('click', () => {
            currentFacingMode = currentFacingMode === 'user' ? 'environment' : 'user';
            isMirrored = (currentFacingMode === 'user');
            liveVideo.style.transform = isMirrored ? 'scaleX(-1)' : 'scaleX(1)';
            initCamera();
        });
    }

    // Start Video Recording
    function startRecording() {
        if (!mediaStream) {
            alert("Camera not initialized.");
            return;
        }

        videoChunks = [];

        // Select supported mime type
        let mimeType = 'video/webm;codecs=vp8,opus';
        if (!MediaRecorder.isTypeSupported(mimeType)) {
            mimeType = 'video/webm';
            if (!MediaRecorder.isTypeSupported(mimeType)) {
                mimeType = 'video/mp4';
                if (!MediaRecorder.isTypeSupported(mimeType)) {
                    mimeType = '';
                }
            }
        }

        const options = mimeType ? { mimeType } : {};
        mediaRecorder = new MediaRecorder(mediaStream, options);

        mediaRecorder.ondataavailable = (e) => {
            if (e.data && e.data.size > 0) {
                videoChunks.push(e.data);
            }
        };

        mediaRecorder.onstop = () => {
            videoBlob = new Blob(videoChunks, { type: mediaRecorder.mimeType || 'video/webm' });
            const videoUrl = URL.createObjectURL(videoBlob);
            
            // Switch to playback view
            liveVideo.style.display = 'none';
            recordedVideo.src = videoUrl;
            recordedVideo.style.display = 'block';
            cameraControls.style.display = 'none';

            document.getElementById('noteDurationSeconds').value = recordedDuration;
            btnPublish.disabled = false;
            btnDraft.disabled = false;
        };

        mediaRecorder.start(500); // 500ms chunks

        recordedDuration = 0;
        timerDisplay.innerText = '00:00';
        timerInterval = setInterval(() => {
            recordedDuration++;
            timerDisplay.innerText = formatTime(recordedDuration);
        }, 1000);

        idleBadge.style.setProperty('display', 'none', 'important');
        pausedBadge.style.setProperty('display', 'none', 'important');
        recordingBadge.style.setProperty('display', 'inline-flex', 'important');

        btnStart.style.display = 'none';
        btnPause.style.display = 'inline-flex';
        btnStop.style.display = 'inline-flex';
        btnDiscard.style.display = 'none';
        btnPublish.disabled = true;
        btnDraft.disabled = true;
    }

    // Pause / Resume
    function togglePause() {
        if (!mediaRecorder) return;
        if (mediaRecorder.state === 'recording') {
            mediaRecorder.pause();
            clearInterval(timerInterval);
            recordingBadge.style.setProperty('display', 'none', 'important');
            pausedBadge.style.setProperty('display', 'inline-flex', 'important');
            document.getElementById('pauseBtnText').innerText = 'Resume';
        } else if (mediaRecorder.state === 'paused') {
            mediaRecorder.resume();
            timerInterval = setInterval(() => {
                recordedDuration++;
                timerDisplay.innerText = formatTime(recordedDuration);
            }, 1000);
            pausedBadge.style.setProperty('display', 'none', 'important');
            recordingBadge.style.setProperty('display', 'inline-flex', 'important');
            document.getElementById('pauseBtnText').innerText = 'Pause';
        }
    }

    // Stop Recording
    function stopRecording() {
        if (!mediaRecorder) return;
        clearInterval(timerInterval);
        mediaRecorder.stop();

        recordingBadge.style.setProperty('display', 'none', 'important');
        pausedBadge.style.setProperty('display', 'none', 'important');
        idleBadge.style.setProperty('display', 'inline-flex', 'important');

        btnPause.style.display = 'none';
        btnStop.style.display = 'none';
        btnDiscard.style.display = 'inline-flex';
    }

    // Discard & Re-record
    function discardRecording() {
        if (confirm("Are you sure you want to discard this video and record again?")) {
            videoBlob = null;
            videoChunks = [];
            recordedDuration = 0;
            document.getElementById('noteDurationSeconds').value = 0;
            recordedVideo.src = '';
            recordedVideo.style.display = 'none';
            liveVideo.style.display = 'block';
            cameraControls.style.display = 'flex';
            timerDisplay.innerText = '00:00';
            btnDiscard.style.display = 'none';
            btnStart.style.display = 'inline-flex';
            btnPublish.disabled = true;
            btnDraft.disabled = true;
        }
    }

    // Fallback file input
    if (fallbackInput) {
        fallbackInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;

            videoBlob = file;
            const videoUrl = URL.createObjectURL(file);
            liveVideo.style.display = 'none';
            standbyOverlay.style.display = 'none';
            cameraControls.style.display = 'none';
            recordedVideo.src = videoUrl;
            recordedVideo.style.display = 'block';

            recordedVideo.onloadedmetadata = () => {
                recordedDuration = Math.round(recordedVideo.duration) || 0;
                timerDisplay.innerText = formatTime(recordedDuration);
                document.getElementById('noteDurationSeconds').value = recordedDuration;
            };

            btnStart.style.display = 'none';
            btnDiscard.style.display = 'inline-flex';
            btnPublish.disabled = false;
            btnDraft.disabled = false;
        });
    }

    // Submit Note
    async function submitNote(status = 'published') {
        const form = document.getElementById('videoNoteForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        if (!videoBlob) {
            alert("Please record or select a video before saving.");
            return;
        }

        const formData = new FormData(form);
        formData.append('action', 'upload_recording');
        formData.append('status', status);

        const filename = (status === 'draft' ? 'draft_vid_' : 'video_note_') + Date.now() + '.webm';
        formData.append('media_file', videoBlob, filename);

        btnPublish.disabled = true;
        btnDraft.disabled = true;
        progressContainer.style.display = 'block';
        uploadProgressBar.style.width = '0%';
        uploadPercentage.innerText = '0%';
        uploadStatusText.innerText = status === 'published' ? 'Uploading and publishing video note...' : 'Saving draft...';

        try {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '<?= url('api/voice-video-notes.php') ?>', true);

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    uploadProgressBar.style.width = percent + '%';
                    uploadPercentage.innerText = percent + '%';
                }
            };

            xhr.onload = () => {
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (xhr.status === 200 && res.success) {
                        uploadProgressBar.classList.remove('bg-primary');
                        uploadProgressBar.classList.add('bg-success');
                        uploadStatusText.innerText = 'Success! Redirecting...';
                        setTimeout(() => {
                            window.location.href = res.redirect || '<?= url('teacher/notes.php') ?>';
                        }, 800);
                    } else {
                        alert(res.message || 'Error saving video note.');
                        btnPublish.disabled = false;
                        btnDraft.disabled = false;
                        progressContainer.style.display = 'none';
                    }
                } catch (err) {
                    console.error("Response parse error:", xhr.responseText);
                    alert("An unexpected server error occurred.");
                    btnPublish.disabled = false;
                    btnDraft.disabled = false;
                    progressContainer.style.display = 'none';
                }
            };

            xhr.onerror = () => {
                alert("Network error occurred while uploading. Please check your connection.");
                btnPublish.disabled = false;
                btnDraft.disabled = false;
                progressContainer.style.display = 'none';
            };

            xhr.send(formData);

        } catch (e) {
            console.error(e);
            alert("Upload failed: " + e.message);
            btnPublish.disabled = false;
            btnDraft.disabled = false;
            progressContainer.style.display = 'none';
        }
    }

    // Bindings
    if (btnInit) btnInit.addEventListener('click', initCamera);
    if (btnStart) btnStart.addEventListener('click', startRecording);
    if (btnPause) btnPause.addEventListener('click', togglePause);
    if (btnStop) btnStop.addEventListener('click', stopRecording);
    if (btnDiscard) btnDiscard.addEventListener('click', discardRecording);
    if (btnPublish) btnPublish.addEventListener('click', () => submitNote('published'));
    if (btnDraft) btnDraft.addEventListener('click', () => submitNote('draft'));
});
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
