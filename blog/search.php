<?php
/**
 * StudyMe AI Platform — Blog Search Results
 */
require_once dirname(__DIR__) . '/config/main.php';

$query = trim($_GET['q'] ?? '');
$posts = [];
$pdo = getDBConnection();

if (!empty($query)) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                   CONCAT(u.first_name, ' ', u.last_name) AS author_name
            FROM blog_posts p
            LEFT JOIN blog_categories c ON p.category_id = c.id
            LEFT JOIN users u ON p.author_id = u.id
            WHERE p.status = 'published' AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?)
            ORDER BY p.published_at DESC
        ");
        $term = "%$query%";
        $stmt->execute([$term, $term, $term]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Blog search query error: " . $e->getMessage());
    }
}

include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= url('index.php') ?>" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= url('blog/index.php') ?>" class="text-decoration-none">Blog</a></li>
                <li class="breadcrumb-item active" aria-current="page">Search Results</li>
            </ol>
        </nav>

        <!-- Header -->
        <div class="text-center max-w-700 mx-auto mb-5">
            <h1 class="display-6 fw-bold mb-2">Search Results</h1>
            <p class="lead text-muted">Showing results for: <span class="fw-bold text-main">&ldquo;<?= e($query) ?>&rdquo;</span> (<?= count($posts) ?> found)</p>
        </div>

        <!-- Search Form -->
        <div class="row justify-content-center mb-5">
            <div class="col-lg-7">
                <form action="<?= url('blog/search.php') ?>" method="GET" class="input-group input-group-lg shadow-sm rounded-4 overflow-hidden">
                    <span class="input-group-text bg-white border-0 ps-4 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control border-0 py-3" value="<?= e($query) ?>" placeholder="Search blog articles or topics..." required>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Search</button>
                </form>
            </div>
        </div>

        <!-- Posts Grid -->
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
                                <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill align-self-start mb-2 px-3 py-1 small fw-semibold">
                                    <?= e($post['category_name'] ?? 'General') ?>
                                </span>
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
                    <i class="bi bi-search"></i>
                </div>
                <h4 class="fw-bold">No articles match your query</h4>
                <p class="text-muted mb-4">Try searching for keywords like &ldquo;AI&rdquo;, &ldquo;Study&rdquo;, or &ldquo;Developer&rdquo;.</p>
                <a href="<?= url('blog/index.php') ?>" class="btn btn-primary rounded-pill px-4">Browse All Articles</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
