<?php

require_once dirname(__DIR__) . '/config/main.php';

$slug = trim($_GET['slug'] ?? '');
if (empty($slug)) {
    redirect('blog/index.php');
}

$pdo = getDBConnection();
$post = null;
$relatedPosts = [];

try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug,
               CONCAT(u.first_name, ' ', u.last_name) AS author_name, u.avatar AS author_avatar
        FROM blog_posts p
        LEFT JOIN blog_categories c ON p.category_id = c.id
        LEFT JOIN users u ON p.author_id = u.id
        WHERE p.slug = ? AND p.status = 'published'
        LIMIT 1
    ");
    $stmt->execute([$slug]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        redirect('blog/index.php');
    }

    $stmt = $pdo->prepare("
        SELECT id, title, slug, featured_image, published_at
        FROM blog_posts
        WHERE category_id = ? AND id != ? AND status = 'published'
        ORDER BY published_at DESC
        LIMIT 3
    ");
    $stmt->execute([$post['category_id'], $post['id']]);
    $relatedPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Blog post query error: " . $e->getMessage());
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
                <?php if ($post['category_name']): ?>
                    <li class="breadcrumb-item"><a href="<?= url('blog/category.php?slug=' . urlencode($post['category_slug'])) ?>" class="text-decoration-none"><?= e($post['category_name']) ?></a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active text-truncate" style="max-width: 300px;" aria-current="page"><?= e($post['title']) ?></li>
            </ol>
        </nav>

        <div class="row justify-content-center">
            <div class="col-lg-9">

                <div class="mb-4">
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold mb-3">
                        <?= e($post['category_name'] ?? 'General') ?>
                    </span>
                    <h1 class="display-6 fw-bold mb-3"><?= e($post['title']) ?></h1>
                    <div class="d-flex align-items-center gap-3 text-muted small pb-3 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-person-circle fs-5 text-primary"></i>
                            <span class="fw-semibold text-main"><?= e($post['author_name'] ?? 'StudyMe Team') ?></span>
                        </div>
                        <span>&bull;</span>
                        <div><i class="bi bi-calendar3 me-1"></i> <?= date('F j, Y', strtotime($post['published_at'] ?? 'now')) ?></div>
                        <span>&bull;</span>
                        <div><i class="bi bi-clock me-1"></i> 5 min read</div>
                    </div>
                </div>

                <?php if ($post['featured_image']): ?>
                    <div class="rounded-4 overflow-hidden shadow-sm mb-5">
                        <img src="<?= e($post['featured_image']) ?>" alt="<?= e($post['title']) ?>" class="img-fluid w-100 object-fit-cover" style="max-height: 460px;">
                    </div>
                <?php endif; ?>

                <article class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-5 lh-lg text-secondary fs-5">
                    <?php if ($post['excerpt']): ?>
                        <p class="lead fw-semibold text-main mb-4 border-start border-4 border-primary ps-3">
                            <?= e($post['excerpt']) ?>
                        </p>
                    <?php endif; ?>

                    <div class="blog-content">
                        <?= $post['content'] ?>
                    </div>
                </article>

                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 p-4 bg-white rounded-4 shadow-sm mb-5">
                    <div class="fw-bold text-main">
                        <i class="bi bi-share-fill me-2 text-primary"></i> Share this article
                    </div>
                    <div class="d-flex gap-2">
                        <a href="https://x.com/intent/tweet?text=<?= urlencode($post['title']) ?>&url=<?= urlencode(url('blog/post.php?slug=' . $post['slug'])) ?>&via=StudyMe910" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm rounded-circle p-2" aria-label="Share on X">
                            <i class="bi bi-twitter-x"></i>
                        </a>
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode(url('blog/post.php?slug=' . $post['slug'])) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm rounded-circle p-2" aria-label="Share on LinkedIn">
                            <i class="bi bi-linkedin"></i>
                        </a>
                        <a href="https://facebook.com/sharer/sharer.php?u=<?= urlencode(url('blog/post.php?slug=' . $post['slug'])) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm rounded-circle p-2" aria-label="Share on Facebook">
                            <i class="bi bi-facebook"></i>
                        </a>
                    </div>
                </div>

                <?php if (!empty($relatedPosts)): ?>
                    <div class="mb-5">
                        <h4 class="fw-bold mb-4">Related Articles</h4>
                        <div class="row g-4">
                            <?php foreach ($relatedPosts as $rel): ?>
                                <div class="col-md-4">
                                    <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden hover-lift">
                                        <img src="<?= e($rel['featured_image'] ?: 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?w=400&q=80') ?>" alt="<?= e($rel['title']) ?>" class="card-img-top" style="height: 140px; object-fit: cover;">
                                        <div class="card-body p-3 d-flex flex-column">
                                            <h6 class="fw-bold mb-2">
                                                <a href="<?= url('blog/post.php?slug=' . urlencode($rel['slug'])) ?>" class="text-decoration-none text-main hover-primary line-clamp-2">
                                                    <?= e($rel['title']) ?>
                                                </a>
                                            </h6>
                                            <div class="mt-auto small text-muted">
                                                <?= date('M j, Y', strtotime($rel['published_at'] ?? 'now')) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
