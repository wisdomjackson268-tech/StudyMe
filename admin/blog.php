<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();
$posts = $pdo->query("
    SELECT bp.*, bc.name AS category_name, CONCAT(u.first_name, ' ', u.last_name) AS author_name
    FROM blog_posts bp
    LEFT JOIN blog_categories bc ON bp.category_id = bc.id
    LEFT JOIN users u ON bp.author_id = u.id
    ORDER BY bp.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-journal-text text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Blog & Editorial Posts</h2>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Title</th>
                    <th>Category</th>
                    <th>Author</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $p): ?>
                <tr>
                    <td class="ps-4 fw-bold text-main"><?= e($p['title']) ?></td>
                    <td><span class="badge bg-light text-muted border"><?= e($p['category_name'] ?: 'General') ?></span></td>
                    <td><?= e($p['author_name'] ?: 'Editorial Team') ?></td>
                    <td><span class="badge rounded-pill <?= $p['status'] === 'published' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst(e($p['status'])) ?></span></td>
                    <td class="text-muted"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($posts)): ?>
                <tr>
                    <td colspan="5" class="text-center py-5 text-muted">No blog posts found. Editorial articles can be added here.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
