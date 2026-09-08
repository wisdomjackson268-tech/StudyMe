<?php
/**
 * StudyMe AI Platform — Admin AI Engine Config
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();
$ai  = $pdo->query("SELECT * FROM ai_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (is_post()) {
    $provider     = trim($_POST['provider'] ?? 'OpenAI');
    $model        = trim($_POST['model'] ?? 'gpt-4o');
    $apiKey       = trim($_POST['api_key'] ?? '');
    $systemPrompt = trim($_POST['system_prompt'] ?? '');

    try {
        if ($ai) {
            $stmt = $pdo->prepare("UPDATE ai_settings SET provider = ?, model = ?, api_key = ?, system_prompt = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$provider, $model, $apiKey, $systemPrompt, $ai['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO ai_settings (provider, model, api_key, system_prompt, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
            $stmt->execute([$provider, $model, $apiKey, $systemPrompt]);
        }
        set_flash('success', 'AI Engine settings saved!');
        redirect('admin/ai-settings.php');
    } catch (Exception $e) {
        set_flash('error', 'Error: ' . $e->getMessage());
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-robot text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">AI Engine Configuration</h2>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 max-w-2xl">
    <form method="POST">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label fw-bold">Provider</label>
                <input type="text" name="provider" class="form-control rounded-3" value="<?= e($ai['provider'] ?? 'Google Gemini / OpenAI') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Model</label>
                <input type="text" name="model" class="form-control rounded-3" value="<?= e($ai['model'] ?? 'gemini-1.5-flash / gpt-4o') ?>">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">API Key</label>
            <input type="password" name="api_key" class="form-control rounded-3" value="<?= e($ai['api_key'] ?? '') ?>" placeholder="sk-...">
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">System Prompt Directive</label>
            <textarea name="system_prompt" class="form-control rounded-3" rows="4"><?= e($ai['system_prompt'] ?? 'You are StudyMe AI, an encouraging and expert personal tutor for students...') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold py-2">
            <i class="bi bi-save me-1"></i> Save AI Config
        </button>
    </form>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
