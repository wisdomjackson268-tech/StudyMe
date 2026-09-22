<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();
$ai  = null;
try {
    $ai = $pdo->query("SELECT * FROM ai_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {

}

$geminiTestResult = null;
$alocTestResult = null;

if (is_post()) {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'test_gemini') {
        $startTime = microtime(true);
        $testPrompt = "Please respond with a brief confirmation: 'StudyMe Google Gemini AI is connected and ready to assist students.'";
        $res = call_gemini_api($testPrompt);
        $durationMs = round((microtime(true) - $startTime) * 1000);

        if ($res['success']) {
            $geminiTestResult = [
                'status'  => 'success',
                'latency' => $durationMs,
                'model'   => $res['model'],
                'reply'   => $res['text']
            ];
            set_flash('success', "Gemini Connection Successful ({$durationMs}ms latency) via model: {$res['model']}");
        } else {
            $geminiTestResult = [
                'status'  => 'error',
                'latency' => $durationMs,
                'model'   => $res['model'],
                'error'   => $res['error'] ?? 'Unknown error'
            ];
            set_flash('error', "Gemini Connection Failed: " . ($res['error'] ?? 'API error'));
        }
    } elseif ($action === 'test_aloc') {
        $res = test_aloc_api_connection();
        if ($res['success']) {
            $alocTestResult = [
                'status'  => 'success',
                'latency' => $res['latency_ms'],
                'code'    => $res['http_code'],
                'message' => $res['message'],
                'sample'  => $res['data']['question'] ?? 'Sample question fetched successfully'
            ];
            set_flash('success', "ALOC Station Connection Successful ({$res['latency_ms']}ms latency)");
        } else {
            $alocTestResult = [
                'status'  => 'error',
                'latency' => $res['latency_ms'],
                'code'    => $res['http_code'],
                'message' => $res['message']
            ];
            set_flash('error', "ALOC Station Diagnostic: " . $res['message']);
        }
    } else {
        $provider     = trim($_POST['provider'] ?? 'Google Gemini');
        $model        = trim($_POST['model'] ?? 'gemini-3.6-flash');
        $systemPrompt = trim($_POST['system_prompt'] ?? '');

        try {
            if ($ai) {
                $stmt = $pdo->prepare("UPDATE ai_settings SET provider = ?, model = ?, system_prompt = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$provider, $model, $systemPrompt, $ai['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO ai_settings (provider, model, system_prompt, status, created_at) VALUES (?, ?, ?, 'active', NOW())");
                $stmt->execute([$provider, $model, $systemPrompt]);
            }
            set_flash('success', 'AI Engine settings saved successfully!');
            redirect('admin/ai-settings.php');
        } catch (Exception $e) {
            set_flash('error', 'Error: ' . $e->getMessage());
        }
    }
}

