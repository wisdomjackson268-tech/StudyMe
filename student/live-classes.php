<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/live_classes.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';

secure_page(ROLE_STUDENT);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$user      = current_user();
$studentId = (int)$user['id'];
$pdo       = getDBConnection();

$stmtStudent = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmtStudent->execute([$studentId]);
$resolvedStudentId = (int)$stmtStudent->fetchColumn();

if (is_post() && isset($_POST['action']) && $_POST['action'] === 'mark_attendance') {
    $classId = (int)($_POST['class_id'] ?? 0);
    if ($classId && $resolvedStudentId) {
        $result = mark_live_class_attendance($classId, $resolvedStudentId, $studentId);
        if ($result['success']) {
            set_flash('success', $result['message']);
        } else {
            set_flash('error', $result['message']);
        }
    }
    redirect('student/live-classes.php');
}

$upcomingLiveClasses = get_student_upcoming_live_classes($studentId);
$pastLiveClasses     = get_student_past_live_classes($studentId);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<style>
.live-page-header { min-width: 0; }
.live-page-header > div:first-child { min-width: 0; }
.live-page-header h1, .live-page-header p { overflow-wrap: anywhere; }

/* Segmented pill tabs */
.live-tabs-nav-container {
    background: #f1f5f9;
    padding: 5px;
    border-radius: 9999px;
    display: inline-flex;
    gap: 4px;
    border: 1px solid #e2e8f0;
    max-width: 100%;
}
.live-tabs-nav-container .nav-link {
    border: none;
    border-radius: 9999px;
    padding: 0.6rem 1.4rem;
    font-size: 0.88rem;
    font-weight: 700;
    color: #64748b;
    background: transparent;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}
.live-tabs-nav-container .nav-link:hover {
    color: #0f172a;
}
.live-tabs-nav-container .nav-link.active {
    background: #ffffff;
    color: #2563eb;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
}

/* Live Session Card */
.live-session-card {
    min-width: 0;
    border-radius: 1.35rem !important;
    background: #ffffff;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 10px 30px -4px rgba(15, 23, 42, 0.06), 0 4px 12px -2px rgba(15, 23, 42, 0.03);
    transition: transform 0.22s ease, box-shadow 0.22s ease;
    overflow: hidden;
    position: relative;
}
.live-session-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 18px 38px -6px rgba(15, 23, 42, 0.1);
}
.live-session-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: #cbd5e1;
}
.live-session-card.border-live::before {
    background: linear-gradient(90deg, #ef4444, #f43f5e, #dc2626);
}
.live-session-card.border-upcoming::before {
    background: linear-gradient(90deg, #3b82f6, #60a5fa, #2563eb);
}

/* Live Badge Pill */
.badge-live-now {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: #ffffff;
    font-size: 0.72rem;
    letter-spacing: 0.04em;
    font-weight: 800;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.28);
}
.badge-upcoming-pill {
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #dbeafe;
    font-size: 0.72rem;
    font-weight: 700;
}
.badge-level-pill {
    background: #f8fafc;
    color: #475569;
    border: 1px solid #e2e8f0;
    font-size: 0.72rem;
    font-weight: 600;
}

/* Instructor Mentor Card */
.teacher-mentor-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    transition: all 0.2s ease;
}
.teacher-mentor-card:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}
.teacher-mentor-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    aspect-ratio: 1 / 1;
    border: 2px solid #ffffff;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.18);
    background: #eff6ff;
}
.live-profile-trigger {
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
    margin: 0 !important;
    text-decoration: none !important;
    color: #0f172a !important;
    font-size: 0.95rem;
    cursor: pointer;
    text-align: left;
    transition: color 0.15s ease;
}
.live-profile-trigger:hover,
.live-profile-trigger:focus,
.live-profile-trigger:active {
    color: #2563eb !important;
    text-decoration: none !important;
    outline: none !important;
    box-shadow: none !important;
}

/* Schedule Dual Grid */
.session-schedule-grid {
    display: grid;
    grid-template-columns: 1.35fr 1fr;
    gap: .65rem;
}
@media (max-width: 575.98px) {
    .session-schedule-grid { grid-template-columns: 1fr; }
}
.schedule-tile {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .75rem .9rem;
    border-radius: 0.9rem;
    background: #f8fafc;
    border: 1px solid #edf2f7;
}
.schedule-icon-wrap {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #eff6ff;
    color: #2563eb;
    font-size: 1.05rem;
    flex-shrink: 0;
}
.schedule-icon-wrap.duration {
    background: #fff1f2;
    color: #ef4444;
}
.schedule-label {
    display: block;
    color: #64748b;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .03em;
    line-height: 1.1;
}
.schedule-val {
    color: #0f172a;
    font-size: .84rem;
    font-weight: 700;
    line-height: 1.25;
}

