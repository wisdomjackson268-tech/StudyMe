<?php
/**
 * StudyMe AI Platform — System Settings & Platform Administration
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/referrals.php';

secure_page(ROLE_ADMIN);
$pdo = getDBConnection();
$user = current_user();

// Handle settings update
if (is_post()) {
    $platformName    = trim($_POST['platform_name'] ?? 'StudyMe AI Platform');
    $supportEmail    = trim($_POST['support_email'] ?? 'support@studyme.online');
    $currency        = trim($_POST['currency'] ?? 'NGN');
    $rateTechnology  = (float)($_POST['bonus_rate_technology'] ?? 1500);
    $rateUniversity  = (float)($_POST['bonus_rate_university'] ?? 1000);
    $rateSecondary   = (float)($_POST['bonus_rate_secondary'] ?? 500);
    $rateTeacher     = (float)($_POST['bonus_rate_teacher'] ?? 1000);
    $aiProvider      = trim($_POST['ai_provider'] ?? 'gemini');

    $stmtSet = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_type) VALUES (?, ?, 'text') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmtSet->execute(['platform_name', $platformName]);
    $stmtSet->execute(['support_email', $supportEmail]);
    $stmtSet->execute(['currency', $currency]);
    $stmtSet->execute(['bonus_rate_technology', number_format($rateTechnology, 2, '.', '')]);
    $stmtSet->execute(['bonus_rate_university', number_format($rateUniversity, 2, '.', '')]);
    $stmtSet->execute(['bonus_rate_secondary', number_format($rateSecondary, 2, '.', '')]);
    $stmtSet->execute(['bonus_rate_teacher', number_format($rateTeacher, 2, '.', '')]);
    $stmtSet->execute(['ai_provider', $aiProvider]);

    set_flash('success', 'System settings saved and applied successfully!');
    redirect('admin/system-settings.php');
}

// Load existing settings
$settingsRows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$platformName   = $settingsRows['platform_name'] ?? 'StudyMe AI Platform';
$supportEmail   = $settingsRows['support_email'] ?? 'support@studyme.online';
$currency       = $settingsRows['currency'] ?? 'NGN';
$rateTechnology = $settingsRows['bonus_rate_technology'] ?? '1500.00';
$rateUniversity = $settingsRows['bonus_rate_university'] ?? '1000.00';
$rateSecondary  = $settingsRows['bonus_rate_secondary'] ?? '500.00';
$rateTeacher    = $settingsRows['bonus_rate_teacher'] ?? '1000.00';
$aiProvider     = $settingsRows['ai_provider'] ?? 'gemini';

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-sliders text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">System Settings &amp; Global Configuration</h2>
    </div>
</div>

<form action="<?= url('admin/system-settings.php') ?>" method="POST">
    <div class="row g-4">
        
        <!-- General Platform Configuration -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white">
                <h5 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-globe text-primary"></i> General Platform Settings
                </h5>

                <div class="mb-3">
                    <label for="platform_name" class="form-label fw-semibold small">Platform Brand Name</label>
                    <input type="text" name="platform_name" id="platform_name" class="form-control rounded-3" value="<?= e($platformName) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="support_email" class="form-label fw-semibold small">Customer Support Email</label>
                    <input type="email" name="support_email" id="support_email" class="form-control rounded-3" value="<?= e($supportEmail) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="currency" class="form-label fw-semibold small">Base Operating Currency</label>
                    <select name="currency" id="currency" class="form-select rounded-3">
                        <option value="NGN" <?= $currency === 'NGN' ? 'selected' : '' ?>>NGN (Nigerian Naira ₦)</option>
                        <option value="USD" <?= $currency === 'USD' ? 'selected' : '' ?>>USD (US Dollar $)</option>
                    </select>
                </div>

                <div class="mb-0">
                    <label class="form-label fw-semibold small">Academic Policy: 1-Course-Per-Student Enforcement</label>
                    <div class="p-3 bg-success bg-opacity-10 border border-success border-opacity-25 rounded-3 text-success d-flex align-items-center gap-2">
                        <i class="bi bi-shield-fill-check fs-4"></i>
                        <div>
                            <strong>Active &amp; Server Enforced</strong>
                            <div class="small text-muted">Students are strictly restricted to 1 active course simultaneously.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Referral & Commissions Engine Config -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white">
                <h5 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-diagram-3-fill text-warning"></i> Referral &amp; Bonus Commission Rates (₦)
                </h5>

                <div class="mb-3">
                    <label for="bonus_rate_technology" class="form-label fw-semibold small text-success">Technology Student Referral Bonus (₦)</label>
                    <input type="number" step="0.01" name="bonus_rate_technology" id="bonus_rate_technology" class="form-control rounded-3" value="<?= e($rateTechnology) ?>" required>
                    <div class="form-text small">Awarded to Tech students when referring another Tech student paying ₦10,000.</div>
                </div>

                <div class="mb-3">
                    <label for="bonus_rate_university" class="form-label fw-semibold small text-info">University Student Referral Bonus (₦)</label>
                    <input type="number" step="0.01" name="bonus_rate_university" id="bonus_rate_university" class="form-control rounded-3" value="<?= e($rateUniversity) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="bonus_rate_secondary" class="form-label fw-semibold small text-warning">Secondary Student Referral Bonus (₦)</label>
                    <input type="number" step="0.01" name="bonus_rate_secondary" id="bonus_rate_secondary" class="form-control rounded-3" value="<?= e($rateSecondary) ?>" required>
                </div>

                <div class="mb-0">
                    <label for="bonus_rate_teacher" class="form-label fw-semibold small text-primary">Teacher Referral Commission (₦)</label>
                    <input type="number" step="0.01" name="bonus_rate_teacher" id="bonus_rate_teacher" class="form-control rounded-3" value="<?= e($rateTeacher) ?>" required>
                </div>
            </div>
        </div>

        <!-- AI Engine & Infrastructure -->
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <h5 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-robot text-primary"></i> AI Engine &amp; Cloud Intelligence
                </h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="ai_provider" class="form-label fw-semibold small">Default AI Intelligence Provider</label>
                        <select name="ai_provider" id="ai_provider" class="form-select rounded-3">
                            <option value="gemini" <?= $aiProvider === 'gemini' ? 'selected' : '' ?>>Google Gemini (Flash 3.7 / 2.0 Pro)</option>
                            <option value="openai" <?= $aiProvider === 'openai' ? 'selected' : '' ?>>OpenAI GPT-4o</option>
                            <option value="anthropic" <?= $aiProvider === 'anthropic' ? 'selected' : '' ?>>Anthropic Claude 3.5 Sonnet</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">AI Tutor 24/7 Availability</label>
                        <input type="text" class="form-control rounded-3 bg-light" value="Enabled for all enrolled students" readonly>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary rounded-pill px-5 py-2 fw-bold shadow-sm" data-feedback="success">
                        <i class="bi bi-check2-circle me-1"></i> Save Global Settings
                    </button>
                </div>
            </div>
        </div>

    </div>
</form>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