$isGeminiConfigured = is_gemini_configured();
$activeGeminiModel = get_gemini_model();
$isAlocConfigured = is_aloc_configured();

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-robot text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">AI Engine &amp; ALOC Station Past Questions</h2>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <?php if ($isGeminiConfigured): ?>
            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill fw-bold border border-success border-opacity-25">
                <i class="bi bi-check-circle-fill me-1"></i> Gemini AI Active
            </span>
        <?php else: ?>
            <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill fw-bold border border-danger border-opacity-25">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> Gemini Key Missing
            </span>
        <?php endif; ?>

        <?php if ($isAlocConfigured): ?>
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold border border-primary border-opacity-25">
                <i class="bi bi-mortarboard-fill me-1"></i> ALOC_API_KEY Configured
            </span>
        <?php else: ?>
            <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 rounded-pill fw-bold border border-warning border-opacity-25">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> ALOC Key Missing
            </span>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4 mb-5">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
            <h5 class="fw-bold mb-3"><i class="bi bi-robot me-2 text-primary"></i> Google Gemini AI Tutor Parameters</h5>

            <form method="POST">
                <input type="hidden" name="action" value="save">

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase text-muted">AI Provider</label>
                        <select name="provider" class="form-select rounded-3">
                            <option value="Google Gemini" selected>Google Gemini</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase text-muted">Active Model</label>
                        <select name="model" class="form-select rounded-3">
                            <option value="gemini-3.6-flash" <?= $activeGeminiModel === 'gemini-3.6-flash' ? 'selected' : '' ?>>gemini-3.6-flash (Recommended &amp; Fast)</option>
                            <option value="gemini-flash-latest" <?= $activeGeminiModel === 'gemini-flash-latest' ? 'selected' : '' ?>>gemini-flash-latest</option>
                            <option value="gemini-3.5-flash" <?= $activeGeminiModel === 'gemini-3.5-flash' ? 'selected' : '' ?>>gemini-3.5-flash</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold small text-uppercase text-muted">System Prompt Directive (AI Tutor Persona)</label>
                    <textarea name="system_prompt" class="form-control rounded-3" rows="3"><?= e($ai['system_prompt'] ?? 'You are StudyMe AI Tutor, an encouraging, highly knowledgeable, and friendly personal tutor on the StudyMe platform. Provide clear, educational explanations with real-world analogies, code snippets where helpful, and concise bullet points.') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                    <i class="bi bi-save me-1"></i> Save AI Settings
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
            <h5 class="fw-bold mb-3"><i class="bi bi-activity me-2 text-warning"></i> Gemini Live Diagnostic</h5>
            <p class="text-muted small mb-3">Ping Google Gemini REST API to verify key validity, quota, and latency.</p>

            <form method="POST">
                <input type="hidden" name="action" value="test_gemini">
                <button type="submit" class="btn btn-outline-warning w-100 rounded-pill fw-bold py-2 mb-3">
                    <i class="bi bi-lightning-charge me-1"></i> Test Gemini Connection
                </button>
            </form>

            <?php if ($geminiTestResult): ?>
                <div class="p-3 rounded-3 <?= $geminiTestResult['status'] === 'success' ? 'bg-success bg-opacity-10 border border-success border-opacity-25' : 'bg-danger bg-opacity-10 border border-danger border-opacity-25' ?>">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="<?= $geminiTestResult['status'] === 'success' ? 'text-success' : 'text-danger' ?>">
                            <i class="bi <?= $geminiTestResult['status'] === 'success' ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> me-1"></i>
                            <?= $geminiTestResult['status'] === 'success' ? 'Passed' : 'Failed' ?>
                        </strong>
                        <span class="badge bg-dark rounded-pill"><?= $geminiTestResult['latency'] ?> ms</span>
                    </div>
                    <?php if ($geminiTestResult['status'] === 'success'): ?>
                        <div class="small text-muted mb-1">Model: <code><?= e($geminiTestResult['model']) ?></code></div>
                        <div class="small text-dark p-2 bg-white rounded border"><?= e($geminiTestResult['reply']) ?></div>
                    <?php else: ?>
                        <div class="small text-danger"><?= e($geminiTestResult['error']) ?></div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="p-3 rounded-3 bg-light text-muted small text-center border">
                    Click button to test Gemini live handshake.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-mortarboard me-2 text-success"></i> ALOC Station Past Questions Integration</h5>
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-bold">JAMB • WAEC • NECO</span>
            </div>

            <p class="text-muted small mb-3">
                ALOC Station powers the secondary school examination vault with authentic questions from WAEC, NECO, and JAMB/UTME.
            </p>

            <div class="alert alert-light border rounded-3 p-3 mb-3 small d-flex align-items-start gap-2">
                <i class="bi bi-shield-check text-success fs-5"></i>
                <div>
                    <strong class="d-block text-dark">Server-Side Secret Protection</strong>
                    The <code>ALOC_API_KEY</code> is stored securely in <code>.env</code> and loaded via <code>env('ALOC_API_KEY')</code>. It is never exposed to browser scripts.
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-sm-6">
                    <div class="p-3 bg-light rounded-3 border">
                        <small class="text-muted d-block fw-bold text-uppercase" style="font-size: 0.7rem;">Endpoint URL</small>
                        <code class="small text-dark">https://questions.aloc.com.ng/api/v2</code>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="p-3 bg-light rounded-3 border">
                        <small class="text-muted d-block fw-bold text-uppercase" style="font-size: 0.7rem;">Fallback System</small>
                        <span class="small text-success fw-bold">StudyMe Local Vault (Active)</span>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <a href="<?= url('courses/past-questions.php') ?>" class="btn btn-outline-primary rounded-pill px-4 fw-bold" target="_blank">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Preview Student Past Questions Explorer
                </a>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
            <h5 class="fw-bold mb-3"><i class="bi bi-activity me-2 text-success"></i> ALOC Station Diagnostic</h5>
            <p class="text-muted small mb-3">Ping ALOC Station REST API with the server-side AccessToken header to verify connectivity.</p>

            <form method="POST">
                <input type="hidden" name="action" value="test_aloc">
                <button type="submit" class="btn btn-outline-success w-100 rounded-pill fw-bold py-2 mb-3">
                    <i class="bi bi-broadcast me-1"></i> Test ALOC API Handshake
                </button>
            </form>

            <?php if ($alocTestResult): ?>
                <div class="p-3 rounded-3 <?= $alocTestResult['status'] === 'success' ? 'bg-success bg-opacity-10 border border-success border-opacity-25' : 'bg-warning bg-opacity-10 border border-warning border-opacity-25' ?>">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="<?= $alocTestResult['status'] === 'success' ? 'text-success' : 'text-dark' ?>">
                            <i class="bi <?= $alocTestResult['status'] === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill' ?> me-1"></i>
                            <?= $alocTestResult['status'] === 'success' ? 'ALOC Streaming Live' : 'Notice (HTTP ' . $alocTestResult['code'] . ')' ?>
                        </strong>
                        <span class="badge bg-dark rounded-pill"><?= $alocTestResult['latency'] ?> ms</span>
                    </div>
                    <div class="small text-dark mb-1"><?= e($alocTestResult['message']) ?></div>
                    <?php if ($alocTestResult['status'] === 'success' && !empty($alocTestResult['sample'])): ?>
                        <div class="small text-muted p-2 bg-white rounded border mt-2">
                            <strong>Sample:</strong> <?= e(substr(strip_tags($alocTestResult['sample']), 0, 140)) ?>...
                        </div>
                    <?php else: ?>
                        <div class="small text-muted mt-2">
                            <i class="bi bi-shield-check text-success me-1"></i> StudyMe's local verified question vault is automatically serving student requests seamlessly.
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="p-3 rounded-3 bg-light text-muted small text-center border">
                    Click button to test ALOC Station live handshake.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
