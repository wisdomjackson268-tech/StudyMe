<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();

if (is_post()) {
    $priceTech       = (float)($_POST['price_tech'] ?? 10000.00);
    $priceUniversity = (float)($_POST['price_university'] ?? 4000.00);
    $priceSecondary  = (float)($_POST['price_secondary'] ?? 3000.00);
    $priceTeacher    = (float)($_POST['price_teacher'] ?? 5000.00);

    $updates = [
        'price_tech'       => number_format($priceTech, 2, '.', ''),
        'price_university' => number_format($priceUniversity, 2, '.', ''),
        'price_secondary'  => number_format($priceSecondary, 2, '.', ''),
        'price_teacher'    => number_format($priceTeacher, 2, '.', ''),
    ];

    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, setting_type)
            VALUES (?, ?, 'text')
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        foreach ($updates as $key => $val) {
            $stmt->execute([$key, $val]);
        }

        log_user_activity(current_user('id'), 'Admin Pricing Rate Update', 'Updated official platform pricing rates.');
        set_flash('success', 'Official pricing rates updated in database successfully!');
        redirect('admin/pricing.php');
    } catch (Exception $e) {
        set_flash('error', 'Update error: ' . $e->getMessage());
    }
}

$rates = get_official_pricing_rates();

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-tags-fill text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Official Course Pricing Configuration</h2>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-cash-stack text-success me-2"></i>Official Rates (Stored in Database)</h5>
            <form method="POST">

                <div class="mb-4 p-3 bg-light rounded-3 border border-primary border-opacity-25">
                    <label class="form-label fw-bold text-primary fs-5 mb-1">
                        <i class="bi bi-cpu me-1"></i> Technology Courses Rate
                    </label>
                    <p class="text-muted small mb-2">Applies to Python, Web Dev, JavaScript, PHP, AI, Machine Learning, Cybersecurity, Data Science, etc. (Per Selected Course)</p>
                    <div class="input-group">
                        <span class="input-group-text fw-bold">₦</span>
                        <input type="number" name="price_tech" class="form-control form-control-lg fw-bold" value="<?= (int)$rates['tech'] ?>" step="100" min="0" required>
                    </div>
                </div>

                <div class="mb-4 p-3 bg-light rounded-3 border">
                    <label class="form-label fw-bold text-main fs-5 mb-1">
                        <i class="bi bi-mortarboard me-1"></i> University Courses Rate
                    </label>
                    <p class="text-muted small mb-2">Applies to University Mathematics, Computer Science, Physics, Economics, etc. (Per Selected Course)</p>
                    <div class="input-group">
                        <span class="input-group-text fw-bold">₦</span>
                        <input type="number" name="price_university" class="form-control form-control-lg fw-bold" value="<?= (int)$rates['university'] ?>" step="100" min="0" required>
                    </div>
                </div>

                <div class="mb-4 p-3 bg-light rounded-3 border">
                    <label class="form-label fw-bold text-main fs-5 mb-1">
                        <i class="bi bi-book me-1"></i> Secondary / WAEC / NECO Rate
                    </label>
                    <p class="text-muted small mb-2">Applies to WAEC Math, WAEC Biology, NECO Physics, Secondary Subjects, etc. (Per Selected Subject)</p>
                    <div class="input-group">
                        <span class="input-group-text fw-bold">₦</span>
                        <input type="number" name="price_secondary" class="form-control form-control-lg fw-bold" value="<?= (int)$rates['secondary'] ?>" step="100" min="0" required>
                    </div>
                </div>

                <div class="mb-4 p-3 bg-light rounded-3 border">
                    <label class="form-label fw-bold text-success fs-5 mb-1">
                        <i class="bi bi-person-badge me-1"></i> Teacher Subscription Access Rate
                    </label>
                    <p class="text-muted small mb-2">Applies to Teacher Access (treated separately from student course enrollments)</p>
                    <div class="input-group">
                        <span class="input-group-text fw-bold">₦</span>
                        <input type="number" name="price_teacher" class="form-control form-control-lg fw-bold" value="<?= (int)$rates['teacher'] ?>" step="100" min="0" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold py-3 shadow">
                    <i class="bi bi-save me-1"></i> Save Official Pricing Rates
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 p-4 sticky-top" style="top: 90px;">
            <h5 class="fw-bold mb-3"><i class="bi bi-shield-lock-fill text-warning me-2"></i>Pricing &amp; Access Policy</h5>
            <ul class="list-unstyled small text-muted d-flex flex-column gap-3 mb-0">
                <li class="d-flex align-items-start gap-2">
                    <i class="bi bi-check-circle-fill text-success fs-5 flex-shrink-0"></i>
                    <div>
                        <strong class="text-main">One Course Access:</strong> Enrolling in Python grants access to Python ONLY. Category does not unlock all courses.
                    </div>
                </li>
                <li class="d-flex align-items-start gap-2">
                    <i class="bi bi-check-circle-fill text-success fs-5 flex-shrink-0"></i>
                    <div>
                        <strong class="text-main">Dynamic Server Pricing:</strong> Prices are calculated dynamically on the server from these database rates. Client inputs cannot tamper with rates.
                    </div>
                </li>
                <li class="d-flex align-items-start gap-2">
                    <i class="bi bi-check-circle-fill text-success fs-5 flex-shrink-0"></i>
                    <div>
                        <strong class="text-main">Verified Payments Only:</strong> Course access is activated strictly after payment verification.
                    </div>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