/* Attendance Cards */
.attendance-verified-card {
    background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%);
    border: 1px solid #a7f3d0;
    border-radius: 1rem;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.08);
}
.attendance-verified-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: #10b981;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.28);
}
.attendance-prompt-card {
    background: linear-gradient(135deg, #fff1f2, #fff7ed);
    border: 1px solid #fecdd3;
    border-radius: 1rem;
}

/* Join Live Button */
.btn-join-live {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    border: none;
    box-shadow: 0 8px 22px rgba(239, 68, 68, 0.35);
    transition: all 0.2s ease;
    font-size: 0.94rem;
    font-weight: 700;
    min-height: 46px;
}
.btn-join-live:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    transform: translateY(-1px);
    box-shadow: 0 12px 26px rgba(239, 68, 68, 0.45);
    color: #ffffff;
}
.btn-ask-chat {
    border: 1.5px solid #bfdbfe;
    background: #eff6ff;
    color: #2563eb;
    font-weight: 700;
    font-size: 0.92rem;
    min-height: 46px;
    transition: all 0.18s ease;
}
.btn-ask-chat:hover {
    background: #dbeafe;
    color: #1d4ed8;
    border-color: #93c5fd;
}

.live-pulse-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #ffffff;
    box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.7);
    animation: livePulseAnim 1.6s infinite;
}
@keyframes livePulseAnim {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(255, 255, 255, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 255, 255, 0); }
}

#conversationModal .modal-content { overflow: hidden; }
#conversationModal .modal-header {
    background: #f8fafc !important;
    color: #0f172a !important;
    border-bottom: 1px solid #e2e8f0;
}
#conversationModal .modal-title { color: #0f172a; }
#conversationModal .modal-header .text-white-50 { color: #64748b !important; }
#conversationModal .modal-header .btn-close-white { filter: none; opacity: .65; }
#conversationModal .modal-header .btn-close-white:hover { opacity: 1; }
#conversationMessages { scrollbar-width: thin; }
.live-session-card h4,
.live-session-card p,
.live-session-card span,
.live-session-card div { overflow-wrap: anywhere; }

/* Responsive adjustments */
@media (max-width: 767.98px) {
    .live-page-header { align-items: stretch !important; margin-top: 0.35rem; }
    .live-page-header h1 { font-size: 1.28rem; }
    .live-page-header p { line-height: 1.45; }
    .live-session-card { padding: 1.15rem !important; }
    .live-session-card h4 { font-size: 1.15rem !important; line-height: 1.35; }
    .live-session-card .badge { font-size: .7rem; }
    .live-session-card .live-actions { flex-direction: column; }
    .live-session-card .live-actions > * { width: 100%; }
    .live-tabs-nav-container { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); width: 100%; }
    .live-tabs-nav-container .nav-item { min-width: 0; }
    .live-tabs-nav-container .nav-link { width: 100%; min-height: 44px; padding: .55rem .45rem !important; font-size: .78rem; line-height: 1.25; white-space: normal; justify-content: center; text-align: center; }
    .modal-dialog { margin: .5rem; }
    #conversationModal .modal-body { padding: 1rem !important; }
    #conversationMessages { height: min(55vh, 360px) !important; }
    .conversation-message-body { max-width: calc(100% - 4.2rem); }
    .conversation-message { gap: .4rem; }
    .conversation-avatar { width: 36px; height: 36px; }
    .conversation-sender { overflow-wrap: anywhere; }
    .conversation-time { display: block; margin-left: 0; }
    #conversationForm .input-group { display: flex; flex-wrap: wrap; gap: .5rem; }
    #conversationForm .input-group > input[name="message_text"] { flex: 1 1 100%; width: 100%; }
    #conversationForm .input-group > button { flex: 0 0 5.5rem; }
}
</style>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 live-page-header">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small">
            <i class="bi bi-broadcast text-danger me-1"></i> Interactive Learning
        </p>
        <h1 class="h3 fw-bold mb-1">Live Classes &amp; Missed Lesson Replays</h1>
        <p class="text-muted small mb-0">Attend scheduled interactive video classes hosted by your assigned instructors, verify your live attendance, or watch past lesson recordings.</p>
    </div>
</div>

<div class="mb-4">
    <div class="nav live-tabs-nav-container" id="liveTabs" role="tablist">
        <div class="nav-item" role="presentation">
            <button class="nav-link active position-relative" id="upcoming-tab" data-bs-toggle="pill" data-bs-target="#upcoming" type="button" role="tab">
                <i class="bi bi-calendar-event"></i>
                <span>Upcoming &amp; Live (<?= count($upcomingLiveClasses) ?>)</span>
                <?php
                    $hasLiveNow = false;
                    foreach ($upcomingLiveClasses as $u) {
                        if ($u['status'] === 'live') { $hasLiveNow = true; break; }
                    }
                    if ($hasLiveNow):
                ?>
                    <span class="badge bg-danger text-white rounded-pill px-1.5 py-0.5" style="font-size:0.62rem;">LIVE</span>
                <?php endif; ?>
            </button>
        </div>
        <div class="nav-item" role="presentation">
            <button class="nav-link" id="past-tab" data-bs-toggle="pill" data-bs-target="#past" type="button" role="tab">
                <i class="bi bi-play-circle-fill"></i>
                <span>Missed &amp; Replays (<?= count($pastLiveClasses) ?>)</span>
            </button>
        </div>
    </div>
</div>

