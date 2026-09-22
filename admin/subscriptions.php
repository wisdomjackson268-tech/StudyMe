<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();
$plans = $pdo->query("SELECT * FROM subscription_plans ORDER BY price ASC")->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-award-fill text-warning me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Subscription Plans &amp; Tiers</h2>
    </div>
</div>

<div class="row g-4">
    <?php foreach ($plans as $p): ?>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-1 me-auto mb-2 text-uppercase fw-bold"><?= e($p['billing_cycle']) ?></span>
                <h4 class="fw-bold mb-1"><?= e($p['name']) ?></h4>
                <div class="display-6 fw-bold text-primary my-3">₦<?= number_format($p['price'], 2) ?></div>
                <p class="text-muted small mb-0"><?= e($p['description']) ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
