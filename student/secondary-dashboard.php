<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';
require_once BASE_PATH . '/includes/functions/activity.php';
require_once BASE_PATH . '/includes/functions/referrals.php';

secure_page(ROLE_STUDENT);

$user   = current_user();
$userId = (int)$user['id'];
$pdo    = getDBConnection();

// Auto-provision student profile if not existing
$stmtSt = $pdo->prepare("SELECT s.* FROM students s WHERE s.user_id = ? LIMIT 1");
$stmtSt->execute([$userId]);
$student = $stmtSt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    $studentNum = 'STD-' . date('Y') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
    $pdo->prepare("INSERT INTO students (user_id, student_number, student_type, academic_level, target_exam) VALUES (?, ?, 'secondary', 'SS3 / WAEC Candidate', 'WAEC / JAMB / NECO')")
        ->execute([$userId, $studentNum]);
    $studentId = (int)$pdo->lastInsertId();
    $student = [
        'id' => $studentId,
        'user_id' => $userId,
        'student_number' => $studentNum,
        'student_type' => 'secondary',
        'academic_level' => 'SS3 / WAEC Candidate',
        'target_exam' => 'WAEC / JAMB / NECO'
    ];
} else {
    $studentId = (int)$student['id'];
}

// Ensure secondary student type
if (empty($student['student_type']) || $student['student_type'] !== 'secondary') {
    $pdo->prepare("UPDATE students SET student_type = 'secondary' WHERE id = ?")->execute([$studentId]);
    $student['student_type'] = 'secondary';
}

log_user_activity($userId, 'secondary_dashboard_visit', 'Student accessed Secondary School Dashboard');

// Referral & Wallet info
$refCode = get_user_referral_code($userId);
$refLink = get_base_url() . '/auth/register.php?ref=' . $refCode;
$wallet  = get_user_wallet($userId);

// Fetch real metrics from database
$practiceStats = get_secondary_practice_stats($studentId);
$recentAttempts = get_secondary_practice_history($studentId, 5);
$streak = calculate_user_learning_streak($userId);
$todayStats = get_user_today_stats($userId);

// Fetch Secondary Subjects from database
$subjects = get_secondary_subjects('active');

// Count past questions by exam type
$pqCounts = ['waec' => 0, 'neco' => 0, 'jamb' => 0, 'total' => 0];
try {
    $stmtPQ = $pdo->query("SELECT exam_type, COUNT(*) AS cnt FROM past_questions GROUP BY exam_type");
    while ($row = $stmtPQ->fetch(PDO::FETCH_ASSOC)) {
        $et = strtolower($row['exam_type']);
        if ($et === 'utme') $et = 'jamb';
        if (isset($pqCounts[$et])) {
            $pqCounts[$et] += (int)$row['cnt'];
        }
        $pqCounts['total'] += (int)$row['cnt'];
    }
} catch (Exception $e) {}

// Recent Activity logs
$recentActivity = [];
try {
    $stmtAct = $pdo->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 6");
    $stmtAct->execute([$userId]);
    $recentActivity = $stmtAct->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Secondary Announcements
$announcements = [];
try {
    $stmtAnn = $pdo->prepare("
        SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) AS author_name
        FROM announcements a
        LEFT JOIN users u ON a.created_by = u.id
        WHERE a.status = 'published' AND a.target_type IN ('all', 'students', 'secondary')
        ORDER BY a.priority = 'urgent' DESC, a.created_at DESC
        LIMIT 3
    ");
    $stmtAnn->execute();
    $announcements = $stmtAnn->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$pageTitle = 'Secondary School Dashboard | StudyMe';
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<style>
.sec-dashboard {
    --sec-navy: #0F172A;
    --sec-blue: #1E40AF;
    --sec-emerald: #059669;
    --sec-gold: #F59E0B;
    --sec-crimson: #DC2626;
    --sec-card-bg: #FFFFFF;
    --sec-border: #E2E8F0;
}

.sec-hero-card {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 50%, #065F46 100%);
    color: #ffffff;
    border-radius: 1.25rem;
    position: relative;
    overflow: hidden;
}

.sec-hero-card::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(245, 158, 11, 0.2) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.sec-metric-card {
    background: #ffffff;
    border: 1px solid var(--sec-border);
    border-radius: 1rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.sec-metric-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08);
}

.sec-exam-btn {
    border-radius: 1rem;
    transition: all 0.25s ease;
    border: 1px solid rgba(255,255,255,0.15);
}
.sec-exam-btn:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 12px 28px rgba(0,0,0,0.15);
}

