<?php

require_once __DIR__ . '/config/main.php';

$pdo = getDBConnection();
$featuredCourses = [];
$teachers = [];

try {

    $activeCourse = null;
    if (is_logged_in() && current_user_role() === ROLE_STUDENT) {
        $stmtSt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
        $stmtSt->execute([current_user('id')]);
        $stRow = $stmtSt->fetch(PDO::FETCH_ASSOC);
        if ($stRow) {
            $activeCourse = get_student_active_course((int)$stRow['id']);
        }
    }

    $categories = $pdo->query("SELECT * FROM categories WHERE status='active' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

    $stmtCourses = $pdo->prepare("
        SELECT c.id, c.title, c.slug, c.thumbnail, c.level, c.short_description, c.price, c.duration_minutes,
               cat.name AS category_name, cat.slug AS category_slug,
               CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
               (SELECT COUNT(*) FROM lessons l JOIN course_sections cs ON l.section_id = cs.id WHERE cs.course_id = c.id) AS lesson_count,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS student_count,
               (SELECT COALESCE(AVG(r.rating), 0) FROM course_reviews r WHERE r.course_id = c.id AND r.status='approved') AS avg_rating
        FROM courses c
        LEFT JOIN categories cat ON c.category_id = cat.id
        JOIN teachers t ON c.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE c.status = 'published'
        ORDER BY c.featured DESC, c.category_id ASC, c.title ASC
        LIMIT 60
    ");
    $stmtCourses->execute();
    $featuredCourses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);

    $stmtTeachers = $pdo->prepare("
        SELECT t.id, t.rating, t.total_students, t.total_courses, t.specialization,
               u.first_name, u.last_name, u.avatar
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        WHERE t.status = 'active' AND u.status = 'active'
        ORDER BY t.rating DESC, t.total_students DESC
        LIMIT 4
    ");
    $stmtTeachers->execute();
    $teachers = $stmtTeachers->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    $featuredCourses = [];
    $teachers = [];
}

$isLoggedIn = is_logged_in();
$userRole = $isLoggedIn ? current_user_role() : null;
$dashboardUrl = 'auth/login.php';
if ($isLoggedIn) {
    $dashboardUrl = ($userRole === 'admin') ? 'admin/dashboard.php' : (($userRole === 'teacher') ? 'teacher/dashboard.php' : 'student/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php
    $seo_options = [
        'title'       => 'StudyMe — AI-Powered Learning Platform',
        'description' => 'StudyMe is an AI-powered learning platform combining personalized 24/7 AI tutoring with certified teacher-led courses across Technology, Secondary School (WAEC, NECO, JAMB), and University faculties.',
        'keywords'    => 'AI learning platform, online courses, technology bootcamps, programming courses, secondary school learning, WAEC prep, NECO prep, JAMB past questions, university courses, AI tutor, verified certificates, StudyMe',
        'canonical'   => get_base_url() . '/index.php',
        'faq'         => [
            ['q' => 'What is StudyMe?', 'a' => 'StudyMe is an advanced AI-powered learning platform combining personalized 24/7 AI tutoring with certified teacher-led courses across Technology, Secondary School (WAEC, NECO, JAMB), and University faculties.'],
            ['q' => 'How does the StudyMe 24/7 AI Tutor work?', 'a' => 'The AI Tutor is built directly into every lesson. It answers questions, breaks down complex equations and code step-by-step, and creates personalized practice quizzes.'],
            ['q' => 'Does StudyMe provide exam preparation for WAEC, NECO, and JAMB?', 'a' => 'Yes. StudyMe includes complete senior secondary syllabi, verified past questions, step-by-step solutions, and simulated CBT timed mock tests.'],
            ['q' => 'Can I learn practical technology and coding skills on StudyMe?', 'a' => 'Yes. We offer hands-on bootcamps in Full-Stack Web Development, Python Programming, Cybersecurity, AI Tools, and UI/UX Design with verified credentials.'],
            ['q' => 'Are course completion certificates verifiable?', 'a' => 'Yes. Every certificate includes a unique verification code that employers and academic institutions can verify instantly on our public portal.']
        ]
    ];
    render_seo_head($seo_options);
    ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="<?= asset('css/variables.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/light.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/dark.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/pages/landing.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
</head>
<body class="lp-body">

    <div id="lp-progress"></div>

    <button id="lp-back-top" aria-label="Back to top">
        <i class="bi bi-arrow-up-short"></i>
    </button>

    <?php include BASE_PATH . '/includes/components/navbar.php'; ?>

    <section class="lp-hero" id="home">
        <div class="container">
            <div class="row align-items-center g-5">

                <div class="col-lg-6">
                    <div class="lp-hero-tag">
                        <i class="bi bi-mortarboard-fill"></i> AI-Powered Learning Platform
                    </div>
                    <h1 class="lp-hero-title">
                        Learn Smarter.<br>
                        Master Any Subject.<br>
                        Guided by <span class="lp-text-gradient">Personalized AI</span>.
                    </h1>
                    <p class="lp-hero-desc">
                        StudyMe unites verified teacher-led curriculum with a dedicated 24/7 AI Tutor. Master high-demand technology skills, prepare for WAEC, NECO &amp; JAMB, excel in university degree courses, and earn verifiable digital certificates.
                    </p>

                    <div class="lp-hero-buttons">
                        <?php if ($isLoggedIn): ?>
                            <a href="<?= url($dashboardUrl) ?>" class="btn-hero-main" data-feedback="click">
                                <i class="bi bi-grid-fill"></i> Go to My Dashboard
                            </a>
                            <a href="<?= url('courses/index.php') ?>" class="btn-hero-secondary" data-feedback="click">
                                <i class="bi bi-compass"></i> Browse All Courses
                            </a>
                        <?php else: ?>
                            <a href="<?= url('auth/register.php') ?>" class="btn-hero-main" data-feedback="success" id="heroCtaPrimary">
                                <i class="bi bi-arrow-right-circle-fill"></i> Get Started Free
                            </a>
                            <a href="<?= url('courses/index.php') ?>" class="btn-hero-secondary" data-feedback="click" id="heroCtaSecondary">
                                <i class="bi bi-compass"></i> Explore Courses
                            </a>
                        <?php endif; ?>
                    </div>

                    <form action="<?= url('courses/search.php') ?>" method="GET" class="lp-hero-search">
                        <i class="bi bi-search text-muted ms-2"></i>
                        <input type="text" name="q" placeholder="Search Python, Physics, WAEC, Calculus..." aria-label="Search course directory" required>
                        <button type="submit" class="btn btn-primary btn-sm rounded px-3 fw-semibold">Search</button>
                    </form>
                </div>

                <div class="col-lg-6">
                    <div class="lp-hero-panel">
                        <div class="lp-panel-header">
                            <div class="d-flex align-items-center gap-2">
                                <div class="badge bg-primary text-white rounded-pill px-2 py-1 small">
                                    <i class="bi bi-robot me-1"></i> StudyMe AI Tutor
                                </div>
                                <span class="text-white-50 small font-monospace">Active Session</span>
                            </div>
                            <span class="text-success small fw-semibold"><i class="bi bi-record-fill me-1"></i> 24/7 Online</span>
                        </div>
                        <div class="lp-panel-body">
                            <div class="lp-chat-exchange">
                                <div class="lp-chat-user" id="demoUserMsg">
                                    "Explain quadratic equations using the quadratic formula with a simple example."
                                </div>
                                <div class="lp-chat-ai" id="demoAiMsg">
                                    The quadratic formula is <strong>x = (-b &plusmn; &radic;(b&sup2; - 4ac)) / 2a</strong> for any equation in the form <em>ax&sup2; + bx + c = 0</em>.<br><br>
                                    For example, in <strong>x&sup2; - 5x + 6 = 0</strong>:
                                    <ul class="mb-1 ps-3 small mt-1">
                                        <li>a = 1, b = -5, c = 6</li>
                                        <li>Discriminant: (-5)&sup2; - 4(1)(6) = 25 - 24 = 1</li>
                                        <li>Solutions: x = (5 &plusmn; 1) / 2 &rarr; <strong>x = 3 or x = 2</strong></li>
                                    </ul>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2 pt-2 border-top">
                                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill small fw-semibold demo-query-chip" data-user="How do I declare a function in Python?" data-ai="In Python, use the <code>def</code> keyword:<br><pre class='p-2 bg-dark text-white rounded mt-1 mb-0 small'><code>def calculate_total(price, tax):\n    return price + (price * tax)</code></pre>">
                                    Python Functions
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill small fw-semibold demo-query-chip" data-user="What is Newton's Second Law of Motion?" data-ai="Newton's 2nd Law states that the acceleration of an object is directly proportional to net force and inversely proportional to mass: <strong>F = ma</strong>. (Force in Newtons, Mass in kg, Acceleration in m/s&sup2;).">
                                    Physics: Newton's Law
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill small fw-semibold demo-query-chip" data-user="Summarize Photosynthesis in one sentence." data-ai="Photosynthesis is the biochemical process where green plants convert <strong>Sunlight + Carbon Dioxide + Water</strong> into chemical energy (Glucose) and release Oxygen.">
                                    Biology: Photosynthesis
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="lp-section-padding bg-body-tertiary" id="pathways">
        <div class="container">
            <div class="lp-section-header">
                <div class="lp-badge-chip">Structured Learning Tracks</div>
                <h2 class="lp-section-title">Designed for Every Stage of Learning</h2>
                <p class="lp-section-desc">StudyMe provides curriculum-aligned tracks tailored to secondary students, university scholars, and technology professionals.</p>
            </div>

            <div class="row g-4">

                <div class="col-md-4">
                    <div class="lp-track-card">
                        <div class="lp-track-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-code-slash"></i>
                        </div>
                        <h3 class="lp-track-title">Technology &amp; Digital Skills</h3>
                        <p class="lp-track-desc">
                            Hands-on bootcamps in Full-Stack Web Development, Python Programming, Cybersecurity, UI/UX Design, and AI Applications with real project submissions.
                        </p>
                        <div class="lp-track-meta">
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1">Coding &amp; Tech</span>
                            <a href="<?= url('courses/technology.php') ?>" class="fw-bold text-primary text-decoration-none small">
                                Explore Tech &rarr;
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="lp-track-card">
                        <div class="lp-track-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-book-half"></i>
                        </div>
                        <h3 class="lp-track-title">Secondary School &amp; SSCE Prep</h3>
                        <p class="lp-track-desc">
                            Complete curriculum coverage for WAEC, NECO, and JAMB exams. Includes thousands of verified past questions, step-by-step solutions, and timed CBT tests.
                        </p>
                        <div class="lp-track-meta">
                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">WAEC • NECO • JAMB</span>
                            <a href="<?= url('courses/secondary.php') ?>" class="fw-bold text-success text-decoration-none small">
                                Explore SSCE &rarr;
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="lp-track-card">
                        <div class="lp-track-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-mortarboard-fill"></i>
                        </div>
                        <h3 class="lp-track-title">University Degree Modules</h3>
                        <p class="lp-track-desc">
                            In-depth undergraduate modules across Computer Science, Business Administration, Engineering, and Natural Sciences with verified instructor materials.
                        </p>
                        <div class="lp-track-meta">
                            <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-1">Higher Education</span>
                            <a href="<?= url('courses/university.php') ?>" class="fw-bold text-info text-decoration-none small">
                                Explore Degree &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="lp-section-padding" id="how-it-works">
        <div class="container">
            <div class="lp-section-header">
                <div class="lp-badge-chip">The StudyMe Experience</div>
                <h2 class="lp-section-title">How Learning on StudyMe Works</h2>
                <p class="lp-section-desc">A structured, distraction-free workflow designed to help you understand and retain knowledge.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="lp-step-item">
                        <div class="lp-step-badge">1</div>
                        <h4 class="fw-bold fs-5 mb-2">Choose Your Pathway</h4>
                        <p class="text-muted small mb-0">Select your course or examination focus area. Access is structured per subject so you can concentrate completely on your curriculum.</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="lp-step-item">
                        <div class="lp-step-badge">2</div>
                        <h4 class="fw-bold fs-5 mb-2">Learn with 24/7 AI Assistance</h4>
                        <p class="text-muted small mb-0">Watch video lessons, read structured topic notes, and ask the integrated AI Tutor for instant line-by-line clarifications whenever you get stuck.</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="lp-step-item">
                        <div class="lp-step-badge">3</div>
                        <h4 class="fw-bold fs-5 mb-2">Practice &amp; Earn Credentials</h4>
                        <p class="text-muted small mb-0">Test your mastery with interactive CBT quizzes and capstone tasks. Earn verifiable digital certificates with unique validation codes.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="lp-section-padding bg-body-tertiary" id="courses">
        <div class="container">
            <div class="d-flex align-items-end justify-content-between mb-4 flex-wrap gap-3">
                <div>
                    <div class="lp-badge-chip"><i class="bi bi-collection-play-fill"></i> Course Catalog</div>
                    <h2 class="lp-section-title mb-1">Featured Courses &amp; Modules</h2>
                    <p class="text-muted mb-0">Browse published courses curated by verified instructors on StudyMe.</p>
                </div>
                <a href="<?= url('courses/index.php') ?>" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-bold">
                    View Complete Directory <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-4">
                <button type="button" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold lp-cat-tab active" data-cat="all" onclick="filterLandingCourses('all', this)">
                    All Courses
                </button>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-semibold lp-cat-tab" data-cat="technology" onclick="filterLandingCourses('technology', this)">
                    <i class="bi bi-code-slash me-1 text-primary"></i> Technology
                </button>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-semibold lp-cat-tab" data-cat="secondary-waec-neco" onclick="filterLandingCourses('secondary-waec-neco', this)">
                    <i class="bi bi-book-half me-1 text-success"></i> Secondary (WAEC / NECO / JAMB)
                </button>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-semibold lp-cat-tab" data-cat="university" onclick="filterLandingCourses('university', this)">
                    <i class="bi bi-mortarboard-fill me-1 text-info"></i> University
                </button>
            </div>

            <?php if (!empty($featuredCourses)): ?>
                <div class="row g-4" id="landingCourseGrid">
                    <?php foreach ($featuredCourses as $idx => $course): ?>
                        <?php
                        $thumb = function_exists('get_course_thumbnail_url') ? get_course_thumbnail_url($course['thumbnail'] ?? '', $course['category_slug'] ?? 'technology', $course['slug'] ?? ($course['title'] ?? '')) : (!empty($course['thumbnail']) ? e($course['thumbnail']) : 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=600&q=80');
                        $courseCatSlug = !empty($course['category_slug']) ? e($course['category_slug']) : 'technology';
                        $officialPrice = function_exists('get_course_official_price') ? get_course_official_price($course['id']) : (float)$course['price'];
                        $isMyActiveCourse = $activeCourse && (int)$activeCourse['course_id'] === (int)$course['id'];
                        $isLockedForMe   = $activeCourse && !$isMyActiveCourse;
                        $durationHours   = max(1, (int)(($course['duration_minutes'] ?? 1800) / 60));
                        ?>
                        <div class="col-md-6 col-lg-4 lp-course-item" data-cat="<?= $courseCatSlug ?>">
                            <div class="lp-course-card h-100 d-flex flex-column">
                                <div class="lp-course-thumb-box position-relative">
                                    <img src="<?= e($thumb) ?>" alt="<?= e($course['title']) ?>" class="lp-course-thumb" loading="lazy" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=600&q=80';">
                                    <span class="course-badge">
                                        <?= ucfirst(str_replace('_', ' ', $course['level'])) ?>
                                    </span>
                                </div>
                                <div class="p-4 d-flex flex-column flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center mb-2 text-muted small">
                                        <span><i class="bi bi-clock me-1"></i><?= $durationHours ?> Hours</span>
                                        <span><i class="bi bi-play-circle me-1"></i><?= (int)$course['lesson_count'] ?> Lessons</span>
                                        <span class="fw-bold text-warning"><i class="bi bi-star-fill me-1"></i><?= number_format((float)$course['avg_rating'] ?: 4.9, 1) ?></span>
                                    </div>
                                    <h4 class="fs-5 fw-bold mb-2 line-clamp-2"><?= e($course['title']) ?></h4>
                                    <p class="text-muted small mb-3 line-clamp-2"><?= e($course['short_description']) ?></p>

                                    <div class="d-flex justify-content-between align-items-center mb-3 pt-3 border-top mt-auto">
                                        <div class="small text-muted d-flex align-items-center gap-1">
                                            <i class="bi bi-person-circle text-primary"></i>
                                            <span class="text-truncate max-w-150"><?= e($course['teacher_name']) ?></span>
                                        </div>
                                        <div class="text-end">
                                            <span class="fs-5 fw-bold text-primary">₦<?= number_format($officialPrice, 0) ?></span>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <a href="<?= url('courses/details.php?slug=' . urlencode($course['slug'])) ?>" class="btn btn-outline-primary rounded-pill flex-grow-1 fw-semibold btn-sm py-2">
                                            View Details
                                        </a>
                                        <?php if ($isMyActiveCourse): ?>
                                            <a href="<?= url('student/course.php?id=' . $course['id']) ?>" class="btn btn-success rounded-pill flex-grow-1 fw-bold btn-sm py-2">
                                                Continue Study
                                            </a>
                                        <?php elseif ($isLockedForMe): ?>
                                            <a href="<?= url('student/my-courses.php') ?>" class="btn btn-secondary rounded-pill px-3 fw-semibold btn-sm py-2" title="You already have an active course">
                                                <i class="bi bi-lock-fill"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= url('courses/details.php?slug=' . urlencode($course['slug'])) ?>" class="btn btn-primary rounded-pill flex-grow-1 fw-bold btn-sm py-2">
                                                Enroll Now
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <script>
                function filterLandingCourses(category, btn) {
                    document.querySelectorAll('.lp-cat-tab').forEach(b => {
                        b.classList.remove('btn-primary', 'active');
                        b.classList.add('btn-outline-secondary');
                    });
                    btn.classList.remove('btn-outline-secondary');
                    btn.classList.add('btn-primary', 'active');

                    const items = document.querySelectorAll('.lp-course-item');
                    items.forEach(item => {
                        if (category === 'all' || item.getAttribute('data-cat') === category) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                }
                </script>
            <?php else: ?>
                <div class="text-center py-5 bg-card rounded-4 border p-5">
                    <i class="bi bi-collection-play text-primary display-4 mb-3"></i>
                    <h3 class="fw-bold mb-2">Curriculum Initializing</h3>
                    <p class="text-muted max-w-md mx-auto mb-4">New courses are currently being published by verified instructors.</p>
                    <a href="<?= url('courses/index.php') ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-bold">Browse Catalog</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="lp-section-padding" id="certificates">
        <div class="container">
            <div class="lp-cert-banner">
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold text-uppercase small mb-3">
                            <i class="bi bi-patch-check-fill me-1"></i> Industry Credential
                        </span>
                        <h2 class="display-6 fw-bold mb-3 text-white">Earn Verifiable Digital Certificates</h2>
                        <p class="text-white-50 lead fs-6 mb-4">
                            Every completed course awards an official StudyMe digital certificate equipped with a unique verification code. Employers and academic institutions can verify your credential legitimacy instantly on our public verification portal.
                        </p>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <a href="<?= url('certificates/verify.php') ?>" class="btn btn-warning rounded-pill px-4 py-2.5 fw-bold text-dark">
                                <i class="bi bi-search me-1"></i> Verify a Certificate
                            </a>
                            <a href="<?= url('auth/register.php') ?>" class="btn btn-outline-light rounded-pill px-4 py-2.5 fw-semibold">
                                Start Learning Free
                            </a>
                        </div>
                    </div>
                    <div class="col-lg-5 text-center">
                        <div class="p-4 bg-white text-dark rounded-3 shadow-lg text-start border border-2 border-warning" style="max-width: 380px; margin: 0 auto;">
                            <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                                <span class="fw-bold small text-primary"><i class="bi bi-mortarboard-fill me-1"></i> StudyMe Verified Credential</span>
                                <i class="bi bi-patch-check-fill text-warning fs-5"></i>
                            </div>
                            <div class="small text-muted mb-1">Issued to</div>
                            <div class="fw-bold mb-2">Verified Graduate</div>
                            <div class="small text-muted mb-1">Course Track</div>
                            <div class="fw-bold small text-primary mb-3">Full-Stack Web Development &amp; AI Integration</div>
                            <div class="p-2 bg-light rounded text-center font-monospace small border">
                                ID: <strong>SM-<?= date('Y') ?>-XXXXXX</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if (!empty($teachers)): ?>
    <section class="lp-section-padding bg-body-tertiary" id="teachers">
        <div class="container">
            <div class="d-flex align-items-end justify-content-between mb-4 flex-wrap gap-3">
                <div>
                    <div class="lp-badge-chip"><i class="bi bi-person-badge-fill"></i> Faculty &amp; Instructors</div>
                    <h2 class="lp-section-title mb-1">Learn From Verified Educators</h2>
                    <p class="text-muted mb-0">Our instructors are experienced professionals and certified subject-matter specialists.</p>
                </div>
                <a href="<?= url('teachers.php') ?>" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-bold">
                    View All Instructors <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="row g-4">
                <?php foreach ($teachers as $t): ?>
                    <div class="col-sm-6 col-lg-3">
                        <div class="lp-teacher-card">
                            <?php if (!empty($t['avatar'])): ?>
                                <img src="<?= e($t['avatar']) ?>" alt="<?= e($t['first_name']) ?>" class="lp-teacher-avatar" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($t['first_name']) ?>&background=1e40af&color=ffffff&bold=true';">
                            <?php else: ?>
                                <div class="lp-teacher-avatar bg-primary d-flex align-items-center justify-content-center text-white fw-bold fs-3">
                                    <?= strtoupper(substr($t['first_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <h5 class="fw-bold mb-1"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></h5>
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 small mb-3"><?= e($t['specialization'] ?: 'Course Instructor') ?></span>
                            <div class="d-flex justify-content-center gap-3 pt-2 mb-3 border-top">
                                <div>
                                    <div class="fw-bold text-main" style="font-size:0.9rem;"><?= (int)$t['total_courses'] ?></div>
                                    <small class="text-muted" style="font-size:0.75rem;">Courses</small>
                                </div>
                                <div>
                                    <div class="fw-bold text-main" style="font-size:0.9rem;"><?= (int)$t['total_students'] ?></div>
                                    <small class="text-muted" style="font-size:0.75rem;">Students</small>
                                </div>
                                <div>
                                    <div class="fw-bold text-warning" style="font-size:0.9rem;"><?= number_format((float)$t['rating'], 1) ?> ★</div>
                                    <small class="text-muted" style="font-size:0.75rem;">Rating</small>
                                </div>
                            </div>
                            <a href="<?= url('teachers.php') ?>" class="btn btn-outline-secondary rounded-pill btn-sm w-100 fw-semibold">View Profile</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="lp-section-padding text-center" id="cta">
        <div class="container">
            <div class="p-5 bg-primary text-white rounded-4 shadow-sm" style="background: linear-gradient(135deg, #1E3A8A 0%, #1E40AF 100%) !important;">
                <div class="max-w-700 mx-auto">
                    <span class="badge bg-white text-primary rounded-pill px-3 py-1 fw-bold text-uppercase small mb-3">
                        Join StudyMe Today
                    </span>
                    <h2 class="display-6 fw-bold mb-3 text-white">Ready to Advance Your Education?</h2>
                    <p class="lead fs-6 text-white-50 mb-4">
                        Create your student account to access structured courses, generate tailored AI study questions, and prepare for academic and professional milestones.
                    </p>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <a href="<?= url('auth/register.php') ?>" class="btn btn-warning btn-lg rounded-pill px-5 fw-bold text-dark shadow-sm">
                            Get Started Free
                        </a>
                        <a href="<?= url('courses/index.php') ?>" class="btn btn-outline-light btn-lg rounded-pill px-5 fw-semibold">
                            Browse Courses
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include BASE_PATH . '/includes/layouts/footer.php'; ?>

    <script>
    // Interactive AI Demo Chip Handler
    document.addEventListener('DOMContentLoaded', () => {
        const chips = document.querySelectorAll('.demo-query-chip');
        const userMsg = document.getElementById('demoUserMsg');
        const aiMsg = document.getElementById('demoAiMsg');

        chips.forEach(chip => {
            chip.addEventListener('click', () => {
                chips.forEach(c => c.classList.remove('btn-primary', 'text-white'));
                chip.classList.add('btn-primary', 'text-white');
                if (userMsg && aiMsg) {
                    userMsg.textContent = '"' + chip.getAttribute('data-user') + '"';
                    aiMsg.innerHTML = chip.getAttribute('data-ai');
                }
            });
        });
    });
    </script>
</body>
</html>
