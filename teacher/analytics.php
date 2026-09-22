<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/live_classes.php';
require_once BASE_PATH . '/includes/functions/courses.php';

secure_page(ROLE_TEACHER);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$user = current_user();
$pdo  = getDBConnection();
$uid  = (int)$user['id'];

$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;

$totalStudents = 0;
$totalCourses  = 0;
$totalQuizzes  = 0;
$totalTasks    = 0;
$attendanceAnalytics = [
    'total_sessions'            => 0,
    'live_sessions'             => 0,
    'ended_sessions'            => 0,
    'total_attendances'         => 0,
    'unique_attending_students' => 0,
    'overall_attendance_rate'   => 0,
    'sessions'                  => []
];

if ($tid) {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE c.teacher_id = ?");
    $stmt->execute([$tid]);
    $totalStudents = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE teacher_id = ?");
    $stmt->execute([$tid]);
    $totalCourses = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quizzes q JOIN courses c ON q.course_id = c.id WHERE c.teacher_id = ?");
    $stmt->execute([$tid]);
    $totalQuizzes = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM assignments a JOIN courses c ON a.course_id = c.id WHERE c.teacher_id = ?");
    $stmt->execute([$tid]);
    $totalTasks = (int)$stmt->fetchColumn();

    $attendanceAnalytics = get_teacher_attendance_analytics($tid);
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-graph-up-arrow text-primary me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0">Performance &amp; Attendance Analytics</h2>
    </div>
    <div>
        <a href="<?= url('teacher/live-classes.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold">
            <i class="bi bi-broadcast me-1"></i> Manage Live Sessions
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-journal-code"></i></div>
            <div><div class="stat-value"><?= $totalCourses ?></div><p class="stat-label">Active Courses</p></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-people-fill"></i></div>
            <div><div class="stat-value"><?= $totalStudents ?></div><p class="stat-label">Total Students</p></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="bi bi-patch-question-fill"></i></div>
            <div><div class="stat-value"><?= $totalQuizzes ?></div><p class="stat-label">Quizzes Created</p></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-list-task"></i></div>
            <div><div class="stat-value"><?= $totalTasks ?></div><p class="stat-label">Tasks Published</p></div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
    <div class="card-header bg-white border-0 p-4 pb-0">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <p class="text-danger fw-bold text-uppercase small mb-1">
                    <i class="bi bi-broadcast me-1"></i> Real-Time Engagement
                </p>
                <h4 class="fw-bold mb-1">Live Lesson Attendance Analytics</h4>
                <p class="text-muted small mb-0">Analysis of students who attended live interactive sessions and marked attendance while classes were active.</p>
            </div>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 fw-bold">
                <i class="bi bi-check2-circle me-1"></i> <?= $attendanceAnalytics['overall_attendance_rate'] ?>% Avg. Attendance Rate
            </span>
        </div>
    </div>
    <div class="card-body p-4">

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="p-3 bg-light rounded-3 text-center border">
                    <div class="text-muted small fw-semibold mb-1">Total Live Sessions</div>
                    <div class="h3 fw-bold text-dark mb-0"><?= $attendanceAnalytics['total_sessions'] ?></div>
                    <div class="small text-muted mt-1"><?= $attendanceAnalytics['ended_sessions'] ?> Completed &bull; <?= $attendanceAnalytics['live_sessions'] ?> Active</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 bg-light rounded-3 text-center border">
                    <div class="text-muted small fw-semibold mb-1">Total Attendances Marked</div>
                    <div class="h3 fw-bold text-success mb-0"><?= $attendanceAnalytics['total_attendances'] ?></div>
                    <div class="small text-muted mt-1">Check-ins recorded</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 bg-light rounded-3 text-center border">
                    <div class="text-muted small fw-semibold mb-1">Unique Students Attended</div>
                    <div class="h3 fw-bold text-primary mb-0"><?= $attendanceAnalytics['unique_attending_students'] ?></div>
                    <div class="small text-muted mt-1">Distinct attendees</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 bg-light rounded-3 text-center border">
                    <div class="text-muted small fw-semibold mb-1">Overall Participation Rate</div>
                    <div class="h3 fw-bold text-danger mb-0"><?= $attendanceAnalytics['overall_attendance_rate'] ?>%</div>
                    <div class="small text-muted mt-1">Enrolled vs. Present</div>
                </div>
            </div>
        </div>

        <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-table me-2 text-primary"></i> Session Breakdown &amp; Roster Records</h5>

        <?php if (empty($attendanceAnalytics['sessions'])): ?>
            <div class="text-center py-5 text-muted bg-light rounded-4">
                <i class="bi bi-calendar-x display-4 d-block mb-2 text-muted"></i>
                <p class="mb-2 fw-semibold">No live sessions recorded yet.</p>
                <a href="<?= url('teacher/live-classes.php') ?>" class="btn btn-sm btn-primary rounded-pill px-3">
                    <i class="bi bi-plus-circle me-1"></i> Schedule a Live Class
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 border rounded-3 overflow-hidden">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">Session Title &amp; Course</th>
                            <th>Date &amp; Time</th>
                            <th>Status</th>
                            <th>Attended / Enrolled</th>
                            <th style="min-width: 150px;">Attendance Rate</th>
                            <th class="text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendanceAnalytics['sessions'] as $s):
                            $isLive = ($s['status'] === 'live');
                            $enrolled = (int)$s['total_enrolled'];
                            $attended = (int)$s['total_attended'];
                            $rate     = (float)$s['attendance_rate'];
                        ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($s['title']) ?></div>
                                    <small class="text-primary fw-semibold"><i class="bi bi-journal-bookmark me-1"></i> <?= htmlspecialchars($s['course_title']) ?></small>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark"><?= date('M j, Y @ g:i A', strtotime($s['scheduled_at'])) ?></div>
                                    <small class="text-muted"><?= (int)$s['duration_minutes'] ?> mins</small>
                                </td>
                                <td>
                                    <span class="badge <?= $isLive ? 'bg-danger text-white animate-pulse' : ($s['status'] === 'ended' ? 'bg-secondary text-white' : 'bg-primary text-white') ?> rounded-pill px-3 py-1">
                                        <?= $isLive ? '🔴 LIVE NOW' : strtoupper($s['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= $attended ?> / <?= $enrolled ?></div>
                                    <small class="text-muted">Students present</small>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $rate ?>%;"></div>
                                        </div>
                                        <span class="small fw-bold text-success"><?= $rate ?>%</span>
                                    </div>
                                </td>
                                <td class="text-end pe-3">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold" onclick="viewAttendanceRoster(<?= (int)$s['id'] ?>, '<?= addslashes(htmlspecialchars($s['title'])) ?>')">
                                        <i class="bi bi-people-fill me-1"></i> Roster
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

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

<script>
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
                document.getElementById('attendeeTableBody').innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><i class="bi bi-people me-1"></i> No students marked attendance for this session yet.</td></tr>';
            } else {
                let html = '';
                attendees.forEach(att => {
                    const avatar = att.student_avatar || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(att.student_name) + '&background=4f46e5&color=ffffff&bold=true';
                    html += `<tr>
                        <td class="ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <img src="${avatar}" class="rounded-circle" style="width:32px; height:32px; object-fit:cover;" onerror="this.src='https://ui-avatars.com/api/?name=Student&background=4f46e5&color=ffffff';">
                                <div>
                                    <div class="fw-bold text-dark small">${escapeHtml(att.student_name)}</div>
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
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
