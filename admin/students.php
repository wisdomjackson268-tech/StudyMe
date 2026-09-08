<?php
/**
 * StudyMe AI Platform — Admin Student & Single Course Management
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();

// Handle Admin Course Reassignment / Removal
if (is_post() && isset($_POST['manage_enrollment'])) {
    $studentId   = (int)($_POST['student_id'] ?? 0);
    $actionType  = $_POST['action_type'] ?? '';
    $newCourseId = (int)($_POST['new_course_id'] ?? 0);

    if ($studentId) {
        if ($actionType === 'clear') {
            $stmt = $pdo->prepare("UPDATE enrollments SET status = 'cancelled' WHERE student_id = ? AND status = 'active'");
            $stmt->execute([$studentId]);
            set_flash('success', 'Student single course enrollment cleared successfully.');
        } elseif ($actionType === 'reassign' && $newCourseId) {
            // Cancel old enrollment and set new enrollment
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE enrollments SET status = 'cancelled' WHERE student_id = ? AND status = 'active'");
                $stmt->execute([$studentId]);

                $stmt2 = $pdo->prepare("
                    INSERT INTO enrollments (student_id, course_id, status, progress, enrolled_at)
                    VALUES (?, ?, 'active', 0.00, NOW())
                    ON DUPLICATE KEY UPDATE status = 'active'
                ");
                $stmt2->execute([$studentId, $newCourseId]);
                $pdo->commit();
                set_flash('success', 'Student single course successfully reassigned by Admin!');
            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash('error', 'Reassignment failed: ' . $e->getMessage());
            }
        }
    }
    redirect('admin/students.php');
}

// Fetch all students with their single active course (or unassigned status)
$stmt = $pdo->prepare("
    SELECT s.id AS student_id, u.id AS user_id, u.first_name, u.last_name, u.email, u.avatar, u.created_at AS joined_at,
           e.id AS enrollment_id, e.progress, e.enrolled_at,
           c.id AS course_id, c.title AS course_title
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN enrollments e ON e.student_id = s.id AND e.status = 'active'
    LEFT JOIN courses c ON e.course_id = c.id
    ORDER BY u.created_at DESC
");
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch published courses for reassignment dropdown
$publishedCourses = $pdo->query("SELECT id, title FROM courses WHERE status = 'published' ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-people-fill text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Student Enrollment &amp; Course Management</h2>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between">
        <span class="fw-bold small text-muted"><i class="bi bi-info-circle me-1"></i> One-Course-Per-Student Enforcement System</span>
        <span class="badge bg-primary rounded-pill px-3 py-1">Total Registered: <?= count($students) ?></span>
    </div>
    <?php if (!empty($students)): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Student</th>
                        <th>Single Active Course</th>
                        <th>Enrolled Date</th>
                        <th>Progress</th>
                        <th class="text-end pe-4">Admin Override Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                                        <?= strtoupper(substr($s['first_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-main"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></div>
                                        <small class="text-muted"><?= e($s['email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($s['course_title'])): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-2 fw-semibold">
                                        <i class="bi bi-journal-check me-1"></i><?= e($s['course_title']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-muted rounded-pill px-3 py-1">
                                        No active course selected
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted">
                                <?= $s['enrolled_at'] ? date('M d, Y', strtotime($s['enrolled_at'])) : '—' ?>
                            </td>
                            <td style="width: 160px;">
                                <?php if (!empty($s['course_title'])): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height:6px;">
                                            <div class="progress-bar bg-success" style="width: <?= (float)$s['progress'] ?>%;"></div>
                                        </div>
                                        <span class="small fw-bold"><?= (int)$s['progress'] ?>%</span>
                                    </div>
                                <?php else: ?>
                                    <span class="small text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#reassignModal<?= $s['student_id'] ?>">
                                    <i class="bi bi-pencil-square me-1"></i> Manage Access
                                </button>

                                <!-- Admin Reassign Modal -->
                                <div class="modal fade text-start" id="reassignModal<?= $s['student_id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <div class="modal-header border-0 pb-0">
                                                <h5 class="modal-title fw-bold">Manage Course Access</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <input type="hidden" name="manage_enrollment" value="1">
                                                <input type="hidden" name="student_id" value="<?= $s['student_id'] ?>">
                                                <div class="modal-body py-4">
                                                    <p class="small text-muted mb-3">
                                                        Student: <strong><?= e($s['first_name'] . ' ' . $s['last_name']) ?></strong><br>
                                                        Current Course: <strong><?= e($s['course_title'] ?: 'None') ?></strong>
                                                    </p>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Select Reassignment Action:</label>
                                                        <select name="action_type" class="form-select rounded-3 mb-3" required>
                                                            <option value="reassign">Reassign / Change Single Course</option>
                                                            <option value="clear">Clear Active Course Access</option>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">New Course:</label>
                                                        <select name="new_course_id" class="form-select rounded-3">
                                                            <option value="">-- Select New Course --</option>
                                                            <?php foreach ($publishedCourses as $pc): ?>
                                                                <option value="<?= $pc['id'] ?>"><?= e($pc['title']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-0 pt-0">
                                                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary rounded-pill fw-bold">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="p-5 text-center text-muted">No students currently registered.</div>
    <?php endif; ?>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
