<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/live_classes.php';
require_once BASE_PATH . '/includes/functions/courses.php';
require_once BASE_PATH . '/includes/functions/notifications.php';
require_once BASE_PATH . '/includes/functions/voice_video_notes.php';

secure_page(ROLE_TEACHER);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$user   = current_user();
$userId = (int)$user['id'];
$pdo    = getDBConnection();

$stmt = $pdo->prepare("SELECT id, user_id, assigned_course_id, assigned_category_id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;

$assignedCourseId = (int)($teacher['assigned_course_id'] ?? 0);
$stmtCourses = $pdo->prepare("SELECT c.id, c.title, c.academic_level, c.academic_year
    FROM courses c
    WHERE c.teacher_id = ? OR c.id = ?
    ORDER BY c.title ASC");
$stmtCourses->execute([$tid, $assignedCourseId]);
$myCourses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);

if (is_post()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'start_live_now') {
        $courseId        = (int)($_POST['course_id'] ?? 0);
        $subject         = trim($_POST['subject'] ?? '');
        $topic           = trim($_POST['topic'] ?? '');
        $durationMinutes = (int)($_POST['duration_minutes'] ?? 60);

        if (!$courseId || empty($subject) || empty($topic)) {
            set_flash('error', 'Please select a course and enter the subject and topic.');
        } else {

            $ownsCourse = false;
            foreach ($myCourses as $mc) {
                if ((int)$mc['id'] === $courseId) {
                    $ownsCourse = true;
                    break;
                }
            }

            if (!$ownsCourse && current_user_role() !== ROLE_ADMIN) {
                set_flash('error', 'You can only schedule live classes for courses you teach.');
            } else {
                $newId = start_live_class_now($courseId, $tid, $subject, $topic, $durationMinutes);
                if ($newId) {
                    log_user_activity($userId, 'start_live_class', "Started live class: {$subject}: {$topic}", $courseId);
                    set_flash('success', 'You are live now. Your enrolled students have been notified.');
                } else {
                    set_flash('error', 'Failed to schedule live class. Please check your database connection.');
                }
            }
        }
        redirect('teacher/live-classes.php');
    }

    if ($action === 'update_status') {
        $classId      = (int)($_POST['class_id'] ?? 0);
        $status       = $_POST['status'] ?? 'scheduled';
        $recordingUrl = trim($_POST['recording_url'] ?? '');
        $previousClass = $classId ? get_live_class_by_id($classId) : null;

        if (!in_array($status, ['scheduled', 'live', 'ended', 'cancelled'])) {
            set_flash('error', 'Invalid status provided.');
        } else {
            $updated = update_live_class_status($classId, $tid, $status, !empty($recordingUrl) ? $recordingUrl : null);
            if ($updated) {
                if ($status === 'live') {
                    set_flash('success', "Session is now LIVE! Attendance checkbox is active for enrolled students.");
                } elseif ($status === 'ended') {
                    $summary = get_live_class_by_id($classId);
                    $attended = (int)($summary['attended_students'] ?? 0);
                    $enrolled = (int)($summary['enrolled_students'] ?? 0);
                    if (($previousClass['status'] ?? '') === 'live') {
                        create_notification(
                            $userId,
                            'Live class summary',
                            "{$summary['title']} ended. {$attended} of {$enrolled} enrolled student(s) attended.",
                            'live_class',
                            'teacher/live-classes.php#live-card-' . $classId
                        );
                    }
                    set_flash('success', "Live session ended. {$attended} of {$enrolled} enrolled student(s) attended.");
                } else {
                    set_flash('success', "Session status updated to " . ucfirst($status) . ".");
                }
            } else {
                set_flash('error', 'Could not update session status.');
            }
        }
        redirect('teacher/live-classes.php');
    }

    if ($action === 'delete_class') {
        $classId = (int)($_POST['class_id'] ?? 0);
        if (delete_live_class($classId, $tid)) {
            set_flash('success', 'Live class session removed.');
        } else {
            set_flash('error', 'Could not delete live class session.');
        }
        redirect('teacher/live-classes.php');
    }
}

