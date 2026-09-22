<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/courses.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';

$pdo  = getDBConnection();
$slug = trim($_GET['slug'] ?? '');
$id   = (int)($_GET['id'] ?? 0);

$course = null;
if (!empty($slug)) {
    $course = get_course_by_slug($slug);
} elseif ($id > 0) {
    $course = get_course_by_id($id);
}

if (!$course) {
    set_flash('error', 'Course not found.');
    redirect('courses/index.php');
}

$sections    = get_course_sections($course['id']);
$user        = current_user();
$isEnrolled  = false;
$activeOtherCourse = null;
$studentId   = null;
$isLoggedIn  = is_logged_in();

if ($isLoggedIn && current_user_role() === ROLE_STUDENT) {
    $pdo  = getDBConnection();
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($student) {
        $studentId  = (int)$student['id'];
        $isEnrolled = is_student_enrolled($studentId, $course['id']);
        $activeCourseRow = get_student_active_course($studentId);
        if ($activeCourseRow && (int)$activeCourseRow['course_id'] !== (int)$course['id']) {
            $activeOtherCourse = $activeCourseRow;
        }
    }
}

$officialPrice = function_exists('get_course_official_price') ? get_course_official_price($course['id']) : (float)$course['price'];
$durationHours = max(1, (int)(($course['duration_minutes'] ?? 1800) / 60));
$courseThumbUrl = function_exists('get_course_thumbnail_url') ? get_course_thumbnail_url($course['thumbnail'], $course['category_slug'] ?? 'technology', $course['slug'] ?? ($course['title'] ?? '')) : ($course['thumbnail'] ?: 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=600&q=80');

$courseCheck = course_has_active_teacher($course['id']);

if ((is_post() && isset($_POST['enroll'])) || isset($_GET['enroll'])) {

    if (!$courseCheck['can_enroll']) {
        set_flash('error', 'Enrollment Denied: ' . $courseCheck['reason'] . ' University regulations require an active verified teacher before student enrollment can be approved.');
        redirect('courses/details.php?slug=' . urlencode($course['slug']));
    }

    if ($activeOtherCourse && !(defined('FREE_TESTING_MODE') && FREE_TESTING_MODE)) {
        set_flash('error', 'You already have an active course: "' . htmlspecialchars($activeOtherCourse['course_title']) . '". Platform policy restricts learners to ONE active course at a time.');
        redirect('student/my-courses.php');
    }

    $_SESSION['pending_course_id']    = (int)$course['id'];
    $_SESSION['pending_course_slug']  = $course['slug'];
    $_SESSION['pending_course_title'] = $course['title'];
    $_SESSION['pending_course_cat']   = $course['category_name'] ?: 'Technology';
    $_SESSION['pending_course_price'] = $officialPrice;

    if (!$isLoggedIn) {
        set_flash('info', 'Please create an account or sign in to complete your enrollment in ' . $course['title']);
        redirect('auth/register.php');
    }

    $role = current_user_role();
    if ($role === ROLE_ADMIN) {
        set_flash('info', 'Admin mode: You have administrative access to all course contents.');
        redirect('admin/courses.php');
    } elseif ($role === ROLE_TEACHER) {
        set_flash('info', 'Teacher mode: Please use the Teacher Suite dashboard.');
        redirect('teacher/dashboard.php');
    } else {

        if (defined('FREE_TESTING_MODE') && FREE_TESTING_MODE) {
            $stmtSt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
            $stmtSt->execute([$user['id']]);
            $stRow = $stmtSt->fetch(PDO::FETCH_ASSOC);
            $sId = $stRow ? (int)$stRow['id'] : 0;
            if (!$sId) {
                $sNum = 'STD-' . date('Y') . '-' . str_pad($user['id'], 4, '0', STR_PAD_LEFT);
                $pdo->prepare("INSERT IGNORE INTO students (user_id, student_number) VALUES (?, ?)")->execute([$user['id'], $sNum]);
                $sId = (int)$pdo->lastInsertId();
            }
            if ($sId > 0) {
                $tchId = !empty($courseCheck['teacher']['id']) ? (int)$courseCheck['teacher']['id'] : null;
                $pdo->prepare("INSERT INTO enrollments (student_id, course_id, teacher_id, status, progress, enrolled_at) VALUES (?, ?, ?, 'active', 0.00, NOW()) ON DUPLICATE KEY UPDATE status = 'active', teacher_id = IF(VALUES(teacher_id) IS NOT NULL, VALUES(teacher_id), teacher_id)")
                    ->execute([$sId, (int)$course['id'], $tchId]);
            }
            unset($_SESSION['pending_course_id'], $_SESSION['pending_course_slug'], $_SESSION['pending_course_title']);
            set_flash('success', 'Enrolled successfully in ' . $course['title'] . '! Free testing access activated.');
            redirect('student/course.php?id=' . $course['id']);
        } else {

            redirect('student/profile.php?enroll=1');
        }
    }
}

$whatYoullLearn = [
    'Master practical and foundational concepts in ' . $course['title'],
    'Build real-world hands-on projects and portfolio demonstrations',
    'Leverage StudyMe 24/7 AI Tutor for instant explanations and coding assistance',
    'Complete module knowledge quizzes and pass evaluation milestones',
    'Earn a verifiable completion certificate with an official verification ID',
    'Gain competitive academic and industry skills for career acceleration'
];

$requirements = [
    'A computer, tablet, or smartphone with internet connection',
    'Eagerness to learn with interactive AI-assisted feedback',
    'No advanced prerequisites required for beginner modules'
];

$skillsGained = [
    'Concept Mastery', 'Problem Solving', 'AI-Assisted Workflow',
    'Practical Application', 'Exam & Industry Readiness', 'Analytical Thinking'
];

$courseCanonical = get_canonical_url('courses/details.php?slug=' . urlencode($course['slug']));
$categorySlug = $course['category_slug'] ?? 'technology';
$categoryName = $course['category_name'] ?? 'Technology';

$breadcrumbs = [
    ['name' => 'Home', 'url' => url('index.php')],
    ['name' => 'Courses', 'url' => url('courses/index.php')],
    ['name' => $categoryName, 'url' => url('courses/' . ($categorySlug === 'technology' ? 'technology.php' : ($categorySlug === 'university' ? 'university.php' : ($categorySlug === 'secondary-waec-neco' ? 'secondary.php' : 'teacher.php'))))],
    ['name' => $course['title'], 'url' => $courseCanonical]
];

$courseFaq = [
    [
        'q' => 'What will I learn in ' . $course['title'] . '?',
        'a' => 'You will master foundational and advanced concepts in ' . $course['title'] . ' through video lessons, practical assignments, module quizzes, and 24/7 AI tutor guidance.'
    ],
    [
        'q' => 'Does this course include 24/7 AI Tutor support?',
        'a' => 'Yes. StudyMe’s AI Tutor is integrated directly into the course player to answer questions, explain complex steps, and provide instant code debugging and homework feedback.'
    ],
    [
        'q' => 'Will I receive a verified certificate upon completion?',
        'a' => 'Yes. Students who complete all lessons and pass the milestone quizzes earn an official StudyMe completion certificate with a verifiable credential ID.'
    ],
    [
        'q' => 'How much does enrollment cost?',
        'a' => 'Enrollment for this course is ₦' . number_format($officialPrice, 2) . ' with full access to curriculum materials, quizzes, and AI tutor support.'
    ]
];

$seo_options = [
    'title'       => !empty($course['seo_title']) ? $course['seo_title'] : $course['title'] . ' Course | StudyMe',
    'description' => !empty($course['seo_description']) ? $course['seo_description'] : 'Enroll in ' . $course['title'] . ' on StudyMe. Master practical skills with 24/7 AI tutoring, structured video lessons, module quizzes, and verifiable certificates.',
    'keywords'    => !empty($course['seo_keywords']) ? $course['seo_keywords'] : strtolower($course['title']) . ', ' . strtolower($categoryName) . ' course, StudyMe AI course, online learning, digital education',
    'canonical'   => $courseCanonical,
    'type'        => 'website',
    'image'       => !empty($course['thumbnail']) ? (strpos($course['thumbnail'], 'http') === 0 ? $course['thumbnail'] : url($course['thumbnail'])) : get_base_url() . '/assets/img/og-preview.png',
    'course'      => [
        'title'            => $course['title'],
        'description'      => !empty($course['short_description']) ? $course['short_description'] : $course['description'],
        'price'            => $officialPrice,
        'duration_minutes' => $course['duration_minutes'] ?? 1800,
        'teacher_name'     => $course['teacher_name'] ?? null
    ],
    'breadcrumbs' => $breadcrumbs,
    'faq'         => $courseFaq
];

$stmtRelated = $pdo->prepare("
    SELECT c.id, c.title, c.slug, c.thumbnail, c.level, c.price,
           cat.name AS category_name
    FROM courses c
    LEFT JOIN categories cat ON c.category_id = cat.id
    WHERE c.category_id = ? AND c.id != ? AND c.status = 'published'
    ORDER BY c.featured DESC, c.title ASC
    LIMIT 3
");
$stmtRelated->execute([$course['category_id'] ?? 1, $course['id']]);
$relatedCourses = $stmtRelated->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">

        <nav class="mb-4" aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="<?= url('index.php') ?>" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= url('courses/index.php') ?>" class="text-decoration-none">Course Catalog</a></li>
                <?php if (!empty($course['category_name'])): ?>
                <li class="breadcrumb-item"><a href="<?= url('courses/category.php?slug=' . urlencode($course['category_slug'] ?? 'technology')) ?>" class="text-decoration-none"><?= e($course['category_name']) ?></a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active"><?= e(substr($course['title'], 0, 35)) ?>...</li>
            </ol>
        </nav>

        <?php if ($activeOtherCourse): ?>
            <div class="alert alert-warning border-warning border-opacity-25 rounded-4 p-4 mb-4 shadow-sm">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-shield-lock-fill text-warning display-6"></i>
                    <div>
                        <h5 class="fw-bold mb-1 text-warning-emphasis">You have an active enrolled course</h5>
                        <p class="mb-0 small text-muted">You are currently enrolled in <strong>"<?= e($activeOtherCourse['course_title']) ?>"</strong>. Under StudyMe's focused learning policy, students focus on ONE course at a time.</p>
                    </div>
                    <a href="<?= url('student/course.php?id=' . $activeOtherCourse['course_id']) ?>" class="btn btn-warning rounded-pill px-4 fw-bold ms-auto text-nowrap">
                        <i class="bi bi-play-circle-fill me-1"></i> Continue Active Course
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-5">

            <div class="col-lg-8">

                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-bold small">
                        <i class="bi bi-tag-fill me-1"></i> <?= e($course['category_name'] ?: 'Technology') ?>
                    </span>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-2 fw-semibold small">
                        <i class="bi bi-bar-chart-fill me-1"></i> <?= ucfirst(str_replace('_', ' ', $course['level'])) ?>
                    </span>
                </div>

                <h1 class="fw-bold mb-3 lh-sm display-6"><?= e($course['title']) ?></h1>
                <p class="lead text-secondary mb-4"><?= e($course['short_description']) ?></p>

                <div class="d-flex flex-wrap gap-4 align-items-center py-3 px-4 bg-light rounded-4 mb-4 border border-secondary border-opacity-10 small">
                    <div>
                        <span class="text-muted d-block mb-1">Teacher</span>
                        <?php if ($categorySlug === 'secondary-waec-neco'): ?>
                            <span class="fw-bold text-main"><i class="bi bi-book-half text-secondary me-1"></i>StudyMe Curriculum</span>
                        <?php elseif (!empty($course['teacher_id']) && !empty($course['teacher_name'])): ?>
                            <a href="<?= url('teacher-profile.php?id=' . (int)$course['teacher_id']) ?>" class="fw-bold text-primary text-decoration-none hover-underline"><i class="bi bi-person-badge-fill me-1"></i><?= e($course['teacher_name']) ?></a>
                        <?php else: ?>
                            <span class="text-muted fst-italic"><i class="bi bi-person-x me-1"></i>Currently unavailable</span>
                        <?php endif; ?>
                    </div>
                    <div class="vr d-none d-sm-block opacity-25"></div>
                    <div>
                        <span class="text-muted d-block mb-1">Duration</span>
                        <span class="fw-bold text-main"><i class="bi bi-clock-fill text-warning me-1"></i><?= $durationHours ?> Hours</span>
                    </div>
                    <div class="vr d-none d-sm-block opacity-25"></div>
                    <div>
                        <span class="text-muted d-block mb-1">Official Fee</span>
                        <span class="fw-bold text-success fs-6">₦<?= number_format($officialPrice, 0) ?></span>
                    </div>
                </div>

                <div class="card border-0 bg-primary bg-opacity-5 rounded-4 p-4 mb-4 border border-primary border-opacity-15">
                    <h4 class="fw-bold mb-3 text-main"><i class="bi bi-lightbulb-fill text-warning me-2"></i>What You Will Learn</h4>
                    <div class="row g-3">
                        <?php foreach ($whatYoullLearn as $item): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-start gap-2 small">
                                <i class="bi bi-check-circle-fill text-success mt-1 flex-shrink-0"></i>
                                <span><?= e($item) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card border-0 rounded-4 overflow-hidden mb-4 shadow-sm position-relative">
                    <img src="<?= e($courseThumbUrl) ?>"
                         class="w-100 img-fluid rounded-4"
                         style="max-height: 360px; width: 100%; object-fit: cover;"
                         alt="StudyMe <?= e($course['title']) ?>"
                         onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=800&q=80';">
                </div>

                <h4 class="fw-bold mb-3">Course Overview</h4>
                <div class="text-secondary lh-lg mb-4" style="white-space: pre-line;">
                    <?= nl2br(e($course['description'])) ?>
                </div>

                <div class="row g-4 mb-5">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                            <h5 class="fw-bold mb-3"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Skills You Will Gain</h5>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($skillsGained as $sk): ?>
                                <span class="badge bg-light text-main border rounded-pill px-3 py-2 small fw-semibold"><?= e($sk) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                            <h5 class="fw-bold mb-3"><i class="bi bi-check2-square text-primary me-2"></i>Course Requirements</h5>
                            <ul class="list-unstyled small text-muted d-flex flex-column gap-2 mb-0">
                                <?php foreach ($requirements as $req): ?>
                                <li class="d-flex align-items-start gap-2">
                                    <i class="bi bi-dot text-primary fs-5 mt-n1"></i> <?= e($req) ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="card border-0 rounded-4 p-4 mb-5" style="background: linear-gradient(135deg, #1e1e38 0%, #111827 100%);">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-2 rounded-3" style="background:rgba(245,158,11,0.2);">
                            <i class="bi bi-robot text-warning fs-3"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold text-white mb-0">24/7 AI Learning Companion</h4>
                            <small class="text-white-50">Included with this course</small>
                        </div>
                    </div>
                    <p class="text-white-50 small mb-3">When you enroll in this course, your personal AI Tutor is available 24/7 inside the video player and quiz dashboard to answer questions, explain concepts in simple analogies, and review tricky problem sets.</p>
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <div class="d-flex align-items-center gap-2 small text-white-50">
                                <i class="bi bi-check-circle-fill text-warning"></i> Instant Concept Clarification
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex align-items-center gap-2 small text-white-50">
                                <i class="bi bi-check-circle-fill text-warning"></i> Step-by-Step Code & Formula Help
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex align-items-center gap-2 small text-white-50">
                                <i class="bi bi-check-circle-fill text-warning"></i> Infinite Practice Questions
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex align-items-center gap-2 small text-white-50">
                                <i class="bi bi-check-circle-fill text-warning"></i> Automated Quiz Feedback
                            </div>
                        </div>
                    </div>
                </div>

                <h4 class="fw-bold mb-3">Course Curriculum</h4>
                <div class="accordion border-0 shadow-sm rounded-4 overflow-hidden mb-5" id="syllabusAccordion">
                    <?php if (!empty($sections)): ?>
                        <?php foreach ($sections as $index => $section): ?>
                            <?php $lessons = get_section_lessons($section['id']); ?>
                            <div class="accordion-item border-0 border-bottom border-light">
                                <h2 class="accordion-header">
                                    <button class="accordion-button py-3 fw-bold fs-6 <?= $index > 0 ? 'collapsed' : '' ?>"
                                            type="button" data-bs-toggle="collapse"
                                            data-bs-target="#collapseSection<?= $section['id'] ?>">
                                        <i class="bi bi-collection-play me-2 text-primary"></i>
                                        Module <?= $index + 1 ?>: <?= e($section['title']) ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary ms-auto me-2 rounded-pill small">
                                            <?= count($lessons) ?> lesson<?= count($lessons) !== 1 ? 's' : '' ?>
                                        </span>
                                    </button>
                                </h2>
                                <div id="collapseSection<?= $section['id'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#syllabusAccordion">
                                    <div class="accordion-body p-0">
                                        <div class="list-group list-group-flush small">
                                            <?php if (!empty($lessons)): ?>
                                                <?php foreach ($lessons as $lesson): ?>
                                                <div class="list-group-item d-flex justify-content-between align-items-center py-3 px-4 border-0 border-bottom border-light">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i class="bi bi-play-circle<?= $lesson['is_free'] ? '-fill text-success' : ' text-primary' ?>"></i>
                                                        <?= e($lesson['title']) ?>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2 text-muted">
                                                        <?php if ($lesson['video_duration'] > 0): ?>
                                                        <span class="badge bg-light border text-muted rounded-pill px-2">
                                                            <?= (int)($lesson['video_duration'] / 60) ?>m
                                                        </span>
                                                        <?php endif; ?>
                                                        <?php if ($lesson['is_free']): ?>
                                                        <span class="badge bg-success-subtle text-success rounded-pill px-2">Free Preview</span>
                                                        <?php else: ?>
                                                        <i class="bi bi-lock-fill text-muted" style="font-size:0.75rem;"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="p-3 text-muted text-center small">Lessons for this module are being populated.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">Curriculum modules are currently being finalized. Enroll now to receive instant updates.</div>
                    <?php endif; ?>
                </div>

                <?php if ($categorySlug === 'secondary-waec-neco'): ?>
                    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                        <h5 class="fw-bold mb-2"><i class="bi bi-book-half text-primary me-2"></i>Secondary School Academic Curriculum</h5>
                        <p class="text-secondary small mb-0 lh-lg">
                            Secondary School subjects on StudyMe operate with direct curriculum access and 24/7 AI tutor guidance. Students get unlimited access to all subjects, topic summaries, and WAEC/NECO/JAMB past questions without individual teacher assignment.
                        </p>
                    </div>
                <?php elseif (!empty($course['teacher_id']) && !empty($course['teacher_name'])): ?>
                    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">About the Teacher</h5>
                            <a href="<?= url('teacher-profile.php?id=' . (int)$course['teacher_id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                                View Full Profile &rarr;
                            </a>
                        </div>
                        <div class="d-flex align-items-start gap-3">
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:64px;height:64px;font-size:1.5rem;">
                                <?= strtoupper(substr($course['teacher_name'] ?? 'T', 0, 1)) ?>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">
                                    <a href="<?= url('teacher-profile.php?id=' . (int)$course['teacher_id']) ?>" class="text-decoration-none text-main hover-primary">
                                        <?= e($course['teacher_name']) ?>
                                    </a>
                                </h6>
                                <p class="text-muted small mb-2">
                                    <i class="bi bi-patch-check-fill text-primary me-1"></i>Verified StudyMe Educator
                                    <span class="ms-3"><i class="bi bi-star-fill text-warning me-1"></i><?= number_format((float)($course['teacher_rating'] ?? 4.9), 1) ?> Rating</span>
                                </p>
                                <p class="text-secondary small mb-0 lh-lg">
                                    <?= e($course['teacher_bio'] ?? 'Dedicated academic specialist with extensive industry expertise, committed to delivering high-impact, AI-supported learning.') ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-light">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-secondary bg-opacity-10 text-secondary d-flex align-items-center justify-content-center flex-shrink-0" style="width:52px;height:52px;">
                                <i class="bi bi-person-x fs-4"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">Teacher: Currently unavailable</h6>
                                <p class="text-muted small mb-0">No teacher is currently assigned to this course. All syllabus modules, evaluation quizzes, and 24/7 AI tutor support remain fully active and accessible.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                    <h4 class="fw-bold mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-question-circle text-primary"></i> Frequently Asked Questions
                    </h4>
                    <div class="accordion accordion-flush" id="courseFaqAccordion">
                        <?php foreach ($courseFaq as $idx => $faq): ?>
                            <div class="accordion-item bg-transparent border-0 mb-2">
                                <h2 class="accordion-header" id="faqHeading<?= $idx ?>">
                                    <button class="accordion-button <?= $idx > 0 ? 'collapsed' : '' ?> bg-light rounded-3 fw-semibold text-dark shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse<?= $idx ?>" aria-expanded="<?= $idx === 0 ? 'true' : 'false' ?>" aria-controls="faqCollapse<?= $idx ?>">
                                        <?= e($faq['q']) ?>
                                    </button>
                                </h2>
                                <div id="faqCollapse<?= $idx ?>" class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>" aria-labelledby="faqHeading<?= $idx ?>" data-bs-parent="#courseFaqAccordion">
                                    <div class="accordion-body text-secondary small lh-lg px-3 py-2">
                                        <?= e($faq['a']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-lg rounded-4 p-4 sticky-top" style="top:2rem; z-index:10;">
                    <img src="<?= e($courseThumbUrl) ?>"
                         class="rounded-4 w-100 mb-3" style="height:180px; object-fit:cover;" alt="StudyMe <?= e($course['title']) ?> Course Thumbnail"
                         onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=400&q=80';">

                    <div class="mb-3 text-center">
                        <div class="text-muted small mb-1">Official Enrollment Rate:</div>
                        <span class="fs-2 fw-bold text-main text-success">
                            ₦<?= number_format($officialPrice, 0) ?>
                        </span>
                        <div class="badge bg-success-subtle text-success px-2 py-1 rounded small fw-bold mt-1 d-block">
                            Full Course Access · Verified Certificate · AI Tutor
                        </div>
                    </div>

                    <?php if ($isEnrolled): ?>
                        <a href="<?= url('student/course.php?id=' . $course['id']) ?>"
                           class="btn btn-success btn-lg rounded-pill w-100 py-3 fw-bold shadow mb-3"
                           data-feedback="click">
                            <i class="bi bi-play-circle-fill me-1"></i> Continue Learning
                        </a>
                        <div class="text-center small text-muted">
                            <i class="bi bi-check-circle-fill text-success me-1"></i> You are actively enrolled in this course
                        </div>
                    <?php elseif (!$courseCheck['can_enroll']): ?>
                        <div class="alert alert-warning border-0 rounded-4 small p-3 mb-3">
                            <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                            <strong>Enrollment Restricted:</strong> <?= e($courseCheck['reason']) ?>
                        </div>
                        <button class="btn btn-secondary bg-opacity-75 btn-lg rounded-pill w-100 py-3 fw-bold mb-3" disabled style="cursor:not-allowed;">
                            <i class="bi bi-person-x-fill me-1"></i> Awaiting Active Instructor
                        </button>
                        <div class="text-center small text-muted">
                            Please choose an active course with an assigned teacher.
                        </div>
                    <?php elseif ($activeOtherCourse && !(defined('FREE_TESTING_MODE') && FREE_TESTING_MODE)): ?>
                        <button class="btn btn-secondary bg-opacity-75 btn-lg rounded-pill w-100 py-3 fw-bold mb-3" disabled>
                            <i class="bi bi-lock-fill me-1"></i> Locked (1 Active Course Limit)
                        </button>
                        <div class="text-center small text-muted">
                            Finish your active course <strong>"<?= e($activeOtherCourse['course_title']) ?>"</strong> before enrolling in a new one.
                        </div>
                    <?php else: ?>
                        <form action="<?= url('courses/details.php?slug=' . urlencode($course['slug'])) ?>" method="POST">
                            <input type="hidden" name="enroll" value="1">
                            <button type="submit" class="btn btn-primary btn-lg rounded-pill w-100 py-3 fw-bold shadow mb-3"
                                    data-feedback="success">
                                <?= (defined('FREE_TESTING_MODE') && FREE_TESTING_MODE) ? 'Enroll Now (Free Testing)' : 'Enroll Now — ₦' . number_format($officialPrice, 0) ?>
                                <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </form>
                        <div class="text-center small text-muted mb-3">
                            <?= (defined('FREE_TESTING_MODE') && FREE_TESTING_MODE)
                                ? '<i class="bi bi-check-circle-fill text-success me-1"></i> Instant free testing access enabled'
                                : '<i class="bi bi-shield-check text-success me-1"></i> Instant unlock upon payment verification' ?>
                        </div>
                    <?php endif; ?>

                    <hr class="border-secondary border-opacity-25 my-3">

                    <h6 class="fw-bold small mb-3 text-uppercase text-muted">This Course Includes:</h6>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                        <li><i class="bi bi-robot text-warning me-2"></i>24/7 AI Learning Assistant</li>
                        <li><i class="bi bi-play-btn-fill text-primary me-2"></i>Video lessons & module materials</li>
                        <li><i class="bi bi-patch-question-fill text-info me-2"></i>Module quizzes & practice tests</li>
                        <li><i class="bi bi-award-fill text-success me-2"></i>Official verified certificate</li>
                        <li><i class="bi bi-phone-fill text-secondary me-2"></i>Lifetime mobile & desktop access</li>
                    </ul>
                </div>
            </div>
        </div>

        <?php if (!empty($relatedCourses)): ?>

        <div class="mt-5 pt-4 border-top">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1">Related <?= e($categoryName) ?> Courses</h3>
                    <p class="text-muted small mb-0">Explore more courses in this pathway to continue your learning journey.</p>
                </div>
                <a href="<?= url('courses/' . ($categorySlug === 'technology' ? 'technology.php' : ($categorySlug === 'university' ? 'university.php' : ($categorySlug === 'secondary-waec-neco' ? 'secondary.php' : 'teacher.php')))) ?>" class="btn btn-outline-primary btn-sm rounded-pill fw-semibold">
                    View All <?= e($categoryName) ?> &rarr;
                </a>
            </div>

            <div class="row g-4">
                <?php foreach ($relatedCourses as $rel): ?>
                    <div class="col-md-4">
                        <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden course-card transition-all">
                            <img src="<?= e($rel['thumbnail'] ?: 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=400&q=80') ?>"
                                 class="card-img-top" style="height:160px; object-fit:cover;" alt="StudyMe <?= e($rel['title']) ?> Course">
                            <div class="card-body p-3 d-flex flex-column">
                                <div class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2 py-1 small fw-bold mb-2 align-self-start">
                                    <?= e($rel['category_name'] ?: $categoryName) ?>
                                </div>
                                <h5 class="fw-bold card-title mb-2 fs-6">
                                    <a href="<?= url('courses/details.php?slug=' . urlencode($rel['slug'])) ?>" class="text-decoration-none text-main">
                                        <?= e($rel['title']) ?>
                                    </a>
                                </h5>
                                <div class="mt-auto d-flex align-items-center justify-content-between pt-2 border-top">
                                    <span class="fw-bold text-success">₦<?= number_format((float)$rel['price'], 0) ?></span>
                                    <a href="<?= url('courses/details.php?slug=' . urlencode($rel['slug'])) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
                                        Explore &rarr;
                                    </a>
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

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
