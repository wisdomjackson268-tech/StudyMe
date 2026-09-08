<?php
/**
 * StudyMe AI Platform — Course Categories Gateway
 * Main landing gateway when users click "Courses" in the navigation bar.
 * Presents the FOUR core learning categories: University, Secondary School, Technology, Teacher.
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/courses.php';
require_once BASE_PATH . '/includes/functions/pricing.php';

$pdo = getDBConnection();
$rates = get_official_pricing_rates();

// Dynamic course counts from DB
$uniCount = (int)$pdo->query("SELECT COUNT(*) FROM courses c JOIN categories cat ON c.category_id = cat.id WHERE cat.slug = 'university' AND c.status = 'published'")->fetchColumn();
$secCount = (int)$pdo->query("SELECT COUNT(*) FROM courses c JOIN categories cat ON c.category_id = cat.id WHERE cat.slug = 'secondary-waec-neco' AND c.status = 'published'")->fetchColumn();
$techCount = (int)$pdo->query("SELECT COUNT(*) FROM courses c JOIN categories cat ON c.category_id = cat.id WHERE cat.slug = 'technology' AND c.status = 'published'")->fetchColumn();
$teacherCount = (int)$pdo->query("SELECT COUNT(*) FROM teachers WHERE status = 'active'")->fetchColumn();

$pageTitle = 'Explore Course Categories — StudyMe AI Platform';
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        
        <!-- Header Section -->
        <div class="text-center max-w-700 mx-auto mb-5">
            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-bold text-uppercase mb-3">
                <i class="bi bi-grid-3x3-gap-fill me-1"></i> Academic Pathways
            </span>
            <h1 class="display-5 fw-bold mb-2">StudyMe Courses</h1>
            <p class="text-secondary fw-semibold mb-2" style="letter-spacing: 0.05em;">Learn. Practice. Grow.</p>
            <p class="lead text-muted fs-6 mb-0">Choose what you want to learn. Select an academic category below to explore curriculum modules, past questions, and personalized 24/7 AI tutor guidance.</p>
        </div>

        <!-- Skeleton Loading Container -->
        <div class="row g-4 skeleton-loading-container" id="categorySkeletons" data-target="#liveCategories">
            <div class="col-sm-6 col-lg-3">
                <div class="skeleton-category-card shadow-sm">
                    <div class="skeleton-block skeleton-icon-lg"></div>
                    <div class="skeleton-block skeleton-line title"></div>
                    <div class="skeleton-block skeleton-line full"></div>
                    <div class="skeleton-block skeleton-line medium mb-4"></div>
                    <div class="skeleton-block skeleton-btn"></div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="skeleton-category-card shadow-sm">
                    <div class="skeleton-block skeleton-icon-lg"></div>
                    <div class="skeleton-block skeleton-line title"></div>
                    <div class="skeleton-block skeleton-line full"></div>
                    <div class="skeleton-block skeleton-line medium mb-4"></div>
                    <div class="skeleton-block skeleton-btn"></div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="skeleton-category-card shadow-sm">
                    <div class="skeleton-block skeleton-icon-lg"></div>
                    <div class="skeleton-block skeleton-line title"></div>
                    <div class="skeleton-block skeleton-line full"></div>
                    <div class="skeleton-block skeleton-line medium mb-4"></div>
                    <div class="skeleton-block skeleton-btn"></div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="skeleton-category-card shadow-sm">
                    <div class="skeleton-block skeleton-icon-lg"></div>
                    <div class="skeleton-block skeleton-line title"></div>
                    <div class="skeleton-block skeleton-line full"></div>
                    <div class="skeleton-block skeleton-line medium mb-4"></div>
                    <div class="skeleton-block skeleton-btn"></div>
                </div>
            </div>
        </div>

        <!-- Live Categories Grid -->
        <div class="row g-4 d-none" id="liveCategories">
            
            <!-- Category 1: UNIVERSITY -->
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 p-lg-4 d-flex flex-column hover-lift transition" style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e2e8f0) !important;">
                    <div class="p-3 bg-info bg-opacity-10 text-info rounded-4 d-inline-flex mb-4" style="width: 58px; height: 58px; align-items: center; justify-content: center;">
                        <i class="bi bi-mortarboard-fill fs-2"></i>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-info bg-opacity-15 text-info-emphasis rounded-pill px-3 py-1 fw-bold small">
                            University Tier
                        </span>
                        <span class="fw-bold text-success small">₦<?= number_format($rates['university'], 0) ?></span>
                    </div>

                    <h3 class="fw-bold fs-4 mb-2 text-main">University</h3>
                    <p class="text-muted small mb-4 flex-grow-1 lh-base">
                        Undergraduate modules in Computer Science, Sciences, Engineering, Economics, and Business Administration with verified academic syllabi.
                    </p>

                    <div class="p-2 rounded-3 bg-light mb-3 text-muted small d-flex justify-content-between">
                        <span><i class="bi bi-collection me-1"></i> <?= $uniCount ?> Modules</span>
                        <span><i class="bi bi-check-circle me-1"></i> Degree Level</span>
                    </div>

                    <a href="<?= url('courses/university.php') ?>" class="btn btn-primary rounded-pill w-100 fw-bold py-2 shadow-sm" data-feedback="click">
                        Explore University <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- Category 2: SECONDARY SCHOOL / WAEC / NECO / JAMB -->
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 p-lg-4 d-flex flex-column hover-lift transition" style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e2e8f0) !important;">
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-4 d-inline-flex mb-4" style="width: 58px; height: 58px; align-items: center; justify-content: center;">
                        <i class="bi bi-book-half fs-2"></i>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-success bg-opacity-15 text-success-emphasis rounded-pill px-3 py-1 fw-bold small">
                            WAEC • NECO • JAMB
                        </span>
                        <span class="fw-bold text-success small">₦<?= number_format($rates['secondary'], 0) ?></span>
                    </div>

                    <h3 class="fw-bold fs-4 mb-2 text-main">Secondary School</h3>
                    <p class="text-muted small mb-4 flex-grow-1 lh-base">
                        Complete high school subjects, past question archives, practice quizzes, and mock examination preparation for SSCE and UTME.
                    </p>

                    <div class="p-2 rounded-3 bg-light mb-3 text-muted small d-flex justify-content-between">
                        <span><i class="bi bi-journals me-1"></i> <?= $secCount ?> Subjects</span>
                        <span><i class="bi bi-file-earmark-text me-1"></i> Past Questions</span>
                    </div>

                    <a href="<?= url('courses/secondary.php') ?>" class="btn btn-success rounded-pill w-100 fw-bold py-2 shadow-sm text-white" data-feedback="click">
                        Explore Secondary <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- Category 3: TECHNOLOGY -->
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 p-lg-4 d-flex flex-column hover-lift transition" style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e2e8f0) !important;">
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-4 d-inline-flex mb-4" style="width: 58px; height: 58px; align-items: center; justify-content: center;">
                        <i class="bi bi-code-slash fs-2"></i>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-primary bg-opacity-15 text-primary rounded-pill px-3 py-1 fw-bold small">
                            Tech Skills
                        </span>
                        <span class="fw-bold text-success small">₦<?= number_format($rates['tech'], 0) ?></span>
                    </div>

                    <h3 class="fw-bold fs-4 mb-2 text-main">Tech</h3>
                    <p class="text-muted small mb-4 flex-grow-1 lh-base">
                        Master modern tech skills: Full-Stack Web Development, Cybersecurity, Data Analytics, Python, AI Content, and Figma UI/UX design.
                    </p>

                    <div class="p-2 rounded-3 bg-light mb-3 text-muted small d-flex justify-content-between">
                        <span><i class="bi bi-laptop me-1"></i> <?= $techCount ?> Bootcamps</span>
                        <span><i class="bi bi-patch-check me-1"></i> Certified</span>
                    </div>

                    <a href="<?= url('courses/technology.php') ?>" class="btn btn-primary rounded-pill w-100 fw-bold py-2 shadow-sm" data-feedback="click">
                        Explore Technology <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- Category 4: TEACHER -->
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 p-lg-4 d-flex flex-column hover-lift transition" style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e2e8f0) !important;">
                    <div class="p-3 bg-warning bg-opacity-10 text-warning-emphasis rounded-4 d-inline-flex mb-4" style="width: 58px; height: 58px; align-items: center; justify-content: center;">
                        <i class="bi bi-person-workspace fs-2 text-warning"></i>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-warning bg-opacity-20 text-dark rounded-pill px-3 py-1 fw-bold small">
                            Educator Suite
                        </span>
                        <span class="fw-bold text-success small">₦<?= number_format($rates['teacher'], 0) ?></span>
                    </div>

                    <h3 class="fw-bold fs-4 mb-2 text-main">Teacher</h3>
                    <p class="text-muted small mb-4 flex-grow-1 lh-base">
                        Instructor workspace, curriculum planning, student telemetry, assignment grading tools, and multimedia publishing suites.
                    </p>

                    <div class="p-2 rounded-3 bg-light mb-3 text-muted small d-flex justify-content-between">
                        <span><i class="bi bi-tools me-1"></i> Pro Tools</span>
                        <span><i class="bi bi-shield-lock me-1"></i> Instructor ID</span>
                    </div>

                    <a href="<?= url('courses/teacher.php') ?>" class="btn btn-outline-dark rounded-pill w-100 fw-bold py-2" data-feedback="click">
                        Explore Teacher <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

        </div>

        <!-- Bottom Trust Note -->
        <div class="text-center mt-5 pt-3">
            <div class="p-3 rounded-pill bg-body d-inline-flex align-items-center gap-3 px-4 border border-subtle shadow-sm">
                <span class="badge bg-primary rounded-circle p-2"><i class="bi bi-shield-check"></i></span>
                <span class="small text-muted fw-semibold">All courses include 24/7 AI tutor guidance, verified instructors, and official certificates.</span>
            </div>
        </div>

    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
