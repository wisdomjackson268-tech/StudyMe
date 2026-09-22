<?php

if (!function_exists('get_flash')) return;
$flash = get_flash();
if (empty($flash)) return;
?>
<div class="flash-alerts-container mb-4">
    <?php foreach ($flash as $type => $messages): ?>
        <?php foreach ($messages as $msg): ?>
            <div class="alert alert-<?= $type === 'error' ? 'danger' : ($type === 'warning' ? 'warning' : 'success') ?> alert-dismissible fade show rounded-3 shadow-sm" role="alert">
                <i class="bi bi-<?= $type === 'error' ? 'exclamation-octagon-fill' : ($type === 'warning' ? 'exclamation-triangle-fill' : 'check-circle-fill') ?> me-2"></i>
                <?= e($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>
