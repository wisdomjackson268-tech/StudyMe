<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/live_classes.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';
require_once BASE_PATH . '/includes/functions/live_conversations.php';

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

$targetClassId = (int)($_GET['class_id'] ?? 0);
$upcomingLiveClasses = get_student_upcoming_live_classes($studentId, $targetClassId, true);
$pastLiveClasses     = get_student_past_live_classes($studentId, true);

if ($targetClassId > 0) {
    $found = false;
    foreach ($upcomingLiveClasses as $u) {
        if ((int)$u['id'] === $targetClassId) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $targetedClass = get_live_class_by_id($targetClassId);
        if ($targetedClass && get_live_conversation_access($targetClassId, $studentId, current_user_role())) {
            array_unshift($upcomingLiveClasses, $targetedClass);
        }
    }
}

$activeLiveClass = null;
foreach ($upcomingLiveClasses as $c) {
    if (($c['status'] ?? '') === 'live') {
        $activeLiveClass = $c;
        break;
    }
}

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

/* Keep live session content balanced between wide and narrow viewports. */
.live-hero-banner { border-radius: 1rem !important; }
.live-hero-banner .card-body { padding: 2rem !important; }
.live-hero-banner .col-lg-4 > div { max-width: 100%; }
.live-session-card { display: flex; flex-direction: column; }
.teacher-mentor-card { min-width: 0; }
.schedule-tile { min-width: 0; }
.schedule-info { min-width: 0; }
.schedule-val { overflow-wrap: anywhere; }
.live-session-card .live-actions { align-items: stretch; }
.live-session-card .live-actions .btn { min-width: 0; }

@media (min-width: 768px) and (max-width: 1199.98px) {
    .live-hero-banner .card-body { padding: 1.5rem !important; }
    .live-session-card { padding: 1.25rem !important; }
    .teacher-mentor-card { gap: .75rem !important; }
    .teacher-mentor-card .btn { padding-inline: .7rem; }
    .session-schedule-grid { grid-template-columns: minmax(0, 1.2fr) minmax(0, .8fr); }
}

/* Responsive adjustments */
@media (max-width: 767.98px) {
    .live-page-header { align-items: stretch !important; margin-top: 0.35rem; }
    .live-page-header h1 { font-size: 1.28rem; }
    .live-page-header p { line-height: 1.45; }
    .live-hero-banner { margin-inline: 0; }
    .live-hero-banner .card-body { padding: 1.25rem !important; }
    .live-hero-banner h2 { font-size: 1.3rem; }
    .live-hero-banner .col-lg-4 { text-align: left !important; }
    .live-hero-banner .col-lg-4 > div { display: flex !important; width: 100%; }
    .live-hero-banner .col-lg-8 > .d-flex:last-child { align-items: stretch !important; }
    .live-hero-banner .col-lg-8 > .d-flex:last-child > button { min-height: 46px; }
    .live-hero-banner #hero-verify-btn { white-space: normal; }
    .live-session-card { padding: 1.15rem !important; }
    .live-session-card h4 { font-size: 1.15rem !important; line-height: 1.35; }
    .live-session-card .badge { font-size: .7rem; }
    .live-session-card .live-actions { display: grid !important; grid-template-columns: minmax(0, 1fr) auto; }
    .live-session-card .live-actions > * { width: auto; }
    .live-session-card .live-actions .btn-ask-chat { min-width: 5rem; padding-inline: .85rem !important; }
    .teacher-mentor-card { padding: .75rem !important; }
    .teacher-mentor-card > .d-flex { min-width: 0; gap: .6rem !important; }
    .teacher-mentor-card .teacher-mentor-avatar { width: 42px; height: 42px; }
    .teacher-mentor-card .text-muted { flex-wrap: wrap; }
    .attendance-verified-card,
    .attendance-prompt-card { align-items: flex-start !important; gap: .65rem !important; }
    .session-schedule-grid { grid-template-columns: minmax(0, 1fr); }
    .live-tabs-nav-container { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); width: 100%; }
    .live-tabs-nav-container .nav-item { min-width: 0; }
    .live-tabs-nav-container .nav-link { width: 100%; min-height: 44px; padding: .55rem .45rem !important; font-size: .78rem; line-height: 1.25; white-space: normal; justify-content: center; text-align: center; }
    #conversationModal .modal-dialog { width: auto; max-width: none; margin: .5rem; }
    #conversationModal .modal-content { max-height: calc(100dvh - 1rem) !important; border-radius: 1rem !important; }
    #conversationModal .modal-header { padding: .85rem 1rem !important; }
    #conversationModal .modal-header .btn-close { flex: 0 0 auto; }
    #conversationModal .modal-body { padding: 1rem !important; }
    #conversationMessages { height: min(52dvh, 360px) !important; min-height: 180px; }
    .conversation-message-body { max-width: calc(100% - 4.2rem); }
    .conversation-message { gap: .4rem; }
    .conversation-avatar { width: 36px; height: 36px; }
    .conversation-sender { overflow-wrap: anywhere; }
    .conversation-time { display: block; margin-left: 0; }
    #conversationForm .live-message-composer {
        display: grid !important;
        grid-template-columns: minmax(0, 1fr) 48px;
        gap: .5rem;
        overflow: visible !important;
        border: 0 !important;
        border-radius: 0 !important;
    }
    #liveMessageInput {
        width: 100%;
        min-width: 0;
        min-height: 48px;
        padding: .7rem .85rem !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 14px !important;
        background: #f8fafc;
        font-size: 16px !important;
        line-height: 1.35;
        box-shadow: none !important;
    }
    #liveMessageInput:focus {
        border-color: #2563eb !important;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .14) !important;
    }
    #liveMessageInput::placeholder { color: #64748b; opacity: 1; }
    #liveSendBtn {
        width: 48px;
        min-height: 48px;
        justify-content: center;
        padding: 0 !important;
        border-radius: 14px !important;
    }
    #liveSendBtn .live-send-label { display: none; }
    #liveSendBtn i { font-size: 1rem !important; }
    #conversationForm .live-composer-meta {
        display: flex !important;
        align-items: flex-start !important;
        flex-direction: column;
        gap: .2rem;
        margin-top: .45rem !important;
        padding-inline: .15rem !important;
    }
    #conversationForm .live-composer-meta small { font-size: .68rem !important; line-height: 1.35; }
}

