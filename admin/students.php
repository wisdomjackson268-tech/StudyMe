<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();

if (is_post() && isset($_POST['manage_enrollment'])) {
    $studentId   = (int)($_POST['student_id'] ?? 0);
    $actionType  = $_POST['action_type'] ?? '';
    $newCourseId = (int)($_POST['new_course_id'] ?? 0);
    $studentType = trim($_POST['student_type'] ?? '');
    $academicLvl = trim($_POST['academic_level'] ?? '');
    $targetExam  = trim($_POST['target_exam'] ?? '');

    if ($studentId) {
        if (!empty($studentType)) {
            $pdo->prepare("UPDATE students SET student_type = ?, academic_level = ?, target_exam = ? WHERE id = ?")
                ->execute([$studentType, $academicLvl ?: null, $targetExam ?: null, $studentId]);
        }

        if ($actionType === 'clear') {
            $stmt = $pdo->prepare("UPDATE enrollments SET status = 'cancelled' WHERE student_id = ? AND status = 'active'");
            $stmt->execute([$studentId]);
            set_flash('success', 'Student enrollment status updated successfully.');
        } elseif ($actionType === 'reassign' && $newCourseId) {

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
                set_flash('success', 'Student course and track successfully updated by Admin!');
            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash('error', 'Reassignment failed: ' . $e->getMessage());
            }
        } else {
            set_flash('success', 'Student academic track updated successfully.');
        }
    }
    redirect('admin/students.php');
}

$trackFilter = trim($_GET['track'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');

$sql = "
    SELECT s.id AS student_id, s.student_type, s.academic_level, s.target_exam,
           u.id AS user_id, u.first_name, u.last_name, u.email, u.avatar, u.created_at AS joined_at,
           e.id AS enrollment_id, e.progress, e.enrolled_at,
           c.id AS course_id, c.title AS course_title
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN enrollments e ON e.student_id = s.id AND e.status = 'active'
    LEFT JOIN courses c ON e.course_id = c.id
    WHERE 1=1
";
$params = [];

if ($trackFilter !== '' && in_array($trackFilter, ['secondary', 'university', 'tech'])) {
    $sql .= " AND s.student_type = ?";
    $params[] = $trackFilter;
}

if ($searchQuery !== '') {
    $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR c.title LIKE ?)";
    $like = "%{$searchQuery}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$publishedCourses = $pdo->query("SELECT id, title FROM courses WHERE status = 'published' ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

$countsStmt = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN student_type = 'secondary' THEN 1 ELSE 0 END) AS secondary_count,
        SUM(CASE WHEN student_type = 'university' THEN 1 ELSE 0 END) AS university_count,
        SUM(CASE WHEN student_type = 'tech' THEN 1 ELSE 0 END) AS tech_count
    FROM students
");
$trackCounts = $countsStmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'secondary_count' => 0, 'university_count' => 0, 'tech_count' => 0];

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-people-fill text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Student Registry &amp; Academic Track Management</h2>
    </div>
</div>

