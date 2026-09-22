<?php

require_once dirname(__DIR__) . '/config/main.php';

try {
    $pdo = getDBConnection();
    $waecCount = (int)$pdo->query("SELECT COUNT(*) FROM past_questions WHERE exam_type = 'waec'")->fetchColumn();
    $necoCount = (int)$pdo->query("SELECT COUNT(*) FROM past_questions WHERE exam_type = 'neco'")->fetchColumn();
    $jambCount = (int)$pdo->query("SELECT COUNT(*) FROM past_questions WHERE exam_type = 'jamb'")->fetchColumn();
    $totalPQ   = $waecCount + $necoCount + $jambCount;
} catch (Exception $e) {
    $waecCount = $necoCount = $jambCount = $totalPQ = 0;
}

$subjectGroups = [
    'Sciences' => [
        ['icon' => 'bi-droplet-half', 'name' => 'Chemistry',          'color' => '#f59e0b'],
        ['icon' => 'bi-lightning-charge', 'name' => 'Physics',         'color' => '#3b82f6'],
        ['icon' => 'bi-tree',            'name' => 'Biology',          'color' => '#22c55e'],
        ['icon' => 'bi-calculator',      'name' => 'Further Mathematics','color' => '#8b5cf6'],
        ['icon' => 'bi-globe2',          'name' => 'Agricultural Science','color' => '#10b981'],
        ['icon' => 'bi-cpu',             'name' => 'Computer Science', 'color' => '#6366f1'],
    ],
    'Arts & Humanities' => [
        ['icon' => 'bi-book',            'name' => 'English Language', 'color' => '#ef4444'],
        ['icon' => 'bi-translate',       'name' => 'Literature in English','color' => '#f97316'],
        ['icon' => 'bi-map',             'name' => 'Geography',        'color' => '#14b8a6'],
        ['icon' => 'bi-clock-history',   'name' => 'History',          'color' => '#a16207'],
        ['icon' => 'bi-palette',         'name' => 'Fine Arts',        'color' => '#ec4899'],
        ['icon' => 'bi-music-note-beamed','name'=> 'Music',            'color' => '#8b5cf6'],
    ],
    'Social Sciences' => [
        ['icon' => 'bi-bank',            'name' => 'Economics',        'color' => '#0ea5e9'],
        ['icon' => 'bi-people',          'name' => 'Government',       'color' => '#6366f1'],
        ['icon' => 'bi-person-heart',    'name' => 'CRS / IRS',        'color' => '#f59e0b'],
        ['icon' => 'bi-shield-check',    'name' => 'Civic Education',  'color' => '#10b981'],
        ['icon' => 'bi-house',           'name' => 'Home Economics',   'color' => '#f43f5e'],
        ['icon' => 'bi-globe-americas',  'name' => 'French',           'color' => '#3b82f6'],
    ],
    'Commercial' => [
        ['icon' => 'bi-briefcase',       'name' => 'Commerce',         'color' => '#22c55e'],
        ['icon' => 'bi-journal-check',   'name' => 'Financial Accounting','color'=>'#f59e0b'],
        ['icon' => 'bi-file-text',       'name' => 'Office Practice',  'color' => '#6366f1'],
        ['icon' => 'bi-bar-chart',       'name' => 'Marketing',        'color' => '#ef4444'],
    ],
    'Core' => [
        ['icon' => 'bi-hash',            'name' => 'Mathematics',      'color' => '#6C2BFF'],
        ['icon' => 'bi-chat-square-text','name' => 'Yoruba / Igbo / Hausa','color'=>'#14b8a6'],
        ['icon' => 'bi-tools',           'name' => 'Technical Drawing', 'color' => '#f97316'],
        ['icon' => 'bi-gear',            'name' => 'Basic Technology',  'color' => '#64748b'],
    ],
];

$pageTitle = 'Secondary School (WAEC • NECO • JAMB) — StudyMe';
include BASE_PATH . '/includes/layouts/header.php';
?>

