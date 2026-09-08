<?php
/**
 * StudyMe AI Platform — Breadcrumb Component
 * Expects $breadcrumbs array in scope: [['label' => 'Home', 'url' => '...'], ['label' => 'Current']]
 */
if (empty($breadcrumbs)) return;
?>
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb mb-0">
        <?php foreach ($breadcrumbs as $index => $item): ?>
            <?php if (!empty($item['url']) && $index < count($breadcrumbs) - 1): ?>
                <li class="breadcrumb-item"><a href="<?= e($item['url']) ?>" class="text-decoration-none"><?= e($item['label']) ?></a></li>
            <?php else: ?>
                <li class="breadcrumb-item active" aria-current="page"><?= e($item['label']) ?></li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
</nav>