@media (max-width: 359.98px) {
    .live-session-card .live-actions { grid-template-columns: 1fr; }
    .live-session-card .live-actions .btn-ask-chat { width: 100%; }
    .live-hero-banner .col-lg-8 > .d-flex:last-child { flex-direction: column; }
    .live-hero-banner .col-lg-8 > .d-flex:last-child > * { width: 100%; justify-content: center; }
    .teacher-mentor-card .btn { padding-inline: .55rem !important; }
    button.position-fixed[data-bs-target="#aiAssistantDrawer"] { display: none !important; }
}

@media (prefers-reduced-motion: reduce) {
    .live-pulse-dot { animation: none; }
    .live-session-card,
    .btn-join-live,
    .btn-ask-chat { transition: none; }
}
</style>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 live-page-header">
    <div>
        <h1 class="h3 fw-bold mb-0"><i class="bi bi-broadcast text-danger me-2"></i>Live classes</h1>
    </div>
</div>

<?php if ($activeLiveClass): 
    $activeAvatar = function_exists('get_teacher_avatar_url') ? get_teacher_avatar_url($activeLiveClass['teacher_avatar'] ?? null, $activeLiveClass['teacher_name'], (int)($activeLiveClass['teacher_id'] ?? 0)) : (function_exists('get_avatar_url') ? get_avatar_url($activeLiveClass['teacher_avatar'] ?? null, $activeLiveClass['teacher_name']) : '');
    $isHeroAttended = !empty($activeLiveClass['is_attended']);
