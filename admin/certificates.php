<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();

$stmt = $pdo->prepare("
    SELECT cert.*, c.title AS course_title,
           u.first_name, u.last_name, u.email
    FROM certificates cert
    JOIN courses c ON cert.course_id = c.id
    JOIN students s ON cert.student_id = s.id
    JOIN users u ON s.user_id = u.id
    ORDER BY cert.issued_at DESC
");
$stmt->execute();
$certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-award-fill text-warning me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Issued Digital Certificates</h2>
    </div>
    <div class="text-muted small">Total: <strong><?= count($certificates) ?></strong> certificates</div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light" style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em;">
                <tr>
                    <th class="ps-4">Certificate ID</th>
                    <th>Recipient</th>
                    <th>Course Completed</th>
                    <th>Issue Date</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($certificates as $cert): ?>
                <tr>
                    <td class="ps-4"><code><?= e($cert['certificate_number']) ?></code></td>
                    <td>
                        <div class="fw-bold text-main"><?= e($cert['first_name'] . ' ' . $cert['last_name']) ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= e($cert['email']) ?></div>
                    </td>
                    <td class="fw-semibold"><?= e($cert['course_title']) ?></td>
                    <td class="text-muted"><?= date('d M Y', strtotime($cert['issued_at'])) ?></td>
                    <td class="text-end pe-4">
                        <a href="<?= url('certificates/verify.php?code=' . urlencode($cert['certificate_number'])) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3" target="_blank">
                            <i class="bi bi-patch-check me-1"></i> Verify
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($certificates)): ?>
                <tr>
                    <td colspan="5" class="text-center py-5 text-muted">No certificates issued yet. Certificates are issued automatically upon 100% course completion.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