<style>
.sec-hero {
    background: linear-gradient(135deg, #064e3b 0%, #065f46 50%, #047857 100%);
    position: relative;
    overflow: hidden;
}
.sec-hero::before {
    content: '';
    position: absolute;
    top: -80px; right: -80px;
    width: 350px; height: 350px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
}
.sec-hero::after {
    content: '';
    position: absolute;
    bottom: -60px; left: -60px;
    width: 250px; height: 250px;
    background: rgba(255,255,255,0.04);
    border-radius: 50%;
}
.exam-badge-card {
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 16px;
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
    cursor: default;
}
.exam-badge-card:hover {
    background: rgba(255,255,255,0.14);
    transform: translateY(-3px);
}
.bundle-card {
    border: 2px solid #d1fae5;
    border-radius: 24px;
    background: #fff;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(6,78,59,0.10);
}
body.dark .bundle-card {
    background: #1e293b;
    border-color: rgba(16,185,129,0.25);
}
.bundle-price-badge {
    background: linear-gradient(135deg, #064e3b, #047857);
    color: #fff;
    border-radius: 50px;
    padding: 10px 28px;
    font-size: 1.05rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    display: inline-block;
}
.subject-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 0.78rem;
    font-weight: 600;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #065f46;
    transition: all 0.2s ease;
    white-space: nowrap;
}
body.dark .subject-pill {
    background: rgba(16,185,129,0.10);
    border-color: rgba(16,185,129,0.2);
    color: #6ee7b7;
}
.subject-pill:hover {
    background: #dcfce7;
    transform: scale(1.04);
}
.exam-card {
    border-radius: 18px;
    border: 1px solid;
    padding: 20px;
    transition: all 0.3s ease;
}
.exam-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0,0,0,0.10);
}
.waec-card  { border-color: #fde68a; background: #fffbeb; }
.neco-card  { border-color: #bfdbfe; background: #eff6ff; }
.jamb-card  { border-color: #ddd6fe; background: #f5f3ff; }
body.dark .waec-card { background: rgba(245,158,11,0.08); border-color: rgba(245,158,11,0.2); }
body.dark .neco-card { background: rgba(59,130,246,0.08); border-color: rgba(59,130,246,0.2); }
body.dark .jamb-card { background: rgba(139,92,246,0.08); border-color: rgba(139,92,246,0.2); }
.tab-group-btn {
    border: none;
    background: #f1f5f9;
    border-radius: 50px;
    padding: 6px 16px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s;
}
.tab-group-btn.active, .tab-group-btn:hover {
    background: #064e3b;
    color: #fff;
}
body.dark .tab-group-btn {
    background: #1e293b;
    color: #94a3b8;
}
body.dark .tab-group-btn.active {
    background: #047857;
    color: #fff;
}
.subject-group-panel { display: none; }
.subject-group-panel.active { display: flex; flex-wrap: wrap; gap: 10px; }
.feature-check { color: #059669; font-size: 0.9rem; }
.cta-enroll-btn {
    background: linear-gradient(135deg, #047857, #059669);
    color: #fff;
    border: none;
    border-radius: 50px;
    padding: 15px 40px;
    font-size: 1.05rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 8px 24px rgba(5,150,105,0.35);
}
.cta-enroll-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(5,150,105,0.45);
    background: linear-gradient(135deg, #065f46, #047857);
}
</style>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">

        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="<?= url('index.php') ?>" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= url('courses/index.php') ?>" class="text-decoration-none">Courses</a></li>
                <li class="breadcrumb-item active" aria-current="page">Secondary School</li>
            </ol>
        </nav>

        <div class="sec-hero rounded-4 p-4 p-md-5 mb-5 position-relative">
            <div class="row align-items-center g-4 position-relative" style="z-index: 2;">
                <div class="col-lg-7">
                    <span class="badge bg-warning text-dark rounded-pill px-3 py-2 fw-bold text-uppercase small mb-3 d-inline-flex align-items-center gap-1">
                        <i class="bi bi-stars"></i> All-Inclusive Bundle
                    </span>
                    <h1 class="display-5 fw-bold text-white mb-2">Secondary School<br>Complete Package</h1>
                    <p class="text-white-50 lead fs-6 mb-4" style="max-width: 560px;">
                        One subscription covers every Senior Secondary subject, WAEC &amp; NECO syllabi, JAMB/UTME preparation, and thousands of past questions — all with 24/7 AI tutoring.
                    </p>
                    <div class="d-flex flex-wrap gap-3 align-items-center">
                        <a href="<?= url('auth/register.php?secondary=1') ?>" class="btn btn-warning rounded-pill px-4 fw-bold shadow">
                            <i class="bi bi-lightning-fill me-1"></i> Get Full Access — ₦3,000 <?= (defined('FREE_TESTING_MODE') && FREE_TESTING_MODE) ? '(Free Testing: ₦0)' : '' ?>
                        </a>
                        <a href="#subjectsSection" class="btn btn-outline-light rounded-pill px-4 fw-semibold">
                            <i class="bi bi-journals me-1"></i> View All Subjects
                        </a>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="exam-badge-card p-3 text-center">
                                <div class="fs-2 fw-black text-warning mb-1"><?= number_format($waecCount + 1200) ?>+</div>
                                <div class="text-white-50 small fw-semibold">WAEC Past Questions</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="exam-badge-card p-3 text-center">
                                <div class="fs-2 fw-black text-info mb-1"><?= number_format($necoCount + 900) ?>+</div>
                                <div class="text-white-50 small fw-semibold">NECO Past Questions</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="exam-badge-card p-3 text-center">
                                <div class="fs-2 fw-black text-warning mb-1"><?= number_format($jambCount + 2000) ?>+</div>
                                <div class="text-white-50 small fw-semibold">JAMB/UTME Questions</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="exam-badge-card p-3 text-center">
                                <div class="fs-2 fw-black text-success mb-1">25+</div>
                                <div class="text-white-50 small fw-semibold">Core Subjects Covered</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-12">
                <h2 class="fw-bold mb-1">Everything in One Package</h2>
                <p class="text-muted">Register once, access everything — no subject-by-subject fees.</p>
            </div>

            <div class="col-md-4">
                <div class="exam-card waec-card h-100 d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-2 rounded-circle" style="background:#fef3c7;">
                            <i class="bi bi-award-fill text-warning fs-4"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">WAEC / WASSCE</h5>
                            <small class="text-muted">Senior Secondary Certificate Exam</small>
                        </div>
                    </div>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-4 flex-grow-1">
                        <li><i class="bi bi-check-circle-fill feature-check me-2"></i>Full SSCE Syllabus for all subjects</li>
                        <li><i class="bi bi-check-circle-fill feature-check me-2"></i>ALOC Station Live &amp; Vault Questions (2005–2024)</li>
                        <li><i class="bi bi-check-circle-fill feature-check me-2"></i>Detailed step-by-step solutions</li>
                        <li><i class="bi bi-check-circle-fill feature-check me-2"></i>Interactive CBT drill mode with instant scoring</li>
                        <li><i class="bi bi-check-circle-fill feature-check me-2"></i>AI-powered Socratic question explanations</li>
                    </ul>
                    <div class="d-flex align-items-center justify-content-between pt-3 border-top border-warning border-opacity-25">
                        <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-semibold">Included in Bundle</span>
                        <a href="<?= url('courses/past-questions.php?exam=waec') ?>" class="btn btn-sm btn-outline-dark rounded-pill px-3 fw-bold">
                            Practice WAEC &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="exam-card neco-card h-100 d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-2 rounded-circle" style="background:#dbeafe;">
                            <i class="bi bi-patch-check-fill text-primary fs-4"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">NECO</h5>
                            <small class="text-muted">National Examinations Council</small>
                        </div>
                    </div>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-4 flex-grow-1">
                        <li><i class="bi bi-check-circle-fill feature-check me-2"></i>Full NECO Syllabus coverage</li>
                        <li><i class="bi bi-check-circle-fill feature-check me-2"></i>Official NECO Past Questions &amp; Answer Bank</li>
                        <li><i class="bi bi-check-circle-fill feature-check me-2"></i>Objective &amp; theory solution breakdowns</li>
                        <li><i class="bi bi-check-circle-fill feature-check me-2"></i>SSCE June/November prep with CBT mode</li>
                        <li><i class="bi bi-check-circle-fill feature-check me-2"></i>AI explanations per question</li>
                    </ul>
                    <div class="d-flex align-items-center justify-content-between pt-3 border-top border-primary border-opacity-25">
                        <span class="badge bg-primary rounded-pill px-3 py-1 fw-semibold text-white">Included in Bundle</span>
                        <a href="<?= url('courses/past-questions.php?exam=neco') ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                            Practice NECO &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="exam-card jamb-card h-100 d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-2 rounded-circle" style="background:#ede9fe;">
                            <i class="bi bi-mortarboard-fill text-purple fs-4" style="color:#7c3aed;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">JAMB / UTME</h5>
                            <small class="text-muted">Unified Tertiary Matriculation Exam</small>
                        </div>
                    </div>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-4 flex-grow-1">
                        <li><i class="bi bi-check-circle-fill feature-check me-2"></i>Full UTME Syllabus (Use of English + 3 subjects)</li>
                        <li><i class="bi bi-check-circle-fill feature-check me-2"></i>ALOC Live Stream JAMB Past Questions</li>
                        <li><i class="bi bi-check-circle-fill feature-check me-2"></i>Interactive CBT simulation with immediate feedback</li>
                        <li><i class="bi bi-check-circle-fill feature-check me-2"></i>Cut-off mark guidance per school</li>
                        <li><i class="bi bi-check-circle-fill feature-check me-2"></i>AI Tutor question solver</li>
                    </ul>
                    <div class="d-flex align-items-center justify-content-between pt-3 border-top border-purple border-opacity-25">
                        <span class="badge rounded-pill px-3 py-1 fw-semibold text-white" style="background:#7c3aed;">Included in Bundle</span>
                        <a href="<?= url('courses/past-questions.php?exam=utme') ?>" class="btn btn-sm btn-outline-dark rounded-pill px-3 fw-bold">
                            Practice JAMB &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-5" id="subjectsSection">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <h3 class="fw-bold mb-1">All Subjects Covered</h3>
                    <p class="text-muted mb-0 small">Every subject in the Nigerian Secondary School curriculum — SS1 to SS3</p>
                </div>
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 fw-bold">25+ Subjects</span>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-4" id="subjectTabs">
                <?php $groupIndex = 0; foreach ($subjectGroups as $groupName => $subjects): ?>
                <button class="tab-group-btn <?= $groupIndex === 0 ? 'active' : '' ?>"
                        data-group="group-<?= $groupIndex ?>"
                        onclick="switchSubjectGroup(this, 'group-<?= $groupIndex ?>')">
                    <?= htmlspecialchars($groupName) ?>
                </button>
                <?php $groupIndex++; endforeach; ?>
            </div>

            <?php $groupIndex = 0; foreach ($subjectGroups as $groupName => $subjects): ?>
            <div class="subject-group-panel <?= $groupIndex === 0 ? 'active' : '' ?>" id="group-<?= $groupIndex ?>">
                <?php foreach ($subjects as $subj): ?>
                <div class="subject-pill">
                    <i class="<?= $subj['icon'] ?>" style="color: <?= $subj['color'] ?>;"></i>
                    <?= htmlspecialchars($subj['name']) ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php $groupIndex++; endforeach; ?>

            <div class="mt-4 p-3 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25 small text-success fw-semibold">
                <i class="bi bi-info-circle-fill me-2"></i>
                All subjects include full syllabus content, WAEC &amp; NECO aligned notes, past questions, and AI-guided explanations.
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-12">
                <h3 class="fw-bold mb-1">What You Get With This Bundle</h3>
                <p class="text-muted">Everything a Nigerian secondary school student needs — in one place.</p>
            </div>
            <?php
            $features = [
                ['icon'=>'bi-journals','color'=>'#047857','bg'=>'#d1fae5','title'=>'Complete Curriculum Notes','desc'=>'SS1–SS3 notes for all 25+ subjects, aligned with NERDC curriculum.'],
                ['icon'=>'bi-file-earmark-text','color'=>'#d97706','bg'=>'#fef3c7','title'=>'Past Questions Bank','desc'=>'Thousands of WAEC, NECO &amp; JAMB past questions with answers from 2005 to date.'],
                ['icon'=>'bi-clock-history','color'=>'#7c3aed','bg'=>'#ede9fe','title'=>'CBT Mock Exams','desc'=>'Timed mock exams that simulate the real JAMB CBT interface and WAEC structure.'],
                ['icon'=>'bi-robot','color'=>'#f59e0b','bg'=>'#fffbeb','title'=>'24/7 AI Tutor','desc'=>'Ask any question anytime. AI explains concepts, solves problems, and tracks weak areas.'],
                ['icon'=>'bi-bar-chart-steps','color'=>'#0ea5e9','bg'=>'#e0f2fe','title'=>'Progress Tracking','desc'=>'Monitor your performance per subject and per exam type in real time.'],
                ['icon'=>'bi-award-fill','color'=>'#059669','bg'=>'#dcfce7','title'=>'Completion Certificate','desc'=>'Earn a StudyMe certificate after completing your secondary school prep program.'],
            ];
            foreach ($features as $feat): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 p-4 h-100 hover-lift" style="transition:all 0.3s ease;">
                    <div class="d-flex align-items-start gap-3">
                        <div class="p-2 rounded-3 flex-shrink-0" style="background:<?= $feat['bg'] ?>;">
                            <i class="<?= $feat['icon'] ?> fs-4" style="color:<?= $feat['color'] ?>;"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1"><?= $feat['title'] ?></h6>
                            <p class="text-muted small mb-0"><?= $feat['desc'] ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="bundle-card mb-5" id="enrollSection">
            <div class="row g-0">
                <div class="col-lg-7 p-4 p-md-5">
                    <span class="badge bg-success rounded-pill px-3 py-2 fw-bold mb-3 d-inline-block">
                        <i class="bi bi-fire me-1"></i> Best Value — Single Payment
                    </span>
                    <h2 class="fw-bold mb-2">Secondary School Complete Bundle</h2>
                    <p class="text-muted mb-4">All subjects · WAEC · NECO · JAMB · Past Questions · AI Tutor · Mock Exams</p>

                    <div class="d-flex flex-wrap gap-3 align-items-center mb-4">
                        <div>
                            <div class="text-muted small mb-1">Bundle Price (Lifetime Access)</div>
                            <div class="d-flex align-items-baseline gap-2">
                                <span class="fs-1 fw-black text-success">₦15,000</span>
                                <span class="text-muted text-decoration-line-through small">₦45,000</span>
                                <span class="badge bg-danger rounded-pill">67% OFF</span>
                            </div>
                        </div>
                    </div>

                    <ul class="list-unstyled d-flex flex-column gap-2 mb-4">
                        <li class="small"><i class="bi bi-check-circle-fill text-success me-2"></i>Instant access to all 25+ subjects</li>
                        <li class="small"><i class="bi bi-check-circle-fill text-success me-2"></i>Full WAEC, NECO &amp; JAMB past questions (2005–2024)</li>
                        <li class="small"><i class="bi bi-check-circle-fill text-success me-2"></i>Unlimited AI Tutor sessions</li>
                        <li class="small"><i class="bi bi-check-circle-fill text-success me-2"></i>CBT Mock Exams with timed simulation</li>
                        <li class="small"><i class="bi bi-check-circle-fill text-success me-2"></i>Certificate of Completion</li>
                        <li class="small"><i class="bi bi-check-circle-fill text-success me-2"></i>Lifetime access — pay once, study forever</li>
                    </ul>

                    <div class="d-flex flex-wrap gap-3">
                        <?php if (function_exists('is_logged_in') && is_logged_in()): ?>
                            <?php

                            try {
                                $pdo = getDBConnection();
                                $secCourse = $pdo->query("SELECT id, slug FROM courses WHERE category_id IN (SELECT id FROM categories WHERE slug LIKE '%secondary%') AND status = 'published' ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                            } catch (Exception $e) { $secCourse = null; }
                            ?>
                            <?php if ($secCourse): ?>
                            <a href="<?= url('courses/details.php?slug=' . urlencode($secCourse['slug'])) ?>" class="cta-enroll-btn text-decoration-none d-inline-flex align-items-center gap-2">
                                <i class="bi bi-lightning-fill"></i> Enroll Now — <?= (defined('FREE_TESTING_MODE') && FREE_TESTING_MODE) ? 'FREE (Testing)' : '₦3,000' ?>
                            </a>
                            <?php else: ?>
                            <a href="<?= url('courses/index.php') ?>" class="cta-enroll-btn text-decoration-none d-inline-flex align-items-center gap-2">
                                <i class="bi bi-lightning-fill"></i> Get Started
                            </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="<?= url('auth/register.php?secondary=1') ?>" class="cta-enroll-btn text-decoration-none d-inline-flex align-items-center gap-2">
                                <i class="bi bi-person-plus-fill"></i> Create Account &amp; Enroll
                            </a>
                            <a href="<?= url('auth/login.php') ?>" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold">
                                Already have an account? Log in
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-5 d-none d-lg-flex align-items-center justify-content-center p-5" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
                    <div class="text-center">
                        <div class="mb-4">
                            <i class="bi bi-mortarboard-fill" style="font-size: 5rem; color: #047857;"></i>
                        </div>
                        <div class="bundle-price-badge mb-3">
                            <?= (defined('FREE_TESTING_MODE') && FREE_TESTING_MODE) ? '₦0 Free Testing (Official ₦3,000)' : '₦3,000 Bundle' ?>
                        </div>
                        <div class="text-muted small mb-3">All Subjects · WAEC / NECO / JAMB · No Teacher Needed</div>
                        <div class="d-flex flex-column gap-2">
                            <div class="badge bg-success text-white rounded-pill px-3 py-2 w-100 fw-semibold">✓ WAEC Prep Included</div>
                            <div class="badge bg-primary text-white rounded-pill px-3 py-2 w-100 fw-semibold">✓ NECO Prep Included</div>
                            <div class="badge rounded-pill px-3 py-2 w-100 fw-semibold text-white" style="background:#7c3aed;">✓ JAMB/UTME Included</div>
                            <div class="badge bg-warning text-dark rounded-pill px-3 py-2 w-100 fw-semibold">✓ All 25+ Subjects</div>
                            <div class="badge bg-dark text-white rounded-pill px-3 py-2 w-100 fw-semibold">✓ Past Questions Bank</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5">
            <h3 class="fw-bold mb-4">Frequently Asked Questions</h3>
            <div class="accordion accordion-flush" id="secFAQ">
                <?php
                $faqs = [
                    ['q'=>'Is this really one price for all subjects?', 'a'=>'Yes. The ₦3,000 bundle gives you access to all 25+ secondary school subjects, full WAEC, NECO, and JAMB past questions, mock exams, 24/7 AI tutoring, and a certificate of completion — no hidden fees, no per-subject charges.'],
                    ['q'=>'Is there an assigned teacher for Secondary School?', 'a'=>'No. Secondary School operates with direct curriculum access and 24/7 AI tutor guidance without assigned individual teachers. You have full self-paced access to all subjects and past questions.'],
                    ['q'=>'Can I use this for both WAEC and JAMB?', 'a'=>'Absolutely. The bundle is designed to cover all three major exams — WAEC (WASSCE), NECO, and JAMB (UTME). You get dedicated practice content and past questions for each.'],
                    ['q'=>'How many years of past questions are available?', 'a'=>'We cover WAEC questions from 2005–2024, NECO from 2008–2024, and JAMB from 2005–2024 with detailed solutions for every question.'],
                    ['q'=>'Is the AI tutor available 24/7?', 'a'=>'Yes. The StudyMe AI Tutor is available at any time. You can ask it to explain any concept, solve past questions step by step, or generate practice questions on any topic.'],
                ];
                foreach ($faqs as $i => $faq): ?>
                <div class="accordion-item border-0 border-bottom">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-semibold bg-transparent shadow-none text-main px-0"
                                type="button" data-bs-toggle="collapse"
                                data-bs-target="#faq<?= $i ?>" aria-expanded="false">
                            <?= htmlspecialchars($faq['q']) ?>
                        </button>
                    </h2>
                    <div id="faq<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#secFAQ">
                        <div class="accordion-body px-0 text-muted small lh-lg">
                            <?= htmlspecialchars($faq['a']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>

<script>
function switchSubjectGroup(btn, groupId) {
    // Deactivate all tabs
    document.querySelectorAll('.tab-group-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.subject-group-panel').forEach(p => p.classList.remove('active'));
    // Activate selected
    btn.classList.add('active');
    const panel = document.getElementById(groupId);
    if (panel) panel.classList.add('active');
}
</script>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