?>
    <div class="card rounded-4 live-hero-banner mb-4 text-white overflow-hidden shadow-lg" id="live-hero-card" style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 55%, #172554 100%); border: 1px solid rgba(99, 102, 241, 0.35);">
        <div class="card-body p-4 p-md-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                        <span class="badge bg-danger text-white rounded-pill px-3 py-1.5 fw-bold d-inline-flex align-items-center gap-2 shadow-sm" style="font-size: 0.78rem; letter-spacing: 0.04em;">
                            <span class="live-pulse-dot" style="width: 8px; height: 8px; background-color: #fff; border-radius: 50%; display: inline-block;"></span>
                            LIVE
                        </span>
                        <span class="badge bg-white bg-opacity-10 text-white border border-white border-opacity-25 rounded-pill px-3 py-1.5 small">
                            <?= htmlspecialchars($activeLiveClass['academic_level'] ?? '100 Level') ?>
                        </span>
                        <span class="badge bg-primary text-white rounded-pill px-3 py-1.5 small">
                            <i class="bi bi-journal-bookmark-fill me-1"></i> <?= htmlspecialchars($activeLiveClass['course_title']) ?>
                        </span>
                    </div>

                    <h2 class="h3 fw-bold text-white mb-2" style="line-height: 1.35;"><?= htmlspecialchars($activeLiveClass['title']) ?></h2>
                    
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <button type="button" class="btn btn-danger btn-lg rounded-pill px-4 py-2.5 fw-bold d-inline-flex align-items-center gap-2 shadow-lg" onclick="openConversation(<?= (int)$activeLiveClass['id'] ?>, '<?= htmlspecialchars(addslashes($activeLiveClass['title']), ENT_QUOTES) ?>')" aria-label="Join live class">
                            <i class="bi bi-box-arrow-in-right"></i><span>Join live</span>
                        </button>
                        
                        <?php if ($isHeroAttended): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2 fw-bold d-inline-flex align-items-center gap-1.5" id="hero-badge-attended">
                                <i class="bi bi-patch-check-fill"></i> Present
                            </span>
                        <?php else: ?>
                            <button type="button" class="btn btn-outline-light rounded-pill px-3 py-2 fw-semibold small" id="hero-verify-btn" onclick="autoMarkAttendance(<?= (int)$activeLiveClass['id'] ?>)">
                                <i class="bi bi-person-check-fill me-1"></i> Mark attendance
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-lg-4 text-lg-end">
                    <div class="d-inline-flex align-items-center gap-3 p-3 rounded-4 text-start shadow-sm" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); backdrop-filter: blur(8px);">
                        <div class="position-relative flex-shrink-0">
                            <img src="<?= e($activeAvatar) ?>" alt="<?= htmlspecialchars($activeLiveClass['teacher_name']) ?>" class="rounded-circle" style="width: 54px; height: 54px; object-fit: cover; border: 2px solid #3b82f6;" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($activeLiveClass['teacher_name']) ?>&background=2563eb&color=ffffff&bold=true';">
                            <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-2 border-white rounded-circle" title="Instructor Live"></span>
                        </div>
                        <div>
                            <div class="text-white fw-bold d-flex align-items-center gap-1">
                                <span><?= htmlspecialchars($activeLiveClass['teacher_name']) ?></span>
                                <i class="bi bi-patch-check-fill text-info" style="font-size: 0.85rem;" title="Verified Instructor"></i>
                            </div>
                            <div class="text-success small fw-semibold mt-1"><i class="bi bi-people-fill me-1"></i><?= (int)($activeLiveClass['total_attendees'] ?? 0) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="mb-4">
    <div class="nav live-tabs-nav-container" id="liveTabs" role="tablist">
        <div class="nav-item" role="presentation">
            <button class="nav-link active position-relative" id="upcoming-tab" data-bs-toggle="pill" data-bs-target="#upcoming" type="button" role="tab">
                <i class="bi bi-calendar-event"></i>
                <span>Live (<?= count($upcomingLiveClasses) ?>)</span>
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
                <span>Replays (<?= count($pastLiveClasses) ?>)</span>
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
                <h4 class="fw-bold mb-2">No live classes</h4>
                <p class="text-muted col-lg-6 mx-auto mb-3">Live classes for your courses will appear here.</p>
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
                                        <div class="text-muted small mt-1">Instructor</div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-light border rounded-circle d-inline-flex align-items-center justify-content-center text-secondary flex-shrink-0 shadow-2xs" style="width: 38px; height: 38px;" aria-label="View instructor profile" title="Instructor profile" onclick="openLiveProfile(<?= (int)($lc['teacher_user_id'] ?? 0) ?>, <?= (int)$lc['id'] ?>)">
                                    <i class="bi bi-person-lines-fill"></i>
                                </button>
                            </div>

                            <!-- Schedule & Duration Grid -->
                            <div class="session-schedule-grid mb-3">
                                <div class="schedule-tile">
                                    <div class="schedule-icon-wrap"><i class="bi bi-calendar3"></i></div>
                                    <div class="schedule-info">
                                        <span class="schedule-label">Date</span>
                                        <strong class="schedule-val"><?= date('D, M j, Y @ g:i A', strtotime($lc['scheduled_at'])) ?></strong>
                                    </div>
                                </div>
                                <div class="schedule-tile">
                                    <div class="schedule-icon-wrap duration"><i class="bi bi-clock-history"></i></div>
                                    <div class="schedule-info">
                                        <span class="schedule-label">Length</span>
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
                                            <div class="fw-bold text-dark" style="font-size: 0.9rem;">Attendance marked</div>
                                            <div class="text-muted small" style="font-size: 0.78rem;"><?= date('g:i A', strtotime($lc['student_attended_at'] ?? 'now')) ?></div>
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
                                                <i class="bi bi-person-check-fill text-danger me-1"></i> Attendance
                                            </label>
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
                                            <span>Join live</span>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-light border flex-grow-1 fw-bold rounded-pill py-2.5 text-muted" disabled>
                                            <i class="bi bi-hourglass-split me-1"></i> Waiting for Teacher
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-ask-chat fw-bold rounded-pill px-4 py-2.5 d-inline-flex align-items-center justify-content-center gap-1.5" onclick="openConversation(<?= (int)$lc['id'] ?>, '<?= htmlspecialchars(addslashes($lc['title']), ENT_QUOTES) ?>')">
                                        <i class="bi bi-chat-dots-fill"></i>
                                        <span>Chat</span>
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
                <h4 class="fw-bold mb-2">No replays yet</h4>
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

