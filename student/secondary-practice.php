<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';
require_once BASE_PATH . '/includes/functions/activity.php';

secure_page(ROLE_STUDENT);

$user   = current_user();
$userId = (int)$user['id'];
$pdo    = getDBConnection();

$studentStmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$studentStmt->execute([$userId]);
$studentId = (int)$studentStmt->fetchColumn();

// ----------------------------------------------------
// 1. HANDLE AJAX PRACTICE ACTIONS (START, ANSWER, COMPLETE, BOOKMARK)
// ----------------------------------------------------
if (is_post() && isset($_POST['practice_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = trim($_POST['practice_action']);
    $attemptId = (int)($_POST['attempt_id'] ?? 0);
    $questionId = (int)($_POST['question_id'] ?? 0);

    if (!$studentId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Student record not found.']);
        exit;
    }

    try {
        if ($action === 'start') {
            $examType = strtolower(trim($_POST['exam_type'] ?? 'waec'));
            if ($examType === 'jamb') $examType = 'utme';
            $subjectSlug = strtolower(trim($_POST['subject_slug'] ?? 'mathematics'));
            $topicName   = !empty($_POST['topic_name']) ? trim($_POST['topic_name']) : null;
            $year        = !empty($_POST['year']) ? (int)$_POST['year'] : null;
            $totalQ      = (int)($_POST['total_questions'] ?? 20);
            $timeLimit   = (int)($_POST['time_limit_minutes'] ?? 25);
            $mode        = trim($_POST['mode'] ?? 'cbt_timed');

            $stmt = $pdo->prepare("
                INSERT INTO secondary_practice_attempts 
                    (student_id, exam_type, subject_slug, topic_name, year, mode, time_limit_minutes, total_questions, started_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$studentId, $examType, $subjectSlug, $topicName, $year, $mode, $timeLimit, $totalQ]);
            $newAttemptId = (int)$pdo->lastInsertId();

            log_user_activity($userId, 'started_practice', "Started " . strtoupper($examType) . " CBT practice in " . ucfirst($subjectSlug) . " ({$totalQ} questions)");

            echo json_encode(['success' => true, 'attempt_id' => $newAttemptId]);
            exit;
        }

        if ($action === 'answer' && $attemptId > 0 && $questionId > 0) {
            $selected = strtoupper(substr(trim($_POST['selected_option'] ?? ''), 0, 1));
            $correctStmt = $pdo->prepare("SELECT UPPER(correct_option) FROM past_questions WHERE id = ? LIMIT 1");
            $correctStmt->execute([$questionId]);
            $correct = strtoupper((string)$correctStmt->fetchColumn());
            
            $isCorrect = ($selected === $correct) ? 1 : 0;

            $stmt = $pdo->prepare("
                INSERT INTO secondary_practice_answers (attempt_id, question_id, selected_option, is_correct, answered_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option), is_correct = VALUES(is_correct), answered_at = NOW()
            ");
            $stmt->execute([$attemptId, $questionId, $selected, $isCorrect]);

            // Recalculate attempt progress stats
            $pdo->prepare("
                UPDATE secondary_practice_attempts a 
                SET answered_questions = (SELECT COUNT(*) FROM secondary_practice_answers WHERE attempt_id = a.id),
                    correct_answers = (SELECT COALESCE(SUM(is_correct), 0) FROM secondary_practice_answers WHERE attempt_id = a.id),
                    wrong_answers = (SELECT COUNT(*) FROM secondary_practice_answers WHERE attempt_id = a.id AND is_correct = 0)
                WHERE a.id = ?
            ")->execute([$attemptId]);

            echo json_encode(['success' => true, 'is_correct' => (bool)$isCorrect, 'correct_option' => $correct]);
            exit;
        }

        if ($action === 'complete' && $attemptId > 0) {
            $timeSpent = (int)($_POST['time_spent_seconds'] ?? 0);
            
            $stmtScore = $pdo->prepare("
                SELECT total_questions, correct_answers, wrong_answers, exam_type, subject_slug
                FROM secondary_practice_attempts
                WHERE id = ? AND student_id = ?
                LIMIT 1
            ");
            $stmtScore->execute([$attemptId, $studentId]);
            $att = $stmtScore->fetch(PDO::FETCH_ASSOC);

            if ($att) {
                $totalQ = max(1, (int)$att['total_questions']);
                $correctQ = (int)$att['correct_answers'];
                $percentage = round(($correctQ / $totalQ) * 100, 2);

                $pdo->prepare("
                    UPDATE secondary_practice_attempts 
                    SET time_spent_seconds = ?, score_percentage = ?, completed_at = NOW()
                    WHERE id = ? AND student_id = ?
                ")->execute([$timeSpent, $percentage, $attemptId, $studentId]);

                log_user_activity($userId, 'completed_practice', "Completed " . strtoupper($att['exam_type']) . " CBT Practice ({$correctQ}/{$totalQ} - {$percentage}%)");

                echo json_encode([
                    'success' => true,
                    'score' => $correctQ,
                    'total' => $totalQ,
                    'percentage' => $percentage,
                    'time_spent' => $timeSpent
                ]);
                exit;
            }
        }

        if ($action === 'bookmark' && $questionId > 0) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO secondary_question_bookmarks (student_id, question_id, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$studentId, $questionId]);
            echo json_encode(['success' => true, 'bookmarked' => true]);
            exit;
        }

        if ($action === 'unbookmark' && $questionId > 0) {
            $stmt = $pdo->prepare("DELETE FROM secondary_question_bookmarks WHERE student_id = ? AND question_id = ?");
            $stmt->execute([$studentId, $questionId]);
            echo json_encode(['success' => true, 'bookmarked' => false]);
            exit;
        }
    } catch (Exception $e) {
        error_log("Practice action error: " . $e->getMessage());
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid practice action.']);
    exit;
}

// ----------------------------------------------------
// 2. CHECK IF WE ARE IN "REVIEW ATTEMPT" MODE
// ----------------------------------------------------
$reviewAttemptId = !empty($_GET['review_attempt']) ? (int)$_GET['review_attempt'] : null;
$reviewData = null;
$reviewQuestions = [];

if ($reviewAttemptId && $studentId) {
    $stmtAtt = $pdo->prepare("
        SELECT a.*, COALESCE(s.name, a.subject_slug) AS subject_name, s.color AS subject_color, s.icon AS subject_icon
        FROM secondary_practice_attempts a
        LEFT JOIN secondary_subjects s ON a.subject_slug = s.slug
        WHERE a.id = ? AND a.student_id = ?
        LIMIT 1
    ");
    $stmtAtt->execute([$reviewAttemptId, $studentId]);
    $reviewData = $stmtAtt->fetch(PDO::FETCH_ASSOC);

    if ($reviewData) {
        // Fetch questions and answers for this attempt
        $stmtQA = $pdo->prepare("
            SELECT pq.*, ans.selected_option, ans.is_correct,
                   (SELECT 1 FROM secondary_question_bookmarks b WHERE b.student_id = ? AND b.question_id = pq.id) AS is_bookmarked
            FROM secondary_practice_answers ans
            JOIN past_questions pq ON ans.question_id = pq.id
            WHERE ans.attempt_id = ?
            ORDER BY ans.id ASC
        ");
        $stmtQA->execute([$studentId, $reviewAttemptId]);
        $reviewQuestions = $stmtQA->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ----------------------------------------------------
// 3. INITIALIZE NEW PRACTICE SESSION
// ----------------------------------------------------
$selectedExam    = strtolower(trim($_GET['exam'] ?? 'waec'));
if ($selectedExam === 'jamb') $selectedExam = 'utme';
$selectedSubject = strtolower(trim($_GET['subject'] ?? 'mathematics'));
$selectedTopic   = trim($_GET['topic'] ?? '');
$selectedYear    = !empty($_GET['year']) ? (int)$_GET['year'] : null;
$countParam      = trim($_GET['count'] ?? '20');
$mode            = trim($_GET['mode'] ?? 'cbt_timed'); // 'cbt_timed' or 'practice_study'

$requestedCount = ($countParam === 'all') ? 100 : max(5, min(100, (int)$countParam));

// Build query to select questions
$where = ["(subject_slug = ? OR subject_name LIKE ?)"];
$params = [$selectedSubject, "%{$selectedSubject}%"];

if (!empty($selectedExam) && in_array($selectedExam, ['waec', 'neco', 'utme', 'jamb'], true)) {
    if ($selectedExam === 'utme' || $selectedExam === 'jamb') {
        $where[] = "(exam_type = 'utme' OR exam_type = 'jamb')";
    } else {
        $where[] = "exam_type = ?";
        $params[] = $selectedExam;
    }
}

if (!empty($selectedYear) && $selectedYear >= 2000) {
    $where[] = "year = ?";
    $params[] = $selectedYear;
}

if (!empty($selectedTopic)) {
    $where[] = "topic_name LIKE ?";
    $params[] = "%{$selectedTopic}%";
}

$sql = "SELECT * FROM past_questions WHERE " . implode(' AND ', $where) . " ORDER BY RAND() LIMIT " . (int)$requestedCount;
$stmtQ = $pdo->prepare($sql);
$stmtQ->execute($params);
$questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

// If local specific is less than requested, fetch more questions from same subject without strict year/topic
if (count($questions) < $requestedCount) {
    $needed = $requestedCount - count($questions);
    $existingIds = !empty($questions) ? array_column($questions, 'id') : [0];
    $inClause = implode(',', array_map('intval', $existingIds));
    
    $fallbackSql = "SELECT * FROM past_questions WHERE (subject_slug = ? OR subject_name LIKE ?) AND id NOT IN ($inClause) ORDER BY RAND() LIMIT $needed";
    $stmtFallback = $pdo->prepare($fallbackSql);
    $stmtFallback->execute([$selectedSubject, "%{$selectedSubject}%"]);
    $fallbackQs = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
    $questions = array_merge($questions, $fallbackQs);
}

// If still empty, grab general pool questions
if (empty($questions)) {
    $stmtAny = $pdo->query("SELECT * FROM past_questions ORDER BY RAND() LIMIT " . min(20, $requestedCount));
    $questions = $stmtAny->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate time limit based on questions (approx 1.25 minutes per question for WAEC/JAMB)
$totalQuestionsCount = count($questions);
$calculatedMinutes = max(5, ceil($totalQuestionsCount * 1.25));

$allSubjects = get_secondary_subjects('active');
$currentSubjectRow = get_secondary_subject_by_slug($selectedSubject);
$currentSubjectName = $currentSubjectRow ? $currentSubjectRow['name'] : ucfirst($selectedSubject);

$pageTitle = 'CBT Practice & Exam Simulator | StudyMe';
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<!-- MathJax for rendering formulas -->
<script>
window.MathJax = {
  tex: {
    inlineMath: [['$', '$'], ['\\(', '\\)']],
    displayMath: [['$$', '$$'], ['\\[', '\\]']]
  },
  svg: { fontCache: 'global' }
};
</script>
<script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>

<style>
/* ============================================================
   StudyMe CBT Exam Simulator — Premium Design System
   ============================================================ */

/* === GLOBAL PAGE OVERRIDES === */
.cbt-exam-page .dashboard-content { padding-bottom: 2rem; }

/* === STICKY HEADER BAR === */
.cbt-header-bar {
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(20px) saturate(1.6);
    -webkit-backdrop-filter: blur(20px) saturate(1.6);
    border: 1px solid rgba(226, 232, 240, 0.6);
    border-radius: var(--radius-xl, 20px);
    box-shadow: 0 4px 24px rgba(15, 23, 42, 0.06), 0 1px 3px rgba(15, 23, 42, 0.04);
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    position: sticky;
    top: 10px;
    z-index: 1020;
    padding: 14px 22px;
    margin-bottom: 1.5rem;
}
.cbt-header-bar:hover {
    box-shadow: 0 6px 28px rgba(15, 23, 42, 0.09), 0 2px 6px rgba(15, 23, 42, 0.05);
}

/* Exam Type Badge */
.cbt-exam-badge {
    background: var(--gradient-primary, linear-gradient(135deg, #1E3A8A, #2563EB));
    color: #fff;
    font-weight: 700;
    font-size: 0.78rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 7px 16px;
    border-radius: var(--radius-pill, 9999px);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 3px 12px rgba(37, 99, 235, 0.25);
}
.cbt-exam-badge i { font-size: 0.9rem; }

.cbt-subject-label {
    font-weight: 700;
    font-size: 1rem;
    color: var(--text-main, #0F172A);
    font-family: var(--font-heading);
}

/* === LIVE TIMER === */
.cbt-timer {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #0F172A, #1E293B);
    color: #fff;
    padding: 8px 18px;
    border-radius: var(--radius-pill, 9999px);
    font-family: 'JetBrains Mono', 'Fira Code', 'Cascadia Code', monospace;
    font-size: 1.15rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.2);
    min-width: 120px;
    justify-content: center;
    position: relative;
    overflow: hidden;
}
.cbt-timer::before {
    content: '';
    position: absolute;
    top: 0; left: -100%; bottom: 0;
    width: 200%;
    background: linear-gradient(90deg, transparent 0%, rgba(251, 191, 36, 0.08) 50%, transparent 100%);
    animation: timerShimmer 3s ease-in-out infinite;
}
@keyframes timerShimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
.cbt-timer i {
    font-size: 1rem;
    color: #FBBf24;
    animation: timerPulse 1.5s ease-in-out infinite;
}
@keyframes timerPulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(1.1); }
}
.cbt-timer.warning { background: linear-gradient(135deg, #92400E, #D97706); }
.cbt-timer.danger {
    background: linear-gradient(135deg, #991B1B, #DC2626);
    animation: timerUrgent 0.6s ease-in-out infinite alternate;
}
@keyframes timerUrgent {
    0% { box-shadow: 0 4px 16px rgba(220, 38, 38, 0.3); }
    100% { box-shadow: 0 4px 24px rgba(220, 38, 38, 0.55); }
}

/* Submit button */
.cbt-submit-btn {
    background: linear-gradient(135deg, #DC2626, #EF4444);
    color: #fff;
    border: none;
    border-radius: var(--radius-pill, 9999px);
    padding: 8px 22px;
    font-weight: 700;
    font-size: 0.88rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 3px 14px rgba(220, 38, 38, 0.2);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    cursor: pointer;
}
.cbt-submit-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.35);
    background: linear-gradient(135deg, #B91C1C, #DC2626);
    color: #fff;
}

/* === QUESTION CARD === */
.cbt-question-card {
    background: var(--bg-surface, #FFFFFF);
    border: 1px solid var(--border-color, #E2E8F0);
    border-radius: var(--radius-xl, 20px);
    box-shadow: var(--shadow-md, 0 4px 12px rgba(15, 23, 42, 0.06));
    padding: 28px;
    position: relative;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    margin-bottom: 1.5rem;
    overflow: hidden;
}
.cbt-question-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--gradient-primary, linear-gradient(135deg, #1E3A8A, #2563EB));
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}

/* Question number badge */
.cbt-q-number {
    background: linear-gradient(135deg, #0F172A, #1E293B);
    color: #fff;
    font-weight: 700;
    font-size: 0.85rem;
    padding: 6px 14px;
    border-radius: var(--radius-pill, 9999px);
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    letter-spacing: 0.02em;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.12);
}

.cbt-q-meta {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: var(--bg-surface-alt, #F1F5F9);
    color: var(--text-muted, #64748B);
    border: 1px solid var(--border-color, #E2E8F0);
    border-radius: var(--radius-pill, 9999px);
    padding: 4px 12px;
    font-size: 0.78rem;
    font-weight: 600;
}

/* Flag button */
.cbt-flag-btn {
    border: 1.5px solid var(--border-color, #E2E8F0);
    background: transparent;
    color: var(--text-muted, #64748B);
    border-radius: var(--radius-pill, 9999px);
    padding: 6px 16px;
    font-size: 0.82rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.cbt-flag-btn:hover {
    border-color: var(--warning, #D97706);
    color: var(--warning, #D97706);
    background: var(--warning-subtle, rgba(217, 119, 6, 0.1));
}
.cbt-flag-btn.flagged {
    background: var(--warning, #D97706) !important;
    color: #fff !important;
    border-color: var(--warning, #D97706) !important;
    box-shadow: 0 2px 8px rgba(217, 119, 6, 0.25);
}

/* Question text */
.cbt-question-text {
    font-size: 1.15rem;
    font-weight: 600;
    line-height: 1.7;
    color: var(--text-main, #0F172A);
    font-family: var(--font-heading);
    margin: 20px 0 24px;
    padding: 0;
}

/* === ANSWER OPTIONS === */
.cbt-option-item {
    cursor: pointer;
    border: 2px solid var(--border-color, #E2E8F0);
    background: var(--bg-surface, #FFFFFF);
    border-radius: var(--radius-md, 12px);
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
}
.cbt-option-item::after {
    content: '';
    position: absolute;
    top: 0; left: 0; bottom: 0;
    width: 0;
    background: var(--primary-subtle, rgba(30, 64, 175, 0.08));
    transition: width 0.3s ease;
    z-index: 0;
}
.cbt-option-item:hover {
    border-color: var(--primary-light, #3B82F6);
    transform: translateX(4px);
    box-shadow: 0 2px 10px rgba(37, 99, 235, 0.08);
}
.cbt-option-item:hover::after {
    width: 100%;
}
.cbt-option-item > * { position: relative; z-index: 1; }
.cbt-option-item.selected {
    border-color: var(--primary, #1E40AF);
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.06), rgba(30, 64, 175, 0.10));
    box-shadow: 0 2px 12px rgba(37, 99, 235, 0.12);
}
.cbt-option-item.selected::after { width: 100%; }

.cbt-option-letter {
    width: 40px;
    height: 40px;
    min-width: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    font-weight: 700;
    font-size: 1rem;
    border-radius: var(--radius-sm, 8px);
    border: 2px solid var(--border-color, #E2E8F0);
    background: var(--bg-surface-alt, #F1F5F9);
    color: var(--text-muted, #64748B);
    transition: all 0.2s ease;
}
.cbt-option-item.selected .cbt-option-letter {
    background: var(--primary, #1E40AF);
    color: #fff;
    border-color: var(--primary, #1E40AF);
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
}
.cbt-option-item:hover .cbt-option-letter {
    border-color: var(--primary-light, #3B82F6);
    color: var(--primary-light, #3B82F6);
}

.cbt-option-text {
    flex: 1;
    font-size: 0.95rem;
    color: var(--text-main, #0F172A);
    line-height: 1.5;
    font-weight: 500;
}

.cbt-option-check {
    font-size: 1.25rem;
    color: var(--primary, #1E40AF);
    opacity: 0;
    transform: scale(0.5);
    transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.cbt-option-item.selected .cbt-option-check {
    opacity: 1;
    transform: scale(1);
}

/* === BOTTOM NAVIGATION === */
.cbt-nav-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 20px;
    margin-top: 24px;
    border-top: 1px solid var(--border-subtle, #F1F5F9);
}

.cbt-nav-btn {
    border: 1.5px solid var(--border-color, #E2E8F0);
    background: var(--bg-surface, #FFFFFF);
    color: var(--text-main, #0F172A);
    border-radius: var(--radius-pill, 9999px);
    padding: 10px 22px;
    font-weight: 600;
    font-size: 0.88rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
    cursor: pointer;
}
.cbt-nav-btn:hover:not(:disabled) {
    border-color: var(--primary-light, #3B82F6);
    color: var(--primary, #1E40AF);
    background: var(--primary-subtle, rgba(30, 64, 175, 0.05));
    transform: translateY(-1px);
}
.cbt-nav-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.cbt-nav-btn.primary {
    background: var(--gradient-primary, linear-gradient(135deg, #1E3A8A, #2563EB));
    color: #fff;
    border-color: transparent;
    box-shadow: 0 3px 12px rgba(37, 99, 235, 0.2);
}
.cbt-nav-btn.primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);
    color: #fff;
}

.cbt-progress-text {
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    font-size: 0.82rem;
    color: var(--text-muted, #64748B);
    font-weight: 500;
}
.cbt-progress-text strong {
    color: var(--success, #059669);
    font-weight: 700;
}

/* === PALETTE SIDEBAR === */
.cbt-palette-card {
    background: var(--bg-surface, #FFFFFF);
    border: 1px solid var(--border-color, #E2E8F0);
    border-radius: var(--radius-xl, 20px);
    box-shadow: var(--shadow-md);
    padding: 22px;
    position: sticky;
    top: 85px;
    margin-bottom: 1.5rem;
}

.cbt-palette-title {
    font-family: var(--font-heading);
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--text-main, #0F172A);
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}
.cbt-palette-title i {
    color: var(--primary-light, #3B82F6);
    font-size: 1.1rem;
}
.cbt-palette-count {
    background: var(--bg-surface-alt, #F1F5F9);
    color: var(--text-muted, #64748B);
    border: 1px solid var(--border-color, #E2E8F0);
    border-radius: var(--radius-pill);
    padding: 2px 10px;
    font-size: 0.75rem;
    font-weight: 700;
}

/* Palette Legend */
.cbt-palette-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 16px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--border-subtle, #F1F5F9);
}
.cbt-legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.72rem;
    color: var(--text-muted, #64748B);
    font-weight: 500;
}
.cbt-legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 3px;
    flex-shrink: 0;
}
.cbt-legend-dot.unanswered { background: var(--bg-surface-alt, #F1F5F9); border: 1.5px solid var(--border-color); }
.cbt-legend-dot.answered { background: var(--success, #059669); }
.cbt-legend-dot.flagged { background: var(--warning, #D97706); }
.cbt-legend-dot.active-dot { background: transparent; border: 2px solid var(--primary-light, #3B82F6); }

/* Palette Grid */
.cbt-palette-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(38px, 1fr));
    gap: 6px;
    max-height: 280px;
    overflow-y: auto;
    padding-right: 4px;
    margin-bottom: 18px;
}
.cbt-palette-grid::-webkit-scrollbar { width: 4px; }
.cbt-palette-grid::-webkit-scrollbar-track { background: transparent; }
.cbt-palette-grid::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }

.cbt-palette-btn {
    width: 100%;
    aspect-ratio: 1;
    font-size: 0.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s cubic-bezier(0.16, 1, 0.3, 1);
    border: 1.5px solid var(--border-color, #E2E8F0);
    background: var(--bg-surface-alt, #F1F5F9);
    color: var(--text-muted, #64748B);
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
}
.cbt-palette-btn:hover {
    border-color: var(--primary-light, #3B82F6);
    color: var(--primary, #1E40AF);
    transform: scale(1.08);
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.12);
}
.cbt-palette-btn.active {
    outline: 2.5px solid var(--primary-light, #3B82F6);
    outline-offset: 2px;
    background: var(--primary-subtle, rgba(30, 64, 175, 0.08));
    color: var(--primary, #1E40AF);
    border-color: var(--primary-light, #3B82F6);
}
.cbt-palette-btn.answered {
    background: var(--success, #059669) !important;
    color: #fff !important;
    border-color: var(--success, #059669) !important;
    box-shadow: 0 2px 6px rgba(5, 150, 105, 0.2);
}
.cbt-palette-btn.flagged {
    background: var(--warning, #D97706) !important;
    color: #fff !important;
    border-color: var(--warning, #D97706) !important;
    box-shadow: 0 2px 6px rgba(217, 119, 6, 0.2);
}

/* Palette action buttons */
.cbt-palette-action {
    border: 1.5px solid var(--border-color, #E2E8F0);
    background: transparent;
    color: var(--text-main, #0F172A);
    border-radius: var(--radius-pill, 9999px);
    padding: 10px 0;
    font-weight: 600;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 100%;
    text-decoration: none;
}
.cbt-palette-action:hover {
    transform: translateY(-1px);
}
.cbt-palette-action.danger {
    border-color: var(--danger, #DC2626);
    color: var(--danger, #DC2626);
    background: var(--danger-subtle, rgba(220, 38, 38, 0.05));
}
.cbt-palette-action.danger:hover {
    background: var(--danger, #DC2626);
    color: #fff;
    box-shadow: 0 3px 12px rgba(220, 38, 38, 0.25);
}
.cbt-palette-action.muted {
    color: var(--text-muted, #64748B);
    font-size: 0.8rem;
}
.cbt-palette-action.muted:hover {
    border-color: var(--text-muted);
    background: var(--bg-surface-alt);
}

/* === MODALS === */
.cbt-modal .modal-content {
    border: none;
    border-radius: var(--radius-xl, 20px);
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.15);
    overflow: hidden;
}
.cbt-modal .modal-header {
    border: none;
    padding: 24px 24px 0;
}
.cbt-modal .modal-body {
    padding: 24px;
}
.cbt-modal .modal-icon {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
}
.cbt-modal .modal-icon.primary {
    background: var(--primary-subtle);
    color: var(--primary);
}
.cbt-modal .modal-icon.success {
    background: var(--success-subtle);
    color: var(--success);
}

.cbt-modal-btn {
    border-radius: var(--radius-pill);
    padding: 10px 24px;
    font-weight: 600;
    font-size: 0.88rem;
    border: none;
    transition: all 0.2s ease;
    cursor: pointer;
}
.cbt-modal-btn.secondary {
    background: var(--bg-surface-alt);
    color: var(--text-muted);
    border: 1px solid var(--border-color);
}
.cbt-modal-btn.primary {
    background: var(--gradient-primary);
    color: #fff;
    box-shadow: 0 3px 12px rgba(37, 99, 235, 0.2);
}
.cbt-modal-btn.primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);
}

/* Result score card */
.cbt-result-score-grid {
    background: var(--bg-surface-alt, #F1F5F9);
    border-radius: var(--radius-lg, 16px);
    padding: 20px;
    border: 1px solid var(--border-color);
}
.cbt-result-stat {
    text-align: center;
}
.cbt-result-stat .value {
    font-size: 1.5rem;
    font-weight: 800;
    font-family: var(--font-heading);
}
.cbt-result-stat .label {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 500;
    margin-top: 2px;
}

/* === REVIEW MODE STYLES === */
.cbt-review-header {
    margin-bottom: 1.5rem;
}
.cbt-review-breadcrumb .breadcrumb-item + .breadcrumb-item::before {
    content: '›';
    font-weight: 700;
    color: var(--text-light);
}
.cbt-review-breadcrumb a {
    color: var(--primary-light, #3B82F6);
    text-decoration: none;
    font-weight: 500;
}
.cbt-review-breadcrumb a:hover {
    color: var(--primary, #1E40AF);
}
.cbt-review-title {
    font-family: var(--font-heading);
    font-weight: 800;
    font-size: 1.5rem;
    color: var(--text-main);
    margin: 6px 0 4px;
}
.cbt-review-meta {
    font-size: 0.85rem;
    color: var(--text-muted);
}

/* Review score summary card */
.cbt-score-summary {
    background: linear-gradient(135deg, #0F172A 0%, #1E293B 40%, #1E3A8A 100%);
    border-radius: var(--radius-xl, 20px);
    padding: 28px;
    color: #fff;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.2);
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
}
.cbt-score-summary::before {
    content: '';
    position: absolute;
    top: -50%; right: -30%;
    width: 300px;
    height: 300px;
    border-radius: 50%;
    background: rgba(37, 99, 235, 0.08);
}
.cbt-score-summary > * { position: relative; z-index: 1; }

.cbt-big-score {
    font-size: 3rem;
    font-weight: 800;
    font-family: var(--font-heading);
    line-height: 1;
}
.cbt-score-label {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    opacity: 0.7;
    font-weight: 600;
    margin-top: 4px;
}
.cbt-score-stat {
    text-align: center;
}
.cbt-score-stat .val {
    font-size: 1.5rem;
    font-weight: 700;
    font-family: var(--font-heading);
}
.cbt-score-stat .lbl {
    font-size: 0.75rem;
    opacity: 0.6;
    font-weight: 500;
}

/* Review question cards */
.cbt-review-q-card {
    border-radius: var(--radius-lg, 16px);
    border: 1.5px solid;
    padding: 22px;
    margin-bottom: 20px;
    transition: all 0.25s ease;
}
.cbt-review-q-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}
.cbt-review-q-card.correct {
    border-color: rgba(5, 150, 105, 0.3);
    background: rgba(5, 150, 105, 0.04);
}
.cbt-review-q-card.incorrect {
    border-color: rgba(220, 38, 38, 0.3);
    background: rgba(220, 38, 38, 0.04);
}
.cbt-review-q-card.skipped {
    border-color: var(--border-color);
    background: var(--bg-surface-alt);
}

/* Review option pills */
.cbt-review-option {
    padding: 12px 16px;
    border-radius: var(--radius-sm, 8px);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.9rem;
    border: 1.5px solid var(--border-color);
    background: var(--bg-surface);
    transition: all 0.2s ease;
}
.cbt-review-option.is-correct {
    border-color: var(--success, #059669);
    background: linear-gradient(135deg, rgba(5, 150, 105, 0.08), rgba(5, 150, 105, 0.15));
    color: #065F46;
    font-weight: 600;
}
.cbt-review-option.is-wrong {
    border-color: var(--danger, #DC2626);
    background: linear-gradient(135deg, rgba(220, 38, 38, 0.06), rgba(220, 38, 38, 0.12));
    color: #991B1B;
    font-weight: 600;
}

.cbt-review-option .letter {
    width: 32px; height: 32px; min-width: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.85rem;
}
.cbt-review-option.is-correct .letter {
    background: var(--success); color: #fff;
}
.cbt-review-option.is-wrong .letter {
    background: var(--danger); color: #fff;
}

/* Explanation box */
.cbt-explanation-box {
    background: var(--bg-surface, #FFFFFF);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md, 12px);
    padding: 16px;
    margin-top: 16px;
    border-left: 4px solid var(--success, #059669);
}
.cbt-explanation-box .title {
    font-weight: 700;
    color: var(--success, #059669);
    font-size: 0.85rem;
    margin-bottom: 6px;
}
.cbt-explanation-box .content {
    font-size: 0.85rem;
    color: var(--text-muted);
    line-height: 1.7;
}

/* === EMPTY STATE === */
.cbt-empty-state {
    background: var(--bg-surface);
    border: 2px dashed var(--border-color, #E2E8F0);
    border-radius: var(--radius-xl, 20px);
    padding: 60px 40px;
    text-align: center;
}
.cbt-empty-state i {
    font-size: 3.5rem;
    color: var(--text-light, #94A3B8);
    margin-bottom: 16px;
    display: block;
}
.cbt-empty-state h4 {
    font-family: var(--font-heading);
    font-weight: 700;
    color: var(--text-main);
    margin-bottom: 8px;
}
.cbt-empty-state p {
    color: var(--text-muted);
    max-width: 400px;
    margin: 0 auto 24px;
}

/* === DARK MODE === */
[data-bs-theme="dark"] .cbt-header-bar {
    background: rgba(15, 23, 42, 0.85);
    border-color: rgba(71, 85, 105, 0.3);
}
[data-bs-theme="dark"] .cbt-question-card {
    background: #1E293B;
    border-color: rgba(71, 85, 105, 0.3);
}
[data-bs-theme="dark"] .cbt-option-item {
    background: #0F172A;
    border-color: rgba(71, 85, 105, 0.3);
}
[data-bs-theme="dark"] .cbt-option-item:hover {
    border-color: var(--primary-light);
    background: rgba(37, 99, 235, 0.08);
}
[data-bs-theme="dark"] .cbt-option-item.selected {
    background: rgba(37, 99, 235, 0.12);
    border-color: var(--primary);
}
[data-bs-theme="dark"] .cbt-option-letter {
    background: rgba(71, 85, 105, 0.2);
    border-color: rgba(71, 85, 105, 0.3);
    color: #94A3B8;
}
[data-bs-theme="dark"] .cbt-option-text {
    color: #E2E8F0;
}
[data-bs-theme="dark"] .cbt-palette-card {
    background: #1E293B;
    border-color: rgba(71, 85, 105, 0.3);
}
[data-bs-theme="dark"] .cbt-palette-btn {
    background: rgba(71, 85, 105, 0.15);
    border-color: rgba(71, 85, 105, 0.3);
    color: #94A3B8;
}
[data-bs-theme="dark"] .cbt-question-text {
    color: #E2E8F0;
}
[data-bs-theme="dark"] .cbt-subject-label {
    color: #E2E8F0;
}
[data-bs-theme="dark"] .cbt-review-q-card.correct {
    background: rgba(5, 150, 105, 0.06);
}
[data-bs-theme="dark"] .cbt-review-q-card.incorrect {
    background: rgba(220, 38, 38, 0.06);
}
[data-bs-theme="dark"] .cbt-explanation-box {
    background: rgba(15, 23, 42, 0.6);
    border-color: rgba(71, 85, 105, 0.3);
}

/* === RESPONSIVE === */
@media (max-width: 991.98px) {
    .cbt-palette-card { position: static; }
    .cbt-header-bar { position: sticky; top: 0; border-radius: 0 0 var(--radius-xl) var(--radius-xl); }
}
@media (max-width: 767.98px) {
    .cbt-question-card { padding: 20px 16px; }
    .cbt-question-text { font-size: 1rem; }
    .cbt-option-item { padding: 12px 14px; gap: 10px; }
    .cbt-option-letter { width: 34px; height: 34px; min-width: 34px; font-size: 0.85rem; }
    .cbt-header-bar { padding: 10px 14px; }
    .cbt-timer { font-size: 1rem; padding: 6px 14px; min-width: 100px; }
    .cbt-submit-btn { padding: 7px 14px; font-size: 0.8rem; }
    .cbt-submit-btn .hide-mobile { display: none; }
    .cbt-score-summary { padding: 20px; }
    .cbt-big-score { font-size: 2.2rem; }
    .cbt-review-q-card { padding: 16px; }
    .cbt-review-option { padding: 10px 12px; font-size: 0.82rem; }
    .cbt-review-title { font-size: 1.2rem; }
    .cbt-palette-grid { grid-template-columns: repeat(auto-fill, minmax(34px, 1fr)); gap: 5px; }
}
@media (max-width: 575.98px) {
    .cbt-nav-bar { flex-wrap: wrap; gap: 10px; }
    .cbt-nav-btn { padding: 8px 16px; font-size: 0.82rem; }
    .cbt-progress-text { width: 100%; text-align: center; order: -1; }
}
</style>

<?php if ($reviewData): ?>
<!-- ============================================================ -->
<!-- REVIEW ATTEMPT MODE -->
<!-- ============================================================ -->
<div class="cbt-review-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb cbt-review-breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= url('student/secondary-dashboard.php') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('student/secondary-practice-history.php') ?>">Practice History</a></li>
                    <li class="breadcrumb-item active">Attempt #<?= (int)$reviewData['id'] ?></li>
                </ol>
            </nav>
            <h2 class="cbt-review-title">
                <i class="bi bi-card-checklist text-primary me-2"></i>CBT Attempt Review & Solutions
            </h2>
            <p class="cbt-review-meta mb-0"><?= strtoupper(e($reviewData['exam_type'])) ?> &bull; <?= e($reviewData['subject_name']) ?> &bull; Completed <?= date('M d, Y h:i A', strtotime($reviewData['completed_at'] ?: $reviewData['started_at'])) ?></p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?= url('student/secondary-practice.php?exam=' . urlencode($reviewData['exam_type']) . '&subject=' . urlencode($reviewData['subject_slug']) . '&count=' . (int)$reviewData['total_questions']) ?>" class="cbt-nav-btn primary">
                <i class="bi bi-arrow-repeat"></i> Retake Practice
            </a>
            <a href="<?= url('student/secondary-practice-history.php') ?>" class="cbt-nav-btn">
                <i class="bi bi-clock-history"></i> All History
            </a>
        </div>
    </div>
</div>

<!-- SCORE SUMMARY CARD -->
<div class="cbt-score-summary">
    <div class="row align-items-center g-4">
        <div class="col-md-3 text-center" style="border-right: 1px solid rgba(255,255,255,0.12);">
            <div class="cbt-big-score <?= (float)$reviewData['score_percentage'] >= 70 ? 'text-success' : ((float)$reviewData['score_percentage'] >= 50 ? 'text-warning' : 'text-danger') ?>">
                <?= (float)$reviewData['score_percentage'] ?>%
            </div>
            <div class="cbt-score-label">Overall Accuracy</div>
        </div>

        <div class="col-md-9">
            <div class="row g-3 text-center">
                <div class="col-4 col-sm-2">
                    <div class="cbt-score-stat">
                        <div class="val"><?= (int)$reviewData['total_questions'] ?></div>
                        <div class="lbl">Total Qs</div>
                    </div>
                </div>
                <div class="col-4 col-sm-2">
                    <div class="cbt-score-stat">
                        <div class="val text-success"><?= (int)$reviewData['correct_answers'] ?></div>
                        <div class="lbl">Correct</div>
                    </div>
                </div>
                <div class="col-4 col-sm-2">
                    <div class="cbt-score-stat">
                        <div class="val text-danger"><?= (int)$reviewData['wrong_answers'] ?></div>
                        <div class="lbl">Wrong</div>
                    </div>
                </div>
                <div class="col-4 col-sm-2">
                    <div class="cbt-score-stat">
                        <div class="val" style="opacity:0.5;"><?= max(0, (int)$reviewData['total_questions'] - (int)$reviewData['answered_questions']) ?></div>
                        <div class="lbl">Skipped</div>
                    </div>
                </div>
                <div class="col-8 col-sm-4">
                    <div class="cbt-score-stat">
                        <div class="val"><?= floor((int)$reviewData['time_spent_seconds'] / 60) ?>m <?= ((int)$reviewData['time_spent_seconds'] % 60) ?>s</div>
                        <div class="lbl">Time Spent</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- REVIEW QUESTIONS LIST -->
<div style="margin-bottom: 1.5rem;">
    <h5 class="cbt-palette-title" style="margin-bottom: 20px; font-size: 1.1rem;">
        <span><i class="bi bi-list-check text-primary me-2"></i>Question-by-Question Solutions</span>
        <span class="cbt-palette-count"><?= count($reviewQuestions) ?> Questions</span>
    </h5>

    <?php if (!empty($reviewQuestions)): ?>
        <?php foreach ($reviewQuestions as $idx => $rq): 
            $isCorrect = (bool)$rq['is_correct'];
            $userChoice = strtoupper(trim($rq['selected_option'] ?? ''));
            $correctChoice = strtoupper(trim($rq['correct_option'] ?? ''));
        ?>
        <div class="cbt-review-q-card <?= $isCorrect ? 'correct' : ($userChoice ? 'incorrect' : 'skipped') ?>">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="cbt-q-number">#<?= $idx + 1 ?></span>
                    <?php if ($isCorrect): ?>
                        <span class="badge bg-success rounded-pill px-3 py-1 fw-bold">
                            <i class="bi bi-check-circle-fill me-1"></i> Correct
                        </span>
                    <?php elseif ($userChoice): ?>
                        <span class="badge bg-danger rounded-pill px-3 py-1 fw-bold">
                            <i class="bi bi-x-circle-fill me-1"></i> Wrong (<?= $userChoice ?>)
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary rounded-pill px-3 py-1">
                            <i class="bi bi-dash-circle-fill me-1"></i> Skipped
                        </span>
                    <?php endif; ?>
                    <span class="cbt-q-meta">
                        <?= strtoupper(e($rq['exam_type'])) ?> <?= (int)$rq['year'] ?>
                    </span>
                </div>

                <a href="<?= url('student/secondary-ai-tutor.php?preset=' . urlencode("I got this question wrong in my practice. Please explain why option {$correctChoice} is correct and why option {$userChoice} is incorrect:\n\n" . $rq['question_text'])) ?>" class="cbt-flag-btn" style="text-decoration:none;">
                    <i class="bi bi-robot"></i> AI Tutor
                </a>
            </div>

            <!-- Question Text -->
            <div class="cbt-question-text" style="margin: 14px 0 18px; font-size: 1.05rem;">
                <?= $rq['question_text'] ?>
            </div>

            <!-- Options Grid -->
            <div class="row g-2 mb-3">
                <?php foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $col): 
                    $isOptCorrect = ($letter === $correctChoice);
                    $isOptChosen  = ($letter === $userChoice);
                    $reviewOptClass = '';
                    if ($isOptCorrect) $reviewOptClass = 'is-correct';
                    elseif ($isOptChosen && !$isCorrect) $reviewOptClass = 'is-wrong';
                ?>
                <div class="col-md-6">
                    <div class="cbt-review-option <?= $reviewOptClass ?>">
                        <span class="letter"><?= $letter ?></span>
                        <div style="flex:1;"><?= $rq[$col] ?></div>
                        <?php if ($isOptCorrect): ?>
                            <i class="bi bi-check-circle-fill ms-auto" style="color: var(--success);"></i>
                        <?php elseif ($isOptChosen && !$isCorrect): ?>
                            <i class="bi bi-x-circle-fill ms-auto" style="color: var(--danger);"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Explanation Box -->
            <div class="cbt-explanation-box">
                <div class="title">
                    <i class="bi bi-lightbulb-fill me-1"></i> Correct Answer: Option <?= $correctChoice ?>
                </div>
                <div class="content">
                    <?= $rq['explanation'] ?: 'Standard verified solution by StudyMe academic reviewers.' ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="cbt-empty-state">
            <i class="bi bi-inbox"></i>
            <h4>No question details found</h4>
            <p>There are no recorded answers for this attempt.</p>
        </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ============================================================ -->
<!-- ACTIVE CBT PRACTICE SIMULATOR -->
<!-- ============================================================ -->
<div id="cbtExamApp">
    <!-- TOP STATUS HEADER & TIMER BAR -->
    <!-- TOP STATUS HEADER & TIMER BAR -->
    <div class="cbt-header-bar d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <span class="cbt-exam-badge">
                <i class="bi bi-mortarboard-fill"></i>
                <?= strtoupper(e($selectedExam)) ?> CBT
            </span>
            <span class="cbt-subject-label d-none d-sm-inline">
                <?= e($currentSubjectName) ?>
            </span>
        </div>

        <div class="d-flex align-items-center gap-3">
            <div class="cbt-timer" id="cbtTimerBadge">
                <i class="bi bi-stopwatch"></i>
                <span id="cbtTimerDisplay"><?= sprintf('%02d:00', $calculatedMinutes) ?></span>
            </div>

            <button type="button" class="cbt-submit-btn" onclick="confirmSubmitExam()">
                <i class="bi bi-check2-circle"></i>
                <span class="hide-mobile">Submit Exam</span>
            </button>
        </div>
    </div>

    <?php if (!empty($questions)): ?>
    <div class="row g-4">
        <!-- LEFT: QUESTION DISPLAY CONTAINER -->
        <div class="col-lg-8">
            <div class="cbt-question-card" id="activeQuestionCard">
                <!-- Top Question Status Bar -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-3" style="border-bottom: 1px solid var(--border-subtle, #F1F5F9);">
                    <div class="d-flex align-items-center gap-2">
                        <span class="cbt-q-number" id="currentQNumberBadge">
                            Question 1 of <?= $totalQuestionsCount ?>
                        </span>
                        <span class="cbt-q-meta" id="currentQExamYearBadge">
                            WAEC 2024
                        </span>
                    </div>

                    <button type="button" class="cbt-flag-btn" id="flagBtn" onclick="toggleFlagCurrentQuestion()">
                        <i class="bi bi-flag"></i> <span id="flagBtnText">Flag for Review</span>
                    </button>
                </div>

                <!-- QUESTION TEXT -->
                <div class="cbt-question-text" id="cbtQuestionText">
                    Loading question...
                </div>

                <!-- OPTIONS LIST -->
                <div class="d-flex flex-column gap-3" id="cbtOptionsContainer">
                    <!-- Dynamic Options Injected via JS -->
                </div>

                <!-- BOTTOM NAVIGATION -->
                <div class="cbt-nav-bar">
                    <button type="button" class="cbt-nav-btn" id="prevBtn" onclick="prevQuestion()">
                        <i class="bi bi-chevron-left"></i> Previous
                    </button>

                    <span class="cbt-progress-text" id="answeredProgressText">
                        Answered: <strong id="answeredCountVal">0</strong> / <?= $totalQuestionsCount ?>
                    </span>

                    <button type="button" class="cbt-nav-btn primary" id="nextBtn" onclick="nextQuestion()">
                        Next <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- RIGHT: QUESTION PALETTE NAVIGATOR -->
        <div class="col-lg-4">
            <div class="cbt-palette-card">
                <div class="cbt-palette-title">
                    <span><i class="bi bi-grid-3x3-gap-fill me-2"></i>Question Palette</span>
                    <span class="cbt-palette-count"><?= $totalQuestionsCount ?> Qs</span>
                </div>

                <!-- Legend -->
                <div class="cbt-palette-legend">
                    <div class="cbt-legend-item">
                        <span class="cbt-legend-dot unanswered"></span> Unanswered
                    </div>
                    <div class="cbt-legend-item">
                        <span class="cbt-legend-dot answered"></span> Answered
                    </div>
                    <div class="cbt-legend-item">
                        <span class="cbt-legend-dot flagged"></span> Flagged
                    </div>
                    <div class="cbt-legend-item">
                        <span class="cbt-legend-dot active-dot"></span> Active
                    </div>
                </div>

                <!-- PALETTE GRID -->
                <div class="cbt-palette-grid" id="cbtPaletteGrid">
                    <?php for ($i = 0; $i < $totalQuestionsCount; $i++): ?>
                        <div class="cbt-palette-btn <?= $i === 0 ? 'active' : '' ?>" id="pal_btn_<?= $i ?>" onclick="jumpToQuestion(<?= $i ?>)">
                            <?= $i + 1 ?>
                        </div>
                    <?php endfor; ?>
                </div>

                <!-- Quick Action Buttons -->
                <div class="d-grid gap-2" style="border-top: 1px solid var(--border-subtle); padding-top: 16px;">
                    <button type="button" class="cbt-palette-action danger" onclick="confirmSubmitExam()">
                        <i class="bi bi-send-check"></i> End & Submit CBT
                    </button>
                    <a href="<?= url('student/secondary-past-questions.php') ?>" class="cbt-palette-action muted" onclick="return confirm('Exit without saving? Your progress will be discarded.');">
                        <i class="bi bi-box-arrow-left"></i> Exit to Hub
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- SUBMIT CONFIRMATION MODAL -->
    <!-- SUBMIT CONFIRMATION MODAL -->
    <div class="modal fade cbt-modal" id="submitExamModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" style="font-family: var(--font-heading);"><i class="bi bi-question-circle-fill text-warning me-2"></i>Confirm Submission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="modal-icon primary">
                        <i class="bi bi-journal-check fs-1"></i>
                    </div>
                    <h5 class="fw-bold mb-2" style="font-family: var(--font-heading); color: var(--text-main);">Ready to submit?</h5>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 20px;">
                        You answered <strong style="color: var(--success);" id="modalAnsweredCount">0</strong> of <strong id="modalTotalCount"><?= $totalQuestionsCount ?></strong> questions.<br>
                        Unanswered: <strong style="color: var(--danger);" id="modalUnansweredCount"><?= $totalQuestionsCount ?></strong>
                    </p>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="cbt-modal-btn secondary" data-bs-dismiss="modal">Keep Practicing</button>
                        <button type="button" class="cbt-modal-btn primary" onclick="finalizeSubmission()">
                            <i class="bi bi-check2-circle me-1"></i> Submit Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FINAL RESULT MODAL -->
    <div class="modal fade cbt-modal" id="finalResultModal" data-bs-backdrop="static" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="padding: 24px;">
                <div class="modal-body text-center">
                    <div class="modal-icon success">
                        <i class="bi bi-trophy-fill fs-1"></i>
                    </div>
                    <h3 class="fw-bold mb-1" style="font-family: var(--font-heading); color: var(--text-main);">CBT Session Completed!</h3>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 20px;">Your verified performance scorecard:</p>

                    <div class="cbt-result-score-grid mb-4">
                        <div class="row g-2">
                            <div class="col-4">
                                <div class="cbt-result-stat">
                                    <div class="value text-success" id="resCorrectScore">0</div>
                                    <div class="label">Correct</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="cbt-result-stat">
                                    <div class="value" id="resTotalScore"><?= $totalQuestionsCount ?></div>
                                    <div class="label">Total</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="cbt-result-stat">
                                    <div class="value" style="color: var(--primary);" id="resPercentage">0%</div>
                                    <div class="label">Accuracy</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="#" id="reviewAttemptLink" class="cbt-modal-btn primary" style="display:block; text-align:center; text-decoration:none; padding: 12px;">
                            <i class="bi bi-card-checklist me-1"></i> Review Full Solutions
                        </a>
                        <a href="<?= url('student/secondary-practice-history.php') ?>" class="cbt-modal-btn secondary" style="display:block; text-align:center; text-decoration:none; padding: 12px;">
                            <i class="bi bi-clock-history me-1"></i> View Practice History
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CBT CLIENT SCRIPT ENGINE -->
    <script>
    const questionsData = <?= json_encode($questions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const totalQuestions = questionsData.length;
    let currentIndex = 0;
    let userAnswers = {}; // { questionId: 'A' | 'B' | 'C' | 'D' }
    let flaggedQuestions = {}; // { index: true }
    let currentAttemptId = 0;
    let timeSpentSeconds = 0;
    let timerDurationSeconds = <?= $calculatedMinutes * 60 ?>;
    let timerInterval = null;

    // Start CBT Attempt Session via AJAX
    function initializeAttempt() {
        const formData = new FormData();
        formData.append('practice_action', 'start');
        formData.append('exam_type', '<?= e($selectedExam) ?>');
        formData.append('subject_slug', '<?= e($selectedSubject) ?>');
        formData.append('topic_name', '<?= e($selectedTopic) ?>');
        formData.append('year', '<?= (int)$selectedYear ?>');
        formData.append('total_questions', totalQuestions);
        formData.append('time_limit_minutes', '<?= (int)$calculatedMinutes ?>');
        formData.append('mode', '<?= e($mode) ?>');

        fetch('secondary-practice.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                currentAttemptId = data.attempt_id;
                startTimer();
            }
        })
        .catch(err => console.error('Error starting attempt:', err));
    }

    function startTimer() {
        let remaining = timerDurationSeconds;
        timerInterval = setInterval(() => {
            timeSpentSeconds++;
            remaining--;
            if (remaining <= 0) {
                clearInterval(timerInterval);
                alert('Time has expired! Submitting your practice session.');
                finalizeSubmission();
                return;
            }
            const mins = Math.floor(remaining / 60);
            const secs = remaining % 60;
            document.getElementById('cbtTimerDisplay').innerText = 
                (mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs;
        }, 1000);
    }

    function renderQuestion(index) {
        if (index < 0 || index >= totalQuestions) return;
        currentIndex = index;
        const q = questionsData[index];

        document.getElementById('currentQNumberBadge').innerText = `Question ${index + 1} of ${totalQuestions}`;
        document.getElementById('currentQExamYearBadge').innerText = `${q.exam_type ? q.exam_type.toUpperCase() : 'WAEC'} ${q.year || 2024}`;
        document.getElementById('cbtQuestionText').innerHTML = q.question_text;

        // Render Options
        const optionsContainer = document.getElementById('cbtOptionsContainer');
        optionsContainer.innerHTML = '';
        const currentAns = userAnswers[q.id] || '';

        const options = [
            { letter: 'A', text: q.option_a },
            { letter: 'B', text: q.option_b },
            { letter: 'C', text: q.option_c },
            { letter: 'D', text: q.option_d }
        ];

        options.forEach(opt => {
            const isSelected = (currentAns === opt.letter);
            const optDiv = document.createElement('div');
            optDiv.className = `cbt-option-item ${isSelected ? 'selected' : ''}`;
            optDiv.onclick = () => selectOption(q.id, opt.letter);
            optDiv.innerHTML = `
                <span class="cbt-option-letter">${opt.letter}</span>
                <div class="cbt-option-text">${opt.text}</div>
                <i class="bi bi-check-circle-fill cbt-option-check"></i>
            `;
            optionsContainer.appendChild(optDiv);
        });

        // Update Flag status
        const isFlagged = Boolean(flaggedQuestions[index]);
        document.getElementById('flagBtnText').innerText = isFlagged ? 'Flagged' : 'Flag for Review';
        document.getElementById('flagBtn').className = `cbt-flag-btn ${isFlagged ? 'flagged' : ''}`;

        // Update Palette Active Class
        document.querySelectorAll('.cbt-palette-btn').forEach((btn, i) => {
            btn.classList.toggle('active', i === index);
        });

        // Prev/Next buttons
        document.getElementById('prevBtn').disabled = (index === 0);
        document.getElementById('nextBtn').innerHTML = (index === totalQuestions - 1) ? '<i class="bi bi-check2-circle me-1"></i> Review & Submit' : 'Next <i class="bi bi-chevron-right"></i>';

        // Re-render MathJax equations
        if (window.MathJax) {
            MathJax.typesetPromise([document.getElementById('activeQuestionCard')]).catch(err => console.warn(err));
        }
    }

    function selectOption(questionId, letter) {
        userAnswers[questionId] = letter;

        // Auto-save answer to server
        if (currentAttemptId > 0) {
            const formData = new FormData();
            formData.append('practice_action', 'answer');
            formData.append('attempt_id', currentAttemptId);
            formData.append('question_id', questionId);
            formData.append('selected_option', letter);

            fetch('secondary-practice.php', {
                method: 'POST',
                body: formData
            }).catch(err => console.error('Error saving answer:', err));
        }

        // Update Palette button color
        const palBtn = document.getElementById(`pal_btn_${currentIndex}`);
        if (palBtn) {
            palBtn.classList.add('answered');
        }

        // Update Answered Counter
        const answeredCount = Object.keys(userAnswers).length;
        document.getElementById('answeredCountVal').innerText = answeredCount;

        // Re-render question to show selected UI
        renderQuestion(currentIndex);
    }

    function toggleFlagCurrentQuestion() {
        flaggedQuestions[currentIndex] = !flaggedQuestions[currentIndex];
        const palBtn = document.getElementById(`pal_btn_${currentIndex}`);
        if (palBtn) {
            if (flaggedQuestions[currentIndex]) {
                palBtn.classList.add('flagged');
            } else {
                palBtn.classList.remove('flagged');
            }
        }
        renderQuestion(currentIndex);
    }

    function nextQuestion() {
        if (currentIndex < totalQuestions - 1) {
            renderQuestion(currentIndex + 1);
        } else {
            confirmSubmitExam();
        }
    }

    function prevQuestion() {
        if (currentIndex > 0) {
            renderQuestion(currentIndex - 1);
        }
    }

    function jumpToQuestion(index) {
        renderQuestion(index);
    }

    function confirmSubmitExam() {
        const answered = Object.keys(userAnswers).length;
        document.getElementById('modalAnsweredCount').innerText = answered;
        document.getElementById('modalUnansweredCount').innerText = totalQuestions - answered;
        const modal = new bootstrap.Modal(document.getElementById('submitExamModal'));
        modal.show();
    }

    function finalizeSubmission() {
        clearInterval(timerInterval);
        const submitModal = bootstrap.Modal.getInstance(document.getElementById('submitExamModal'));
        if (submitModal) submitModal.hide();

        const formData = new FormData();
        formData.append('practice_action', 'complete');
        formData.append('attempt_id', currentAttemptId);
        formData.append('time_spent_seconds', timeSpentSeconds);

        fetch('secondary-practice.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('resCorrectScore').innerText = data.score;
                document.getElementById('resPercentage').innerText = data.percentage + '%';
                document.getElementById('reviewAttemptLink').href = `secondary-practice.php?review_attempt=${currentAttemptId}`;
                const resultModal = new bootstrap.Modal(document.getElementById('finalResultModal'));
                resultModal.show();
            }
        })
        .catch(err => console.error('Error completing attempt:', err));
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', () => {
        initializeAttempt();
        renderQuestion(0);
    });
    </script>

    <?php else: ?>
    <div class="cbt-empty-state">
        <i class="bi bi-inbox"></i>
        <h4>No questions found for the selected subject / exam</h4>
        <p>Please try choosing another subject from our Past Questions Hub.</p>
        <a href="<?= url('student/secondary-past-questions.php') ?>" class="cbt-nav-btn primary" style="text-decoration:none;">
            <i class="bi bi-arrow-left"></i> Return to Past Questions Hub
        </a>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