<div class="tab-content pb-5 mb-4" id="liveTabsContent">

    <div class="tab-pane fade show active" id="upcoming" role="tabpanel">
        <?php if (empty($upcomingLiveClasses)): ?>
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
                <div class="mb-3">
                    <span class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle p-4" style="width: 80px; height: 80px;">
                        <i class="bi bi-calendar-check fs-1"></i>
                    </span>
                </div>
                <h4 class="fw-bold mb-2">No Upcoming Live Sessions Right Now</h4>
                <p class="text-muted col-lg-6 mx-auto mb-3">Your assigned course instructors haven't scheduled a live session for this week yet. Check back soon or continue your self-paced video lessons.</p>
                <div>
                    <a href="<?= url('student/my-courses.php') ?>" class="btn btn-outline-primary rounded-pill px-4 fw-bold">
                        <i class="bi bi-play-circle me-1"></i> Continue Enrolled Courses
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($upcomingLiveClasses as $lc):
                    $isLive     = ($lc['status'] === 'live');
                    $isAttended = !empty($lc['is_attended']);
                    $avatar     = function_exists('get_teacher_avatar_url') ? get_teacher_avatar_url($lc['teacher_avatar'] ?? null, $lc['teacher_name'], (int)($lc['teacher_id'] ?? 0)) : (function_exists('get_avatar_url') ? get_avatar_url($lc['teacher_avatar'] ?? null, $lc['teacher_name']) : ($lc['teacher_avatar'] ?? ''));
                ?>
                    <div class="col-lg-6">
                        <div class="card live-session-card h-100 p-4 <?= $isLive ? 'border-live' : 'border-upcoming' ?>" id="live-card-<?= (int)$lc['id'] ?>">
                            <!-- Top Status Tags -->
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-3 flex-wrap">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="badge <?= $isLive ? 'badge-live-now' : 'badge-upcoming-pill' ?> rounded-pill px-3 py-1.5 d-inline-flex align-items-center gap-1.5">
                                        <?php if ($isLive): ?>
                                            <span class="live-pulse-dot"></span>
                                            <span>LIVE NOW</span>
                                        <?php else: ?>
                                            <i class="bi bi-calendar-event"></i>
                                            <span>UPCOMING</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="badge badge-level-pill rounded-pill px-2.5 py-1.5">
                                        <?= htmlspecialchars($lc['academic_level'] ?? '100 Level') ?>
                                    </span>
                                </div>
                                <?php if ($isAttended): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1.5 fw-bold d-inline-flex align-items-center gap-1" id="badge-attended-<?= (int)$lc['id'] ?>">
                                        <i class="bi bi-patch-check-fill"></i> Present (Marked)
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Title & Subject -->
                            <h4 class="fw-bold text-dark mb-1" style="font-size: 1.2rem; line-height: 1.35;"><?= htmlspecialchars($lc['title']) ?></h4>
                            <div class="d-inline-flex align-items-center gap-1.5 text-primary fw-bold small mb-2.5">
                                <i class="bi bi-journal-bookmark-fill"></i>
                                <span><?= htmlspecialchars($lc['course_title']) ?></span>
                            </div>

                            <?php if (!empty($lc['description'])): ?>
                                <p class="text-muted small mb-3" style="line-height: 1.45; font-size: 0.88rem;"><?= nl2br(htmlspecialchars($lc['description'])) ?></p>
                            <?php endif; ?>

                            <!-- Instructor Mentor Card -->
                            <div class="teacher-mentor-card p-3 mb-3 d-flex align-items-center justify-content-between gap-3">
                                <div class="d-flex align-items-center gap-3 min-w-0">
                                    <div class="position-relative flex-shrink-0">
                                        <img src="<?= e($avatar) ?>" alt="<?= htmlspecialchars($lc['teacher_name']) ?>" class="teacher-mentor-avatar" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($lc['teacher_name']) ?>&background=2563eb&color=ffffff&bold=true';">
                                        <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-2 border-white rounded-circle" title="Instructor Active"></span>
                                    </div>
                                    <div class="min-w-0">
                                        <button type="button" class="live-profile-trigger fw-bold text-dark d-flex align-items-center gap-1.5 text-truncate" data-profile-user-id="<?= (int)($lc['teacher_user_id'] ?? 0) ?>" data-profile-class-id="<?= (int)$lc['id'] ?>">
                                            <span class="text-truncate fw-bold"><?= htmlspecialchars($lc['teacher_name']) ?></span>
                                            <i class="bi bi-patch-check-fill text-primary flex-shrink-0" style="font-size: 0.95rem;" title="Verified Instructor"></i>
                                        </button>
                                        <div class="text-muted d-flex align-items-center gap-1 mt-0.5" style="font-size: 0.76rem;">
                                            <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-0.5 fw-semibold" style="font-size: 0.66rem;">Instructor</span>
                                            <span>Assigned Course Instructor</span>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 py-1.5 text-secondary small fw-semibold flex-shrink-0 shadow-2xs" onclick="openLiveProfile(<?= (int)($lc['teacher_user_id'] ?? 0) ?>, <?= (int)$lc['id'] ?>)">
                                    Profile
                                </button>
                            </div>

                            <!-- Schedule & Duration Grid -->
                            <div class="session-schedule-grid mb-3">
                                <div class="schedule-tile">
                                    <div class="schedule-icon-wrap"><i class="bi bi-calendar3"></i></div>
                                    <div class="schedule-info">
                                        <span class="schedule-label">Scheduled Date</span>
                                        <strong class="schedule-val"><?= date('D, M j, Y @ g:i A', strtotime($lc['scheduled_at'])) ?></strong>
                                    </div>
                                </div>
                                <div class="schedule-tile">
                                    <div class="schedule-icon-wrap duration"><i class="bi bi-clock-history"></i></div>
                                    <div class="schedule-info">
                                        <span class="schedule-label">Expected Duration</span>
                                        <strong class="schedule-val"><?= (int)$lc['duration_minutes'] ?> Minutes</strong>
                                    </div>
                                </div>
                            </div>

                            <!-- Attendance Verification Card -->
                            <?php if ($isLive): ?>
                                <?php if ($isAttended): ?>
                                    <div class="attendance-verified-card p-3 mb-3 d-flex align-items-center gap-3">
                                        <div class="attendance-verified-icon">
                                            <i class="bi bi-shield-fill-check"></i>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="fw-bold text-dark" style="font-size: 0.9rem;">Attendance Verified for this Live Session</div>
                                            <div class="text-muted small" style="font-size: 0.78rem;">Marked as Present on <?= date('g:i A', strtotime($lc['student_attended_at'] ?? 'now')) ?></div>
                                        </div>
                                        <span class="badge bg-success rounded-pill px-2.5 py-1 text-white fw-bold small flex-shrink-0">Present</span>
                                    </div>
                                <?php else: ?>
                                    <div class="attendance-prompt-card p-3 mb-3 d-flex align-items-center gap-3" id="attendance-section-<?= (int)$lc['id'] ?>">
                                        <div class="form-check form-switch fs-4 m-0 p-0 d-flex align-items-center flex-shrink-0">
                                            <input class="form-check-input attendance-checkbox"
                                                   type="checkbox"
                                                   id="attendanceCheck_<?= (int)$lc['id'] ?>"
                                                   data-class-id="<?= (int)$lc['id'] ?>"
                                                   style="width: 2.75rem; height: 1.45rem; cursor: pointer;">
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <label for="attendanceCheck_<?= (int)$lc['id'] ?>" class="fw-bold text-dark mb-0 d-block" style="cursor: pointer; font-size: 0.88rem;">
                                                <i class="bi bi-person-check-fill text-danger me-1"></i> Mark Attendance
                                            </label>
                                            <small class="text-muted d-block" style="font-size: 0.75rem;">Join session or toggle to confirm participation.</small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- Action Buttons -->
                            <div class="mt-auto pt-1">
                                <div class="d-flex gap-2 live-actions">
                                    <?php if ($isLive): ?>
                                        <button type="button" class="btn btn-join-live flex-grow-1 text-white fw-bold rounded-pill py-2.5 d-inline-flex align-items-center justify-content-center gap-2" onclick="openConversation(<?= (int)$lc['id'] ?>, '<?= htmlspecialchars(addslashes($lc['title']), ENT_QUOTES) ?>')">
                                            <span class="live-pulse-dot"></span>
                                            <span>Join Live Now</span>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-light border flex-grow-1 fw-bold rounded-pill py-2.5 text-muted" disabled>
                                            <i class="bi bi-hourglass-split me-1"></i> Waiting for Teacher
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-ask-chat fw-bold rounded-pill px-4 py-2.5 d-inline-flex align-items-center justify-content-center gap-1.5" onclick="openConversation(<?= (int)$lc['id'] ?>, '<?= htmlspecialchars(addslashes($lc['title']), ENT_QUOTES) ?>')">
                                        <i class="bi bi-chat-dots-fill"></i>
                                        <span>Ask</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="tab-pane fade" id="past" role="tabpanel">
        <?php if (empty($pastLiveClasses)): ?>
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
                <div class="mb-3">
                    <span class="d-inline-flex align-items-center justify-content-center bg-secondary-subtle text-secondary rounded-circle p-4" style="width: 80px; height: 80px;">
                        <i class="bi bi-film fs-1"></i>
                    </span>
                </div>
                <h4 class="fw-bold mb-2">No Past Lesson Replays Yet</h4>
                <p class="text-muted col-lg-6 mx-auto mb-0">Recordings of live interactive classes will appear here after sessions conclude, so you never miss a lecture.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($pastLiveClasses as $plc):
                    $hasRecording = !empty($plc['recording_url']);
                    $attendedPast = !empty($plc['is_attended']);
                ?>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm rounded-4 h-100 p-4">
                            <div class="d-flex align-items-start justify-content-between gap-3 mb-2">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                        <span class="badge bg-secondary text-white rounded-pill px-2 py-1 small">
                                            <i class="bi bi-check-circle me-1"></i> CONCLUDED
                                        </span>
                                        <?php if ($attendedPast): ?>
                                            <span class="badge bg-success text-white rounded-pill px-2 py-1 small">
                                                <i class="bi bi-patch-check-fill me-1"></i> Attended Live
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border rounded-pill px-2 py-1 small">
                                                Missed Live Broadcast
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <h5 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($plc['title']) ?></h5>
                                    <p class="text-primary fw-semibold small mb-0">
                                        <i class="bi bi-journal-bookmark me-1"></i> <?= htmlspecialchars($plc['course_title']) ?>
                                    </p>
                                </div>
                            </div>

                            <p class="text-muted small mb-3">Conducted on <?= date('D, M j, Y', strtotime($plc['scheduled_at'])) ?> by <?= htmlspecialchars($plc['teacher_name']) ?>.</p>

                            <div class="mt-auto">
                                <?php if ($hasRecording): ?>
                                    <a href="<?= htmlspecialchars($plc['recording_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-danger w-100 fw-bold rounded-pill">
                                        <i class="bi bi-play-circle-fill me-1"></i> Watch Missed Class Recording
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-light border text-muted w-100 rounded-pill disabled" style="font-size: 0.85rem;">
                                        <i class="bi bi-clock-history me-1"></i> Recording Processing by Instructor
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="conversationModal" tabindex="-1" aria-labelledby="conversationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold" id="conversationModalLabel"><i class="bi bi-chat-dots-fill text-primary me-2"></i>Ask Your Teacher <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill align-middle ms-1" style="font-size:.65rem;"><i class="bi bi-circle-fill me-1" style="font-size:.45rem;"></i>Live chat</span></h5>
                    <small id="conversationTitle" class="text-white-50"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div id="conversationMessages" class="bg-light rounded-3 p-3" style="height: 340px; overflow-y: auto;"></div>
                <form id="conversationForm" class="mt-3">
                    <input type="hidden" name="reply_to_message_id" value="">
                    <div class="input-group">
                        <input type="text" name="message_text" class="form-control" placeholder="Ask your teacher a question..." maxlength="2000" required>
                        <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-send-fill me-1"></i> Ask</button>
                    </div>
                    <div id="liveTypingIndicator" class="live-typing-indicator" role="status" aria-live="polite">
                        <span class="live-typing-dots"><span></span><span></span><span></span></span>
                        <span id="liveTypingText">Someone is typing...</span>
                    </div>
                    <div class="form-text">You can ask questions here. Voice notes are available to the teacher only.</div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="liveProfileModal" tabindex="-1" aria-labelledby="liveProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold" id="liveProfileModalLabel"><i class="bi bi-person-circle me-2"></i>Live Class Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4" id="liveProfileBody">
                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading profile</span></div>
            </div>
        </div>
    </div>