.sec-subject-card {
    border: 1px solid var(--sec-border);
    border-radius: 1rem;
    background: #ffffff;
    transition: all 0.2s ease;
}
.sec-subject-card:hover {
    border-color: #93C5FD;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(30, 64, 175, 0.08);
}

.sec-icon-circle {
    width: 48px;
    height: 48px;
    border-radius: 0.85rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
}
</style>

<div class="sec-dashboard pb-5">

    <!-- HERO HEADER & WELCOME BANNER -->
    <div class="sec-hero-card p-4 p-md-5 mb-4 shadow-sm">
        <div class="row align-items-center g-4 position-relative" style="z-index: 2;">
            <div class="col-lg-8">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <span class="badge bg-warning text-dark fw-bold rounded-pill px-3 py-2">
                        <i class="bi bi-mortarboard-fill me-1"></i> SECONDARY SCHOOL HUB
                    </span>
                    <span class="badge bg-white bg-opacity-20 text-white rounded-pill px-3 py-2">
                        <i class="bi bi-person-badge me-1"></i> <?= e($student['academic_level'] ?? 'SS3 / WAEC Candidate') ?>
                    </span>
                    <span class="badge bg-emerald bg-opacity-25 text-white border border-light border-opacity-25 rounded-pill px-3 py-2" style="background: rgba(5,150,105,0.4);">
                        <i class="bi bi-bullseye me-1"></i> <?= e($student['target_exam'] ?? 'WAEC / JAMB 2025/2026') ?>
                    </span>
                </div>

                <h1 class="display-6 fw-bold mb-2 text-white">
                    Welcome back, <?= e($user['first_name']) ?>! 👋
                </h1>
                <p class="text-white-50 fs-6 mb-4" style="max-width: 620px;">
                    Your dedicated secondary school study portal. Practice thousands of verified WAEC, NECO &amp; JAMB past questions, master syllabus topics with step-by-step notes, and consult your 24/7 AI Tutor.
                </p>

                <!-- Search Bar -->
                <form action="<?= url('student/secondary-past-questions.php') ?>" method="GET" class="d-flex gap-2" style="max-width: 580px;">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-0 text-muted ps-3 rounded-start-pill">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-0 py-2 py-md-3" placeholder="Search any topic, formula, or exam question..." required>
                        <button type="submit" class="btn btn-warning px-4 fw-bold rounded-end-pill">
                            Search Vault
                        </button>
                    </div>
                </form>
            </div>

            <!-- Quick Action Exam Portals -->
            <div class="col-lg-4">
                <div class="bg-white bg-opacity-10 p-4 rounded-4 border border-white border-opacity-20 backdrop-blur">
                    <h6 class="text-warning fw-bold text-uppercase small mb-3 letter-spacing-1">
                        <i class="bi bi-lightning-charge-fill me-1"></i> Instant Exam Drills
                    </h6>
                    <div class="d-grid gap-2">
                        <a href="<?= url('student/secondary-waec.php') ?>" class="btn btn-warning text-dark fw-bold sec-exam-btn py-2 text-start d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-award-fill me-2"></i> WAEC SSCE CBT</span>
                            <span class="badge bg-dark text-white rounded-pill px-2"><?= $pqCounts['waec'] ?> Qs</span>
                        </a>
                        <a href="<?= url('student/secondary-neco.php') ?>" class="btn btn-info text-dark fw-bold sec-exam-btn py-2 text-start d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-patch-check-fill me-2"></i> NECO SSCE Practice</span>
                            <span class="badge bg-dark text-white rounded-pill px-2"><?= $pqCounts['neco'] ?> Qs</span>
                        </a>
                        <a href="<?= url('student/secondary-jamb.php') ?>" class="btn btn-success text-white fw-bold sec-exam-btn py-2 text-start d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-mortarboard-fill me-2"></i> JAMB / UTME Mock</span>
                            <span class="badge bg-dark text-white rounded-pill px-2"><?= $pqCounts['jamb'] ?> Qs</span>
                        </a>
                        <a href="<?= url('student/secondary-ai-tutor.php') ?>" class="btn btn-outline-light fw-bold sec-exam-btn py-2 text-start d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-robot text-warning me-2"></i> Ask AI Tutor</span>
                            <span class="badge bg-warning text-dark rounded-pill px-2">Online</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- METRICS & KEY STATS ROW -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="sec-metric-card p-3 p-md-4 h-100 d-flex align-items-center gap-3">
                <div class="sec-icon-circle bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Questions Solved</div>
                    <h3 class="fw-bold mb-0 text-dark"><?= number_format($practiceStats['total_questions']) ?></h3>
                    <small class="text-success fw-semibold"><i class="bi bi-check-lg"></i> <?= $practiceStats['correct_answers'] ?> correct</small>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="sec-metric-card p-3 p-md-4 h-100 d-flex align-items-center gap-3">
                <div class="sec-icon-circle bg-success bg-opacity-10 text-success">
                    <i class="bi bi-pie-chart-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Overall Accuracy</div>
                    <h3 class="fw-bold mb-0 text-dark"><?= $practiceStats['average_accuracy'] ?>%</h3>
                    <small class="text-muted small"><?= $practiceStats['total_attempts'] ?> drills completed</small>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="sec-metric-card p-3 p-md-4 h-100 d-flex align-items-center gap-3">
                <div class="sec-icon-circle bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-fire"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Learning Streak</div>
                    <h3 class="fw-bold mb-0 text-dark"><?= is_array($streak) ? (int)($streak['streak_days'] ?? 0) : (int)$streak ?> Days</h3>
                    <small class="text-warning fw-semibold">🔥 <?= (int)($todayStats['activities_count'] ?? 0) ?> actions today</small>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="sec-metric-card p-3 p-md-4 h-100 d-flex align-items-center gap-3">
                <div class="sec-icon-circle bg-info bg-opacity-10 text-info">
                    <i class="bi bi-journal-bookmark-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Active Subjects</div>
                    <h3 class="fw-bold mb-0 text-dark"><?= count($subjects) ?></h3>
                    <small class="text-info fw-semibold">WAEC &amp; JAMB Core</small>
                </div>
            </div>
        </div>
    </div>

    <!-- ANNOUNCEMENTS BANNER (IF ANY) -->
    <?php if (!empty($announcements)): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-light overflow-hidden">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-megaphone-fill text-warning"></i> Announcements &amp; Exam Updates
                </h6>
                <a href="<?= url('student/secondary-notifications.php') ?>" class="text-decoration-none small fw-bold">View all</a>
            </div>
            <div class="row g-2">
                <?php foreach ($announcements as $ann): ?>
                <div class="col-md-4">
                    <div class="bg-white p-3 rounded-3 border h-100">
                        <span class="badge <?= $ann['priority'] === 'urgent' ? 'bg-danger' : 'bg-primary' ?> rounded-pill mb-1" style="font-size: 0.7rem;">
                            <?= strtoupper($ann['priority']) ?>
                        </span>
                        <h6 class="fw-bold text-dark mb-1 small"><?= e($ann['title']) ?></h6>
                        <p class="text-muted small mb-0 text-truncate"><?= e(strip_tags($ann['content'])) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- MAIN TWO-COLUMN CONTENT -->
    <div class="row g-4">

        <!-- LEFT COLUMN: SUBJECTS & PAST QUESTIONS -->
        <div class="col-lg-8">

            <!-- SECONDARY SUBJECTS CATALOG -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div>
                    <h4 class="fw-bold mb-1 text-dark">
                        <i class="bi bi-journals text-primary me-2"></i> Secondary School Subjects
                    </h4>
                    <p class="text-muted small mb-0">Select a subject to practice questions, read notes, and test yourself.</p>
                </div>
                <a href="<?= url('student/secondary-subjects.php') ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                    View All (<?= count($subjects) ?>)
                </a>
            </div>

            <div class="row g-3 mb-4">
                <?php foreach (array_slice($subjects, 0, 6) as $subj): ?>
                <div class="col-md-6">
                    <div class="sec-subject-card p-3 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="sec-icon-circle" style="background: <?= e($subj['color'] ?? '#2563EB') ?>15; color: <?= e($subj['color'] ?? '#2563EB') ?>;">
                                    <i class="bi <?= e($subj['icon'] ?? 'bi-book-half') ?>"></i>
                                </div>
                                <span class="badge bg-light text-dark border rounded-pill px-3 py-1 font-monospace fw-bold">
                                    <?= e($subj['code']) ?>
                                </span>
                            </div>
                            <h5 class="fw-bold mb-1 text-dark"><?= e($subj['name']) ?></h5>
                            <p class="text-muted small mb-3 text-truncate-2" style="font-size: 0.85rem; line-height: 1.4;">
                                <?= e($subj['description']) ?>
                            </p>
                        </div>
                        <div class="pt-2 border-top d-flex align-items-center justify-content-between">
                            <span class="small text-muted">
                                <i class="bi bi-layers-fill me-1"></i> <?= (int)$subj['topic_count'] ?> Topics &bull; <?= (int)$subj['question_count'] ?> Qs
                            </span>
                            <a href="<?= url('student/secondary-practice.php?subject=' . urlencode($subj['slug'])) ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">
                                Practice <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- RECENT CBT PRACTICE ATTEMPTS -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white p-3 p-md-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-clock-history text-success me-2"></i> Recent Practice History
                        </h5>
                        <small class="text-muted">Your latest test attempts and score breakdown</small>
                    </div>
                    <a href="<?= url('student/secondary-progress.php') ?>" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold">
                        Full Progress
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Exam &amp; Subject</th>
                                <th>Date</th>
                                <th>Score</th>
                                <th>Accuracy</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentAttempts)): ?>
                                <?php foreach ($recentAttempts as $att): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?= strtoupper(e($att['exam_type'])) ?> &bull; <?= e($att['subject_name'] ?? ucfirst($att['subject_slug'])) ?></div>
                                        <div class="text-muted small"><?= e($att['mode'] === 'cbt_timed' ? 'Timed CBT Exam' : 'Practice Drill') ?></div>
                                    </td>
                                    <td class="text-muted"><?= date('M d, Y h:i A', strtotime($att['started_at'])) ?></td>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1 rounded-pill">
                                            <?= $att['correct_answers'] ?> / <?= $att['total_questions'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= (float)$att['score_percentage'] >= 70 ? 'bg-success' : ((float)$att['score_percentage'] >= 50 ? 'bg-warning text-dark' : 'bg-danger') ?> rounded-pill px-3 py-1 fw-bold">
                                            <?= round((float)$att['score_percentage']) ?>%
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="<?= url('student/secondary-practice.php?subject=' . urlencode($att['subject_slug']) . '&exam=' . urlencode($att['exam_type'])) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                            Retake
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-3 d-block mb-1 text-muted"></i>
                                        No practice attempts recorded yet.
                                        <div class="mt-2">
                                            <a href="<?= url('student/secondary-practice.php') ?>" class="btn btn-sm btn-primary rounded-pill px-3">
                                                Start Your First CBT Practice
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- RIGHT COLUMN: AI TUTOR, REFERRAL CARD & DAILY ACTIVITY -->
        <div class="col-lg-4">

            <!-- 24/7 SECONDARY AI TUTOR CARD -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 text-white" style="background: linear-gradient(135deg, #1E1B4B 0%, #312E81 100%);">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="sec-icon-circle bg-warning bg-opacity-20 text-warning" style="width: 40px; height: 40px;">
                            <i class="bi bi-robot fs-5"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-white">StudyMe AI Tutor</h6>
                            <small class="text-white-50">High School Curriculum Expert</small>
                        </div>
                    </div>
                    <span class="badge bg-success bg-opacity-25 text-white border border-success border-opacity-50 rounded-pill px-2 py-1">
                        Active
                    </span>
                </div>

                <p class="text-white-50 small mb-3">
                    Stuck on a tricky math equation, physics law, or English comprehension passage? Get step-by-step Socratic assistance.
                </p>

                <div class="d-flex flex-column gap-2 mb-3">
                    <a href="<?= url('student/secondary-ai-tutor.php?preset=' . urlencode('Explain step-by-step how to solve quadratic equations by factorization')) ?>" class="btn btn-sm btn-outline-light text-start rounded-3 p-2 text-truncate">
                        <i class="bi bi-lightbulb-fill text-warning me-1"></i> Quadratic Equation Factorization
                    </a>
                    <a href="<?= url('student/secondary-ai-tutor.php?preset=' . urlencode('Explain Newton\'s three laws of motion with everyday Nigerian examples')) ?>" class="btn btn-sm btn-outline-light text-start rounded-3 p-2 text-truncate">
                        <i class="bi bi-lightning-fill text-warning me-1"></i> Newton's Laws with Examples
                    </a>
                    <a href="<?= url('student/secondary-ai-tutor.php?preset=' . urlencode('Give me rules and examples for vowel sounds and word stress in WAEC English')) ?>" class="btn btn-sm btn-outline-light text-start rounded-3 p-2 text-truncate">
                        <i class="bi bi-chat-text-fill text-warning me-1"></i> Oral English Vowel Sounds
                    </a>
                </div>

                <a href="<?= url('student/secondary-ai-tutor.php') ?>" class="btn btn-warning w-100 rounded-pill fw-bold text-dark">
                    <i class="bi bi-chat-dots-fill me-1"></i> Open AI Study Chat
                </a>
            </div>

            <!-- REFERRAL & WALLET REWARD CARD -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white border">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="sec-icon-circle bg-warning bg-opacity-10 text-warning" style="width: 40px; height: 40px;">
                            <i class="bi bi-gift-fill fs-5"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">Refer &amp; Earn ₦1,000</h6>
                            <small class="text-muted">Secondary Student Bonus</small>
                        </div>
                    </div>
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1 fw-bold">
                        ₦1,000 / Invite
                    </span>
                </div>

                <div class="bg-light p-3 rounded-3 mb-3 border">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Available Wallet Balance</span>
                        <span class="fw-bold text-success fs-6">₦<?= number_format((float)($wallet['available_balance'] ?? 0), 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between small text-muted">
                        <span>Total Referrals Earned</span>
                        <span class="fw-bold text-dark">₦<?= number_format((float)($wallet['total_earned'] ?? 0), 2) ?></span>
                    </div>
                </div>

                <label class="form-label small text-muted fw-semibold">Your Referral Link:</label>
                <div class="input-group mb-3">
                    <input type="text" id="refLinkInput" class="form-control form-control-sm bg-light" value="<?= e($refLink) ?>" readonly>
                    <button type="button" class="btn btn-sm btn-primary px-3" onclick="copyReferralLink()">
                        <i class="bi bi-copy"></i> Copy
                    </button>
                </div>

                <div class="d-grid gap-2">
                    <a href="https://api.whatsapp.com/send?text=<?= urlencode("Hey! Join me on StudyMe to practice WAEC, NECO and JAMB past questions with AI tutoring. Sign up here: " . $refLink) ?>" target="_blank" class="btn btn-sm btn-success rounded-pill fw-bold">
                        <i class="bi bi-whatsapp me-1"></i> Share on WhatsApp
                    </a>
                    <a href="<?= url('student/secondary-wallet.php') ?>" class="btn btn-sm btn-outline-secondary rounded-pill fw-bold">
                        <i class="bi bi-wallet2 me-1"></i> View Wallet &amp; Withdraw
                    </a>
                </div>
            </div>

            <!-- RECENT ACTIVITIES FEED -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white border">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-activity text-primary me-2"></i> Today's Activity
                    </h6>
                    <a href="<?= url('student/secondary-activity.php') ?>" class="text-decoration-none small fw-bold">Full Log</a>
                </div>

                <?php if (!empty($recentActivity)): ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($recentActivity as $act): ?>
                    <div class="d-flex align-items-start gap-2">
                        <div class="mt-1 text-primary"><i class="bi bi-check-circle-fill" style="font-size: 0.85rem;"></i></div>
                        <div>
                            <div class="small fw-semibold text-dark"><?= e($act['description'] ?: ucfirst($act['action'])) ?></div>
                            <small class="text-muted" style="font-size: 0.75rem;"><?= date('h:i A', strtotime($act['created_at'])) ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-muted small text-center py-3">No activity logged today yet.</div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
function copyReferralLink() {
    const input = document.getElementById('refLinkInput');
    if (!input) return;
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        if (typeof StudyMeFeedback !== 'undefined') {
            StudyMeFeedback.success('Referral link copied to clipboard!');
        } else {
            alert('Referral link copied to clipboard!');
        }
    });
}
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>