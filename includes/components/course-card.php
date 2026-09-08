<?php
/**
 * StudyMe AI Platform — Course Card Component
 * Expects $course array in scope.
 */
if (!isset($course)) return;

$coursePrice = function_exists('get_course_official_price') ? get_course_official_price($course['id']) : (float)($course['price'] ?? 10000.00);
$catSlug = strtolower($course['category_slug'] ?? '');
$isSecondary = ($catSlug === 'secondary-waec-neco' || $catSlug === 'secondary');
$isFreeTesting = defined('FREE_TESTING_MODE') && FREE_TESTING_MODE;
$cardThumb = function_exists('get_course_thumbnail_url') ? get_course_thumbnail_url($course['thumbnail'] ?? '', $catSlug ?: 'technology') : ($course['thumbnail'] ?? 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=400&q=80');
?>
<div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden d-flex flex-column hover-lift">
    <div class="position-relative">
        <a href="<?= url('courses/details.php?slug=' . urlencode($course['slug'])) ?>" class="d-block overflow-hidden">
            <img src="<?= e($cardThumb) ?>" 
                 class="card-img-top w-100" style="height: 180px; object-fit: cover;" 
                 alt="StudyMe <?= e($course['title']) ?>"
                 onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=400&q=80';">
        </a>
        <?php if (!empty($course['featured'])): ?>
            <span class="badge bg-warning text-dark position-absolute top-0 start-0 m-3 rounded-pill fw-bold px-3 py-2 shadow-sm">
                <i class="bi bi-star-fill me-1"></i> Featured
            </span>
        <?php endif; ?>
        <?php if ($isFreeTesting): ?>
            <span class="badge bg-success position-absolute top-0 end-0 m-3 rounded-pill fw-bold px-3 py-1 shadow-sm small">
                Free Testing Mode
            </span>
        <?php endif; ?>
    </div>
    <div class="card-body p-4 d-flex flex-column">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill small px-3">
                <?= e($course['category_name'] ?? 'Academic') ?>
            </span>
            <span class="text-muted small" style="text-transform: capitalize;">
                <?= str_replace('_', ' ', e($course['level'] ?? 'Beginner')) ?>
            </span>
        </div>
        <h5 class="fw-bold mb-2 line-clamp-2">
            <a href="<?= url('courses/details.php?slug=' . urlencode($course['slug'])) ?>" class="text-decoration-none text-main hover-primary">
                <?= e($course['title']) ?>
            </a>
        </h5>
        <p class="text-muted small mb-4 line-clamp-3"><?= e($course['short_description'] ?? '') ?></p>
        
        <div class="mt-auto d-flex justify-content-between align-items-center pt-3 border-top border-light">
            <div class="pe-2">
                <div class="text-muted" style="font-size:0.75rem;">Teacher</div>
                <?php if ($isSecondary): ?>
                    <span class="text-muted small"><i class="bi bi-book-half me-1 text-secondary"></i>StudyMe Curriculum</span>
                <?php elseif (!empty($course['teacher_id']) && !empty($course['teacher_name'])): ?>
                    <a href="<?= url('teacher-profile.php?id=' . (int)$course['teacher_id']) ?>" class="fw-bold text-primary small text-decoration-none hover-underline text-truncate d-inline-block" style="max-width: 140px;">
                        <i class="bi bi-person-badge-fill me-1"></i><?= e($course['teacher_name']) ?>
                    </a>
                <?php else: ?>
                    <span class="text-muted fst-italic small text-nowrap"><i class="bi bi-person-x me-1"></i>Currently unavailable</span>
                <?php endif; ?>
            </div>
            <div class="text-end ps-2">
                <div class="text-muted" style="font-size:0.75rem;">Official Price</div>
                <?php if ($isFreeTesting): ?>
                    <div class="fw-bold text-success">
                        ₦0 <span class="text-muted text-decoration-line-through small fw-normal">₦<?= number_format($coursePrice, 0) ?></span>
                    </div>
                <?php else: ?>
                    <div class="fw-bold text-success">
                        ₦<?= number_format($coursePrice, 0) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-0 px-4 pb-4">
        <a href="<?= url('courses/details.php?slug=' . urlencode($course['slug'])) ?>" class="btn btn-outline-primary rounded-pill w-100 py-2 fw-bold" data-feedback="click">
            Explore Course &amp; Enroll
        </a>
    </div>
</div>
