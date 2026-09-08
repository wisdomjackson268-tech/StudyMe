<?php
/**
 * StudyMe AI Platform — Blog & Articles Hub (Premium Redesign)
 */
require_once dirname(__DIR__) . '/config/main.php';

$categories  = [];
$posts        = [];
$featuredPost = null;

try {
    $pdo = getDBConnection();

    $categories = $pdo->query("
        SELECT c.*, (SELECT COUNT(*) FROM blog_posts WHERE category_id = c.id AND status = 'published') AS post_count
        FROM blog_categories c ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $featuredPost = $pdo->query("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug,
               CONCAT(u.first_name, ' ', u.last_name) AS author_name, u.avatar AS author_avatar
        FROM blog_posts p
        LEFT JOIN blog_categories c ON p.category_id = c.id
        LEFT JOIN users u ON p.author_id = u.id
        WHERE p.status = 'published'
        ORDER BY p.published_at DESC LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    $featuredId = $featuredPost['id'] ?? 0;
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug,
               CONCAT(u.first_name, ' ', u.last_name) AS author_name
        FROM blog_posts p
        LEFT JOIN blog_categories c ON p.category_id = c.id
        LEFT JOIN users u ON p.author_id = u.id
        WHERE p.status = 'published' AND p.id != ?
        ORDER BY p.published_at DESC LIMIT 12
    ");
    $stmt->execute([$featuredId]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Blog index query error: " . $e->getMessage());
}

$seo_options = [
    'title'       => 'Blog & Insights — StudyMe AI Platform',
    'description' => 'Evidence-based study strategies, AI education breakthroughs, WAEC & JAMB guides, and student success stories from the StudyMe team.',
];

include BASE_PATH . '/includes/layouts/header.php';
?>

<style>
/* Blog Hero */
.blog-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 55%, #0f172a 100%);
    padding: 90px 0 100px;
    position: relative;
    overflow: hidden;
}
.blog-hero::before {
    content: '';
    position: absolute;
    top: -130px; left: -130px;
    width: 520px; height: 520px;
    background: radial-gradient(circle, rgba(108,43,255,0.20) 0%, transparent 70%);
    border-radius: 50%;
}
.blog-hero::after {
    content: '';
    position: absolute;
    bottom: -90px; right: -90px;
    width: 420px; height: 420px;
    background: radial-gradient(circle, rgba(59,130,246,0.13) 0%, transparent 70%);
    border-radius: 50%;
}
.blog-search-glass {
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.13);
    border-radius: 50px;
    display: flex;
    align-items: center;
    overflow: hidden;
    max-width: 600px;
    margin: 0 auto;
    backdrop-filter: blur(14px);
}
.blog-search-glass .search-icon { padding: 0 16px 0 22px; color: rgba(255,255,255,0.45); font-size: 1rem; }
.blog-search-glass input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    padding: 15px 10px;
    color: #fff;
    font-size: 0.95rem;
}
.blog-search-glass input::placeholder { color: rgba(255,255,255,0.4); }
.blog-search-glass button {
    background: #6C2BFF;
    border: none;
    color: #fff;
    padding: 15px 28px;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    border-radius: 0 50px 50px 0;
    transition: background 0.2s;
    white-space: nowrap;
}
.blog-search-glass button:hover { background: #5517f5; }
.hero-chip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 18px;
    border-radius: 50px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.13);
    color: rgba(255,255,255,0.82);
    font-size: 0.8rem;
    font-weight: 600;
}
/* Category pills */
.cat-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 18px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    border: 1.5px solid transparent;
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
    cursor: pointer;
}
.cat-pill-primary { background: #6C2BFF; color: #fff; border-color: #6C2BFF; }
.cat-pill-outline {
    background: transparent;
    color: #64748b;
    border-color: rgba(100,116,139,0.35);
}
body.dark .cat-pill-outline { color: #94a3b8; border-color: rgba(148,163,184,0.25); }
.cat-pill-outline:hover { background: #6C2BFF; color: #fff; border-color: #6C2BFF; }
/* Section marker */
.sec-marker {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}
.sec-marker-bar { width: 30px; height: 4px; background: #6C2BFF; border-radius: 4px; }
.sec-marker span { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.09em; color: #94a3b8; }
/* Featured Article */
.feat-card {
    border-radius: 24px;
    overflow: hidden;
    border: 1px solid var(--border-color, #e2e8f0);
    background: var(--bg-card, #fff);
    box-shadow: 0 20px 60px rgba(0,0,0,0.08);
    transition: transform 0.35s ease, box-shadow 0.35s ease;
}
.feat-card:hover { transform: translateY(-4px); box-shadow: 0 28px 70px rgba(108,43,255,0.12); }
body.dark .feat-card { background: #1e293b; border-color: rgba(255,255,255,0.07); }
.feat-img { width: 100%; height: 100%; min-height: 340px; max-height: 470px; object-fit: cover; display: block; }
.feat-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, #6C2BFF, #8b5cf6);
    color: #fff;
    border-radius: 50px;
    padding: 6px 16px;
    font-size: 0.73rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    margin-bottom: 16px;
}
/* Blog Cards */
.blog-card {
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid var(--border-color, #e2e8f0);
    background: var(--bg-card, #fff);
    transition: all 0.3s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.blog-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 50px rgba(108,43,255,0.11);
    border-color: rgba(108,43,255,0.28);
}
body.dark .blog-card { background: #1e293b; border-color: rgba(255,255,255,0.07); }
body.dark .blog-card:hover { border-color: rgba(108,43,255,0.4); }
.blog-thumb-wrap { position: relative; overflow: hidden; height: 215px; }
.blog-thumb-wrap img { width:100%; height:100%; object-fit:cover; transition: transform 0.45s ease; display:block; }
.blog-card:hover .blog-thumb-wrap img { transform: scale(1.06); }
.blog-cat-label {
    position: absolute; top: 12px; left: 12px;
    background: rgba(108,43,255,0.92);
    color: #fff;
    border-radius: 50px;
    padding: 4px 12px;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    backdrop-filter: blur(8px);
}
.read-time-badge {
    position: absolute; bottom: 12px; right: 12px;
    background: rgba(0,0,0,0.58);
    color: #fff;
    border-radius: 50px;
    padding: 3px 10px;
    font-size: 0.7rem;
    font-weight: 600;
    backdrop-filter: blur(8px);
}
.blog-card-body { padding: 22px; display:flex; flex-direction:column; flex:1; }
.blog-card-title {
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.45;
    color: var(--text-main);
    text-decoration: none;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 10px;
    transition: color 0.2s;
}
.blog-card-title:hover { color: #6C2BFF; }
.blog-card-excerpt {
    color: #64748b;
    font-size: 0.84rem;
    line-height: 1.65;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    flex: 1;
    margin-bottom: 16px;
}
body.dark .blog-card-excerpt { color: #94a3b8; }
.blog-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 14px;
    border-top: 1px solid var(--border-color, #e2e8f0);
    font-size: 0.78rem;
    color: #94a3b8;
}
body.dark .blog-card-footer { border-color: rgba(255,255,255,0.07); }
.read-link {
    color: #6C2BFF;
    font-weight: 700;
    font-size: 0.8rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: gap 0.2s;
}
.read-link:hover { gap: 8px; color: #5517f5; }
.author-dot {
    width: 27px; height: 27px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, #6C2BFF, #4f46e5);
    flex-shrink: 0;
}
/* Newsletter */
.newsletter-section {
    background: linear-gradient(135deg, #6C2BFF 0%, #4f46e5 100%);
    border-radius: 24px;
    padding: 52px 48px;
    position: relative;
    overflow: hidden;
}
.newsletter-section::before {
    content: '';
    position: absolute;
    top: -70px; right: -70px;
    width: 260px; height: 260px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
}
.newsletter-section::after {
    content: '';
    position: absolute;
    bottom: -50px; left: -50px;
    width: 200px; height: 200px;
    background: rgba(255,255,255,0.04);
    border-radius: 50%;
}
.nl-input {
    background: rgba(255,255,255,0.14);
    border: 1px solid rgba(255,255,255,0.22);
    border-radius: 50px;
    padding: 14px 22px;
    color: #fff;
    font-size: 0.95rem;
    outline: none;
    width: 100%;
}
.nl-input::placeholder { color: rgba(255,255,255,0.52); }
.nl-btn {
    background: #fff;
    color: #6C2BFF;
    border: none;
    border-radius: 50px;
    padding: 14px 28px;
    font-weight: 800;
    font-size: 0.88rem;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.nl-btn:hover { background: #ede9fe; transform: translateY(-1px); }
/* Empty state */
.empty-state { text-align: center; padding: 90px 24px; }
.empty-icon-wrap {
    width: 100px; height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(108,43,255,0.08), rgba(79,70,229,0.08));
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 24px;
    font-size: 2.6rem; color: #6C2BFF;
}
</style>

<!-- HERO SECTION -->
<section class="blog-hero">
    <div class="container position-relative" style="z-index:2;">
        <div class="text-center">
            <!-- Eyebrow chips -->
            <div class="d-flex justify-content-center flex-wrap gap-2 mb-4">
                <span class="hero-chip"><i class="bi bi-newspaper text-warning"></i> StudyMe Blog</span>
                <span class="hero-chip"><i class="bi bi-stars text-warning"></i> AI-Powered Insights</span>
                <span class="hero-chip"><i class="bi bi-mortarboard text-warning"></i> Exam Strategies</span>
            </div>

            <h1 class="fw-black text-white mb-3" style="font-size: clamp(2.2rem,5vw,3.4rem); letter-spacing:-0.03em; line-height:1.15;">
                Learn Smarter,<br>
                <span style="background: linear-gradient(90deg, #a78bfa, #60a5fa); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;">
                    Not Harder
                </span>
            </h1>
            <p class="text-white-50 mx-auto mb-5" style="max-width:540px; font-size:1.05rem; line-height:1.7;">
                Evidence-based study strategies, WAEC &amp; JAMB guides, AI breakthroughs in education, and real student success stories — all in one place.
            </p>

            <!-- Search -->
            <div class="blog-search-glass">
                <span class="search-icon"><i class="bi bi-search"></i></span>
                <form action="<?= url('blog/search.php') ?>" method="GET" style="flex:1; display:flex;">
                    <input type="text" name="q" placeholder="Search articles, topics, exam guides…" autocomplete="off">
                    <button type="submit"><i class="bi bi-arrow-right me-1"></i> Search</button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- MAIN CONTENT -->
<div class="py-5 bg-light-subtle" style="min-height:60vh;">
    <div class="container">

        <!-- Category Filter -->
        <div class="d-flex flex-wrap justify-content-center gap-2 mb-5">
            <a href="<?= url('blog/index.php') ?>" class="cat-pill cat-pill-primary">
                <i class="bi bi-grid-fill"></i> All Articles
                <?php $total = count($posts) + ($featuredPost ? 1 : 0); if ($total > 0): ?>
                    <span class="opacity-70">(<?= $total ?>)</span>
                <?php endif; ?>
            </a>
            <?php foreach ($categories as $cat): if ((int)$cat['post_count'] < 1) continue; ?>
                <a href="<?= url('blog/category.php?slug=' . urlencode($cat['slug'])) ?>" class="cat-pill cat-pill-outline">
                    <?= e($cat['name']) ?> <span class="opacity-60 fw-bold"><?= (int)$cat['post_count'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($featuredPost): ?>
        <!-- FEATURED ARTICLE -->
        <div class="sec-marker">
            <div class="sec-marker-bar"></div>
            <span>Featured Article</span>
        </div>
        <div class="feat-card mb-5">
            <div class="row g-0 align-items-stretch">
                <div class="col-lg-6 position-relative">
                    <img src="<?= e($featuredPost['featured_image'] ?: 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?w=900&q=85') ?>"
                         alt="<?= e($featuredPost['title']) ?>"
                         class="feat-img">
                    <div class="d-lg-none position-absolute bottom-0 start-0 end-0" style="height:70px;background:linear-gradient(transparent,rgba(0,0,0,.4));"></div>
                </div>
                <div class="col-lg-6 p-4 p-md-5 d-flex flex-column justify-content-center">
                    <div class="feat-label">
                        <i class="bi bi-star-fill"></i>
                        Featured &bull; <?= e($featuredPost['category_name'] ?? 'Insight') ?>
                    </div>

                    <h2 class="fw-black mb-3" style="font-size:1.7rem; line-height:1.3; letter-spacing:-0.02em;">
                        <a href="<?= url('blog/post.php?slug=' . urlencode($featuredPost['slug'])) ?>"
                           class="text-decoration-none text-main"
                           style="transition:color .2s;"
                           onmouseover="this.style.color='#6C2BFF'" onmouseout="this.style.color=''">
                            <?= e($featuredPost['title']) ?>
                        </a>
                    </h2>

                    <p class="text-muted mb-4" style="font-size:.96rem; line-height:1.7;">
                        <?= e(mb_strimwidth($featuredPost['excerpt'] ?? '', 0, 220, '…')) ?>
                    </p>

                    <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
                        <?php if (!empty($featuredPost['author_avatar'])): ?>
                            <img src="<?= e($featuredPost['author_avatar']) ?>" class="rounded-circle" style="width:38px;height:38px;object-fit:cover;" alt="">
                        <?php else: ?>
                            <div class="author-dot" style="width:38px;height:38px;font-size:.82rem;">
                                <?= strtoupper(substr($featuredPost['author_name'] ?? 'S', 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div class="fw-semibold small"><?= e($featuredPost['author_name'] ?? 'StudyMe Team') ?></div>
                            <div class="text-muted" style="font-size:.75rem;">
                                <?= date('M j, Y', strtotime($featuredPost['published_at'] ?? 'now')) ?> &bull; 5 min read
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <a href="<?= url('blog/post.php?slug=' . urlencode($featuredPost['slug'])) ?>"
                           class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                            Read Article <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                        <?php if (!empty($featuredPost['category_name'])): ?>
                        <a href="<?= url('blog/category.php?slug=' . urlencode($featuredPost['category_slug'] ?? '')) ?>"
                           class="btn btn-outline-secondary rounded-pill px-4 fw-semibold">
                            More <?= e($featuredPost['category_name']) ?> &rarr;
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($posts)): ?>
        <!-- ARTICLES GRID -->
        <div class="sec-marker">
            <div class="sec-marker-bar"></div>
            <span>Latest Articles</span>
        </div>
        <div class="row g-4 mb-5">
            <?php foreach ($posts as $post): ?>
            <div class="col-md-6 col-lg-4">
                <div class="blog-card shadow-sm">
                    <div class="blog-thumb-wrap">
                        <img src="<?= e($post['featured_image'] ?: 'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=700&q=80') ?>"
                             alt="<?= e($post['title']) ?>">
                        <div class="blog-cat-label"><?= e($post['category_name'] ?? 'General') ?></div>
                        <div class="read-time-badge"><i class="bi bi-clock me-1"></i><?= rand(3, 9) ?> min</div>
                    </div>
                    <div class="blog-card-body">
                        <a href="<?= url('blog/post.php?slug=' . urlencode($post['slug'])) ?>" class="blog-card-title">
                            <?= e($post['title']) ?>
                        </a>
                        <p class="blog-card-excerpt">
                            <?= e($post['excerpt'] ?? 'Click to read tips, study guides, and insights to help you ace your exams.') ?>
                        </p>
                        <div class="blog-card-footer">
                            <div class="d-flex align-items-center gap-2">
                                <div class="author-dot"><?= strtoupper(substr($post['author_name'] ?? 'S', 0, 1)) ?></div>
                                <span><?= e($post['author_name'] ?? 'StudyMe') ?></span>
                            </div>
                            <a href="<?= url('blog/post.php?slug=' . urlencode($post['slug'])) ?>" class="read-link">
                                Read <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php elseif (!$featuredPost): ?>
        <!-- EMPTY STATE -->
        <div class="empty-state">
            <div class="empty-icon-wrap">
                <i class="bi bi-journal-richtext"></i>
            </div>
            <h3 class="fw-bold mb-2">No Articles Published Yet</h3>
            <p class="text-muted mb-4" style="max-width:400px; margin:0 auto 24px;">
                Our educators and AI researchers are writing fresh guides. Check back soon!
            </p>
            <a href="<?= url('courses/index.php') ?>" class="btn btn-primary rounded-pill px-5 py-3 fw-bold shadow-sm">
                <i class="bi bi-compass-fill me-2"></i> Explore Courses Instead
            </a>
        </div>
        <?php endif; ?>

        <!-- NEWSLETTER STRIP -->
        <div class="newsletter-section mb-5 position-relative">
            <div class="row align-items-center g-4" style="position:relative;z-index:2;">
                <div class="col-lg-5">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-envelope-paper-fill text-warning fs-5"></i>
                        <span class="text-white fw-bold text-uppercase small" style="letter-spacing:.07em;">Weekly Digest</span>
                    </div>
                    <h3 class="fw-black text-white mb-2" style="letter-spacing:-.02em;">Stay Ahead of Your Studies</h3>
                    <p class="mb-0 small" style="color:rgba(255,255,255,.55);">
                        Get the best exam tips, AI study hacks, and new article alerts every week.
                    </p>
                </div>
                <div class="col-lg-7">
                    <form class="d-flex gap-2" onsubmit="handleNewsletter(event)">
                        <input type="email" class="nl-input flex-grow-1" placeholder="Enter your email address…" required>
                        <button type="submit" class="nl-btn"><i class="bi bi-send-fill me-1"></i> Subscribe</button>
                    </form>
                    <p class="mt-2 mb-0" style="font-size:.73rem; color:rgba(255,255,255,.45);">
                        <i class="bi bi-shield-check me-1"></i> No spam. Unsubscribe any time.
                    </p>
                </div>
            </div>
        </div>

        <!-- TOPICS BROWSER -->
        <div class="text-center mb-5">
            <div class="sec-marker justify-content-center mb-3">
                <div class="sec-marker-bar"></div>
                <span>Browse by Topic</span>
                <div class="sec-marker-bar"></div>
            </div>
            <h4 class="fw-bold mb-4">What Are You Studying For?</h4>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <?php
                $topics = [
                    ['icon'=>'bi-patch-question-fill', 'label'=>'WAEC Prep',         'color'=>'#f59e0b'],
                    ['icon'=>'bi-file-earmark-text',   'label'=>'NECO Guides',        'color'=>'#3b82f6'],
                    ['icon'=>'bi-mortarboard-fill',    'label'=>'JAMB Strategies',    'color'=>'#7c3aed'],
                    ['icon'=>'bi-robot',               'label'=>'AI in Education',    'color'=>'#f59e0b'],
                    ['icon'=>'bi-graph-up-arrow',      'label'=>'Study Habits',       'color'=>'#10b981'],
                    ['icon'=>'bi-brain',               'label'=>'Memory Techniques',  'color'=>'#ef4444'],
                    ['icon'=>'bi-clock-history',       'label'=>'Time Management',    'color'=>'#0ea5e9'],
                    ['icon'=>'bi-award-fill',          'label'=>'Scholarships',       'color'=>'#f97316'],
                    ['icon'=>'bi-laptop',              'label'=>'EdTech',             'color'=>'#6366f1'],
                ];
                foreach ($topics as $t):
                ?>
                <a href="<?= url('blog/search.php?q=' . urlencode($t['label'])) ?>"
                   class="cat-pill cat-pill-outline">
                    <i class="<?= $t['icon'] ?>" style="color:<?= $t['color'] ?>;"></i>
                    <?= htmlspecialchars($t['label']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>

<script>
function handleNewsletter(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    const inp = e.target.querySelector('input');
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Subscribed!';
    btn.style.background = '#22c55e';
    btn.style.color = '#fff';
    btn.disabled = inp.disabled = true;
    setTimeout(() => {
        btn.innerHTML = orig;
        btn.style.background = btn.style.color = '';
        btn.disabled = inp.disabled = false;
        inp.value = '';
    }, 3500);
}
</script>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
