<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();

$stmt = $pdo->prepare("
    SELECT ta.*, u.first_name, u.last_name, u.email, u.avatar
    FROM teacher_applications ta
    JOIN users u ON ta.user_id = u.id
    ORDER BY ta.created_at DESC
");
$stmt->execute();
$apps = $stmt->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-patch-check-fill text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Teacher Applications</h2>
    </div>
</div>

<?php if (!empty($apps)): ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Applicant</th>
                        <th>Specialization</th>
                        <th>Qualification</th>
                        <th>Experience</th>
                        <th>Applied Date</th>
                        <th class="text-end pe-4">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($apps as $a): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-main"><?= e($a['first_name'] . ' ' . $a['last_name']) ?></div>
                                <small class="text-muted"><?= e($a['email']) ?></small>
                            </td>
                            <td><span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1"><?= e($a['specialization'] ?: 'General') ?></span></td>
                            <td class="small text-muted"><?= e($a['qualification'] ?: 'Not specified') ?></td>
                            <td class="fw-bold text-main"><?= (int)$a['experience_years'] ?> years</td>
                            <td class="small text-muted"><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
                            <td class="text-end pe-4">
                                <span class="badge <?= $a['status'] === 'approved' ? 'bg-success' : 'bg-warning text-dark' ?> rounded-pill">
                                    <?= ucfirst($a['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
        <i class="bi bi-person-badge text-muted display-4 mb-3"></i>
        <h5 class="fw-bold">No Pending Teacher Applications</h5>
        <p class="text-muted small mb-0">When new users apply to become instructors, their applications will be listed here.</p>
    </div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
