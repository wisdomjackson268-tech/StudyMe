<?php

if (!isset($totalPages) || $totalPages <= 1) return;
$currentPage = max(1, (int)($currentPage ?? 1));
$baseUrl = $baseUrl ?? '?page=';
?>
<nav aria-label="Page navigation" class="my-4">
    <ul class="pagination justify-content-center">
        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
            <a class="page-link rounded-circle m-1" href="<?= $baseUrl . ($currentPage - 1) ?>" aria-label="Previous">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                <a class="page-link rounded-circle m-1" href="<?= $baseUrl . $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link rounded-circle m-1" href="<?= $baseUrl . ($currentPage + 1) ?>" aria-label="Next">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    </ul>
</nav>