</div>

<style>
.teacher-card-avatar {
    width: 44px;
    height: 44px;
    flex-shrink: 0;
    border-radius: 50%;
    object-fit: cover;
    aspect-ratio: 1 / 1;
    border: 2px solid #2563eb;
    box-shadow: 0 4px 10px rgba(37, 99, 235, 0.18);
    background-color: #eff6ff;
}
.conversation-message { display: flex; align-items: flex-start; gap: .75rem; margin-bottom: 1rem; }
.conversation-message-student { flex-direction: row-reverse; }
.conversation-avatar-wrap { flex-shrink: 0; position: relative; }
.conversation-avatar { width: 44px; height: 44px; flex-shrink: 0; aspect-ratio: 1/1; border-radius: 50%; object-fit: cover; border: 3px solid #fff; box-shadow: 0 4px 12px rgba(15, 23, 42, .14); background: #f8fafc; }
.conversation-message-teacher .conversation-avatar { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25), 0 4px 12px rgba(37, 99, 235, .2); }
.conversation-message-body { max-width: min(78%, 620px); }
.conversation-actions { align-self: center; position: relative; }
.conversation-actions-trigger { border: 0; background: transparent; color: #64748b; padding: .3rem; cursor: pointer; }
.conversation-actions-menu { display: none; position: absolute; z-index: 5; right: 0; top: 2rem; min-width: 130px; padding: .3rem; background: #fff; border: 1px solid #dbeafe; border-radius: .5rem; box-shadow: 0 8px 24px rgba(15, 23, 42, .16); }
.conversation-actions-menu.open { display: flex; flex-direction: column; }
.conversation-actions-menu button { border: 0; background: transparent; color: #334155; text-align: left; padding: .45rem .6rem; border-radius: .35rem; font-size: .8rem; }
.conversation-actions-menu button:hover { background: #eff6ff; }
@media (pointer: coarse) { .conversation-actions-trigger { opacity: 0; } .conversation-message.actions-visible .conversation-actions-trigger { opacity: 1; } }
.conversation-message-student .conversation-message-body { text-align: right; }
.conversation-sender { color: #1e3a8a; font-size: .8rem; font-weight: 700; margin-bottom: .25rem; }
.conversation-message-student .conversation-sender { color: #047857; }
.conversation-role, .conversation-time { color: #64748b; font-size: .7rem; font-weight: 500; margin-left: .35rem; }
.conversation-bubble { background: #fff; border: 1px solid #dbeafe; border-left: 4px solid #2563eb; border-radius: .25rem .8rem .8rem .8rem; color: #1e293b; padding: .7rem .85rem; text-align: left; overflow-wrap: anywhere; box-shadow: 0 2px 8px rgba(15, 23, 42, .05); }
.conversation-message-student .conversation-bubble { background: #ecfdf5; border-color: #a7f3d0; border-left: 1px solid #a7f3d0; border-right: 4px solid #059669; border-radius: .8rem .25rem .8rem .8rem; }
.conversation-bubble audio { max-width: 100%; height: 36px; }
.live-audio-message { min-width: min(100%, 280px); }
.live-audio-duration { display: inline-block; margin-top: .25rem; color: #64748b; font-size: .7rem; font-weight: 700; }
.live-chat-image { display: block; width: min(100%, 320px); max-height: 260px; object-fit: cover; border-radius: .75rem; border: 1px solid #dbeafe; box-shadow: 0 5px 16px rgba(15,23,42,.1); }
.live-chat-video { display: block; width: min(100%, 360px); max-height: 260px; border-radius: .75rem; background: #0f172a; }
.live-profile-trigger {
    border: 0;
    padding: 0;
    background: transparent;
    color: inherit;
    font: inherit;
    font-weight: inherit;
    cursor: pointer;
    text-decoration: none !important;
    transition: color 0.15s ease;
}
.live-profile-trigger:hover {
    color: #2563eb !important;
}
.mention-profile-trigger { padding: 0; border: 0; background: transparent; color: inherit; font-weight: 800; text-decoration: underline; text-underline-offset: 2px; cursor: pointer; }
.mention-profile-trigger:hover, .mention-profile-trigger:focus-visible { color: #1e40af; outline: 0; }
.live-profile-avatar { width: 104px; height: 104px; object-fit: cover; border: 4px solid #dbeafe; box-shadow: 0 12px 28px rgba(37, 99, 235, .16); }
.live-typing-indicator { display: none; align-items: center; gap: .5rem; min-height: 28px; margin-top: .55rem; padding: .35rem .7rem; border-radius: .75rem; background: #eff6ff; color: #1d4ed8; font-size: .76rem; font-weight: 700; }
.live-typing-indicator.is-visible { display: inline-flex; }
.live-typing-dots { display: inline-flex; gap: 3px; }
.live-typing-dots span { width: 5px; height: 5px; border-radius: 50%; background: currentColor; animation: typingDot 1s infinite ease-in-out; }
.live-typing-dots span:nth-child(2) { animation-delay: .15s; }
.live-typing-dots span:nth-child(3) { animation-delay: .3s; }
@keyframes typingDot { 0%, 60%, 100% { transform: translateY(0); opacity: .45; } 30% { transform: translateY(-3px); opacity: 1; } }
</style>

<script>
let conversationClassId = 0;
let conversationTimer = null;
const conversationUserId = <?= (int)$studentId ?>;

function escapeConversationText(value) {
    return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function conversationAvatar(message) {
    const palette = ['#2563eb', '#0f766e', '#7c3aed', '#c2410c', '#be123c', '#4f46e5', '#047857'];
    let hash = 0;
    const identity = String(message.user_id || message.sender_name || 'User');
    const displayName = String(message.sender_name || 'User');
    for (let index = 0; index < identity.length; index++) hash = ((hash << 5) - hash) + identity.charCodeAt(index);
    const color = message.role === 'teacher' ? '#2563eb' : palette[Math.abs(hash) % palette.length];
    const fallback = `https://ui-avatars.com/api/?name=${encodeURIComponent(displayName)}&background=${color.slice(1)}&color=ffffff&bold=true`;
    return `<img src="${escapeConversationText(message.sender_avatar || fallback)}" alt="${escapeConversationText(displayName)}" class="conversation-avatar" style="background:${color};" onerror="this.onerror=null;this.src='${fallback}';">`;
}

function formatLiveDuration(seconds) {
    const total = Math.max(0, Number(seconds) || 0);
    return Math.floor(total / 60) + ':' + String(total % 60).padStart(2, '0');
}

function updateLiveTypingIndicator(typingUsers) {
    const indicator = document.getElementById('liveTypingIndicator');
    const text = document.getElementById('liveTypingText');
    if (!indicator || !text) return;
    const names = (typingUsers || []).map(user => user.name).filter(Boolean);
    if (!names.length) {
        indicator.classList.remove('is-visible');
        return;
    }
    text.textContent = names.length === 1 ? names[0] + ' is typing...' : names.slice(0, 2).join(' and ') + ' are typing...';
    indicator.classList.add('is-visible');
}

async function loadConversation() {
    if (!conversationClassId) return;
    const response = await fetch('<?= url('api/live-conversation.php') ?>?class_id=' + conversationClassId);
    const data = await response.json();
    updateLiveTypingIndicator(data.typing_users || []);
    const box = document.getElementById('conversationMessages');
    if (!data.success || !data.messages || !data.messages.length) {
        box.innerHTML = '<div class="text-center text-muted small py-5">No messages yet. Ask your teacher a question.</div>';
        return;
    }
    box.innerHTML = data.messages.map(message => {
        const mediaPath = String(message.media_url || '').toLowerCase();
        const content = message.message_type === 'voice'
            ? `<div class="live-audio-message"><audio controls class="w-100" src="<?= rtrim(APP_URL, '/') ?>/` + escapeConversationText(message.media_url) + `"></audio><span class="live-audio-duration"><i class="bi bi-clock me-1"></i>${formatLiveDuration(message.duration_seconds)}</span></div>`
            : (message.message_type === 'video' || /\.(mp4|webm|mov|ogv)(\?|$)/.test(mediaPath))
                ? `<video controls playsinline class="live-chat-video" src="<?= rtrim(APP_URL, '/') ?>/` + escapeConversationText(message.media_url) + `"></video>`
            : message.message_type === 'image'
                ? `<a href="<?= rtrim(APP_URL, '/') ?>/` + escapeConversationText(message.media_url) + `" target="_blank" rel="noopener noreferrer"><img src="<?= rtrim(APP_URL, '/') ?>/` + escapeConversationText(message.media_url) + `" class="live-chat-image" alt="Picture shared by ${escapeConversationText(message.sender_name)}"></a>`
                : `<div>${escapeConversationText(message.message_text)}</div>`;
        const roleLabel = message.role === 'teacher' 
            ? '<span class="badge bg-primary text-white rounded-pill px-2 py-0.5 ms-1" style="font-size:0.65rem;"><i class="bi bi-patch-check-fill me-1"></i>Teacher</span>' 
            : '<span class="conversation-role">Student</span>';
        const mentionBadge = message.mention_user_name 
            ? `<div class="mb-1"><span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1" style="font-size:0.72rem;"><i class="bi bi-at me-0.5"></i>Mentioned <button type="button" class="mention-profile-trigger" data-profile-user-id="${Number(message.mention_user_id)}" data-profile-class-id="${conversationClassId}">${escapeConversationText(message.mention_user_name)}</button></span></div>` 
            : '';
        const ownMessage = Number(message.user_id) === conversationUserId;
        const actions = `<div class="conversation-actions"><button type="button" class="conversation-actions-trigger" aria-label="Message options" data-message-menu="${message.id}"><i class="bi bi-three-dots-vertical"></i></button><div class="conversation-actions-menu" id="message-menu-${message.id}"><button type="button" data-message-action="reply" data-message-id="${message.id}" data-message-text="${escapeConversationText(message.message_text || '')}" data-sender-name="${escapeConversationText(message.sender_name)}"><i class="bi bi-reply-fill"></i> Reply</button>${ownMessage && message.message_type === 'text' ? `<button type="button" data-message-action="edit" data-message-id="${message.id}" data-message-text="${escapeConversationText(message.message_text || '')}"><i class="bi bi-pencil-fill"></i> Edit</button>` : ''}${ownMessage ? `<button type="button" class="text-danger" data-message-action="delete" data-message-id="${message.id}"><i class="bi bi-trash-fill"></i> Delete</button>` : ''}</div></div>`;
        return `<div class="conversation-message ${message.role === 'teacher' ? 'conversation-message-teacher' : 'conversation-message-student'}" data-message-id="${message.id}"><div class="conversation-avatar-wrap">${conversationAvatar(message)}</div><div class="conversation-message-body"><div class="conversation-sender"><button type="button" class="live-profile-trigger" data-profile-user-id="${Number(message.user_id)}" data-profile-class-id="${conversationClassId}">${escapeConversationText(message.sender_name)}</button> ${roleLabel}<span class="conversation-time">${escapeConversationText(message.created_at)}</span></div><div class="conversation-bubble">${mentionBadge}${content}</div></div>${actions}</div>`;
    }).join('');
    box.scrollTop = box.scrollHeight;
    bindConversationMessageActions(box);
}

function bindConversationMessageActions(box) {
    let pressTimer;
    box.querySelectorAll('.conversation-message').forEach(message => {
        message.addEventListener('touchstart', () => { pressTimer = setTimeout(() => message.classList.add('actions-visible'), 550); }, {passive: true});
        ['touchend', 'touchmove', 'touchcancel'].forEach(eventName => message.addEventListener(eventName, () => clearTimeout(pressTimer), {passive: true}));
    });
    box.querySelectorAll('[data-message-menu]').forEach(button => button.addEventListener('click', event => {
        event.stopPropagation();
        const menu = document.getElementById('message-menu-' + button.dataset.messageMenu);
        box.querySelectorAll('.conversation-actions-menu.open').forEach(item => item.classList.remove('open'));
        menu.classList.toggle('open');
    }));
    box.querySelectorAll('[data-message-action]').forEach(button => button.addEventListener('click', () => handleConversationAction(button)));
}

async function handleConversationAction(button) {
    const action = button.dataset.messageAction;
    const formData = new FormData();
    formData.append('class_id', conversationClassId);
    formData.append('action', action + '_message');
    formData.append('message_id', button.dataset.messageId);
    if (action === 'reply') {
        const form = document.getElementById('conversationForm');
        form.querySelector('input[name="message_text"]').value = 'Reply to ' + button.dataset.senderName + ': ';
        form.querySelector('input[name="reply_to_message_id"]').value = button.dataset.messageId;
        form.querySelector('input[name="message_text"]').focus();
        return;
    }
    if (action === 'edit') {
        const editedText = window.prompt('Edit your message:', button.dataset.messageText);
        if (editedText === null || !editedText.trim()) return;
        formData.append('message_text', editedText);
    }
    if (action === 'delete' && !window.confirm('Delete this message?')) return;
    const response = await fetch('<?= url('api/live-conversation.php') ?>', {method: 'POST', body: formData});
    const data = await response.json();
    if (!data.success) { alert(data.message || 'Message action failed.'); return; }
    loadConversation();
}

async function autoMarkAttendance(classId) {
    if (!classId) return;
    try {
        const response = await fetch('<?= url('api/attendance.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_attendance', class_id: classId })
        });
        const data = await response.json();
        if (data.success) {
            const chk = document.getElementById('attendanceCheck_' + classId);
            if (chk) { chk.checked = true; chk.disabled = true; }
            const label = document.getElementById('label-text-' + classId);
            if (label) label.textContent = 'Attendance Verified for this Live Session';
            const sub = document.getElementById('subtext-' + classId);
            if (sub) sub.textContent = 'Marked as Present on ' + new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            let badge = document.getElementById('badge-attended-' + classId);
            if (!badge) {
                const card = document.getElementById('live-card-' + classId);
                const badgeContainer = card ? card.querySelector('.d-flex.align-items-center.gap-2.mb-2') : null;
                if (badgeContainer) {
                    const newBadge = document.createElement('span');
                    newBadge.id = 'badge-attended-' + classId;
                    newBadge.className = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 fw-bold';
                    newBadge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Present (Marked)';
                    badgeContainer.appendChild(newBadge);
                }
            }
        }
    } catch (e) {
        console.warn('Auto attendance failed', e);
    }
}

function openConversation(classId, title) {
    conversationClassId = classId;
    autoMarkAttendance(classId);
    document.getElementById('conversationTitle').textContent = title;
    document.getElementById('conversationMessages').innerHTML = '<div class="text-center text-muted py-5">Loading conversation...</div>';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('conversationModal')).show();
    loadConversation();
    clearInterval(conversationTimer);
    conversationTimer = setInterval(loadConversation, 2000);
}

document.getElementById('conversationModal').addEventListener('hidden.bs.modal', () => {
    sendTypingState(false);
    clearInterval(conversationTimer);
    conversationClassId = 0;
});

async function openLiveProfile(userId, classId) {
    const body = document.getElementById('liveProfileBody');
    body.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading profile</span></div>';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('liveProfileModal')).show();
    try {
        const response = await fetch('<?= url('api/live-profile.php') ?>?class_id=' + encodeURIComponent(classId) + '&user_id=' + encodeURIComponent(userId), { cache: 'no-store' });
        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'Profile unavailable');
        const profile = data.profile;
        const fallback = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(profile.name) + '&background=1e40af&color=ffffff&bold=true';
        body.innerHTML = `<img src="${escapeConversationText(profile.avatar || fallback)}" class="rounded-circle live-profile-avatar mb-3" alt="${escapeConversationText(profile.name)}" onerror="this.onerror=null;this.src='${fallback}';"><h4 class="fw-bold mb-1">${escapeConversationText(profile.name)}</h4><span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 mb-3">${escapeConversationText(profile.role)}</span><p class="text-primary fw-semibold small mb-2">${escapeConversationText(profile.headline || '')}</p><p class="text-muted small mb-0">${escapeConversationText(profile.bio || '')}</p>`;
    } catch (error) {
        body.innerHTML = '<div class="text-danger"><i class="bi bi-exclamation-circle me-1"></i> Profile could not be loaded.</div>';
    }
}

document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-profile-user-id]');
    if (trigger) openLiveProfile(trigger.dataset.profileUserId, trigger.dataset.profileClassId);
});

document.getElementById('conversationForm').addEventListener('submit', async function(event) {
    event.preventDefault();
    const formData = new FormData(this);
    formData.append('class_id', conversationClassId);
    formData.append('message_type', 'text');
    const response = await fetch('<?= url('api/live-conversation.php') ?>', { method: 'POST', body: formData });
    const data = await response.json();
    if (!data.success) { alert(data.message || 'Could not send question.'); return; }
    this.reset();
    sendTypingState(false);
    loadConversation();
});

let typingStopTimer = null;
async function sendTypingState(isTyping) {
    if (!conversationClassId) return;
    const formData = new FormData();
    formData.append('class_id', conversationClassId);
    formData.append('action', 'typing');
    formData.append('is_typing', isTyping ? '1' : '0');
    await fetch('<?= url('api/live-conversation.php') ?>', { method: 'POST', body: formData }).catch(() => {});
}

document.querySelector('#conversationForm input[name="message_text"]')?.addEventListener('input', function() {
    sendTypingState(this.value.trim().length > 0);
    clearTimeout(typingStopTimer);
    typingStopTimer = setTimeout(() => sendTypingState(false), 1800);
});

document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.attendance-checkbox');
    checkboxes.forEach(chk => {
        chk.addEventListener('change', async function() {
            if (!this.checked) return; // Only allow marking present

            const classId = this.dataset.classId;
            const labelText = document.getElementById('label-text-' + classId);
            const subtext = document.getElementById('subtext-' + classId);
            const card = document.getElementById('live-card-' + classId);

            // Optimistic UI update
            if (labelText) labelText.textContent = 'Recording attendance...';
            this.disabled = true;

            try {
                const response = await fetch('<?= url('api/attendance.php') ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'mark_attendance',
                        class_id: classId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    if (labelText) labelText.textContent = 'Attendance Verified for this Live Session';
                    if (subtext) subtext.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> You are marked Present!</span>';

                    // Add badge to header if not present
                    let badge = document.getElementById('badge-attended-' + classId);
                    if (!badge && card) {
                        const headerArea = card.querySelector('.d-flex.align-items-center.gap-2');
                        if (headerArea) {
                            const newBadge = document.createElement('span');
                            newBadge.id = 'badge-attended-' + classId;
                            newBadge.className = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 fw-bold';
                            newBadge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Present (Marked)';
                            headerArea.appendChild(newBadge);
                        }
                    }

                    // Toast notification
                    if (typeof showToast === 'function') {
                        showToast('success', data.message || 'Attendance marked successfully!');
                    }
                } else {
                    alert(data.message || 'Could not mark attendance.');
                    this.checked = false;
                    this.disabled = false;
                    if (labelText) labelText.textContent = 'Click Checkbox to Mark My Attendance';
                }
            } catch (err) {
                console.error(err);
                alert('Network error. Please try again.');
                this.checked = false;
                this.disabled = false;
                if (labelText) labelText.textContent = 'Click Checkbox to Mark My Attendance';
            }
        });
    });
});
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