<!-- Track Filter Tabs -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="nav nav-pills gap-2">
                <a href="students.php" class="nav-link rounded-pill px-3 py-2 <?= empty($trackFilter) ? 'active bg-primary' : 'text-muted' ?>">
                    All Students <span class="badge bg-light text-dark ms-1"><?= (int)$trackCounts['total'] ?></span>
                </a>
                <a href="students.php?track=secondary" class="nav-link rounded-pill px-3 py-2 <?= $trackFilter === 'secondary' ? 'active bg-success' : 'text-muted' ?>">
                    <i class="bi bi-mortarboard me-1"></i> Secondary School <span class="badge bg-light text-dark ms-1"><?= (int)$trackCounts['secondary_count'] ?></span>
                </a>
                <a href="students.php?track=university" class="nav-link rounded-pill px-3 py-2 <?= $trackFilter === 'university' ? 'active bg-primary' : 'text-muted' ?>">
                    <i class="bi bi-buildings me-1"></i> University <span class="badge bg-light text-dark ms-1"><?= (int)$trackCounts['university_count'] ?></span>
                </a>
                <a href="students.php?track=tech" class="nav-link rounded-pill px-3 py-2 <?= $trackFilter === 'tech' ? 'active bg-info' : 'text-muted' ?>">
                    <i class="bi bi-code-slash me-1"></i> Tech Bootcamp <span class="badge bg-light text-dark ms-1"><?= (int)$trackCounts['tech_count'] ?></span>
                </a>
            </div>
            <form method="GET" class="d-flex gap-2">
                <?php if ($trackFilter): ?><input type="hidden" name="track" value="<?= e($trackFilter) ?>"><?php endif; ?>
                <div class="input-group">
                    <input type="text" name="q" class="form-control rounded-start-pill ps-3" placeholder="Search by name, email..." value="<?= e($searchQuery) ?>">
                    <button class="btn btn-outline-secondary rounded-end-pill px-3" type="submit"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between">
        <span class="fw-bold small text-muted"><i class="bi bi-person-lines-fill me-1"></i> Student Track &amp; Course Roster</span>
        <span class="badge bg-primary rounded-pill px-3 py-1">Showing: <?= count($students) ?></span>
    </div>
    <?php if (!empty($students)): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Student</th>
                        <th>Academic Track</th>
                        <th>Level / Target Exam</th>
                        <th>Active Course</th>
                        <th>Progress</th>
                        <th class="text-end pe-4">Actions</th>
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
                                <?php if ($s['student_type'] === 'secondary'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-1 fw-semibold">
                                        <i class="bi bi-mortarboard me-1"></i> Secondary School
                                    </span>
                                <?php elseif ($s['student_type'] === 'tech'): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 rounded-pill px-3 py-1 fw-semibold">
                                        <i class="bi bi-code-slash me-1"></i> Tech Bootcamp
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-3 py-1 fw-semibold">
                                        <i class="bi bi-buildings me-1"></i> University
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-dark small"><?= e($s['academic_level'] ?: 'Not Set') ?></span>
                                    <?php if ($s['target_exam']): ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-dark rounded-pill px-2 py-0 small mt-1" style="width: fit-content;">
                                            <?= e($s['target_exam']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($s['course_title'])): ?>
                                    <span class="badge bg-light text-dark border rounded-pill px-3 py-1 fw-normal">
                                        <i class="bi bi-journal-check text-primary me-1"></i><?= e($s['course_title']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">Standard Curriculum</span>
                                <?php endif; ?>
                            </td>
                            <td style="width: 140px;">
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
                                    <i class="bi bi-pencil-square me-1"></i> Manage
                                </button>

                                <div class="modal fade text-start" id="reassignModal<?= $s['student_id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <div class="modal-header border-0 pb-0">
                                                <h5 class="modal-title fw-bold">Manage Student Track &amp; Course</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <input type="hidden" name="manage_enrollment" value="1">
                                                <input type="hidden" name="student_id" value="<?= $s['student_id'] ?>">
                                                <div class="modal-body py-3">
                                                    <p class="small text-muted mb-3">
                                                        Student: <strong><?= e($s['first_name'] . ' ' . $s['last_name']) ?></strong> (<?= e($s['email']) ?>)
                                                    </p>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">Academic Track</label>
                                                        <select name="student_type" class="form-select rounded-3">
                                                            <option value="secondary" <?= $s['student_type'] === 'secondary' ? 'selected' : '' ?>>Secondary School (SS1 - SS3 / WAEC / NECO / JAMB)</option>
                                                            <option value="university" <?= $s['student_type'] === 'university' ? 'selected' : '' ?>>University (Undergraduate / Degree)</option>
                                                            <option value="tech" <?= $s['student_type'] === 'tech' ? 'selected' : '' ?>>Tech Bootcamp (Software &amp; Digital Skills)</option>
                                                        </select>
                                                    </div>

                                                    <div class="row g-2 mb-3">
                                                        <div class="col-6">
                                                            <label class="form-label fw-bold small">Academic Level</label>
                                                            <select name="academic_level" class="form-select rounded-3">
                                                                <option value="SS3" <?= ($s['academic_level'] ?? '') === 'SS3' ? 'selected' : '' ?>>SS 3</option>
                                                                <option value="SS2" <?= ($s['academic_level'] ?? '') === 'SS2' ? 'selected' : '' ?>>SS 2</option>
                                                                <option value="SS1" <?= ($s['academic_level'] ?? '') === 'SS1' ? 'selected' : '' ?>>SS 1</option>
                                                                <option value="100 Level" <?= ($s['academic_level'] ?? '') === '100 Level' ? 'selected' : '' ?>>100 Level</option>
                                                                <option value="200 Level" <?= ($s['academic_level'] ?? '') === '200 Level' ? 'selected' : '' ?>>200 Level</option>
                                                                <option value="300 Level" <?= ($s['academic_level'] ?? '') === '300 Level' ? 'selected' : '' ?>>300 Level</option>
                                                                <option value="400 Level" <?= ($s['academic_level'] ?? '') === '400 Level' ? 'selected' : '' ?>>400 Level</option>
                                                                <option value="500 Level" <?= ($s['academic_level'] ?? '') === '500 Level' ? 'selected' : '' ?>>500 Level</option>
                                                                <option value="Bootcamp" <?= ($s['academic_level'] ?? '') === 'Bootcamp' ? 'selected' : '' ?>>Bootcamp</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-6">
                                                            <label class="form-label fw-bold small">Target Exam / Goal</label>
                                                            <select name="target_exam" class="form-select rounded-3">
                                                                <option value="WAEC / JAMB" <?= ($s['target_exam'] ?? '') === 'WAEC / JAMB' ? 'selected' : '' ?>>WAEC / JAMB</option>
                                                                <option value="WAEC" <?= ($s['target_exam'] ?? '') === 'WAEC' ? 'selected' : '' ?>>WAEC</option>
                                                                <option value="NECO" <?= ($s['target_exam'] ?? '') === 'NECO' ? 'selected' : '' ?>>NECO</option>
                                                                <option value="JAMB UTME" <?= ($s['target_exam'] ?? '') === 'JAMB UTME' ? 'selected' : '' ?>>JAMB UTME</option>
                                                                <option value="Semester Exams" <?= ($s['target_exam'] ?? '') === 'Semester Exams' ? 'selected' : '' ?>>Semester Exams</option>
                                                                <option value="Portfolio / Job" <?= ($s['target_exam'] ?? '') === 'Portfolio / Job' ? 'selected' : '' ?>>Portfolio / Job</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="border-top pt-3 mt-3">
                                                        <label class="form-label fw-bold small">Enrolled Course (University / Tech only)</label>
                                                        <select name="action_type" class="form-select rounded-3 mb-2">
                                                            <option value="">Keep Existing Course Enrollment</option>
                                                            <option value="reassign">Reassign / Change Course</option>
                                                            <option value="clear">Clear Active Course Access</option>
                                                        </select>
                                                        <select name="new_course_id" class="form-select rounded-3">
                                                            <option value="">-- Select New Course (if reassigning) --</option>
                                                            <?php foreach ($publishedCourses as $pc): ?>
                                                                <option value="<?= $pc['id'] ?>"><?= e($pc['title']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-0 pt-0">
                                                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary rounded-pill fw-bold px-4">Save Changes</button>
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
        <div class="p-5 text-center text-muted">No students match your filter criteria.</div>
    <?php endif; ?>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