<div class="modal fade" id="conversationModal" tabindex="-1" aria-labelledby="conversationModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-2xl rounded-4 overflow-hidden" style="max-height: 92vh;">
            <!-- Modal Header -->
            <div class="modal-header px-4 py-3 bg-dark text-white border-0 d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 60%, #0f172a 100%) !important;">
                <div class="min-w-0 flex-grow-1 me-3">
                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                        <span class="badge bg-danger text-white rounded-pill px-2.5 py-1 fw-bold d-inline-flex align-items-center gap-1.5" style="font-size: 0.7rem; letter-spacing: 0.04em;">
                            <span class="live-pulse-dot" style="width: 7px; height: 7px; background:#fff; border-radius:50%; display:inline-block;"></span>
                            <span>LIVE</span>
                        </span>
                        <span id="liveModalAttendeeBadge" class="badge bg-white bg-opacity-10 text-white rounded-pill px-2.5 py-1 small border border-white border-opacity-25" style="font-size: 0.7rem;" aria-label="Participants">
                            <i class="bi bi-people-fill text-info me-1"></i><span id="liveModalAttendeeCount">0</span>
                        </span>
                    </div>
                    <h5 class="modal-title fw-bold text-white text-truncate mb-0" id="conversationModalLabel" style="font-size: 1.15rem;">Live Class Session</h5>
                    <small id="conversationTitle" class="text-white-50 text-truncate d-block" style="font-size: 0.78rem;"></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-0 d-flex flex-column" style="background: #f8fafc; min-height: 480px;">
                <!-- Live Classroom Broadcast Stage / Media Bar -->
                <div id="liveClassroomStage" class="p-3 border-bottom" style="background: linear-gradient(180deg, #ffffff 0%, #f1f5f9 100%);">
                    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                        <div class="d-flex align-items-center gap-2.5 min-w-0">
                            <div class="position-relative flex-shrink-0">
                                <img id="liveStageTeacherAvatar" src="https://ui-avatars.com/api/?name=Teacher&background=2563eb&color=ffffff&bold=true" class="rounded-circle shadow-sm" style="width: 42px; height: 42px; object-fit: cover; border: 2px solid #3b82f6;" alt="Teacher">
                                <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-2 border-white rounded-circle"></span>
                            </div>
                            <div class="min-w-0">
                                <div class="fw-bold text-dark text-truncate d-flex align-items-center gap-1" style="font-size: 0.88rem;">
                                    <span id="liveStageTeacherName">Instructor Live</span>
                                    <i class="bi bi-patch-check-fill text-primary" style="font-size: 0.82rem;"></i>
                                </div>
                                <div class="text-muted small d-flex align-items-center gap-2" style="font-size: 0.74rem;">
                                    <span id="liveStageCourseName">Course Stream</span>
                                </div>
                            </div>
                        </div>

                        <!-- In-Modal Attendance Button / Badge -->
                        <div class="d-flex align-items-center gap-2" id="liveModalAttendanceArea">
                            <button type="button" class="btn btn-sm btn-outline-success fw-bold rounded-pill px-3 py-1.5 shadow-2xs d-inline-flex align-items-center gap-1.5" id="liveModalMarkAttendanceBtn" onclick="markAttendanceInsideClassroom()">
                                <i class="bi bi-person-check-fill"></i>
                                <span>Attendance</span>
                            </button>
                        </div>
                    </div>

                    <!-- Highlighted Latest Broadcast Media / Lesson Note -->
                    <div id="liveStageLatestMedia" class="mt-2.5 pt-2 border-top" style="display: none;"></div>
                </div>

                <!-- Chat Messages Feed -->
                <div id="conversationMessages" class="flex-grow-1 p-3 p-md-4" style="height: 360px; overflow-y: auto; background: #ffffff;">
                    <div class="text-center text-muted py-5 small">
                        <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                        <div>Joining live class...</div>
                    </div>
                </div>

                <!-- Interactive Question / Chat Form -->
                <div class="p-3 bg-white border-top shadow-sm">
                    <form id="conversationForm" class="m-0">
                        <input type="hidden" name="reply_to_message_id" id="replyToMessageId" value="">
                        
                        <!-- Active Reply Pill -->
                        <div id="activeReplyBadge" class="mb-2" style="display: none;">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1.5 d-inline-flex align-items-center gap-2" style="font-size: 0.76rem;">
                                <i class="bi bi-reply-fill"></i> Replying to <strong id="activeReplySender">Instructor</strong>: <span id="activeReplyPreview" class="text-truncate" style="max-width: 260px;"></span>
                                <button type="button" class="btn-close ms-1" style="font-size: 0.55rem;" onclick="cancelReply()" aria-label="Cancel reply"></button>
                            </span>
                        </div>

                        <div class="input-group input-group-lg rounded-pill overflow-hidden border live-message-composer">
                            <input type="text" name="message_text" id="liveMessageInput" class="form-control border-0 ps-3" style="font-size: 0.9rem;" placeholder="Write a message..." maxlength="2000" autocomplete="off" required>
                            <button class="btn btn-primary fw-bold px-4 d-inline-flex align-items-center gap-1.5" type="submit" id="liveSendBtn" aria-label="Send message" title="Send message">
                                <span class="live-send-label">Send</span>
                                <i class="bi bi-send-fill" style="font-size: 0.85rem;"></i>
                            </button>
                        </div>

                        <div id="liveTypingIndicator" class="live-typing-indicator mt-2" role="status" aria-live="polite">
                            <span class="live-typing-dots"><span></span><span></span><span></span></span>
                            <span id="liveTypingText">Instructor is speaking...</span>
                        </div>

                    </form>
                </div>
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
.conversation-avatar { width: 42px; height: 42px; flex-shrink: 0; aspect-ratio: 1/1; border-radius: 50%; object-fit: cover; border: 3px solid #fff; box-shadow: 0 4px 12px rgba(15, 23, 42, .14); background: #f8fafc; }
.conversation-message-teacher .conversation-avatar { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25), 0 4px 12px rgba(37, 99, 235, .2); }
.conversation-message-body { max-width: min(80%, 620px); }
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
.conversation-bubble { background: #fff; border: 1px solid #dbeafe; border-left: 4px solid #2563eb; border-radius: .25rem .8rem .8rem .8rem; color: #1e293b; padding: .75rem .95rem; text-align: left; overflow-wrap: anywhere; box-shadow: 0 2px 8px rgba(15, 23, 42, .05); }
.conversation-message-student .conversation-bubble { background: #ecfdf5; border-color: #a7f3d0; border-left: 1px solid #a7f3d0; border-right: 4px solid #059669; border-radius: .8rem .25rem .8rem .8rem; }
.conversation-bubble audio { max-width: 100%; height: 38px; }
.live-audio-message { min-width: min(100%, 290px); }
.live-audio-duration { display: inline-block; margin-top: .25rem; color: #64748b; font-size: .7rem; font-weight: 700; }
.live-chat-image { display: block; width: min(100%, 340px); max-height: 280px; object-fit: cover; border-radius: .75rem; border: 1px solid #dbeafe; box-shadow: 0 5px 16px rgba(15,23,42,.1); }
.live-chat-video { display: block; width: min(100%, 380px); max-height: 280px; border-radius: .75rem; background: #0f172a; border: 1px solid rgba(0,0,0,0.1); }
.live-video-player-wrap { position: relative; max-width: 100%; }

/* Interactive Voice Note Player Card */
.live-audio-player-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.55rem 0.85rem;
    border-radius: 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    min-width: 250px;
    max-width: 100%;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.live-audio-player-card.playing {
    border-color: #3b82f6;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
    background: #eff6ff;
}
.live-audio-play-btn {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: none;
    background: #2563eb;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);
    transition: transform 0.15s ease, background 0.15s ease;
}
.live-audio-play-btn:hover {
    transform: scale(1.06);
    background: #1d4ed8;
}
.live-audio-play-btn.is-playing {
    background: #dc2626;
    box-shadow: 0 4px 10px rgba(220, 38, 38, 0.3);
}
.live-audio-track {
    width: 100%;
    height: 7px;
    border-radius: 7px;
    background: #cbd5e1;
    position: relative;
    cursor: pointer;
    overflow: hidden;
}
.live-audio-progress {
    height: 100%;
    background: linear-gradient(90deg, #2563eb, #3b82f6);
    border-radius: 7px;
    transition: width 0.08s linear;
}
.live-audio-time, .live-audio-total {
    font-size: 0.72rem;
    color: #64748b;
    font-weight: 600;
}
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
.live-typing-indicator { display: none; align-items: center; gap: .5rem; min-height: 26px; padding: .3rem .65rem; border-radius: .75rem; background: #eff6ff; color: #1d4ed8; font-size: .75rem; font-weight: 700; }
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
    text.textContent = names.length === 1 ? names[0] + ' is speaking / typing...' : names.slice(0, 2).join(' and ') + ' are typing...';
    indicator.classList.add('is-visible');
}

function cancelReply() {
    document.getElementById('replyToMessageId').value = '';
    document.getElementById('activeReplyBadge').style.display = 'none';
}

async function markAttendanceInsideClassroom() {
    if (!conversationClassId) return;
    const btn = document.getElementById('liveModalMarkAttendanceBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Verifying...';
    }
    await autoMarkAttendance(conversationClassId);
}

// Track rendered message IDs and their text for incremental DOM updates
let _renderedMessageMap = {};

function buildMessageHtml(message) {
    const mediaPath = String(message.media_url || '').toLowerCase();
    const content = message.message_type === 'voice'
        ? `<div class="live-audio-message"><audio controls preload="metadata" class="w-100" src="<?= rtrim(APP_URL, '/') ?>/` + escapeConversationText(message.media_url) + `"></audio><span class="live-audio-duration"><i class="bi bi-clock me-1"></i>${formatLiveDuration(message.duration_seconds)}</span></div>`
        : (message.message_type === 'video' || /\.(mp4|webm|mov|ogv)(\?|$)/.test(mediaPath))
            ? `<video controls playsinline preload="metadata" class="live-chat-video" src="<?= rtrim(APP_URL, '/') ?>/` + escapeConversationText(message.media_url) + `"></video>`
        : message.message_type === 'image'
            ? `<a href="<?= rtrim(APP_URL, '/') ?>/` + escapeConversationText(message.media_url) + `" target="_blank" rel="noopener noreferrer"><img src="<?= rtrim(APP_URL, '/') ?>/` + escapeConversationText(message.media_url) + `" class="live-chat-image" alt="Picture shared by ${escapeConversationText(message.sender_name)}"></a>`
            : `<div>${escapeConversationText(message.message_text)}</div>`;

    const roleLabel = message.role === 'teacher'
        ? '<span class="badge bg-primary text-white rounded-pill px-2 py-0.5 ms-1" style="font-size:0.65rem;"><i class="bi bi-patch-check-fill me-1"></i>Instructor</span>'
        : '<span class="conversation-role">Student</span>';

    const mentionBadge = message.mention_user_name
        ? `<div class="mb-1"><span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1" style="font-size:0.72rem;"><i class="bi bi-at me-0.5"></i>Mentioned <button type="button" class="mention-profile-trigger" data-profile-user-id="${Number(message.mention_user_id)}" data-profile-class-id="${conversationClassId}">${escapeConversationText(message.mention_user_name)}</button></span></div>`
        : '';

    const ownMessage = Number(message.user_id) === conversationUserId;
    const actions = `<div class="conversation-actions"><button type="button" class="conversation-actions-trigger" aria-label="Message options" data-message-menu="${message.id}"><i class="bi bi-three-dots-vertical"></i></button><div class="conversation-actions-menu" id="message-menu-${message.id}"><button type="button" data-message-action="reply" data-message-id="${message.id}" data-message-text="${escapeConversationText(message.message_text || '')}" data-sender-name="${escapeConversationText(message.sender_name)}"><i class="bi bi-reply-fill"></i> Reply</button>${ownMessage && message.message_type === 'text' ? `<button type="button" data-message-action="edit" data-message-id="${message.id}" data-message-text="${escapeConversationText(message.message_text || '')}"><i class="bi bi-pencil-fill"></i> Edit</button>` : ''}${ownMessage ? `<button type="button" class="text-danger" data-message-action="delete" data-message-id="${message.id}"><i class="bi bi-trash-fill"></i> Delete</button>` : ''}</div></div>`;

    return `<div class="conversation-message ${message.role === 'teacher' ? 'conversation-message-teacher' : 'conversation-message-student'}" data-message-id="${message.id}"><div class="conversation-avatar-wrap">${conversationAvatar(message)}</div><div class="conversation-message-body"><div class="conversation-sender"><button type="button" class="live-profile-trigger" data-profile-user-id="${Number(message.user_id)}" data-profile-class-id="${conversationClassId}">${escapeConversationText(message.sender_name)}</button> ${roleLabel}<span class="conversation-time">${escapeConversationText(message.created_at)}</span></div><div class="conversation-bubble">${mentionBadge}${content}</div></div>${actions}</div>`;
}

function isMediaPlaying(container) {
    if (!container) return false;
    const audios = container.querySelectorAll('audio');
    const videos = container.querySelectorAll('video');
    for (const a of audios) { if (!a.paused) return true; }
    for (const v of videos) { if (!v.paused) return true; }
    return false;
}

async function loadConversation() {
    if (!conversationClassId) return;
    const box = document.getElementById('conversationMessages');
    try {
        const response = await fetch('<?= url('api/live-conversation.php') ?>?class_id=' + encodeURIComponent(conversationClassId), {cache: 'no-store'});
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Could not join this live conversation.');
        }

        // Update Live Header Details
        if (data.live_class) {
            const lc = data.live_class;
            document.getElementById('conversationModalLabel').textContent = lc.title || 'Live Class Session';
            document.getElementById('conversationTitle').textContent = (lc.course_title ? lc.course_title + ' • ' : '') + 'Instructor: ' + (lc.teacher_name || 'Assigned Teacher');
            document.getElementById('liveStageTeacherName').textContent = lc.teacher_name || 'Instructor';
            document.getElementById('liveStageCourseName').textContent = lc.course_title || 'Enrolled Course';

            if (lc.teacher_avatar) {
                document.getElementById('liveStageTeacherAvatar').src = lc.teacher_avatar;
            }
            if (lc.total_attendees || lc.attended_students) {
                const count = Math.max(1, Number(lc.total_attendees || lc.attended_students || 1));
                document.getElementById('liveModalAttendeeCount').textContent = count;
            }
        }

        updateLiveTypingIndicator(data.typing_users || []);

        if (!data.messages || !data.messages.length) {
            box.innerHTML = '<div class="text-center text-muted small py-5"><i class="bi bi-chat-heart text-primary fs-3 d-block mb-2"></i>You have joined the live classroom. Type below to ask your instructor a question!</div>';
            _renderedMessageMap = {};
            return;
        }

        // --- Stage Media: only update if nothing is currently playing ---
        const stageMediaContainer = document.getElementById('liveStageLatestMedia');
        if (stageMediaContainer && !isMediaPlaying(stageMediaContainer)) {
            let latestMediaHtml = '';
            let latestMediaId = '';
            for (let i = data.messages.length - 1; i >= 0; i--) {
                const m = data.messages[i];
                if (m.role === 'teacher' && (m.message_type === 'voice' || m.message_type === 'video' || m.message_type === 'image')) {
                    const mediaPath = String(m.media_url || '').toLowerCase();
                    latestMediaId = String(m.id);
                    if (m.message_type === 'voice') {
                        latestMediaHtml = `<div class="d-flex align-items-center gap-2 p-2 rounded-3 bg-primary-subtle border border-primary-subtle"><i class="bi bi-mic-fill text-danger fs-5"></i><div class="flex-grow-1"><small class="fw-bold text-primary d-block">Instructor Live Voice Broadcast</small><audio controls preload="metadata" class="w-100" src="<?= rtrim(APP_URL, '/') ?>/${escapeConversationText(m.media_url)}"></audio></div></div>`;
                    } else if (m.message_type === 'video' || /\.(mp4|webm|mov|ogv)(\?|$)/.test(mediaPath)) {
                        latestMediaHtml = `<div class="p-2 rounded-3 bg-dark text-white"><div class="d-flex align-items-center justify-content-between mb-1"><small class="fw-bold text-info"><i class="bi bi-camera-video-fill me-1"></i>Instructor Video Demonstration</small></div><video controls playsinline preload="metadata" class="w-100 rounded" style="max-height: 220px;" src="<?= rtrim(APP_URL, '/') ?>/${escapeConversationText(m.media_url)}"></video></div>`;
                    } else if (m.message_type === 'image') {
                        latestMediaHtml = `<div class="p-2 rounded-3 bg-white border"><small class="fw-bold text-dark d-block mb-1"><i class="bi bi-image me-1"></i>Shared Whiteboard Diagram</small><a href="<?= rtrim(APP_URL, '/') ?>/${escapeConversationText(m.media_url)}" target="_blank"><img src="<?= rtrim(APP_URL, '/') ?>/${escapeConversationText(m.media_url)}" class="rounded w-100" style="max-height: 180px; object-fit: contain;" alt="Diagram"></a></div>`;
                    }
                    break;
                }
            }
            // Only re-render stage media if it changed
            if (latestMediaHtml && stageMediaContainer.dataset.mediaId !== latestMediaId) {
                stageMediaContainer.innerHTML = latestMediaHtml;
                stageMediaContainer.dataset.mediaId = latestMediaId;
                stageMediaContainer.style.display = 'block';
            } else if (!latestMediaHtml) {
                stageMediaContainer.innerHTML = '';
                stageMediaContainer.dataset.mediaId = '';
                stageMediaContainer.style.display = 'none';
            }
        }

        // --- Incremental DOM Updates for Chat Messages ---
        const wasAtBottom = (box.scrollHeight - box.scrollTop - box.clientHeight) < 60;
        const incomingIds = new Set(data.messages.map(m => String(m.id)));
        const existingIds = new Set(Object.keys(_renderedMessageMap));

        // 1. Remove deleted messages (only if their media isn't playing)
        for (const id of existingIds) {
            if (!incomingIds.has(id)) {
                const el = box.querySelector(`[data-message-id="${id}"]`);
                if (el && !isMediaPlaying(el)) {
                    el.remove();
                }
                delete _renderedMessageMap[id];
            }
        }

        // 2. Update existing or append new messages
        let needsRebind = false;
        data.messages.forEach((message, index) => {
            const msgId = String(message.id);
            const existingEl = box.querySelector(`[data-message-id="${msgId}"]`);
            const fingerprint = `${message.message_text || ''}|${message.media_url || ''}|${message.message_type}`;

            if (existingEl) {
                // Message already in DOM — check if text was edited (only for text messages)
                if (message.message_type === 'text' && _renderedMessageMap[msgId] !== fingerprint) {
                    const bubble = existingEl.querySelector('.conversation-bubble');
                    if (bubble) {
                        const textDiv = bubble.querySelector(':scope > div:last-child') || bubble.querySelector(':scope > div');
                        if (textDiv && !textDiv.classList.contains('mb-1')) {
                            textDiv.textContent = message.message_text || '';
                        }
                    }
                    _renderedMessageMap[msgId] = fingerprint;
                }
                // For voice/video/image messages already in DOM — NEVER touch them to preserve playback
            } else {
                // New message — insert at correct position
                const html = buildMessageHtml(message);
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                const newNode = tempDiv.firstElementChild;

                // Find the next sibling to insert before
                let insertBefore = null;
                for (let j = index + 1; j < data.messages.length; j++) {
                    const nextEl = box.querySelector(`[data-message-id="${data.messages[j].id}"]`);
                    if (nextEl) { insertBefore = nextEl; break; }
                }

                if (insertBefore) {
                    box.insertBefore(newNode, insertBefore);
                } else {
                    box.appendChild(newNode);
                }

                _renderedMessageMap[msgId] = fingerprint;
                needsRebind = true;
            }
        });

        if (wasAtBottom) {
            box.scrollTop = box.scrollHeight;
        }
        if (needsRebind) {
            bindConversationMessageActions(box);
        }
    } catch (error) {
        box.innerHTML = `<div class="text-center text-danger small py-5" role="alert">${escapeConversationText(error.message || 'Could not load this live conversation. Please try again.')}</div>`;
    }
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
        if (menu) menu.classList.toggle('open');
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
        document.getElementById('replyToMessageId').value = button.dataset.messageId;
        document.getElementById('activeReplySender').textContent = button.dataset.senderName;
        document.getElementById('activeReplyPreview').textContent = button.dataset.messageText || 'media';
        document.getElementById('activeReplyBadge').style.display = 'block';
        document.getElementById('liveMessageInput').focus();
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
            // Update Card Checkbox
            const chk = document.getElementById('attendanceCheck_' + classId);
            if (chk) { chk.checked = true; chk.disabled = true; }
            const label = document.getElementById('label-text-' + classId);
            if (label) label.textContent = 'Attendance Verified for this Live Session';
            const sub = document.getElementById('subtext-' + classId);
            if (sub) sub.textContent = 'Marked as Present on ' + new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            // Update In-Modal Attendance Area
            const modalAttendanceArea = document.getElementById('liveModalAttendanceArea');
            if (modalAttendanceArea) {
                modalAttendanceArea.innerHTML = '<span class="badge bg-success text-white rounded-pill px-3 py-1.5 fw-bold d-inline-flex align-items-center gap-1"><i class="bi bi-patch-check-fill"></i> Present (Marked)</span>';
            }

            // Update Hero Banner Badge
            const heroBtn = document.getElementById('hero-verify-btn');
            if (heroBtn) {
                heroBtn.outerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2 fw-bold d-inline-flex align-items-center gap-1.5" id="hero-badge-attended"><i class="bi bi-patch-check-fill"></i> Attendance Verified (Present)</span>';
            }

            let badge = document.getElementById('badge-attended-' + classId);
            if (!badge) {
                const card = document.getElementById('live-card-' + classId);
                const badgeContainer = card ? card.querySelector('.d-flex.align-items-center.gap-2.mb-3') || card.querySelector('.d-flex.align-items-center.gap-2') : null;
                if (badgeContainer) {
                    const newBadge = document.createElement('span');
                    newBadge.id = 'badge-attended-' + classId;
                    newBadge.className = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1.5 fw-bold';
                    newBadge.innerHTML = '<i class="bi bi-patch-check-fill me-1"></i> Present (Marked)';
                    badgeContainer.appendChild(newBadge);
                }
            }
        }
    } catch (e) {
        console.warn('Auto attendance error:', e);
    }
}

function openConversation(classId, title) {
    if (!classId) return;
    conversationClassId = classId;
    autoMarkAttendance(classId);
    
    document.getElementById('conversationModalLabel').textContent = title || 'Live Class Session';
    document.getElementById('conversationTitle').textContent = 'Connecting to live broadcast stream...';
    document.getElementById('conversationMessages').innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm text-primary mb-2"></div><div>Connecting to live stream...</div></div>';
    
    // Reset reply state
    cancelReply();
    _renderedMessageMap = {};

    
    // Show Modal
    const modalEl = document.getElementById('conversationModal');
    const modalInst = bootstrap.Modal.getOrCreateInstance(modalEl);
    modalInst.show();
    
    loadConversation();
    clearInterval(conversationTimer);
    conversationTimer = setInterval(loadConversation, 1600);
}

document.addEventListener('DOMContentLoaded', () => {
    const classId = Number(new URLSearchParams(window.location.search).get('class_id'));
    if (Number.isInteger(classId) && classId > 0) {
        const card = document.getElementById('live-card-' + classId);
        const title = card ? (card.querySelector('h4')?.textContent.trim() || 'Live Class Session') : 'Live Class Session';
        
        // Auto scroll if card exists
        if (card) {
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        // Auto open the live classroom modal immediately
        setTimeout(() => {
            openConversation(classId, title);
        }, 300);
    }
});

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
    if (!conversationClassId) return;
    
    const sendBtn = document.getElementById('liveSendBtn');
    const input = document.getElementById('liveMessageInput');
    const textVal = input.value.trim();
    if (!textVal) return;
    
    sendBtn.disabled = true;
    
    const formData = new FormData(this);
    formData.append('class_id', conversationClassId);
    formData.append('message_type', 'text');
    
    try {
        const response = await fetch('<?= url('api/live-conversation.php') ?>', { method: 'POST', body: formData });
        const data = await response.json();
        if (!data.success) { 
            alert(data.message || 'Could not send question.'); 
        } else {
            this.reset();
            cancelReply();
            sendTypingState(false);
            loadConversation();
        }
    } catch (e) {
        alert('Network error. Please try again.');
    } finally {
        sendBtn.disabled = false;
        input.focus();
    }
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
            if (!this.checked) return;

            const classId = this.dataset.classId;
            const labelText = document.getElementById('label-text-' + classId);
            const subtext = document.getElementById('subtext-' + classId);
            const card = document.getElementById('live-card-' + classId);

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

                    let badge = document.getElementById('badge-attended-' + classId);
                    if (!badge && card) {
                        const headerArea = card.querySelector('.d-flex.align-items-center.gap-2.mb-3') || card.querySelector('.d-flex.align-items-center.gap-2');
                        if (headerArea) {
                            const newBadge = document.createElement('span');
                            newBadge.id = 'badge-attended-' + classId;
                            newBadge.className = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1.5 fw-bold';
                            newBadge.innerHTML = '<i class="bi bi-patch-check-fill me-1"></i> Present (Marked)';
                            headerArea.appendChild(newBadge);
                        }
                    }

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