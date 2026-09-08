<?php
/**
 * StudyMe AI-Powered Learning Platform — Premium Landing Page Redesign
 * 
 * Rebuilt from scratch with modern glassmorphism, AI visuals, interactive cards,
 * smooth bidirectional scroll animations, and full integration with existing backend routes.
 */
require_once __DIR__ . '/config/main.php';

// Fetch database records for courses and teachers
$pdo = getDBConnection();
$featuredCourses = [];
$teachers = [];

try {
    // Check if logged in student has an active course
    $activeCourse = null;
    if (is_logged_in() && current_user_role() === ROLE_STUDENT) {
        $stmtSt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
        $stmtSt->execute([current_user('id')]);
        $stRow = $stmtSt->fetch(PDO::FETCH_ASSOC);
        if ($stRow) {
            $activeCourse = get_student_active_course((int)$stRow['id']);
        }
    }

    // Fetch Categories
    $categories = $pdo->query("SELECT * FROM categories WHERE status='active' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Published Courses for landing page showcase
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

    // Active Teachers
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
    // Graceful fallback if database tables are unpopulated or error occurs
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
        'description' => 'StudyMe is an AI-powered learning platform that helps students learn, practice, prepare for exams (WAEC, NECO, JAMB), and develop in-demand technology skills with personalized 24/7 AI tutoring.',
        'keywords'    => 'AI learning platform, AI-powered learning platform, online learning platform, online courses, technology courses, web development course, cybersecurity course, programming courses, university courses, secondary school learning, WAEC preparation, NECO preparation, JAMB preparation, past questions, online education, digital skills, teacher learning platform, AI tutor, AI study assistant, online study platform, StudyMe',
        'canonical'   => get_base_url() . '/index.php',
        'faq'         => [
            ['q' => 'What is StudyMe?', 'a' => 'StudyMe is an advanced AI-powered learning platform combining personalized 24/7 AI tutoring with certified teacher-led courses across Technology, Secondary School (WAEC, NECO, JAMB), and University faculties.'],
            ['q' => 'How does the StudyMe 24/7 AI Tutor work?', 'a' => 'The AI Tutor is built directly into every lesson and course. It answers questions instantly, explains difficult equations and code line-by-line, creates tailored practice quizzes, and adapts to your personal pace.'],
            ['q' => 'Does StudyMe provide exam preparation for WAEC, NECO, and JAMB?', 'a' => 'Yes. StudyMe includes complete senior secondary syllabi, thousands of verified past questions, step-by-step solutions, and simulated CBT timed mock tests.'],
            ['q' => 'Can I learn practical technology and coding skills on StudyMe?', 'a' => 'Yes. We offer hands-on bootcamps in Full-Stack Web Development, Python Programming, Cybersecurity, AI Content Creation, and UI/UX Design with verified certificate credentials.'],
            ['q' => 'Are course completion certificates verifiable?', 'a' => 'Yes. Every certificate comes with a unique verification ID that employers and institutions can verify instantly on our public verification portal.']
        ]
    ];
    render_seo_head($seo_options);
    ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <!-- Design System & Theme Stylesheets -->
    <link rel="stylesheet" href="<?= asset('css/variables.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/light.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/dark.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/pages/landing.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
</head>
<body class="lp-body">

    <!-- Scroll Progress Indicator -->
    <div id="lp-progress"></div>

    <!-- Floating Back To Top Button -->
    <button id="lp-back-top" aria-label="Back to top">
        <i class="bi bi-arrow-up-short"></i>
    </button>

    <!-- Background Gradient Blobs -->
    <div class="lp-bg-blobs">
        <div class="lp-blob lp-blob-1"></div>
        <div class="lp-blob lp-blob-2"></div>
        <div class="lp-blob lp-blob-3"></div>
    </div>

    <!-- ─── 1. NAVBAR ────────────────────────────────────────────── -->
    <nav class="lp-navbar" id="lpNavbar">
        <div class="container">
            <div class="d-flex align-items-center justify-content-between w-100">
                <!-- Brand Logo -->
                <a href="<?= url('index.php') ?>" class="lp-logo">
                    <div class="lp-logo-icon">
                        <i class="bi bi-mortarboard-fill"></i>
                    </div>
                    <span>StudyMe</span>
                </a>

                <!-- Desktop Menu -->
                <ul class="navbar-nav d-none d-lg-flex flex-row align-items-center gap-1 mx-auto">
                    <li class="nav-item"><a class="nav-link lp-nav-link active" href="<?= url('index.php') ?>">Home</a></li>
                    <li class="nav-item"><a class="nav-link lp-nav-link" href="<?= url('courses/index.php') ?>">Courses</a></li>
                    <li class="nav-item"><a class="nav-link lp-nav-link" href="<?= url('ai-learning.php') ?>">AI Learning</a></li>
                    <li class="nav-item"><a class="nav-link lp-nav-link" href="<?= url('teachers.php') ?>">Teachers</a></li>
                    <li class="nav-item"><a class="nav-link lp-nav-link" href="<?= url('pricing.php') ?>">Pricing</a></li>
                    <li class="nav-item"><a class="nav-link lp-nav-link" href="<?= url('about.php') ?>">About</a></li>
                    <li class="nav-item"><a class="nav-link lp-nav-link" href="<?= url('contact.php') ?>">Contact</a></li>
                </ul>

                <!-- Right Actions -->
                <div class="d-none d-lg-flex align-items-center gap-3">
                    <!-- Theme Toggle -->
                    <button class="btn theme-btn" type="button" aria-label="Toggle Light/Dark Theme" data-feedback="click">
                        <i class="bi bi-moon-stars-fill"></i>
                    </button>

                    <?php if ($isLoggedIn): ?>
                        <a href="<?= url($dashboardUrl) ?>" class="btn-lp-start" data-feedback="click">
                            <i class="bi bi-grid-fill me-1"></i> Dashboard
                        </a>
                    <?php else: ?>
                        <a href="<?= url('auth/login.php') ?>" class="btn-lp-login" data-feedback="click">Sign In</a>
                        <a href="<?= url('auth/register.php') ?>" class="btn-lp-start" data-feedback="success">
                            Get Started <i class="bi bi-arrow-right-short ms-1"></i>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Mobile Hamburger Toggle -->
                <button class="lp-toggler d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#lpMobileNav" aria-label="Toggle navigation">
                    <i class="bi bi-list"></i>
                </button>
            </div>

            <!-- Mobile Menu Dropdown -->
            <div class="collapse d-lg-none mt-3" id="lpMobileNav">
                <div class="p-4 rounded-4 bg-surface border border-subtle shadow-lg">
                    <ul class="navbar-nav gap-2 mb-3">
                        <li><a class="nav-link lp-nav-link active" href="<?= url('index.php') ?>">Home</a></li>
                        <li><a class="nav-link lp-nav-link" href="<?= url('courses/index.php') ?>">Courses</a></li>
                        <li><a class="nav-link lp-nav-link" href="<?= url('ai-learning.php') ?>">AI Learning</a></li>
                        <li><a class="nav-link lp-nav-link" href="<?= url('teachers.php') ?>">Teachers</a></li>
                        <li><a class="nav-link lp-nav-link" href="<?= url('pricing.php') ?>">Pricing</a></li>
                        <li><a class="nav-link lp-nav-link" href="<?= url('about.php') ?>">About</a></li>
                        <li><a class="nav-link lp-nav-link" href="<?= url('contact.php') ?>">Contact</a></li>
                    </ul>
                    <div class="d-flex align-items-center gap-2 pt-2 border-top">
                        <button class="btn theme-btn flex-shrink-0" type="button" aria-label="Toggle Theme">
                            <i class="bi bi-moon-stars-fill"></i>
                        </button>
                        <?php if ($isLoggedIn): ?>
                            <a href="<?= url($dashboardUrl) ?>" class="btn-lp-start w-100 text-center justify-content-center">Dashboard</a>
                        <?php else: ?>
                            <a href="<?= url('auth/login.php') ?>" class="btn-lp-login flex-grow-1 text-center">Sign In</a>
                            <a href="<?= url('auth/register.php') ?>" class="btn-lp-start flex-grow-1 text-center justify-content-center">Get Started</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- ─── 2. HERO SECTION ──────────────────────────────────────── -->
    <section class="lp-hero" id="home">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 lp-reveal-left">
                    <div class="lp-hero-badge">
                        <i class="bi bi-stars"></i> Next-Gen AI Learning Platform
                    </div>
                    <h1 class="lp-hero-title">
                        Learn Smarter.<br>
                        Build Your Future.<br>
                        Powered by <span class="lp-text-gradient">AI Intelligence</span>.
                    </h1>
                    <p class="lp-hero-desc">
                        StudyMe merges certified expert instructors with an instant 24/7 AI Tutor. Master complex subjects, generate adaptive quizzes, track progress in real-time, and earn verifiable certificates.
                    </p>
                    <div class="lp-hero-buttons">
                        <a href="<?= url('auth/register.php') ?>" class="btn-hero-main" data-feedback="success" id="heroCtaPrimary">
                            <i class="bi bi-rocket-takeoff-fill"></i> Get Started Free
                        </a>
                        <a href="<?= url('courses/index.php') ?>" class="btn-hero-secondary" data-feedback="click" id="heroCtaSecondary">
                            <i class="bi bi-compass"></i> Explore Courses
                        </a>
                    </div>
                </div>

                <!-- Hero AI Visual & Floating Cards -->
                <div class="col-lg-6 lp-reveal-right">
                    <div class="lp-hero-visual">
                        <!-- Floating Glass Card 1 -->
                        <div class="lp-float-card lp-float-card-1">
                            <div class="lp-float-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-robot"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-main" style="font-size:0.88rem;">AI Assistant</h6>
                                <small class="text-muted" style="font-size:0.75rem;">Ready to answer your questions</small>
                            </div>
                        </div>

                        <!-- Main AI Interface Visual Card -->
                        <div class="lp-hero-card-main">
                            <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="lp-logo-icon" style="width:34px; height:34px; font-size:1rem;">
                                        <i class="bi bi-cpu-fill"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold small text-main">StudyMe AI Engine</div>
                                        <div class="text-success" style="font-size:0.72rem;"><i class="bi bi-record-fill me-1"></i> Active Learning Mode</div>
                                    </div>
                                </div>
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 fw-semibold" style="font-size:0.75rem;">v2.4 Neural</span>
                            </div>

                            <!-- Chat Snippet -->
                            <div class="p-3 rounded-3 bg-body-tertiary mb-3 border border-subtle">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge bg-secondary rounded-pill" style="font-size:0.68rem;">Student</span>
                                    <small class="text-muted" style="font-size:0.72rem;">Physics 101</small>
                                </div>
                                <p class="mb-0 small fw-medium text-main">"Can you summarize Quantum Entanglement simply?"</p>
                            </div>

                            <div class="p-3 rounded-3 bg-primary bg-opacity-10 border border-primary border-opacity-25 mb-3">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge bg-primary text-white rounded-pill" style="font-size:0.68rem;"><i class="bi bi-stars me-1"></i>AI Tutor</span>
                                </div>
                                <p class="mb-0 small text-main" style="line-height:1.5;">
                                    Think of it like two magic coins: flip one to heads in Tokyo, and the other instantly becomes tails in London! 🪙✨
                                </p>
                            </div>

                            <!-- Mock Progress Bar -->
                            <div class="pt-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small fw-semibold text-muted" style="font-size:0.78rem;">Mastery Progress</span>
                                    <span class="small fw-bold text-primary" style="font-size:0.78rem;">92%</span>
                                </div>
                                <div class="progress" style="height:7px; border-radius:10px;">
                                    <div class="progress-bar bg-gradient-primary" style="width: 92%; border-radius:10px;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Floating Glass Card 2 -->
                        <div class="lp-float-card lp-float-card-2">
                            <div class="lp-float-icon bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-trophy-fill"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-main" style="font-size:0.88rem;">Learning Goal</h6>
                                <small class="text-muted" style="font-size:0.75rem;">Mathematics • 90% Completed</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 3. TRUST / STATISTICS SECTION ────────────────────────── -->
    <section class="lp-stats-section">
        <div class="container">
            <div class="lp-stats-banner lp-reveal-scale">
                <div class="row g-4 text-center">
                    <div class="col-6 col-md-3">
                        <div class="lp-stat-number" data-target="20000" data-suffix="K+">0</div>
                        <div class="lp-stat-label"><i class="bi bi-people-fill text-primary me-1"></i> Active Learners</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="lp-stat-number" data-target="500" data-suffix="+">0</div>
                        <div class="lp-stat-label"><i class="bi bi-book-half text-secondary me-1"></i> Interactive Courses</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="lp-stat-number" data-target="100" data-suffix="+">0</div>
                        <div class="lp-stat-label"><i class="bi bi-mortarboard text-warning me-1"></i> Expert Teachers</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="lp-stat-number" data-target="10000" data-suffix="K+">0</div>
                        <div class="lp-stat-label"><i class="bi bi-cpu-fill text-success me-1"></i> AI Learning Resources</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 4. AI LEARNING SECTION ───────────────────────────────── -->
    <section class="lp-section-padding" id="ai-learning">
        <div class="container">
            <div class="lp-section-header lp-reveal">
                <div class="lp-badge-chip">
                    <i class="bi bi-robot"></i> Artificial Intelligence
                </div>
                <h2 class="lp-section-title">
                    Meet Your Personal <span class="lp-text-gradient">AI Tutor</span>
                </h2>
                <p class="lp-section-desc">
                    StudyMe AI adapts specifically to your learning speed, breaking down tough topics and keeping you motivated around the clock.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4 lp-reveal delay-100">
                    <div class="lp-ai-grid-card">
                        <div class="lp-ai-icon-wrapper bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-lightbulb-fill"></i>
                        </div>
                        <h4 class="fw-bold fs-5 mb-2">AI Explanations</h4>
                        <p class="text-muted small mb-0 lh-lg">
                            Get instant, step-by-step breakdowns for any concept, code snippet, or mathematical formula.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 lp-reveal delay-200">
                    <div class="lp-ai-grid-card">
                        <div class="lp-ai-icon-wrapper bg-secondary bg-opacity-10 text-secondary">
                            <i class="bi bi-file-earmark-text-fill"></i>
                        </div>
                        <h4 class="fw-bold fs-5 mb-2">Smart Summaries</h4>
                        <p class="text-muted small mb-0 lh-lg">
                            Transform long textbook chapters and video transcripts into concise key takeaways in seconds.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 lp-reveal delay-300">
                    <div class="lp-ai-grid-card">
                        <div class="lp-ai-icon-wrapper bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-sliders"></i>
                        </div>
                        <h4 class="fw-bold fs-5 mb-2">Personalized Paths</h4>
                        <p class="text-muted small mb-0 lh-lg">
                            AI analyzes your strengths and weaknesses to curate custom study schedules suited for your goals.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 lp-reveal delay-100">
                    <div class="lp-ai-grid-card">
                        <div class="lp-ai-icon-wrapper bg-success bg-opacity-10 text-success">
                            <i class="bi bi-patch-question-fill"></i>
                        </div>
                        <h4 class="fw-bold fs-5 mb-2">Practice Questions</h4>
                        <p class="text-muted small mb-0 lh-lg">
                            Generate infinite practice quizzes tailored to your active courses to guarantee exam readiness.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 lp-reveal delay-200">
                    <div class="lp-ai-grid-card">
                        <div class="lp-ai-icon-wrapper bg-info bg-opacity-10 text-info">
                            <i class="bi bi-journal-code"></i>
                        </div>
                        <h4 class="fw-bold fs-5 mb-2">Concept Assistance</h4>
                        <p class="text-muted small mb-0 lh-lg">
                            Stuck on a tricky assignment? Ask the AI tutor for hint-based guidance without giving away raw answers.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 lp-reveal delay-300">
                    <div class="lp-ai-grid-card">
                        <div class="lp-ai-icon-wrapper bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <h4 class="fw-bold fs-5 mb-2">Learning Insights</h4>
                        <p class="text-muted small mb-0 lh-lg">
                            Deep analytics highlight your memory retention trends and recommend when to review past subjects.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 5. FEATURES SECTION ──────────────────────────────────── -->
    <section class="lp-section-padding bg-body-tertiary" id="features">
        <div class="container">
            <div class="lp-section-header lp-reveal">
                <div class="lp-badge-chip">
                    <i class="bi bi-grid-fill"></i> Platform Ecosystem
                </div>
                <h2 class="lp-section-title">Everything You Need To Master Any Skill</h2>
                <p class="lp-section-desc">StudyMe combines the best elements of structured course education with modern intelligent tools.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4 lp-reveal delay-100">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper">
                            <i class="bi bi-cpu"></i>
                        </div>
                        <h4>AI-Powered Learning</h4>
                        <p>Personalized assistance, explanations, and continuous feedback while taking courses.</p>
                    </div>
                </div>

                <div class="col-md-4 lp-reveal delay-200">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper">
                            <i class="bi bi-play-btn-fill"></i>
                        </div>
                        <h4>Interactive Courses</h4>
                        <p>Structured video modules, downloadable resources, and practical projects from experts.</p>
                    </div>
                </div>

                <div class="col-md-4 lp-reveal delay-300">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper">
                            <i class="bi bi-person-badge-fill"></i>
                        </div>
                        <h4>Expert Teachers</h4>
                        <p>Learn directly from industry specialists, verified educators, and academic mentors.</p>
                    </div>
                </div>

                <div class="col-md-4 lp-reveal delay-100">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper">
                            <i class="bi bi-bar-chart-line-fill"></i>
                        </div>
                        <h4>Smart Progress Tracking</h4>
                        <p>Visual dashboards track your course completion, quiz scores, and daily study streaks.</p>
                    </div>
                </div>

                <div class="col-md-4 lp-reveal delay-200">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper">
                            <i class="bi bi-check2-square"></i>
                        </div>
                        <h4>Quizzes & Practice</h4>
                        <p>Evaluate your retention with interactive quizzes, instant grading, and automated reviews.</p>
                    </div>
                </div>

                <div class="col-md-4 lp-reveal delay-300">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper">
                            <i class="bi bi-patch-check-fill"></i>
                        </div>
                        <h4>Verifiable Certificates</h4>
                        <p>Earn official digital certificates upon course completion to showcase on your LinkedIn & resume.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 6. HOW IT WORKS ──────────────────────────────────────── -->
    <section class="lp-section-padding" id="how-it-works">
        <div class="container">
            <div class="lp-section-header lp-reveal">
                <div class="lp-badge-chip">
                    <i class="bi bi-signpost-split-fill"></i> Simple Process
                </div>
                <h2 class="lp-section-title">Your Path To Success</h2>
                <p class="lp-section-desc">Four simple steps to transform your learning journey with StudyMe.</p>
            </div>

            <div class="row g-4 lp-timeline-wrapper">
                <div class="col-md-3 col-sm-6 lp-reveal delay-100">
                    <div class="lp-timeline-step">
                        <div class="lp-step-number">01</div>
                        <h4 class="fw-bold fs-5 mb-2">Create Account</h4>
                        <p class="text-muted small mb-0">Sign up free in under 60 seconds to unlock your personalized learning dashboard.</p>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 lp-reveal delay-200">
                    <div class="lp-timeline-step">
                        <div class="lp-step-number">02</div>
                        <h4 class="fw-bold fs-5 mb-2">Choose Topic</h4>
                        <p class="text-muted small mb-0">Browse hundreds of courses in Tech, Business, Science, Design, and more.</p>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 lp-reveal delay-300">
                    <div class="lp-timeline-step">
                        <div class="lp-step-number">03</div>
                        <h4 class="fw-bold fs-5 mb-2">Learn with AI</h4>
                        <p class="text-muted small mb-0">Watch lessons while querying your 24/7 AI tutor whenever you get stuck.</p>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 lp-reveal delay-400">
                    <div class="lp-timeline-step">
                        <div class="lp-step-number">04</div>
                        <h4 class="fw-bold fs-5 mb-2">Track & Certify</h4>
                        <p class="text-muted small mb-0">Pass quizzes, monitor progress analytics, and earn verifiable certificates.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 7. OUR COURSES SECTION ─────────────────────────────── -->
    <section class="lp-section-padding bg-body-tertiary" id="courses">
        <div class="container">
            <div class="d-flex align-items-end justify-content-between mb-4 flex-wrap gap-3 lp-reveal">
                <div>
                    <div class="lp-badge-chip"><i class="bi bi-mortarboard-fill"></i> AI-Powered Curriculum</div>
                    <h2 class="lp-section-title mb-2">Our Courses</h2>
                    <p class="text-muted mb-0">Learn the skills and subjects you need with StudyMe's AI-powered learning platform.</p>
                </div>
                <a href="<?= url('courses/index.php') ?>" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-bold">
                    View All Courses <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

            <!-- Category Filter Tabs -->
            <div class="d-flex flex-wrap gap-2 mb-5 lp-reveal">
                <button type="button" class="btn btn-primary rounded-pill px-4 py-2 fw-bold lp-cat-tab active" data-cat="all" onclick="filterLandingCourses('all', this)">
                    All Courses
                </button>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-bold lp-cat-tab" data-cat="technology" onclick="filterLandingCourses('technology', this)">
                    <i class="bi bi-cpu-fill me-1 text-primary"></i> Technology <span class="badge bg-primary ms-1">₦10,000</span>
                </button>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-bold lp-cat-tab" data-cat="secondary-waec-neco" onclick="filterLandingCourses('secondary-waec-neco', this)">
                    <i class="bi bi-book-half me-1 text-success"></i> Secondary / WAEC / NECO <span class="badge bg-success ms-1">₦3,000</span>
                </button>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-bold lp-cat-tab" data-cat="university" onclick="filterLandingCourses('university', this)">
                    <i class="bi bi-mortarboard-fill me-1 text-info"></i> University <span class="badge bg-info text-dark ms-1">₦4,000</span>
                </button>
                <a href="<?= url('auth/teacher-register.php') ?>" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-bold lp-cat-tab">
                    <i class="bi bi-person-workspace me-1 text-warning"></i> Teacher Suite <span class="badge bg-warning text-dark ms-1">₦5,000</span>
                </a>
            </div>

            <?php if (!empty($featuredCourses)): ?>
                <div class="row g-4" id="landingCourseGrid">
                    <?php foreach ($featuredCourses as $idx => $course): ?>
                        <?php 
                        $thumb = function_exists('get_course_thumbnail_url') ? get_course_thumbnail_url($course['thumbnail'] ?? '', $course['category_slug'] ?? 'technology') : (!empty($course['thumbnail']) ? e($course['thumbnail']) : 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=600&q=80');
                        $courseCatSlug = !empty($course['category_slug']) ? e($course['category_slug']) : 'technology';
                        $officialPrice = function_exists('get_course_official_price') ? get_course_official_price($course['id']) : (float)$course['price'];
                        $isMyActiveCourse = $activeCourse && (int)$activeCourse['course_id'] === (int)$course['id'];
                        $isLockedForMe   = $activeCourse && !$isMyActiveCourse;
                        $durationHours   = max(1, (int)(($course['duration_minutes'] ?? 1800) / 60));
                        ?>
                        <div class="col-md-6 col-lg-4 lp-course-item lp-reveal delay-<?= ($idx % 3 + 1) * 100 ?>" data-cat="<?= $courseCatSlug ?>">
                            <div class="lp-course-card h-100 d-flex flex-column">
                                <div class="lp-course-thumb-box position-relative">
                                    <img src="<?= e($thumb) ?>" alt="<?= e($course['title']) ?>" class="lp-course-thumb" loading="lazy" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=600&q=80';">
                                    <span class="course-badge">
                                        <i class="bi bi-layer-backward me-1"></i><?= ucfirst(str_replace('_', ' ', $course['level'])) ?>
                                    </span>
                                    <span class="badge bg-primary bg-opacity-90 position-absolute bottom-0 start-0 m-3 rounded-pill px-3 py-1 small">
                                        <?= e($course['category_name'] ?: 'Technology') ?>
                                    </span>
                                </div>
                                <div class="course-body d-flex flex-column flex-grow-1 p-4">
                                    <div class="course-meta mb-2 d-flex justify-content-between align-items-center">
                                        <span><i class="bi bi-clock me-1"></i><?= $durationHours ?> Hours</span>
                                        <span><i class="bi bi-play-circle me-1"></i><?= (int)$course['lesson_count'] ?> Lessons</span>
                                        <span class="rating-badge"><i class="bi bi-star-fill me-1"></i><?= number_format((float)$course['avg_rating'] ?: 4.9, 1) ?></span>
                                    </div>
                                    <h4 class="course-title mb-2 fw-bold line-clamp-2"><?= e($course['title']) ?></h4>
                                    <p class="text-muted small mb-3 line-clamp-2"><?= e($course['short_description']) ?></p>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3 pt-2 border-top">
                                        <div class="course-instructor mb-0">
                                            <i class="bi bi-person-circle text-primary"></i>
                                            <span class="instructor-name small"><?= e($course['teacher_name']) ?></span>
                                        </div>
                                        <div class="text-end">
                                            <span class="fs-5 fw-bold text-success">₦<?= number_format($officialPrice, 0) ?></span>
                                        </div>
                                    </div>

                                    <div class="course-footer pt-2 mt-auto d-flex gap-2">
                                        <a href="<?= url('courses/details.php?slug=' . urlencode($course['slug'])) ?>" class="btn btn-outline-primary rounded-pill flex-grow-1 fw-bold" data-feedback="click">
                                            View Course
                                        </a>
                                        <?php if ($isMyActiveCourse): ?>
                                            <a href="<?= url('student/course.php?id=' . $course['id']) ?>" class="btn btn-success rounded-pill flex-grow-1 fw-bold">
                                                Continue
                                            </a>
                                        <?php elseif ($isLockedForMe): ?>
                                            <button class="btn btn-secondary bg-opacity-75 rounded-pill px-3 fw-bold" disabled title="1 active course limit">
                                                <i class="bi bi-lock-fill"></i>
                                            </button>
                                        <?php else: ?>
                                            <form action="<?= url('courses/details.php?slug=' . urlencode($course['slug'])) ?>" method="POST" class="d-inline flex-grow-1">
                                                <input type="hidden" name="enroll" value="1">
                                                <button type="submit" class="btn btn-primary rounded-pill w-100 fw-bold shadow-sm" data-feedback="success">
                                                    Enroll Now
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="text-center mt-5 lp-reveal">
                    <a href="<?= url('courses/index.php') ?>" class="btn btn-primary btn-lg rounded-pill px-5 py-3 fw-bold shadow">
                        <i class="bi bi-grid-fill me-2"></i> Browse Complete Course Catalog (40+ Courses)
                    </a>
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
                <!-- Professional Empty State -->
                <div class="text-center py-5 bg-card rounded-4 border p-5 lp-reveal">
                    <i class="bi bi-collection-play text-primary display-3 mb-3"></i>
                    <h3 class="fw-bold mb-2">Interactive Catalog Initializing</h3>
                    <p class="text-muted max-w-md mx-auto mb-4">Our educators are currently creating masterclass courses. Register today to get early access notifications.</p>
                    <a href="<?= url('auth/register.php') ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-bold" data-feedback="success">Get Started Free</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ─── 8. TEACHERS SECTION ─────────────────────────────────── -->
    <section class="lp-section-padding" id="teachers">
        <div class="container">
            <div class="lp-section-header lp-reveal">
                <div class="lp-badge-chip"><i class="bi bi-person-badge-fill"></i> World-Class Educators</div>
                <h2 class="lp-section-title">Learn From Expert Instructors</h2>
                <p class="lp-section-desc">Our verified teachers bring industry authority, passion, and academic excellence.</p>
            </div>

            <?php if (!empty($teachers)): ?>
                <div class="row g-4">
                    <?php foreach ($teachers as $idx => $teacher): ?>
                        <div class="col-sm-6 col-lg-3 lp-reveal delay-<?= ($idx + 1) * 100 ?>">
                            <div class="lp-teacher-card">
                                <?php if (!empty($teacher['avatar'])): ?>
                                    <img src="<?= e($teacher['avatar']) ?>" alt="<?= e($teacher['first_name']) ?>" class="lp-teacher-avatar">
                                <?php else: ?>
                                    <div class="lp-teacher-avatar bg-gradient-primary d-flex align-items-center justify-content-center text-white fw-bold fs-3">
                                        <?= strtoupper(substr($teacher['first_name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <h5 class="fw-bold mb-1"><?= e($teacher['first_name'] . ' ' . $teacher['last_name']) ?></h5>
                                <span class="teacher-spec"><?= e($teacher['specialization'] ?: 'Educator') ?></span>
                                <div class="d-flex justify-content-center gap-3 pt-2 mb-3 border-top border-subtle">
                                    <div>
                                        <div class="fw-bold text-main" style="font-size:0.9rem;"><?= (int)$teacher['total_courses'] ?></div>
                                        <small class="text-muted" style="font-size:0.75rem;">Courses</small>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-main" style="font-size:0.9rem;"><?= (int)$teacher['total_students'] ?></div>
                                        <small class="text-muted" style="font-size:0.75rem;">Students</small>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-warning" style="font-size:0.9rem;"><?= number_format((float)$teacher['rating'], 1) ?>★</div>
                                        <small class="text-muted" style="font-size:0.75rem;">Rating</small>
                                    </div>
                                </div>
                                <a href="<?= url('teachers.php') ?>" class="btn btn-outline-primary rounded-pill btn-sm w-100 fw-semibold">View Profile</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4 text-muted lp-reveal">
                    <p>Instructor profiles are currently updating. <a href="<?= url('auth/teacher-register.php') ?>" class="fw-bold text-primary">Join as an instructor</a>.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ─── 9. WHY STUDYME ───────────────────────────────────────── -->
    <section class="lp-section-padding bg-body-tertiary" id="why-us">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 lp-reveal-left">
                    <div class="lp-badge-chip"><i class="bi bi-shield-check"></i> Why Choose StudyMe</div>
                    <h2 class="lp-section-title">Built For The Future Of Education</h2>
                    <p class="lp-section-desc mb-4">Traditional LMS platforms only store videos. StudyMe acts as your active co-pilot in masterclass learning.</p>
                    
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-start gap-3 p-3 rounded-3 bg-surface border border-subtle">
                            <i class="bi bi-lightning-charge-fill text-primary fs-3 flex-shrink-0"></i>
                            <div>
                                <h5 class="fw-bold fs-6 mb-1">Instant 24/7 Clarification</h5>
                                <p class="text-muted small mb-0">Never wait for forum replies. Get real-time answers to your questions while watching course lectures.</p>
                            </div>
                        </div>

                        <div class="d-flex align-items-start gap-3 p-3 rounded-3 bg-surface border border-subtle">
                            <i class="bi bi-graph-up text-success fs-3 flex-shrink-0"></i>
                            <div>
                                <h5 class="fw-bold fs-6 mb-1">Adaptive Retention Testing</h5>
                                <p class="text-muted small mb-0">AI evaluates your quiz responses to continuously reinforce weak knowledge areas.</p>
                            </div>
                        </div>

                        <div class="d-flex align-items-start gap-3 p-3 rounded-3 bg-surface border border-subtle">
                            <i class="bi bi-award-fill text-warning fs-3 flex-shrink-0"></i>
                            <div>
                                <h5 class="fw-bold fs-6 mb-1">Authentic Digital Credentials</h5>
                                <p class="text-muted small mb-0">Verified certificates backed by QR validation so employers know your mastery is real.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 10. AI INTERFACE DEMO MOCKUP -->
                <div class="col-lg-6 lp-reveal-right">
                    <div class="lp-ai-mockup-wrapper">
                        <div class="lp-mockup-header">
                            <div class="d-flex align-items-center gap-2">
                                <span class="lp-mockup-dot bg-danger"></span>
                                <span class="lp-mockup-dot bg-warning"></span>
                                <span class="lp-mockup-dot bg-success"></span>
                                <span class="ms-2 small text-muted font-monospace">StudyMe AI Session</span>
                            </div>
                            <span class="badge bg-primary rounded-pill px-3 py-1">Interactive Demo</span>
                        </div>

                        <!-- Chat Conversation -->
                        <div class="lp-chat-bubble lp-chat-bubble-user">
                            <div class="fw-bold small mb-1 opacity-75">Student</div>
                            Explain Photosynthesis simply and give me a quick quiz!
                        </div>

                        <div class="lp-chat-bubble lp-chat-bubble-ai">
                            <div class="fw-bold small mb-1 text-primary"><i class="bi bi-robot me-1"></i>StudyMe AI</div>
                            Photosynthesis is how plants turn <strong>Sunlight + Water + CO₂</strong> into delicious plant food (Glucose) and Oxygen! 🌿☀️<br><br>
                            <em>Quick Question: What pigment gives leaves their green color and traps sunlight?</em>
                        </div>

                        <!-- Clickable Chips -->
                        <div class="d-flex flex-wrap gap-2 pt-2">
                            <button class="btn btn-sm btn-outline-light rounded-pill lp-ai-chip" data-prompt="A) Chlorophyll">A) Chlorophyll</button>
                            <button class="btn btn-sm btn-outline-light rounded-pill lp-ai-chip" data-prompt="B) Hemoglobin">B) Hemoglobin</button>
                            <button class="btn btn-sm btn-outline-light rounded-pill lp-ai-chip" data-prompt="Explain in more detail">Explain in more detail</button>
                        </div>

                        <div class="mt-3 pt-3 border-top border-white border-opacity-10">
                            <input type="text" id="lpChatInputMock" class="form-control form-control-dark bg-dark text-white border-secondary rounded-pill px-3" placeholder="Type your response..." readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 11. TESTIMONIALS / BUILT FOR MODERN LEARNERS ─────────── -->
    <section class="lp-section-padding" id="testimonials">
        <div class="container">
            <div class="lp-section-header lp-reveal">
                <div class="lp-badge-chip"><i class="bi bi-chat-quote-fill"></i> Community Impact</div>
                <h2 class="lp-section-title">Loved By Learners Worldwide</h2>
                <p class="lp-section-desc">See how StudyMe AI has transformed study habits and career outcomes.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4 lp-reveal delay-100">
                    <div class="lp-testimonial-card">
                        <div>
                            <div class="stars mb-3">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <p class="testimonial-text">
                                "The AI Tutor is game-changing. Whenever I get stuck on a Python syntax error, it doesn't just fix it — it explains why it broke in plain English."
                            </p>
                        </div>
                        <div class="testimonial-author mt-4">
                            <div class="author-avatar bg-primary text-white d-flex align-items-center justify-content-center fw-bold">CO</div>
                            <div class="author-info">
                                <h6>Chukwuemeka O.</h6>
                                <small>Software Engineering Student</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 lp-reveal delay-200">
                    <div class="lp-testimonial-card">
                        <div>
                            <div class="stars mb-3">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <p class="testimonial-text">
                                "Being able to generate practice quizzes before my university midterms gave me so much confidence. I completed 4 courses last semester!"
                            </p>
                        </div>
                        <div class="testimonial-author mt-4">
                            <div class="author-avatar bg-secondary text-white d-flex align-items-center justify-content-center fw-bold">FA</div>
                            <div class="author-info">
                                <h6>Fatima A.</h6>
                                <small>Bio-Chemistry Major</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 lp-reveal delay-300">
                    <div class="lp-testimonial-card">
                        <div>
                            <div class="stars mb-3">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <p class="testimonial-text">
                                "As a self-taught UI designer, StudyMe's structured paths and verified certificates helped me build a portfolio and land my first client contract."
                            </p>
                        </div>
                        <div class="testimonial-author mt-4">
                            <div class="author-avatar bg-warning text-dark d-flex align-items-center justify-content-center fw-bold">TB</div>
                            <div class="author-info">
                                <h6>Tunde B.</h6>
                                <small>Freelance Product Designer</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 12. FINAL CALL TO ACTION (CTA) ───────────────────────── -->
    <section class="lp-section-padding" id="cta">
        <div class="container">
            <div class="lp-cta-banner lp-reveal-scale">
                <div class="max-w-xl mx-auto position-relative z-2">
                    <span class="fs-1 mb-3 d-inline-block">🚀</span>
                    <h2 class="fw-bold display-5 mb-3 text-white">Ready to Learn Smarter?</h2>
                    <p class="lead mb-4 text-white text-opacity-90">
                        Join thousands of students and educators using StudyMe to accelerate their knowledge and reach their full potential.
                    </p>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <a href="<?= url('auth/register.php') ?>" class="btn btn-light btn-lg rounded-pill px-5 fw-bold text-primary shadow" data-feedback="success" id="ctaRegister">
                            Get Started Free
                        </a>
                        <a href="<?= url('courses/index.php') ?>" class="btn btn-outline-light btn-lg rounded-pill px-5 fw-semibold" data-feedback="click" id="ctaBrowse">
                            Explore Courses
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 13. FOOTER ───────────────────────────────────────────── -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <!-- Brand Column -->
                <div class="col-lg-4 col-md-6">
                    <a class="logo text-white mb-3 text-decoration-none d-inline-flex align-items-center gap-2" href="<?= url('index.php') ?>">
                        <i class="bi bi-mortarboard-fill text-warning fs-2"></i>
                        <span class="fs-4 fw-bold">StudyMe</span>
                    </a>
                    <p class="text-white-50 small mb-4 pe-lg-4 lh-base">
                        StudyMe is an advanced AI-powered learning platform designed to help students master skills with 24/7 AI tutoring, learn from certified expert teachers, track real-time progress, and earn verifiable digital certificates.
                    </p>
                    <div class="d-flex align-items-center gap-2">
                        <a href="https://facebook.com/studymehq" target="_blank" rel="noopener noreferrer" class="social-icon-btn" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="https://twitter.com/studyme_ai" target="_blank" rel="noopener noreferrer" class="social-icon-btn" aria-label="Twitter X"><i class="bi bi-twitter-x"></i></a>
                        <a href="https://instagram.com/studymehq" target="_blank" rel="noopener noreferrer" class="social-icon-btn" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                        <a href="https://linkedin.com/company/studyme-ai" target="_blank" rel="noopener noreferrer" class="social-icon-btn" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
                        <a href="https://youtube.com/@studyme-ai" target="_blank" rel="noopener noreferrer" class="social-icon-btn" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
                    </div>
                </div>

                <!-- Platform Links -->
                <div class="col-lg-2 col-6">
                    <h5>Platform</h5>
                    <a href="<?= url('courses/index.php') ?>" class="footer-link">Browse Courses</a>
                    <a href="<?= url('ai-learning.php') ?>" class="footer-link">AI Learning Tutor</a>
                    <a href="<?= url('teachers.php') ?>" class="footer-link">Expert Teachers</a>
                    <a href="<?= url('pricing.php') ?>" class="footer-link">Pricing &amp; Plans</a>
                    <a href="<?= url('certificates/verify.php') ?>" class="footer-link">Verify Certificate</a>
                </div>

                <!-- Resources Links -->
                <div class="col-lg-2 col-6">
                    <h5>Resources</h5>
                    <a href="<?= url('help.php') ?>" class="footer-link">Help Center</a>
                    <a href="<?= url('faq.php') ?>" class="footer-link">FAQ</a>
                    <a href="<?= url('blog/index.php') ?>" class="footer-link">Blog &amp; Articles</a>
                    <a href="<?= url('community.php') ?>" class="footer-link">Community Hub</a>
                    <a href="<?= url('contact.php') ?>" class="footer-link">Contact Support</a>
                </div>

                <!-- Company & Legal Links -->
                <div class="col-lg-4 col-md-6">
                    <h5>Company &amp; Legal</h5>
                    <a href="<?= url('about.php') ?>" class="footer-link">About StudyMe</a>
                    <a href="<?= url('careers.php') ?>" class="footer-link">Careers &amp; Opportunities</a>
                    <a href="<?= url('auth/teacher-register.php') ?>" class="footer-link">Become an Instructor</a>
                    <a href="<?= url('privacy.php') ?>" class="footer-link">Privacy Policy</a>
                    <a href="<?= url('terms.php') ?>" class="footer-link">Terms &amp; Conditions</a>
                    <a href="<?= url('cookies.php') ?>" class="footer-link">Cookie Policy</a>
                </div>
            </div>

            <!-- Account Quick Bar -->
            <div class="row pt-4 mt-4 border-top border-secondary border-opacity-25 align-items-center justify-content-between">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="d-flex align-items-center gap-2 flex-wrap text-white-50 small">
                        <span>Account:</span>
                        <?php if ($isLoggedIn): ?>
                            <a href="<?= url($dashboardUrl) ?>" class="badge bg-primary text-decoration-none px-3 py-2 rounded-pill">
                                <i class="bi bi-grid-fill me-1"></i> Go to <?= ucfirst($userRole ?? 'User') ?> Dashboard
                            </a>
                            <a href="<?= url('auth/logout.php') ?>" class="text-white-50 text-decoration-none hover-white ms-2">
                                <i class="bi bi-box-arrow-right me-1"></i> Sign Out
                            </a>
                        <?php else: ?>
                            <a href="<?= url('auth/login.php') ?>" class="text-white-50 text-decoration-none hover-white">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                            </a>
                            <span class="text-secondary">&bull;</span>
                            <a href="<?= url('auth/register.php') ?>" class="text-warning text-decoration-none fw-semibold">
                                <i class="bi bi-person-plus-fill me-1"></i> Get Started Free
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0 text-white-50 small">&copy; <?= date('Y') ?> StudyMe AI-Powered Learning Platform. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Core Theme & Helper JS -->
    <script src="<?= asset('js/theme.js') ?>"></script>
    <script src="<?= asset('js/feedback.js') ?>"></script>
    <script src="<?= asset('js/app.js') ?>"></script>

    <!-- Landing Page Specific JS -->
    <script src="<?= asset('js/pages/landing.js') ?>"></script>

</body>
</html>