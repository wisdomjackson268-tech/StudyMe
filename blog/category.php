<?php

require_once dirname(__DIR__) . '/config/main.php';

$slug = trim($_GET['slug'] ?? '');
if (empty($slug)) {
    redirect('blog/index.php');
}

$pdo = getDBConnection();
$category = null;
$posts = [];
$allCategories = [];

try {
    $stmt = $pdo->prepare("SELECT * FROM blog_categories WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        redirect('blog/index.php');
    }

    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug,
               CONCAT(u.first_name, ' ', u.last_name) AS author_name
        FROM blog_posts p
        LEFT JOIN blog_categories c ON p.category_id = c.id
        LEFT JOIN users u ON p.author_id = u.id
        WHERE p.category_id = ? AND p.status = 'published'
        ORDER BY p.published_at DESC
    ");
    $stmt->execute([$category['id']]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $allCategories = $pdo->query("
        SELECT c.*, (SELECT COUNT(*) FROM blog_posts WHERE category_id = c.id AND status = 'published') AS post_count
        FROM blog_categories c
        ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Blog category query error: " . $e->getMessage());
    redirect('blog/index.php');
}

include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">

        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= url('index.php') ?>" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= url('blog/index.php') ?>" class="text-decoration-none">Blog</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($category['name']) ?></li>
            </ol>
        </nav>

        <div class="text-center max-w-700 mx-auto mb-5">
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold mb-2">Topic Category</span>
            <h1 class="display-5 fw-bold mb-2"><?= e($category['name']) ?></h1>
            <p class="lead text-muted"><?= e($category['description'] ?: 'Articles and research on ' . $category['name']) ?></p>
        </div>

        <div class="d-flex flex-wrap justify-content-center gap-2 mb-5">
            <a href="<?= url('blog/index.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">All Articles</a>
            <?php foreach ($allCategories as $cat): ?>
                <a href="<?= url('blog/category.php?slug=' . urlencode($cat['slug'])) ?>" class="btn btn-<?= $cat['slug'] === $slug ? 'primary' : 'outline-secondary' ?> btn-sm rounded-pill px-3">
                    <?= e($cat['name']) ?> <span class="badge bg-secondary rounded-pill ms-1"><?= (int)$cat['post_count'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($posts)): ?>
            <div class="row g-4 mb-5">
                <?php foreach ($posts as $post): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden hover-lift d-flex flex-column">
                            <img src="<?= e($post['featured_image'] ?: 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?w=600&q=80') ?>"
                                 alt="<?= e($post['title']) ?>"
                                 class="card-img-top object-fit-cover"
                                 style="height: 200px;">
                            <div class="card-body p-4 d-flex flex-column">
                                <h5 class="fw-bold mb-2">
                                    <a href="<?= url('blog/post.php?slug=' . urlencode($post['slug'])) ?>" class="text-decoration-none text-main hover-primary line-clamp-2">
                                        <?= e($post['title']) ?>
                                    </a>
                                </h5>
                                <p class="text-muted small lh-base mb-4 line-clamp-3"><?= e($post['excerpt']) ?></p>
                                <div class="mt-auto pt-3 border-top d-flex align-items-center justify-content-between small text-muted">
                                    <span><i class="bi bi-calendar3 me-1"></i> <?= date('M j, Y', strtotime($post['published_at'] ?? 'now')) ?></span>
                                    <a href="<?= url('blog/post.php?slug=' . urlencode($post['slug'])) ?>" class="text-primary fw-bold text-decoration-none">
                                        Read More &rarr;
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <div class="p-3 bg-secondary bg-opacity-10 text-secondary rounded-circle d-inline-flex mb-3 fs-2">
                    <i class="bi bi-journal-x"></i>
                </div>
                <h4 class="fw-bold">No articles found in this category</h4>
                <p class="text-muted mb-4">Check back soon or browse other topic categories.</p>
                <a href="<?= url('blog/index.php') ?>" class="btn btn-primary rounded-pill px-4">Browse All Articles</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