$liveClasses = get_teacher_live_classes($tid ?: $userId);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<style>
.live-page-header { min-width: 0; }
.live-page-header > div:first-child { min-width: 0; }
.live-page-header h1, .live-page-header p { overflow-wrap: anywhere; }
.live-session-card { min-width: 0; }
#scheduleModal .modal-content, #conversationModal .modal-content { overflow: hidden; }
#scheduleModal .modal-body, #conversationModal .modal-body { min-width: 0; }
#conversationMessages { scrollbar-width: thin; }
#videoNoteModal .modal-content {
    overflow: hidden;
    border: 1px solid rgba(99, 102, 241, .16) !important;
    border-radius: 1.25rem;
    box-shadow: 0 24px 70px rgba(15, 23, 42, .2) !important;
}
#videoNoteModal .modal-header {
    background: linear-gradient(120deg, #172554, #1d4ed8 58%, #0f766e) !important;
    border-bottom: 0;
    padding: 1.1rem 1.35rem;
}
#videoNoteModal .modal-body { background: linear-gradient(180deg, #f8fafc, #eef6ff); }
#videoNoteModal .modal-footer { border-top: 1px solid rgba(148, 163, 184, .18); }
#vidSessionInfo {
    border: 0 !important;
    border-left: 4px solid #2563eb !important;
    background: rgba(255, 255, 255, .82) !important;
    box-shadow: 0 8px 22px rgba(15, 23, 42, .06);
}
#videoNoteModal .position-relative[style*="aspect-ratio"] {
    border: 8px solid #fff;
    background: #0f172a !important;
    box-shadow: 0 18px 38px rgba(15, 23, 42, .2);
}
#vidPreview, #vidPlayback { border-radius: .7rem; }
#videoNoteModal #vidRecBadge {
    background: rgba(220, 38, 38, .92) !important;
    box-shadow: 0 6px 18px rgba(220, 38, 38, .35);
}
#videoNoteModal #vidStatus {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    padding: .45rem .8rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, .8);
    box-shadow: 0 5px 16px rgba(15, 23, 42, .06);
}
#videoNoteModal #vidStatus::before {
    content: "";
    width: .45rem;
    height: .45rem;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 0 4px rgba(16, 185, 129, .12);
}
@media (max-width: 767.98px) {
    .live-page-header { align-items: stretch !important; }
    .live-page-header > div:last-child { width: 100%; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .live-page-header > div:last-child > * { width: 100%; }
    .live-page-header h1 { font-size: 1.35rem; }
    .live-page-header p { line-height: 1.45; }
    .live-session-card { padding: 1rem !important; }
    .live-session-card .d-flex.gap-2 { flex-wrap: wrap; }
    .live-session-card .d-flex.gap-2 > * { flex: 1 1 100%; }
    .modal-dialog { margin: .5rem; }
    #scheduleModal .modal-body, #conversationModal .modal-body { padding: 1rem !important; }
    #scheduleModal .modal-footer { flex-direction: column-reverse; gap: .5rem; }
    #scheduleModal .modal-footer > * { width: 100%; margin: 0; }
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
@media (max-width: 575.98px) {
    .live-page-header > div:last-child { grid-template-columns: 1fr; }
    #conversationModal .modal-title { font-size: 1rem; }
}
</style>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 live-page-header">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small">
            <i class="bi bi-broadcast text-danger me-1"></i> Instructor Suite
        </p>
        <h1 class="h3 fw-bold mb-1">Live Interactive Classes &amp; Attendance</h1>
        <p class="text-muted small mb-0">Start a live lesson, receive student questions, and reply in the live conversation.</p>
    </div>

    <div class="d-flex gap-2">
        <a href="<?= url('teacher/analytics.php') ?>" class="btn btn-outline-primary rounded-pill px-3 fw-bold">
            <i class="bi bi-bar-chart-line-fill me-1"></i> Attendance Analytics
        </a>
        <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#scheduleModal">
            <i class="bi bi-broadcast me-2"></i> Go Live
        </button>
    </div>
</div>

<div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="scheduleModalLabel">
                    <i class="bi bi-broadcast me-2"></i> Start Live Lesson
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="start_live_now">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Select Course <span class="text-danger">*</span></label>
                        <select name="course_id" class="form-select" required>
                            <option value="">-- Choose Course --</option>
                            <?php foreach ($myCourses as $course): ?>
                                <option value="<?= (int)$course['id'] ?>">
                                    <?= htmlspecialchars($course['title']) ?> (<?= htmlspecialchars($course['academic_level'] ?? '100 Level') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($myCourses)): ?>
                            <small class="text-danger d-block mt-1">
                                You do not have any active courses yet. <a href="<?= url('teacher/select-course.php') ?>" class="text-primary fw-bold">Select</a> or <a href="<?= url('teacher/create-course.php') ?>" class="text-primary fw-bold">Create one</a> first.
                            </small>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Subject <span class="text-danger">*</span></label>
                        <input type="text" name="subject" class="form-control" placeholder="e.g. Civil Engineering" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Topic <span class="text-danger">*</span></label>
                        <input type="text" name="topic" class="form-control" placeholder="e.g. Introduction to Structural Design" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Duration (Mins)</label>
                        <input type="number" name="duration_minutes" class="form-control" value="60" min="15" max="300">
                    </div>

                    <div class="mb-0">
                        <div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-1"></i> Students enrolled in this course will be notified immediately and can join from their Live Classes page.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold" <?= empty($myCourses) ? 'disabled' : '' ?>>
                        <i class="bi bi-broadcast me-1"></i> Start Live &amp; Notify Students
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (empty($liveClasses)): ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
        <div class="mb-3">
            <span class="d-inline-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-circle p-4" style="width: 80px; height: 80px;">
                <i class="bi bi-camera-video-off fs-1"></i>
            </span>
        </div>
        <h4 class="fw-bold mb-2">No Live Classes Scheduled Yet</h4>
        <p class="text-muted col-lg-6 mx-auto mb-4">You haven't scheduled any live interactive video sessions for your courses. Keep your students engaged by hosting regular live Q&amp;As, problem-solving workshops, or exam revision sessions.</p>
        <div>
            <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                <i class="bi bi-plus-circle me-1"></i> Schedule Your First Session
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($liveClasses as $lc):
            $isLive   = ($lc['status'] === 'live');
            $isEnded  = ($lc['status'] === 'ended');
            $enrolled = (int)$lc['enrolled_students'];
            $attended = (int)$lc['attended_students'];
            $pct      = $enrolled > 0 ? round(($attended / $enrolled) * 100, 1) : 0;

            $statusBadgeClass = match($lc['status']) {
                'live' => 'bg-danger text-white animate-pulse',
                'scheduled' => 'bg-primary text-white',
                'ended' => 'bg-secondary text-white',
                default => 'bg-light text-muted border'
            };
        ?>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 p-4 border-top border-4 <?= $isLive ? 'border-danger' : 'border-primary' ?> position-relative live-session-card">
                    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                                <span class="badge <?= $statusBadgeClass ?> rounded-pill px-3 py-1 fw-bold">
                                    <?= $isLive ? '🔴 LIVE NOW' : strtoupper($lc['status']) ?>
                                </span>
                                <span class="badge bg-light text-dark border rounded-pill px-2 py-1">
                                    <?= htmlspecialchars($lc['academic_level'] ?? '100 Level') ?>
                                </span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 fw-bold">
                                    <i class="bi bi-person-check-fill me-1"></i> <?= $attended ?>/<?= $enrolled ?> Attended (<?= $pct ?>%)
                                </span>
                            </div>
                            <h5 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($lc['title']) ?></h5>
                            <p class="text-primary fw-semibold small mb-0">
                                <i class="bi bi-journal-bookmark me-1"></i> <?= htmlspecialchars($lc['course_title']) ?>
                            </p>
                        </div>

                        <div class="dropdown">
                            <button class="btn btn-sm btn-light border rounded-circle" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                <li>
                                    <button type="button" class="dropdown-item small py-2" onclick="viewAttendanceRoster(<?= (int)$lc['id'] ?>, '<?= addslashes(htmlspecialchars($lc['title'])) ?>')">
                                        <i class="bi bi-people me-2 text-primary"></i> View Attendance Roster
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="" class="p-0 m-0">
                                        <input type="hidden" name="action" value="delete_class">
                                        <input type="hidden" name="class_id" value="<?= (int)$lc['id'] ?>">
                                        <button type="submit" class="dropdown-item text-danger small py-2" onclick="return confirm('Are you sure you want to delete this session?');">
                                            <i class="bi bi-trash me-2"></i> Delete Session
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <?php if (!empty($lc['description'])): ?>
                        <p class="text-muted small mb-3"><?= nl2br(htmlspecialchars($lc['description'])) ?></p>
                    <?php endif; ?>

                    <div class="bg-light rounded-3 p-3 mb-3 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted"><i class="bi bi-calendar3 me-1"></i> Scheduled:</span>
                            <span class="fw-bold"><?= date('D, M j, Y @ g:i A', strtotime($lc['scheduled_at'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted"><i class="bi bi-clock me-1"></i> Duration:</span>
                            <span class="fw-bold"><?= (int)$lc['duration_minutes'] ?> Minutes</span>
                        </div>

                        <div class="mt-2 pt-2 border-top">
                            <div class="d-flex justify-content-between text-muted mb-1" style="font-size: 0.75rem;">
                                <span><i class="bi bi-check2-all text-success me-1"></i> Student Attendance</span>
                                <span class="fw-bold text-dark"><?= $attended ?> / <?= $enrolled ?> (<?= $pct ?>%)</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $pct ?>%;" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="" class="mt-auto">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="class_id" value="<?= (int)$lc['id'] ?>">

                        <div class="d-flex gap-2 mb-3">
                            <?php if ($lc['status'] === 'scheduled'): ?>
                                <button type="submit" name="status" value="live" class="btn btn-sm btn-danger rounded-pill flex-grow-1 fw-bold">
                                    <i class="bi bi-broadcast me-1"></i> Start Session (Open Attendance)
                                </button>
                            <?php elseif ($isLive): ?>
                                <button type="submit" name="status" value="ended" class="btn btn-sm btn-secondary rounded-pill flex-grow-1 fw-bold">
                                    <i class="bi bi-stop-circle-fill me-1"></i> End Session (Close Attendance)
                                </button>
                            <?php else: ?>
                                <button type="submit" name="status" value="live" class="btn btn-sm btn-outline-danger rounded-pill flex-grow-1 fw-bold">
                                    <i class="bi bi-arrow-repeat me-1"></i> Re-Open Live Session
                                </button>
                            <?php endif; ?>

                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold" onclick="viewAttendanceRoster(<?= (int)$lc['id'] ?>, '<?= addslashes(htmlspecialchars($lc['title'])) ?>')">
                                <i class="bi bi-people-fill me-1"></i> Roster
                            </button>
                        </div>

                        <?php if ($isEnded): ?>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted mb-1">Replay / Recording Video URL (for missed students)</label>
                                <div class="input-group input-group-sm">
                                    <input type="url" name="recording_url" class="form-control" placeholder="https://youtube.com/watch?v=... or Drive link" value="<?= htmlspecialchars($lc['recording_url'] ?? '') ?>">
                                    <button type="submit" class="btn btn-outline-primary fw-bold">Save Replay</button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex gap-2 flex-wrap">
                            <?php if ($isLive): ?>
                                <button type="button" class="btn btn-sm btn-danger animate-pulse flex-grow-1 fw-bold rounded-pill" onclick="openConversation(<?= (int)$lc['id'] ?>, '<?= htmlspecialchars(addslashes($lc['title']), ENT_QUOTES) ?>')">
                                    <i class="bi bi-broadcast me-1"></i> Open Live Room
                                    <span class="live-attendee-indicator ms-1" data-live-attendance-count="<?= (int)$lc['id'] ?>" title="Students currently marked present">
                                        <i class="bi bi-people-fill"></i><span><?= (int)$attended ?></span>
                                    </span>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-light border flex-grow-1 fw-bold rounded-pill" disabled>
                                    <i class="bi bi-hourglass-split me-1"></i> Session Ended
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill fw-bold" onclick="openConversation(<?= (int)$lc['id'] ?>, '<?= htmlspecialchars(addslashes($lc['title']), ENT_QUOTES) ?>', <?= (int)$lc['course_id'] ?>)">
                                <i class="bi bi-chat-dots-fill me-1"></i> Conversation
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill fw-bold" onclick="confirmDeleteLiveClass(<?= (int)$lc['id'] ?>, '<?= htmlspecialchars(addslashes($lc['title']), ENT_QUOTES) ?>')" title="Permanently delete this live session">
                                <i class="bi bi-trash3-fill me-1"></i> Delete
                            </button>
                        </div>

                        <?php if ($isLive): ?>
                        <!-- Voice & Video Note Recording (Live Only) -->
                        <div class="live-recording-panel">
                            <div class="live-recording-heading"><i class="bi bi-record-circle-fill"></i><span>Record and send to students</span></div>
                            <div class="live-recording-actions">
                                <button type="button" class="live-recording-button voice" aria-label="Record and send a voice note" onclick="openVoiceNoteModal(<?= (int)$lc['id'] ?>, <?= (int)$lc['course_id'] ?>, '<?= htmlspecialchars(addslashes($lc['title']), ENT_QUOTES) ?>')">
                                    <i class="bi bi-mic-fill"></i><span>Voice note</span>
                                </button>
                                <button type="button" class="live-recording-button video" aria-label="Record and send a video note" onclick="openVideoNoteModal(<?= (int)$lc['id'] ?>, <?= (int)$lc['course_id'] ?>, '<?= htmlspecialchars(addslashes($lc['title']), ENT_QUOTES) ?>')">
                                    <i class="bi bi-camera-video-fill"></i><span>Video note</span>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="modal fade" id="conversationModal" tabindex="-1" aria-labelledby="conversationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="conversationModalLabel"><i class="bi bi-chat-dots-fill me-2"></i>Teacher Conversation <span class="live-attendee-indicator ms-2" data-modal-live-count><i class="bi bi-people-fill"></i><span>0</span></span></h5>
                    <small id="conversationTitle" class="text-white-50"></small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-danger rounded-pill fw-bold px-3 d-inline-flex align-items-center gap-1.5 shadow-sm" onclick="confirmDeleteCurrentLiveClass()" title="Delete this live stream completely">
                        <i class="bi bi-trash3-fill"></i> <span class="d-none d-sm-inline">Delete Stream</span>
                    </button>
                    <button type="button" class="btn-close btn-close-white ms-1" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-3">
                <div id="liveJoinedNotice" class="live-joined-notice" role="status" aria-live="polite"></div>
                <div id="conversationMessages" class="bg-light rounded-3 p-3" style="height: 340px; overflow-y: auto;"></div>
                <form id="conversationForm" class="mt-3" enctype="multipart/form-data">
                    <input type="hidden" name="reply_to_message_id" value="">
                    <input type="hidden" name="mention_user_id" value="">
                    <div class="input-group">
                        <input type="text" name="message_text" class="form-control" placeholder="Reply to your students..." maxlength="2000">
                        <label for="chatImageInput" class="btn btn-outline-secondary d-flex align-items-center gap-1 mb-0" title="Share picture">
                            <i class="bi bi-image-fill"></i>
                            <span class="d-none d-sm-inline small fw-bold">Picture</span>
                        </label>
                        <input type="file" id="chatImageInput" name="image_note" accept="image/jpeg,image/png,image/gif,image/webp" class="d-none">
                        <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-send-fill me-1"></i> Send</button>
                    </div>
                    <div id="liveTypingIndicator" class="live-typing-indicator" role="status" aria-live="polite">
                        <span class="live-typing-dots"><span></span><span></span><span></span></span>
                        <span id="liveTypingText">Someone is typing...</span>
                    </div>
                    <div class="d-flex gap-2 align-items-center mt-2 flex-wrap">
                        <button type="button" id="mentionStudentButton" class="btn btn-sm btn-outline-primary rounded-pill fw-semibold d-inline-flex align-items-center gap-1">
                            <i class="bi bi-at fs-6"></i> Mention student <span id="mentionStudentCount" class="badge bg-primary text-white ms-1 rounded-pill">0</span>
                        </button>
                        <button type="button" class="chat-recording-button chat-recording-voice" onclick="triggerModalVoiceRecord()">
                            <span class="chat-recording-icon"><i class="bi bi-mic-fill"></i></span>
                            <span><strong>Record voice</strong><small>Send an audio note</small></span>
                        </button>
                        <button type="button" class="chat-recording-button chat-recording-video" onclick="triggerModalVideoRecord()">
                            <span class="chat-recording-icon"><i class="bi bi-camera-video-fill"></i></span>
                            <span><strong>Record video</strong><small>Send a video note</small></span>
                        </button>
                        <select id="mentionStudentSelect" class="form-select form-select-sm" style="display:none; max-width:280px;" aria-label="Select student to mention">
                            <option value="">Select a student to mention...</option>
                        </select>
                        <div id="mentionActiveContainer" style="display:none;" class="ms-1">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1.5 d-inline-flex align-items-center gap-2">
                                <i class="bi bi-at me-0.5"></i>Mentioning: <strong id="mentionStudentName"></strong>
                                <button type="button" class="btn-close ms-1" id="removeMentionBtn" style="font-size:0.55rem;" aria-label="Remove mention"></button>
                            </span>
                        </div>
                    </div>
                    <div class="form-text">Send text replies, click the mic/video icons to record notes live, or mention a student directly.</div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
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
.live-attendee-indicator {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    min-width: 2.7rem;
    justify-content: center;
    padding: .35rem .65rem;
    border: 1px solid rgba(255, 255, 255, .42);
    border-radius: 999px;
    background: rgba(255, 255, 255, .16);
    color: #fff;
    font-size: .78rem;
    font-weight: 800;
}
.live-attendee-indicator i { font-size: .9rem; }
.live-attendee-indicator.is-updated { animation: attendeePulse .65s ease; }
.live-typing-indicator { display: none; align-items: center; gap: .5rem; min-height: 28px; margin-top: .55rem; padding: .35rem .7rem; border-radius: .75rem; background: #eff6ff; color: #1d4ed8; font-size: .76rem; font-weight: 700; }
.live-typing-indicator.is-visible { display: inline-flex; }
.live-typing-dots { display: inline-flex; gap: 3px; }
.live-typing-dots span { width: 5px; height: 5px; border-radius: 50%; background: currentColor; animation: typingDot 1s infinite ease-in-out; }
.live-typing-dots span:nth-child(2) { animation-delay: .15s; }
.live-typing-dots span:nth-child(3) { animation-delay: .3s; }
@keyframes typingDot { 0%, 60%, 100% { transform: translateY(0); opacity: .45; } 30% { transform: translateY(-3px); opacity: 1; } }
.live-recording-panel { margin-top: 1rem; padding: .85rem; border: 1px solid #e2e8f0; border-radius: 1rem; background: #f8fafc; }
.live-recording-heading { display: flex; align-items: center; gap: .55rem; margin-bottom: .7rem; color: #334155; font-size: .76rem; font-weight: 800; letter-spacing: .02em; text-transform: uppercase; }
.live-recording-heading i { color: #dc2626; font-size: 1rem; }
.live-recording-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .6rem; }
.live-recording-button { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; min-height: 44px; border: 1px solid #cbd5e1; border-radius: .8rem; background: #fff; color: #1e293b; font-size: .78rem; font-weight: 800; transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease; }
.live-recording-button i { font-size: 1.05rem; }
.live-recording-button:hover, .live-recording-button:focus-visible { transform: translateY(-1px); box-shadow: 0 8px 18px rgba(15,23,42,.08); }
.live-recording-button.voice:hover, .live-recording-button.voice:focus-visible { border-color: #fca5a5; color: #b91c1c; }
.live-recording-button.video:hover, .live-recording-button.video:focus-visible { border-color: #93c5fd; color: #1d4ed8; }
.chat-recording-button {
    display: inline-flex;
    align-items: center;
    gap: .6rem;
    min-width: 174px;
    padding: .55rem .8rem;
    border: 1px solid #cbd5e1;
    border-radius: .9rem;
    background: #fff;
    color: #1e293b;
    text-align: left;
    transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
}
.chat-recording-button > span:last-child { display: flex; flex-direction: column; gap: .08rem; }
.chat-recording-button strong { font-size: .78rem; line-height: 1.15; }
.chat-recording-button small { color: #64748b; font-size: .66rem; line-height: 1.15; }
.chat-recording-icon { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; flex: 0 0 34px; border-radius: .7rem; color: #fff; font-size: 1rem; }
.chat-recording-button:hover, .chat-recording-button:focus-visible { transform: translateY(-1px); box-shadow: 0 8px 18px rgba(15,23,42,.1); outline: 0; }
.chat-recording-voice .chat-recording-icon { background: #334155; }
.chat-recording-video .chat-recording-icon { background: #2563eb; }
.chat-recording-voice:hover, .chat-recording-voice:focus-visible { border-color: #94a3b8; }
.chat-recording-video:hover, .chat-recording-video:focus-visible { border-color: #93c5fd; }
@media (max-width: 575.98px) { .live-recording-actions { grid-template-columns: 1fr; } }
.live-joined-notice {
    display: none;
    margin: 0 0 .85rem;
    padding: .65rem 1rem;
    border: 1px solid rgba(16, 185, 129, .35);
    border-radius: .75rem;
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    color: #065f46;
    font-size: .82rem;
    font-weight: 700;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.15);
    animation: slideDownFade 0.3s ease-out;
}
.live-joined-notice.is-visible { display: flex; align-items: center; gap: 0.5rem; }
@keyframes slideDownFade {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes attendeePulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.12); }
}
.record-studio-card {
    border-radius: 1.25rem;
    overflow: hidden;
    border: none;
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.22);
}
.record-studio-main-btn {
    width: 76px;
    height: 76px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    border: none;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.record-studio-main-btn:hover {
    transform: scale(1.08);
    box-shadow: 0 12px 28px rgba(220, 38, 38, 0.45);
}
.recording-send-btn {
    background: linear-gradient(135deg, #10b981, #059669);
    border: none;
    transition: all 0.2s ease;
}
.recording-send-btn:hover {
    background: linear-gradient(135deg, #059669, #047857);
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(16, 185, 129, 0.35);
}
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
.live-profile-trigger { border: 0; padding: 0; background: transparent; color: inherit; font: inherit; font-weight: inherit; cursor: pointer; text-decoration: underline; text-decoration-color: rgba(37, 99, 235, .25); text-underline-offset: 2px; }
.live-profile-trigger:hover { color: #2563eb; text-decoration-color: currentColor; }
.mention-profile-trigger { padding: 0; border: 0; background: transparent; color: inherit; font-weight: 800; text-decoration: underline; text-underline-offset: 2px; cursor: pointer; }
.mention-profile-trigger:hover, .mention-profile-trigger:focus-visible { color: #1e40af; outline: 0; }
.live-profile-avatar { width: 104px; height: 104px; object-fit: cover; border: 4px solid #dbeafe; box-shadow: 0 12px 28px rgba(37, 99, 235, .16); }
</style>

<div class="modal fade" id="attendanceModal" tabindex="-1" aria-labelledby="attendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <div>
                    <h5 class="modal-title fw-bold" id="attendanceModalLabel">
                        <i class="bi bi-people-fill me-2 text-primary"></i> Live Session Attendance Roster
                    </h5>
                    <p class="small text-muted mb-0" id="modalSessionTitle"></p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">

                <div class="row g-3 mb-4" id="modalMetricsRow">
                    <div class="col-sm-4">
                        <div class="bg-light p-3 rounded-3 text-center">
                            <div class="text-muted small fw-semibold">Enrolled Students</div>
                            <div class="h4 fw-bold mb-0 text-dark" id="modalEnrolledCount">-</div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="bg-light p-3 rounded-3 text-center">
                            <div class="text-muted small fw-semibold">Students Present</div>
                            <div class="h4 fw-bold mb-0 text-success" id="modalAttendedCount">-</div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="bg-light p-3 rounded-3 text-center">
                            <div class="text-muted small fw-semibold">Attendance Rate</div>
                            <div class="h4 fw-bold mb-0 text-primary" id="modalAttendanceRate">-</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-card-checklist me-1"></i> Attending Students</h6>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="btnExportCsv" onclick="exportAttendanceCsv()">
                        <i class="bi bi-download me-1"></i> Export CSV
                    </button>
                </div>

                <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" id="attendeeTable">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th class="ps-3">Student</th>
                                <th>Student Number</th>
                                <th>Check-In Time</th>
                                <th class="text-end pe-3">Status</th>
                            </tr>
                        </thead>
                        <tbody id="attendeeTableBody">
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <div class="spinner-border spinner-border-sm text-primary me-2"></div> Loading attendee data...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="liveProfileModal" tabindex="-1" aria-labelledby="liveProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold" id="liveProfileModalLabel"><i class="bi bi-person-circle me-2"></i>Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4" id="liveProfileBody">
                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading profile</span></div>
            </div>
        </div>
    </div>
</div>

<script>
let conversationClassId = 0;
let conversationTimer = null;
let liveAttendanceTimer = null;
let knownLiveAttendees = null;
const conversationUserId = <?= (int)$userId ?>;

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
    const initials = identity.split(/\s+/).map(part => part.charAt(0)).join('').slice(0, 2).toUpperCase() || 'U';
    const fallback = `https://ui-avatars.com/api/?name=${encodeURIComponent(displayName)}&background=${color.slice(1)}&color=ffffff&bold=true`;
    return `<img src="${escapeConversationText(message.sender_avatar || fallback)}" alt="${escapeConversationText(displayName)}" class="conversation-avatar" style="background:${color};" onerror="this.onerror=null;this.src='${fallback}';"><span class="visually-hidden">${escapeConversationText(initials)}</span>`;
}

async function refreshLiveAttendance() {
    if (!conversationClassId) return;

    try {
        const response = await fetch('<?= url('api/attendance.php') ?>?class_id=' + conversationClassId, { cache: 'no-store' });
        const data = await response.json();
        if (!data.success) return;

        const attendees = data.attendees || [];
        const count = Number(data.stats?.total_attended || attendees.length);
        document.querySelectorAll(`[data-live-attendance-count="${conversationClassId}"]`).forEach(indicator => {
            const value = indicator.querySelector('span');
            if (value && value.textContent !== String(count)) {
                value.textContent = count;
                indicator.classList.remove('is-updated');
                void indicator.offsetWidth;
                indicator.classList.add('is-updated');
            }
        });

        const modalCount = document.querySelector('[data-modal-live-count] span');
        if (modalCount) modalCount.textContent = count;

        const currentAttendees = new Map(attendees.map(attendee => [String(attendee.student_id), attendee]));
        if (knownLiveAttendees !== null) {
            const newlyJoined = attendees.filter(attendee => !knownLiveAttendees.has(String(attendee.student_id)));
            if (newlyJoined.length) {
                const names = newlyJoined.map(attendee => attendee.student_name).join(', ');
                const notice = document.getElementById('liveJoinedNotice');
                if (notice) {
                    notice.innerHTML = `<i class="bi bi-person-check-fill text-success fs-5"></i> <span><strong>${escapeConversationText(names)}</strong> ${newlyJoined.length === 1 ? 'just joined the live class!' : 'just joined the live class!'}</span>`;
                    notice.classList.add('is-visible');
                    window.clearTimeout(window.liveJoinedNoticeTimer);
                    window.liveJoinedNoticeTimer = window.setTimeout(() => notice.classList.remove('is-visible'), 8000);
                }
            }
        } else {
            knownLiveAttendees = new Map();
        }
        knownLiveAttendees = currentAttendees;
    } catch (error) {
        console.warn('Live attendance refresh failed', error);
    }
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
    
    // Update mention count and enrolled students dropdown
    const studentCountEl = document.getElementById('mentionStudentCount');
    const enrolledStudents = data.students || [];
    if (studentCountEl) {
        studentCountEl.textContent = enrolledStudents.length || (data.total_students || 0);
    }
    const studentSelect = document.getElementById('mentionStudentSelect');
    if (studentSelect) {
        const currentVal = studentSelect.value;
        const msgStudents = data.messages ? [...new Map(data.messages.filter(m => m.role === 'student').map(m => [m.user_id, { user_id: m.user_id, name: m.sender_name }])).values()] : [];
        const studentList = enrolledStudents.length ? enrolledStudents : msgStudents;
        studentSelect.innerHTML = `<option value="">Select a student to mention (${studentList.length} enrolled)</option>` + 
            studentList.map(st => `<option value="${st.user_id}" data-name="${escapeConversationText(st.name)}">${escapeConversationText(st.name)}${st.student_number ? ' (' + escapeConversationText(st.student_number) + ')' : ''}</option>`).join('');
        if (currentVal) studentSelect.value = currentVal;
    }

    if (!data.success || !data.messages || !data.messages.length) {
        box.innerHTML = '<div class="text-center text-muted small py-5">No messages yet. Start the conversation with your students.</div>';
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
        const actions = `<div class="conversation-actions"><button type="button" class="conversation-actions-trigger" aria-label="Message options" data-message-menu="${message.id}"><i class="bi bi-three-dots-vertical"></i></button><div class="conversation-actions-menu" id="message-menu-${message.id}"><button type="button" data-message-action="reply" data-message-id="${message.id}" data-message-text="${escapeConversationText(message.message_text || '')}" data-sender-name="${escapeConversationText(message.sender_name)}"><i class="bi bi-reply-fill"></i> Reply</button>${ownMessage && message.message_type === 'text' ? `<button type="button" data-message-action="edit" data-message-id="${message.id}" data-message-text="${escapeConversationText(message.message_text || '')}"><i class="bi bi-pencil-fill"></i> Edit</button>` : ''}<button type="button" class="text-danger" data-message-action="delete" data-message-id="${message.id}"><i class="bi bi-trash-fill"></i> Delete</button></div></div>`;
        return `<div class="conversation-message ${message.role === 'teacher' ? 'conversation-message-teacher' : 'conversation-message-student'}" data-message-id="${message.id}"><div class="conversation-avatar-wrap">${conversationAvatar(message)}</div><div class="conversation-message-body"><div class="conversation-sender"><button type="button" class="live-profile-trigger" data-profile-user-id="${Number(message.user_id)}" data-profile-class-id="${conversationClassId}">${escapeConversationText(message.sender_name)}</button> ${roleLabel}<span class="conversation-time">${escapeConversationText(message.created_at)}</span></div><div class="conversation-bubble">${mentionBadge}${content}</div></div>${actions}</div>`;
    }).join('');
    box.scrollTop = box.scrollHeight;
    bindConversationMessageActions(box);
}

function formatLiveDuration(seconds) {
    const total = Math.max(0, Number(seconds) || 0);
    return Math.floor(total / 60) + ':' + String(total % 60).padStart(2, '0');
}

function clearMention() {
    const form = document.getElementById('conversationForm');
    if (!form) return;
    form.querySelector('input[name="mention_user_id"]').value = '';
    const container = document.getElementById('mentionActiveContainer');
    if (container) container.style.display = 'none';
    const select = document.getElementById('mentionStudentSelect');
    if (select) select.value = '';
}

function setMention(userId, name) {
    const form = document.getElementById('conversationForm');
    if (!form) return;
    form.querySelector('input[name="mention_user_id"]').value = userId;
    const container = document.getElementById('mentionActiveContainer');
    const nameEl = document.getElementById('mentionStudentName');
    if (container && nameEl) {
        nameEl.textContent = name;
        container.style.display = 'inline-block';
    }
    const input = form.querySelector('input[name="message_text"]');
    if (input && !input.value.includes('@' + name)) {
        input.value = '@' + name + ' ' + input.value;
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

let conversationCourseId = 0;
let conversationTitleStr = '';

function triggerModalVoiceRecord() {
    if (!conversationClassId) return;
    openVoiceNoteModal(conversationClassId, conversationCourseId, conversationTitleStr);
}

function triggerModalVideoRecord() {
    if (!conversationClassId) return;
    openVideoNoteModal(conversationClassId, conversationCourseId, conversationTitleStr);
}

function openConversation(classId, title, courseId = 0) {
    conversationClassId = classId;
    conversationCourseId = courseId;
    conversationTitleStr = title;
    knownLiveAttendees = null;
    clearMention();
    document.getElementById('conversationTitle').textContent = title;
    document.getElementById('conversationMessages').innerHTML = '<div class="text-center text-muted py-5">Loading conversation...</div>';
    document.getElementById('liveJoinedNotice').classList.remove('is-visible');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('conversationModal')).show();
    loadConversation();
    refreshLiveAttendance();
    clearInterval(conversationTimer);
    conversationTimer = setInterval(loadConversation, 2000);
    clearInterval(liveAttendanceTimer);
    liveAttendanceTimer = setInterval(refreshLiveAttendance, 2000);
}

document.getElementById('conversationModal').addEventListener('hidden.bs.modal', () => {
    clearInterval(conversationTimer);
    clearInterval(liveAttendanceTimer);
    sendTypingState(false);
    conversationClassId = 0;
    knownLiveAttendees = null;
    clearMention();
});

document.getElementById('conversationForm').addEventListener('submit', async function(event) {
    event.preventDefault();
    const formData = new FormData(this);
    formData.append('class_id', conversationClassId);
    const imageInput = this.querySelector('input[name="image_note"]');
    const hasImage = imageInput && imageInput.files && imageInput.files.length > 0;
    formData.append('message_type', hasImage ? 'image' : 'text');
    const response = await fetch('<?= url('api/live-conversation.php') ?>', { method: 'POST', body: formData });
    const data = await response.json();
    if (!data.success) { alert(data.message || 'Could not send message.'); return; }
    this.reset();
    clearMention();
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

document.getElementById('mentionStudentButton').addEventListener('click', function() {
    const select = document.getElementById('mentionStudentSelect');
    select.style.display = select.style.display === 'none' ? 'inline-block' : 'none';
    if (select.style.display === 'inline-block') select.focus();
});

document.getElementById('mentionStudentSelect').addEventListener('change', function() {
    if (this.value) {
        const selectedOpt = this.options[this.selectedIndex];
        const studentName = selectedOpt.getAttribute('data-name') || selectedOpt.text.split('(')[0].trim();
        setMention(this.value, studentName);
    } else {
        clearMention();
    }
});

const removeMentionBtn = document.getElementById('removeMentionBtn');
if (removeMentionBtn) {
    removeMentionBtn.addEventListener('click', clearMention);
}

let currentRosterData = null;
let currentSessionTitle = '';

async function viewAttendanceRoster(classId, sessionTitle) {
    currentSessionTitle = sessionTitle;
    document.getElementById('modalSessionTitle').textContent = sessionTitle;
    document.getElementById('modalEnrolledCount').textContent = '...';
    document.getElementById('modalAttendedCount').textContent = '...';
    document.getElementById('modalAttendanceRate').textContent = '...';
    document.getElementById('attendeeTableBody').innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Loading attendee data...</td></tr>';

    const modal = new bootstrap.Modal(document.getElementById('attendanceModal'));
    modal.show();

    try {
        const res = await fetch('<?= url('api/attendance.php') ?>?class_id=' + classId);
        const data = await res.json();

        if (data.success) {
            currentRosterData = data;
            const stats = data.stats;
            document.getElementById('modalEnrolledCount').textContent = stats.total_enrolled;
            document.getElementById('modalAttendedCount').textContent = stats.total_attended;
            document.getElementById('modalAttendanceRate').textContent = stats.attendance_rate + '%';

            const attendees = data.attendees || [];
            if (attendees.length === 0) {
                document.getElementById('attendeeTableBody').innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><i class="bi bi-people me-1"></i> No students have marked attendance for this session yet.</td></tr>';
            } else {
                let html = '';
                attendees.forEach(att => {
                    const avatar = att.student_avatar || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(att.student_name) + '&background=4f46e5&color=ffffff&bold=true';
                    html += `<tr>
                        <td class="ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <img src="${avatar}" class="rounded-circle" style="width:32px; height:32px; object-fit:cover;" onerror="this.src='https://ui-avatars.com/api/?name=Student&background=4f46e5&color=ffffff';">
                                <div>
                                    <button type="button" class="live-profile-trigger fw-bold text-dark small" data-profile-user-id="${Number(att.user_id)}" data-profile-class-id="${classId}">${escapeHtml(att.student_name)}</button>
                                    <div class="text-muted" style="font-size:0.75rem;">${escapeHtml(att.student_email)}</div>
                                </div>
                            </div>
                        </td>
                        <td class="small text-muted">${escapeHtml(att.student_number || 'N/A')}</td>
                        <td class="small text-dark fw-semibold">${formatDateTime(att.attended_at)}</td>
                        <td class="text-end pe-3">
                            <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1 small">
                                <i class="bi bi-check-circle-fill me-1"></i> Present
                            </span>
                        </td>
                    </tr>`;
                });
                document.getElementById('attendeeTableBody').innerHTML = html;
            }
        } else {
            document.getElementById('attendeeTableBody').innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">' + escapeHtml(data.message || 'Failed to load attendee data.') + '</td></tr>';
        }
    } catch (err) {
        console.error(err);
        document.getElementById('attendeeTableBody').innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">Network error loading attendance list.</td></tr>';
    }
}

function exportAttendanceCsv() {
    if (!currentRosterData || !currentRosterData.attendees || currentRosterData.attendees.length === 0) {
        alert('No attendance data available to export.');
        return;
    }

    let csv = 'Student Name,Email,Student Number,Check-In Time,Status\n';
    currentRosterData.attendees.forEach(a => {
        csv += `"${a.student_name}","${a.student_email}","${a.student_number || ''}","${a.attended_at}","Present"\n`;
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = (currentSessionTitle.replace(/[^a-zA-Z0-9]/g, '_') || 'attendance') + '_roster.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function formatDateTime(dtStr) {
    if (!dtStr) return 'N/A';
    const d = new Date(dtStr);
    return isNaN(d.getTime()) ? dtStr : d.toLocaleString();
}

async function openLiveProfile(userId, classId) {
    const body = document.getElementById('liveProfileBody');
    const modalTitle = document.getElementById('liveProfileModalLabel');
    body.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading profile</span></div>';
    if (modalTitle) modalTitle.innerHTML = '<i class="bi bi-person-circle me-2"></i>Profile';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('liveProfileModal')).show();
    try {
        const response = await fetch('<?= url('api/live-profile.php') ?>?class_id=' + encodeURIComponent(classId) + '&user_id=' + encodeURIComponent(userId), { cache: 'no-store' });
        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'Profile unavailable');
        const profile = data.profile;
        const fallback = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(profile.name) + '&background=1e40af&color=ffffff&bold=true';
        if (modalTitle) modalTitle.innerHTML = '<i class="bi bi-person-circle me-2"></i>' + escapeConversationText(profile.name) + ' Profile';
        body.innerHTML = `<img src="${escapeConversationText(profile.avatar || fallback)}" class="rounded-circle live-profile-avatar mb-3" alt="${escapeConversationText(profile.name)}" onerror="this.onerror=null;this.src='${fallback}';"><h4 class="fw-bold mb-1">${escapeConversationText(profile.name)}</h4><span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 mb-3">${escapeConversationText(profile.role)}</span><p class="text-primary fw-semibold small mb-2">${escapeConversationText(profile.headline || '')}</p><p class="text-muted small mb-0">${escapeConversationText(profile.bio || '')}</p>`;
    } catch (error) {
        body.innerHTML = '<div class="text-danger"><i class="bi bi-exclamation-circle me-1"></i> Profile could not be loaded.</div>';
    }
}

function confirmDeleteLiveClass(classId, title) {
    if (!confirm('Are you sure you want to permanently delete the live session "' + title + '"? This will remove all messages, attendance records, and cannot be undone.')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= url('teacher/live-classes.php') ?>';
    form.innerHTML = '<input type="hidden" name="action" value="delete_class"><input type="hidden" name="class_id" value="' + classId + '">';
    document.body.appendChild(form);
    form.submit();
}

function confirmDeleteCurrentLiveClass() {
    if (!conversationClassId) return;
    if (!confirm('Are you sure you want to permanently delete this live stream? This will remove all messages, attendance records, and cannot be undone.')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= url('teacher/live-classes.php') ?>';
    form.innerHTML = '<input type="hidden" name="action" value="delete_class"><input type="hidden" name="class_id" value="' + conversationClassId + '">';
    document.body.appendChild(form);
    form.submit();
}

document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-profile-user-id]');
    if (trigger) openLiveProfile(trigger.dataset.profileUserId, trigger.dataset.profileClassId);
});

</script>

<!-- ======================== Voice Note Recording Modal ======================== -->
<div class="modal fade" id="voiceNoteModal" tabindex="-1" aria-labelledby="voiceNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg record-studio-card">
            <div class="modal-header bg-gradient text-white border-0 py-3" style="background: linear-gradient(135deg, #dc2626, #ea580c);">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-white bg-opacity-20 rounded-circle d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
                        <i class="bi bi-mic-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold mb-0" id="voiceNoteModalLabel">Voice Note Studio</h6>
                        <small class="text-white-50" id="vnSessionTitle">Live Session</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <!-- Recording Status & Timer -->
                <div class="mb-3">
                    <div id="vnStatus" class="fw-bold text-muted small text-uppercase letter-spacing-1 mb-1">Ready to record</div>
                    <div id="vnTimer" class="display-5 fw-bold font-monospace text-danger">00:00</div>
                </div>

                <!-- Waveform Visualizer -->
                <div class="rounded-4 bg-dark bg-opacity-95 mb-4 p-2 shadow-inner" style="height: 75px; position: relative;">
                    <canvas id="vnWaveform" class="w-100 h-100"></canvas>
                </div>

                <!-- Initial State: Big Record Button -->
                <div id="vnInitialControls" class="mb-2">
                    <button type="button" class="btn btn-danger record-studio-main-btn rounded-circle shadow-lg" id="vnBtnStart" onclick="vnStartRecording()" title="Click to start recording">
                        <i class="bi bi-mic-fill fs-2"></i>
                    </button>
                    <div class="text-muted small mt-2 fw-semibold">Click the microphone to start recording</div>
                </div>

                <!-- Recording In-Progress Controls -->
                <div class="d-none gap-2 justify-content-center mb-2" id="vnLiveControls">
                    <button type="button" class="btn btn-warning rounded-pill px-3.5 py-2 fw-bold d-inline-flex align-items-center gap-1.5 shadow-sm" id="vnBtnPause" onclick="vnPauseResume()">
                        <i class="bi bi-pause-fill fs-5"></i> <span>Pause</span>
                    </button>
                    <button type="button" class="btn btn-danger rounded-pill px-4 py-2 fw-bold d-inline-flex align-items-center gap-1.5 shadow-sm" id="vnBtnStop" onclick="vnStopRecording()">
                        <i class="bi bi-stop-fill fs-5"></i> <span>Finish Recording</span>
                    </button>
                </div>

                <!-- Recording Finished State: Preview & Instant Send -->
                <div id="vnPreviewWrap" class="d-none mt-3">
                    <div class="p-3 bg-light rounded-4 border mb-3">
                        <label class="form-label small fw-bold text-muted d-block text-start mb-2"><i class="bi bi-soundwave text-primary me-1"></i> Recording Preview:</label>
                        <audio id="vnPreviewAudio" controls class="w-100 rounded-3 mb-2" style="height: 42px;"></audio>
                    </div>

                    <div class="d-flex flex-column align-items-center gap-2">
                        <button type="button" class="btn btn-success btn-lg rounded-pill px-5 py-2.5 fw-bold shadow-lg d-inline-flex align-items-center justify-content-center gap-2 w-100 recording-send-btn" id="vnBtnPublish" onclick="vnPublish()">
                            <i class="bi bi-send-fill fs-5"></i>
                            <span>Send Note Now</span>
                        </button>
                        <button type="button" class="btn btn-link text-muted btn-sm text-decoration-none" onclick="vnDiscard()">
                            <i class="bi bi-arrow-repeat me-1"></i> Discard & Re-record
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================== Video Note Recording Modal ======================== -->
<div class="modal fade" id="videoNoteModal" tabindex="-1" aria-labelledby="videoNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg record-studio-card">
            <div class="modal-header bg-gradient text-white border-0 py-3" style="background: linear-gradient(135deg, #2563eb, #4f46e5);">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-white bg-opacity-20 rounded-circle d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
                        <i class="bi bi-camera-video-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold mb-0" id="videoNoteModalLabel">Video Note Studio</h6>
                        <small class="text-white-50" id="vidSessionTitle">Live Session</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <!-- Video Viewport -->
                <div class="rounded-4 bg-black position-relative mb-3 overflow-hidden shadow" style="aspect-ratio: 16/9; max-height: 380px;">
                    <video id="vidPreview" autoplay playsinline muted class="w-100 h-100" style="object-fit: cover; transform: scaleX(-1);"></video>
                    <video id="vidPlayback" controls playsinline class="w-100 h-100 d-none" style="object-fit: contain;"></video>
                    <div class="position-absolute top-0 start-0 w-100 d-flex justify-content-between align-items-center p-3">
                        <span id="vidRecBadge" class="badge bg-danger rounded-pill px-3 py-1.5 d-none animate-pulse"><i class="bi bi-record-circle me-1"></i>REC</span>
                        <span id="vidTimerOverlay" class="badge bg-dark bg-opacity-75 text-white rounded-pill px-3 py-1.5 font-monospace fs-6 d-none">00:00</span>
                    </div>
                </div>

                <!-- Status -->
                <div id="vidStatus" class="fw-bold text-muted small mb-3">Camera ready</div>

                <!-- Controls: Before Recording -->
                <div id="vidInitialControls" class="mb-2">
                    <button type="button" class="btn btn-primary btn-lg rounded-pill px-5 py-2.5 fw-bold shadow-lg d-inline-flex align-items-center gap-2" id="vidBtnStart" onclick="vidStartRecording()">
                        <i class="bi bi-camera-video-fill fs-5"></i> <span>Start Recording</span>
                    </button>
                </div>

                <!-- Controls: During Recording -->
                <div class="d-none gap-2 justify-content-center mb-2" id="vidLiveControls">
                    <button type="button" class="btn btn-warning rounded-pill px-3.5 py-2 fw-bold d-inline-flex align-items-center gap-1.5 shadow-sm" id="vidBtnPause" onclick="vidPauseResume()">
                        <i class="bi bi-pause-fill fs-5"></i> <span>Pause</span>
                    </button>
                    <button type="button" class="btn btn-danger rounded-pill px-4 py-2 fw-bold d-inline-flex align-items-center gap-1.5 shadow-sm" id="vidBtnStop" onclick="vidStopRecording()">
                        <i class="bi bi-stop-fill fs-5"></i> <span>Finish Recording</span>
                    </button>
                </div>

                <!-- Controls: After Recording (Instant Send) -->
                <div id="vidFinishedControls" class="d-none mt-3">
                    <div class="d-flex flex-column align-items-center gap-2">
                        <button type="button" class="btn btn-success btn-lg rounded-pill px-5 py-2.5 fw-bold shadow-lg d-inline-flex align-items-center justify-content-center gap-2 w-100 recording-send-btn" id="vidBtnPublish" onclick="vidPublish()">
                            <i class="bi bi-send-fill fs-5"></i>
                            <span>Send Note Now</span>
                        </button>
                        <button type="button" class="btn btn-link text-muted btn-sm text-decoration-none" onclick="vidDiscard()">
                            <i class="bi bi-arrow-repeat me-1"></i> Discard & Re-record
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================== Recording JavaScript ======================== -->
<script>
function showModernToast(type, message) {
    const toast = document.createElement('div');
    toast.className = 'position-fixed bottom-0 end-0 p-3';
    toast.style.zIndex = '99999';
    toast.innerHTML = `
        <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0 show shadow-lg rounded-4" role="alert">
            <div class="d-flex">
                <div class="toast-body fw-bold d-flex align-items-center gap-2">
                    <i class="bi ${type === 'success' ? 'bi-check-circle-fill fs-5' : 'bi-exclamation-triangle-fill fs-5'}"></i>
                    <span>${escapeConversationText(message)}</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    document.body.appendChild(toast);
    setTimeout(() => { toast.remove(); }, 5000);
}

// ===== Voice Note Recording =====
let vnStream = null, vnRecorder = null, vnChunks = [], vnBlob = null;
let vnTimerInterval = null, vnSeconds = 0;
let vnCourseId = 0, vnClassId = 0, vnAnalyser = null, vnAnimFrame = null;

function openVoiceNoteModal(classId, courseId, title) {
    vnClassId = classId;
    vnCourseId = courseId;
    const sessionTitleEl = document.getElementById('vnSessionTitle');
    if (sessionTitleEl) sessionTitleEl.textContent = title || 'Live Interactive Class';
    vnDiscard();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('voiceNoteModal')).show();
}

async function vnStartRecording() {
    try {
        vnStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const source = audioCtx.createMediaStreamSource(vnStream);
        vnAnalyser = audioCtx.createAnalyser();
        vnAnalyser.fftSize = 256;
        source.connect(vnAnalyser);
        vnDrawWaveform();

        vnRecorder = new MediaRecorder(vnStream, { mimeType: MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus' : 'audio/webm' });
        vnChunks = [];
        vnRecorder.ondataavailable = e => { if (e.data.size > 0) vnChunks.push(e.data); };
        vnRecorder.onstop = () => {
            // Stop stream tracks AFTER recorder has flushed all data
            if (vnStream) { vnStream.getTracks().forEach(t => t.stop()); }
            vnBlob = new Blob(vnChunks, { type: vnRecorder.mimeType });
            const url = URL.createObjectURL(vnBlob);
            const preview = document.getElementById('vnPreviewAudio');
            if (preview) preview.src = url;
            
            document.getElementById('vnInitialControls').classList.add('d-none');
            document.getElementById('vnLiveControls').classList.add('d-none');
            document.getElementById('vnLiveControls').classList.remove('d-flex');
            document.getElementById('vnPreviewWrap').classList.remove('d-none');
            document.getElementById('vnStatus').textContent = 'Recording ready — review and send';
            cancelAnimationFrame(vnAnimFrame);
        };
        vnRecorder.start(250);

        vnSeconds = 0;
        vnTimerInterval = setInterval(() => {
            vnSeconds++;
            document.getElementById('vnTimer').textContent = formatRecTime(vnSeconds);
        }, 1000);

        document.getElementById('vnInitialControls').classList.add('d-none');
        document.getElementById('vnLiveControls').classList.remove('d-none');
        document.getElementById('vnLiveControls').classList.add('d-flex');
        document.getElementById('vnPreviewWrap').classList.add('d-none');
        document.getElementById('vnStatus').textContent = '🔴 Recording live audio...';
    } catch (err) {
        alert('Microphone access denied. Please allow microphone permissions in your browser.');
    }
}

function vnPauseResume() {
    if (!vnRecorder) return;
    const pauseBtn = document.getElementById('vnBtnPause');
    if (vnRecorder.state === 'recording') {
        vnRecorder.pause();
        clearInterval(vnTimerInterval);
        if (pauseBtn) pauseBtn.innerHTML = '<i class="bi bi-play-fill fs-5"></i> <span>Resume</span>';
        document.getElementById('vnStatus').textContent = '⏸ Paused';
    } else {
        vnRecorder.resume();
        vnTimerInterval = setInterval(() => { vnSeconds++; document.getElementById('vnTimer').textContent = formatRecTime(vnSeconds); }, 1000);
        if (pauseBtn) pauseBtn.innerHTML = '<i class="bi bi-pause-fill fs-5"></i> <span>Pause</span>';
        document.getElementById('vnStatus').textContent = '🔴 Recording live audio...';
    }
}

function vnStopRecording() {
    if (vnRecorder && vnRecorder.state !== 'inactive') vnRecorder.stop();
    clearInterval(vnTimerInterval);
    // Stream tracks are now stopped inside onstop handler after blob is created
}

function vnDiscard() {
    vnBlob = null; vnChunks = []; vnSeconds = 0;
    clearInterval(vnTimerInterval);
    cancelAnimationFrame(vnAnimFrame);
    if (vnStream) { vnStream.getTracks().forEach(t => t.stop()); vnStream = null; }
    document.getElementById('vnTimer').textContent = '00:00';
    document.getElementById('vnStatus').textContent = 'Ready to record';
    document.getElementById('vnPreviewWrap').classList.add('d-none');
    document.getElementById('vnLiveControls').classList.add('d-none');
    document.getElementById('vnLiveControls').classList.remove('d-flex');
    document.getElementById('vnInitialControls').classList.remove('d-none');
    
    // Clear canvas
    const canvas = document.getElementById('vnWaveform');
    if (canvas) { const ctx = canvas.getContext('2d'); ctx.clearRect(0, 0, canvas.width, canvas.height); }
}

function vnDrawWaveform() {
    const canvas = document.getElementById('vnWaveform');
    if (!canvas || !vnAnalyser) return;
    const ctx = canvas.getContext('2d');
    canvas.width = canvas.offsetWidth * 2;
    canvas.height = canvas.offsetHeight * 2;
    const bufferLength = vnAnalyser.frequencyBinCount;
    const dataArray = new Uint8Array(bufferLength);

    function draw() {
        vnAnimFrame = requestAnimationFrame(draw);
        vnAnalyser.getByteFrequencyData(dataArray);
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        const barWidth = (canvas.width / bufferLength) * 2.5;
        let x = 0;
        for (let i = 0; i < bufferLength; i++) {
            const barHeight = (dataArray[i] / 255) * canvas.height;
            const gradient = ctx.createLinearGradient(0, canvas.height, 0, canvas.height - barHeight);
            gradient.addColorStop(0, '#ef4444');
            gradient.addColorStop(1, '#f97316');
            ctx.fillStyle = gradient;
            ctx.fillRect(x, canvas.height - barHeight, barWidth, barHeight);
            x += barWidth + 1;
        }
    }
    draw();
}

async function vnPublish() {
    if (!vnBlob) { alert('Please record a voice note first.'); return; }

    const btn = document.getElementById('vnBtnPublish');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Sending...';
    }

    const fd = new FormData();
    fd.append('class_id', vnClassId);
    fd.append('message_type', 'voice');
    fd.append('message_text', 'Voice note from the teacher');
    fd.append('duration_seconds', String(Math.max(1, vnSeconds)));
    fd.append('voice_note', vnBlob, 'live-voice-note-' + Date.now() + '.webm');

    try {
        const res = await fetch('<?= url("api/live-conversation.php") ?>', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('voiceNoteModal')).hide();
            vnDiscard();
            showModernToast('success', 'Voice note sent to the live class.');
            if (typeof loadConversation === 'function' && conversationClassId) {
                loadConversation();
            }
        } else {
                alert(data.message || 'Failed to send voice note.');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send-fill fs-5"></i> <span>Send Voice Note</span>';
            }
        }
    } catch (err) {
        alert('Network error. Please try again.');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send-fill fs-5"></i> <span>Send Voice Note</span>';
        }
    }
}

// ===== Video Note Recording =====
let vidStream = null, vidRecorder = null, vidChunks = [], vidBlob = null;
let vidTimerInterval = null, vidSeconds = 0, vidCourseId = 0, vidClassId = 0;

async function openVideoNoteModal(classId, courseId, title) {
    vidClassId = classId;
    vidCourseId = courseId;
    const sessionTitleEl = document.getElementById('vidSessionTitle');
    if (sessionTitleEl) sessionTitleEl.textContent = title || 'Live Interactive Class';
    vidDiscard();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('videoNoteModal')).show();

    try {
        vidStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } }, audio: true });
        document.getElementById('vidPreview').srcObject = vidStream;
        document.getElementById('vidPreview').classList.remove('d-none');
        document.getElementById('vidPlayback').classList.add('d-none');
        document.getElementById('vidStatus').textContent = 'Camera preview ready';
    } catch (err) {
        alert('Camera/Microphone access denied. Please allow permissions in your browser.');
    }
}

function vidStartRecording() {
    if (!vidStream) { alert('Camera not active.'); return; }
    vidRecorder = new MediaRecorder(vidStream, { mimeType: MediaRecorder.isTypeSupported('video/webm;codecs=vp9,opus') ? 'video/webm;codecs=vp9,opus' : 'video/webm' });
    vidChunks = [];
    vidRecorder.ondataavailable = e => { if (e.data.size > 0) vidChunks.push(e.data); };
    vidRecorder.onstop = () => {
        // Stop stream tracks AFTER recorder has flushed all data
        if (vidStream) { vidStream.getTracks().forEach(t => t.stop()); }
        vidBlob = new Blob(vidChunks, { type: vidRecorder.mimeType });
        const url = URL.createObjectURL(vidBlob);
        document.getElementById('vidPreview').classList.add('d-none');
        const playback = document.getElementById('vidPlayback');
        playback.src = url;
        playback.classList.remove('d-none');
        
        document.getElementById('vidInitialControls').classList.add('d-none');
        document.getElementById('vidLiveControls').classList.add('d-none');
        document.getElementById('vidLiveControls').classList.remove('d-flex');
        document.getElementById('vidFinishedControls').classList.remove('d-none');
        document.getElementById('vidStatus').textContent = 'Recording ready — review and send';
        document.getElementById('vidRecBadge').classList.add('d-none');
        document.getElementById('vidTimerOverlay').classList.add('d-none');
    };
    vidRecorder.start(250);

    vidSeconds = 0;
    vidTimerInterval = setInterval(() => {
        vidSeconds++;
        const t = formatRecTime(vidSeconds);
        document.getElementById('vidTimerOverlay').textContent = t;
    }, 1000);

    document.getElementById('vidInitialControls').classList.add('d-none');
    document.getElementById('vidLiveControls').classList.remove('d-none');
    document.getElementById('vidLiveControls').classList.add('d-flex');
    document.getElementById('vidFinishedControls').classList.add('d-none');
    document.getElementById('vidRecBadge').classList.remove('d-none');
    document.getElementById('vidTimerOverlay').classList.remove('d-none');
    document.getElementById('vidStatus').textContent = '🔴 Recording live video...';
}

function vidPauseResume() {
    if (!vidRecorder) return;
    const pauseBtn = document.getElementById('vidBtnPause');
    if (vidRecorder.state === 'recording') {
        vidRecorder.pause();
        clearInterval(vidTimerInterval);
        if (pauseBtn) pauseBtn.innerHTML = '<i class="bi bi-play-fill fs-5"></i> <span>Resume</span>';
        document.getElementById('vidStatus').textContent = '⏸ Paused';
    } else {
        vidRecorder.resume();
        vidTimerInterval = setInterval(() => { vidSeconds++; document.getElementById('vidTimerOverlay').textContent = formatRecTime(vidSeconds); }, 1000);
        if (pauseBtn) pauseBtn.innerHTML = '<i class="bi bi-pause-fill fs-5"></i> <span>Pause</span>';
        document.getElementById('vidStatus').textContent = '🔴 Recording live video...';
    }
}

function vidStopRecording() {
    if (vidRecorder && vidRecorder.state !== 'inactive') vidRecorder.stop();
    clearInterval(vidTimerInterval);
    // Stream tracks are now stopped inside onstop handler after blob is created
}

function vidDiscard() {
    vidBlob = null; vidChunks = []; vidSeconds = 0;
    clearInterval(vidTimerInterval);
    if (vidStream) { vidStream.getTracks().forEach(t => t.stop()); vidStream = null; }
    document.getElementById('vidPreview').srcObject = null;
    document.getElementById('vidPreview').classList.remove('d-none');
    document.getElementById('vidPlayback').classList.add('d-none');
    document.getElementById('vidPlayback').src = '';
    document.getElementById('vidRecBadge').classList.add('d-none');
    document.getElementById('vidTimerOverlay').classList.add('d-none');
    document.getElementById('vidStatus').textContent = 'Camera preview active';
    document.getElementById('vidInitialControls').classList.remove('d-none');
    document.getElementById('vidLiveControls').classList.add('d-none');
    document.getElementById('vidLiveControls').classList.remove('d-flex');
    document.getElementById('vidFinishedControls').classList.add('d-none');
}

async function vidPublish() {
    if (!vidBlob) { alert('Please record a video note first.'); return; }

    const btn = document.getElementById('vidBtnPublish');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Sending...';
    }

    const fd = new FormData();
    fd.append('class_id', vidClassId);
    fd.append('message_type', 'video');
    fd.append('message_text', 'Video note from the teacher');
    fd.append('duration_seconds', String(Math.max(1, vidSeconds)));
    fd.append('video_note', vidBlob, 'live-video-note-' + Date.now() + '.webm');

    try {
        const res = await fetch('<?= url("api/live-conversation.php") ?>', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('videoNoteModal')).hide();
            vidDiscard();
            showModernToast('success', 'Video note sent to the live class.');
            if (typeof loadConversation === 'function' && conversationClassId) {
                loadConversation();
            }
        } else {
            alert(data.message || 'Failed to send video note.');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send-fill fs-5"></i> <span>Send Note Now</span>';
            }
        }
    } catch (err) {
        alert('Network error. Please try again.');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send-fill fs-5"></i> <span>Send Note Now</span>';
        }
    }
}

// Cleanup camera when modals are closed
document.getElementById('voiceNoteModal').addEventListener('hidden.bs.modal', () => vnDiscard());
document.getElementById('videoNoteModal').addEventListener('hidden.bs.modal', () => vidDiscard());

function formatRecTime(s) {
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return String(m).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
}
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
