<?php
/**
 * University Teacher Voice Note Recording Studio
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

$pageTitle = 'Record Voice Note — Studio';
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
                    <li class="breadcrumb-item active" aria-current="page">Record Voice Note</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold text-main mb-1 d-flex align-items-center gap-2">
                <span class="d-inline-flex p-2 rounded-3 bg-danger bg-opacity-10 text-danger shadow-sm">
                    <lord-icon src="https://cdn.lordicon.com/bkhmabup.json" trigger="hover" colors="primary:#ef4444,secondary:#dc2626" style="width:32px;height:32px;"></lord-icon>
                </span>
                Voice Note Studio
            </h1>
            <p class="text-muted small mb-0">Record crystal-clear audio explanations, lecture summaries, or quick audio feedback for your university students.</p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="<?= url('teacher/record-video-note.php') ?>" class="btn btn-outline-primary rounded-pill px-3 py-2 fw-semibold d-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-camera-video-fill"></i> Switch to Video Studio
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
                <p class="mb-2 text-muted">You need an active university course assigned to your instructor profile before you can publish voice notes.</p>
                <a href="<?= url('teacher/select-course.php') ?>" class="btn btn-warning btn-sm fw-bold rounded-pill px-3">
                    <i class="bi bi-collection-play me-1"></i> Select or Claim Course
                </a>
            </div>
        </div>
    <?php else: ?>

    <div class="row g-4">
        <!-- Recording Console Column -->
        <div class="col-lg-7">
            <div class="vvn-studio-card h-100 bg-body">
                <div class="vvn-studio-header">
                    <div class="d-flex align-items-center justify-content-between position-relative" style="z-index: 2;">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-pill bg-danger d-flex align-items-center gap-1 px-3 py-2 vvn-pulse" id="recordingBadge" style="display: none !important;">
                                <span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true" style="width: 8px; height: 8px;"></span>
                                <span class="fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">LIVE RECORDING</span>
                            </span>
                            <span class="badge rounded-pill bg-secondary d-flex align-items-center gap-1 px-3 py-2" id="idleBadge">
                                <i class="bi bi-circle-fill text-muted" style="font-size: 8px;"></i>
                                <span class="fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">STUDIO READY</span>
                            </span>
                            <span class="badge rounded-pill bg-warning text-dark d-flex align-items-center gap-1 px-3 py-2" id="pausedBadge" style="display: none !important;">
                                <i class="bi bi-pause-fill"></i>
                                <span class="fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">RECORDING PAUSED</span>
                            </span>
                        </div>
                        <div class="text-white-50 font-monospace fs-5 fw-bold bg-black bg-opacity-40 px-3 py-1 rounded-pill border border-white border-opacity-10" id="timerDisplay">00:00</div>
                    </div>
                </div>

                <div class="card-body p-3 p-md-4 text-center d-flex flex-column justify-content-between">
                    <!-- Audio Waveform Visualizer Canvas -->
                    <div class="vvn-visualizer-box my-3">
                        <canvas id="audioVisualizer" class="w-100" height="150" style="max-height: 160px; border-radius: 12px;"></canvas>
                        
                        <!-- Initial Mic Prompt Overlay -->
                        <div id="micPromptOverlay" class="position-absolute text-white-50 text-center px-3">
                            <div class="d-inline-flex p-3 rounded-circle bg-danger bg-opacity-20 text-danger mb-2">
                                <i class="bi bi-mic fs-2 animate-bounce"></i>
                            </div>
                            <span class="small fw-medium d-block text-white">Microphone will activate when you press Record</span>
                            <small class="text-white-50" style="font-size: 0.75rem;">Ensure browser microphone permissions are enabled</small>
                        </div>
                    </div>

                    <!-- Audio Review Player (Shown when recording finishes) -->
                    <div id="audioPlaybackContainer" class="vvn-player-box text-start shadow-sm" style="display: none;">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-success bg-opacity-10 text-success fw-bold d-flex align-items-center gap-1">
                                <i class="bi bi-check2-circle"></i> Audio Recorded Successfully
                            </span>
                            <span class="small text-muted font-monospace fw-bold" id="recordedDurationLabel">00:00</span>
                        </div>
                        <audio id="audioPreview" controls class="w-100 rounded-3 shadow-none" style="height: 48px;"></audio>
                    </div>

                    <!-- Recording Controls -->
                    <div class="vvn-controls-bar">
                        <!-- Record Button -->
                        <button type="button" class="vvn-btn-record" id="btnStartRecord">
                            <i class="bi bi-record-circle-fill fs-5"></i>
                            <span id="startBtnText">Start Recording</span>
                        </button>

                        <!-- Pause/Resume Button (Hidden initially) -->
                        <button type="button" class="btn btn-warning btn-lg rounded-pill px-4 py-3 fw-bold align-items-center gap-2 shadow-sm" id="btnPauseRecord" style="display: none;">
                            <i class="bi bi-pause-fill fs-5"></i>
                            <span id="pauseBtnText">Pause</span>
                        </button>

                        <!-- Stop Button (Hidden initially) -->
                        <button type="button" class="btn btn-dark btn-lg rounded-pill px-4 py-3 fw-bold align-items-center gap-2 shadow-sm" id="btnStopRecord" style="display: none;">
                            <i class="bi bi-stop-fill fs-5"></i>
                            <span>Stop Recording</span>
                        </button>

                        <!-- Discard / Re-record Button (Hidden initially) -->
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-medium align-items-center gap-1 shadow-sm" id="btnDiscardRecord" style="display: none;">
                            <i class="bi bi-trash3"></i>
                            <span>Discard &amp; Re-record</span>
                        </button>
                    </div>

                    <!-- Audio File Fallback Alternative -->
                    <div class="mt-4 pt-3 border-top text-center">
                        <span class="small text-muted d-block mb-2">Or upload a pre-recorded audio file (MP3, WAV, M4A, OGG):</span>
                        <input type="file" id="fallbackAudioInput" accept="audio/*" class="d-none">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 shadow-sm" onclick="document.getElementById('fallbackAudioInput').click();">
                            <i class="bi bi-cloud-arrow-up me-1"></i> Upload Audio File Instead
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
                            <span class="profile-avatar-badge bg-danger text-white" style="width: 16px; height: 16px; font-size: 8px;">
                                <i class="bi bi-mic-fill"></i>
                            </span>
                        </div>
                        <div>
                            <div class="fw-bold text-main small mb-0"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></div>
                            <small class="text-muted" style="font-size: 0.75rem;">University Instructor &bull; <?= e($teacher['specialization'] ?? 'Faculty') ?></small>
                        </div>
                    </div>

                    <h5 class="fw-bold mb-1 text-main d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-text text-primary"></i>
                        Note Details &amp; Assignment
                    </h5>
                    <p class="text-muted small mb-0">Specify the course, topic, and description for this voice note.</p>
                </div>
                        <i class="bi bi-file-earmark-text text-primary"></i>
                        Note Details &amp; Assignment
                    </h5>
                    <p class="text-muted small mb-0">Specify the course, topic, and description for this voice note.</p>
                </div>

                <div class="card-body p-4">
                    <form id="voiceNoteForm" onsubmit="return false;">
                        <input type="hidden" name="media_type" value="voice">
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
                            <div class="form-text small">Students enrolled in this course will be able to listen to this note.</div>
                        </div>

                        <!-- Lesson / Topic Selection (Optional) -->
                        <div class="mb-3">
                            <label for="lessonSelect" class="form-label fw-semibold small text-muted text-uppercase">
                                Associated Topic / Lesson <span class="text-muted">(Optional)</span>
                            </label>
                            <select class="form-select rounded-3" id="lessonSelect" name="lesson_id">
                                <option value="">-- General Course Audio Note --</option>
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
                                Note Title <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-lg rounded-3" id="noteTitle" name="title" placeholder="e.g. Week 4 Exam Tips & Key Formulas" required maxlength="255">
                        </div>

                        <!-- Note Description -->
                        <div class="mb-4">
                            <label for="noteDescription" class="form-label fw-semibold small text-muted text-uppercase">
                                Description / Notes <span class="text-muted">(Optional)</span>
                            </label>
                            <textarea class="form-control rounded-3" id="noteDescription" name="description" rows="4" placeholder="Add key points, timestamps, or instructions to accompany this audio recording..."></textarea>
                        </div>

                        <!-- Progress Bar (Hidden during preparation) -->
                        <div id="uploadProgressContainer" class="mb-4" style="display: none;">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span id="uploadStatusText">Uploading audio note...</span>
                                <span id="uploadPercentage">0%</span>
                            </div>
                            <div class="progress rounded-pill" style="height: 8px;">
                                <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary btn-lg rounded-pill fw-bold py-3 shadow-sm d-flex align-items-center justify-content-center gap-2" id="btnPublishNote" disabled>
                                <i class="bi bi-send-fill"></i>
                                <span>Publish Voice Note</span>
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

<!-- Studio Audio Recorder & Visualizer JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    let mediaRecorder = null;
    let audioChunks = [];
    let audioBlob = null;
    let recordedDuration = 0;
    let timerInterval = null;
    let audioContext = null;
    let analyser = null;
    let microphoneStream = null;
    let animationFrameId = null;

    // Elements
    const canvas = document.getElementById('audioVisualizer');
    const canvasCtx = canvas ? canvas.getContext('2d') : null;
    const timerDisplay = document.getElementById('timerDisplay');
    const recordingBadge = document.getElementById('recordingBadge');
    const idleBadge = document.getElementById('idleBadge');
    const pausedBadge = document.getElementById('pausedBadge');
    const micPrompt = document.getElementById('micPromptOverlay');
    const audioPlayback = document.getElementById('audioPlaybackContainer');
    const audioPreview = document.getElementById('audioPreview');
    const durationLabel = document.getElementById('recordedDurationLabel');
    const btnStart = document.getElementById('btnStartRecord');
    const btnPause = document.getElementById('btnPauseRecord');
    const btnStop = document.getElementById('btnStopRecord');
    const btnDiscard = document.getElementById('btnDiscardRecord');
    const btnPublish = document.getElementById('btnPublishNote');
    const btnDraft = document.getElementById('btnDraftNote');
    const courseSelect = document.getElementById('courseSelect');
    const lessonSelect = document.getElementById('lessonSelect');
    const fallbackInput = document.getElementById('fallbackAudioInput');
    const progressContainer = document.getElementById('uploadProgressContainer');
    const progressBar = document.getElementById('uploadProgressBar');
    const uploadStatusText = document.getElementById('uploadStatusText');
    const uploadPercentage = document.getElementById('uploadPercentage');

    // Canvas size responsiveness
    function resizeCanvas() {
        if (canvas) {
            canvas.width = canvas.parentElement.clientWidth || 400;
            canvas.height = 150;
        }
    }
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    // Dynamic lesson loader on course change
    if (courseSelect && lessonSelect) {
        courseSelect.addEventListener('change', async () => {
            const courseId = courseSelect.value;
            lessonSelect.innerHTML = '<option value="">Loading lessons...</option>';
            try {
                const res = await fetch(`<?= url('api/voice-video-notes.php') ?>?action=get_lessons&course_id=${encodeURIComponent(courseId)}`);
                const data = await res.json();
                if (data.success && Array.isArray(data.lessons)) {
                    let html = '<option value="">-- General Course Audio Note --</option>';
                    data.lessons.forEach(l => {
                        const sec = l.section_title ? `${l.section_title} &bull; ` : '';
                        html += `<option value="${l.id}">${sec}${escapeHtml(l.title)}</option>`;
                    });
                    lessonSelect.innerHTML = html;
                } else {
                    lessonSelect.innerHTML = '<option value="">-- General Course Audio Note --</option>';
                }
            } catch (e) {
                console.error(e);
                lessonSelect.innerHTML = '<option value="">-- General Course Audio Note --</option>';
            }
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.innerText = text;
        return div.innerHTML;
    }

    // Format timer
    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }

    // Draw idle visualizer waveform
    function drawIdleVisualizer() {
        if (!canvasCtx || !canvas) return;
        canvasCtx.clearRect(0, 0, canvas.width, canvas.height);
        const centerY = canvas.height / 2;
        canvasCtx.beginPath();
        canvasCtx.strokeStyle = 'rgba(239, 68, 68, 0.4)';
        canvasCtx.lineWidth = 2;
        canvasCtx.moveTo(0, centerY);
        for (let x = 0; x < canvas.width; x += 10) {
            canvasCtx.lineTo(x, centerY + Math.sin(x * 0.05) * 3);
        }
        canvasCtx.stroke();
    }
    drawIdleVisualizer();

    // Start Real-Time Waveform Visualizer
    function startVisualizer(stream) {
        try {
            audioContext = new (window.AudioContext || window.webkitAudioContext)();
            analyser = audioContext.createAnalyser();
            const source = audioContext.createMediaStreamSource(stream);
            source.connect(analyser);
            analyser.fftSize = 128;
            const bufferLength = analyser.frequencyBinCount;
            const dataArray = new Uint8Array(bufferLength);

            function renderWave() {
                animationFrameId = requestAnimationFrame(renderWave);
                analyser.getByteFrequencyData(dataArray);

                canvasCtx.fillStyle = 'rgba(15, 23, 42, 0.3)';
                canvasCtx.fillRect(0, 0, canvas.width, canvas.height);

                const barWidth = (canvas.width / bufferLength) * 2.2;
                let barHeight;
                let x = 0;

                for (let i = 0; i < bufferLength; i++) {
                    barHeight = (dataArray[i] / 255) * (canvas.height - 20);

                    // Gradient for audio bars
                    const gradient = canvasCtx.createLinearGradient(0, canvas.height, 0, 0);
                    gradient.addColorStop(0, '#ef4444');
                    gradient.addColorStop(0.5, '#f59e0b');
                    gradient.addColorStop(1, '#10b981');

                    canvasCtx.fillStyle = gradient;
                    canvasCtx.fillRect(x, (canvas.height - barHeight) / 2, barWidth, barHeight || 4);

                    x += barWidth + 3;
                }
            }
            renderWave();
        } catch (e) {
            console.warn("Visualizer initialization failed:", e);
        }
    }

    function stopVisualizer() {
        if (animationFrameId) cancelAnimationFrame(animationFrameId);
        if (audioContext && audioContext.state !== 'closed') {
            audioContext.close();
        }
        drawIdleVisualizer();
    }

    // Start Recording
    async function startRecording() {
        audioChunks = [];
        try {
            microphoneStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            startVisualizer(microphoneStream);

            const options = { mimeType: 'audio/webm;codecs=opus' };
            if (!MediaRecorder.isTypeSupported(options.mimeType)) {
                delete options.mimeType;
            }

            mediaRecorder = new MediaRecorder(microphoneStream, options);

            mediaRecorder.ondataavailable = (e) => {
                if (e.data && e.data.size > 0) {
                    audioChunks.push(e.data);
                }
            };

            mediaRecorder.onstop = () => {
                audioBlob = new Blob(audioChunks, { type: mediaRecorder.mimeType || 'audio/webm' });
                const audioUrl = URL.createObjectURL(audioBlob);
                audioPreview.src = audioUrl;
                audioPlayback.style.display = 'block';
                durationLabel.innerText = formatTime(recordedDuration);
                document.getElementById('noteDurationSeconds').value = recordedDuration;
                
                // Enable publish and draft buttons
                btnPublish.disabled = false;
                btnDraft.disabled = false;
            };

            mediaRecorder.start(250); // collect 250ms chunks

            // UI State updates
            recordedDuration = 0;
            timerDisplay.innerText = '00:00';
            timerInterval = setInterval(() => {
                recordedDuration++;
                timerDisplay.innerText = formatTime(recordedDuration);
            }, 1000);

            idleBadge.style.setProperty('display', 'none', 'important');
            pausedBadge.style.setProperty('display', 'none', 'important');
            recordingBadge.style.setProperty('display', 'inline-flex', 'important');
            if (micPrompt) micPrompt.style.display = 'none';

            btnStart.style.display = 'none';
            btnPause.style.display = 'inline-flex';
            btnStop.style.display = 'inline-flex';
            btnDiscard.style.display = 'none';
            audioPlayback.style.display = 'none';
            btnPublish.disabled = true;
            btnDraft.disabled = true;

        } catch (err) {
            console.error("Microphone access error:", err);
            alert("Microphone permission denied or no audio input device found. Please allow microphone access in your browser settings, or use the file upload alternative.");
        }
    }

    // Pause / Resume Recording
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
        if (microphoneStream) {
            microphoneStream.getTracks().forEach(track => track.stop());
        }
        stopVisualizer();

        // UI Reset
        recordingBadge.style.setProperty('display', 'none', 'important');
        pausedBadge.style.setProperty('display', 'none', 'important');
        idleBadge.style.setProperty('display', 'inline-flex', 'important');

        btnPause.style.display = 'none';
        btnStop.style.display = 'none';
        btnStart.style.display = 'none';
        btnDiscard.style.display = 'inline-flex';
    }

    // Discard Recording
    function discardRecording() {
        if (confirm("Are you sure you want to discard this recording and start over?")) {
            audioBlob = null;
            audioChunks = [];
            recordedDuration = 0;
            document.getElementById('noteDurationSeconds').value = 0;
            audioPreview.src = '';
            audioPlayback.style.display = 'none';
            timerDisplay.innerText = '00:00';
            btnDiscard.style.display = 'none';
            btnStart.style.display = 'inline-flex';
            btnPublish.disabled = true;
            btnDraft.disabled = true;
            if (micPrompt) micPrompt.style.display = 'block';
        }
    }

    // Fallback file input handler
    if (fallbackInput) {
        fallbackInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;

            audioBlob = file;
            const audioUrl = URL.createObjectURL(file);
            audioPreview.src = audioUrl;
            audioPlayback.style.display = 'block';

            // Extract duration from audio element metadata
            audioPreview.onloadedmetadata = () => {
                recordedDuration = Math.round(audioPreview.duration) || 0;
                durationLabel.innerText = formatTime(recordedDuration);
                timerDisplay.innerText = formatTime(recordedDuration);
                document.getElementById('noteDurationSeconds').value = recordedDuration;
            };

            btnStart.style.display = 'none';
            btnDiscard.style.display = 'inline-flex';
            btnPublish.disabled = false;
            btnDraft.disabled = false;
            if (micPrompt) micPrompt.style.display = 'none';
        });
    }

    // Save / Publish Note Submission
    async function submitNote(status = 'published') {
        const form = document.getElementById('voiceNoteForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        if (!audioBlob) {
            alert("Please record or select an audio file before saving.");
            return;
        }

        const formData = new FormData(form);
        formData.append('action', 'upload_recording');
        formData.append('status', status);
        
        // Append recorded audio file blob
        const filename = (status === 'draft' ? 'draft_voice_' : 'voice_note_') + Date.now() + '.webm';
        formData.append('media_file', audioBlob, filename);

        // UI upload progress state
        btnPublish.disabled = true;
        btnDraft.disabled = true;
        progressContainer.style.display = 'block';
        uploadProgressBar.style.width = '0%';
        uploadPercentage.innerText = '0%';
        uploadStatusText.innerText = status === 'published' ? 'Publishing voice note...' : 'Saving draft...';

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
                        alert(res.message || 'Error saving voice note.');
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

    // Event Bindings
    if (btnStart) btnStart.addEventListener('click', startRecording);
    if (btnPause) btnPause.addEventListener('click', togglePause);
    if (btnStop) btnStop.addEventListener('click', stopRecording);
    if (btnDiscard) btnDiscard.addEventListener('click', discardRecording);
    if (btnPublish) btnPublish.addEventListener('click', () => submitNote('published'));
    if (btnDraft) btnDraft.addEventListener('click', () => submitNote('draft'));
});
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
