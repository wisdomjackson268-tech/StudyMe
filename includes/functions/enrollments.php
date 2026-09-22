<?php
require_once dirname(__DIR__, 2) . '/config/database.php';

function is_student_enrolled($studentId, $courseId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$studentId, $courseId]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        error_log("Error in is_student_enrolled: " . $e->getMessage());
        return false;
    }
}

function get_enrollment($studentId, $courseId) {
    $pdo = getDBConnection();
    try {
        $stmtCheck = $pdo->prepare("SELECT id FROM students WHERE id = ? LIMIT 1");
        $stmtCheck->execute([(int)$studentId]);
        $resolvedStudentId = (int)$stmtCheck->fetchColumn();
        if (!$resolvedStudentId) {
            $stmtUser = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
            $stmtUser->execute([(int)$studentId]);
            $resolvedStudentId = (int)$stmtUser->fetchColumn();
        }
        if (!$resolvedStudentId) {
            return null;
        }
        $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE student_id = ? AND course_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([(int)$resolvedStudentId, (int)$courseId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    } catch (Exception $e) {
        error_log("Error in get_enrollment: " . $e->getMessage());
        return null;
    }
}

if (!function_exists('get_student_active_course')) {
    function get_student_active_course($studentIdOrUserId = null) {
        $pdo = getDBConnection();
        try {
            $studentId = (int)$studentIdOrUserId;

            if ($studentId <= 0 && function_exists('is_logged_in') && is_logged_in()) {
                $uid = (int)current_user('id');
                if ($uid > 0) {
                    $st = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
                    $st->execute([$uid]);
                    $studentId = (int)$st->fetchColumn();
                }
            }

            if ($studentId <= 0) {
                return null;
            }

            $stmtCheck = $pdo->prepare("SELECT id FROM students WHERE id = ? LIMIT 1");
            $stmtCheck->execute([$studentId]);
            $resolvedStudentId = (int)$stmtCheck->fetchColumn();

            if ($resolvedStudentId <= 0) {
                $stmtCheckUser = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
                $stmtCheckUser->execute([$studentId]);
                $resolvedStudentId = (int)$stmtCheckUser->fetchColumn();
            }

            if ($resolvedStudentId <= 0) {
                return null;
            }

            $stmt = $pdo->prepare("
                SELECT e.*, c.id AS course_id, c.title AS course_title, c.slug AS course_slug, c.thumbnail,
                       c.category_id, cat.name AS category_name, cat.slug AS category_slug,
                       c.academic_level, c.academic_year,
                       COALESCE(e.teacher_id, c.teacher_id) AS teacher_id,
                       CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
                       t.qualification AS teacher_qualification,
                       u.avatar AS teacher_avatar
                FROM enrollments e
                JOIN courses c ON e.course_id = c.id
                LEFT JOIN categories cat ON c.category_id = cat.id
                LEFT JOIN teachers t ON (e.teacher_id = t.id OR c.teacher_id = t.id)
                LEFT JOIN users u ON t.user_id = u.id
                WHERE e.student_id = ? AND e.status = 'active'
                ORDER BY e.enrolled_at DESC
                LIMIT 1
            ");
            $stmt->execute([$resolvedStudentId]);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);
            return $course ?: null;
        } catch (Exception $e) {
            error_log("Error in get_student_active_course: " . $e->getMessage());
            return null;
        }
    }
}

function is_secondary_student($studentIdOrUserId = null) {
    $pdo = getDBConnection();
    $studentIdOrUserId = (int)$studentIdOrUserId;

    if ($studentIdOrUserId <= 0 && function_exists('current_user')) {
        $studentIdOrUserId = (int)current_user('id');
    }

    if ($studentIdOrUserId <= 0) {
        return false;
    }

    try {
        $stmtType = $pdo->prepare("SELECT s.student_type FROM students s WHERE s.id = ? OR s.user_id = ? LIMIT 1");
        $stmtType->execute([$studentIdOrUserId, $studentIdOrUserId]);
        $type = $stmtType->fetchColumn();

        if ($type === 'secondary') {
            $stmtSec = $pdo->prepare("SELECT 1 FROM students s JOIN enrollments e ON e.student_id = s.id AND e.status = 'active' JOIN courses c ON c.id = e.course_id JOIN categories cat ON cat.id = c.category_id WHERE (s.id = ? OR s.user_id = ?) AND cat.slug IN ('secondary-waec-neco', 'secondary') LIMIT 1");
            $stmtSec->execute([$studentIdOrUserId, $studentIdOrUserId]);
            return (bool)$stmtSec->fetchColumn();
        }

        if ($type === 'university' || $type === 'technology' || $type === 'tech') {
            return false;
        }

        $stmt = $pdo->prepare("SELECT 1 FROM students s JOIN enrollments e ON e.student_id = s.id AND e.status = 'active' JOIN courses c ON c.id = e.course_id JOIN categories cat ON cat.id = c.category_id WHERE (s.id = ? OR s.user_id = ?) AND cat.slug IN ('secondary-waec-neco', 'secondary') LIMIT 1");
        $stmt->execute([$studentIdOrUserId, $studentIdOrUserId]);
        if ((bool)$stmt->fetchColumn()) {
            return true;
        }

        $stmtUni = $pdo->prepare("SELECT 1 FROM students s JOIN enrollments e ON e.student_id = s.id AND e.status = 'active' JOIN courses c ON c.id = e.course_id JOIN categories cat ON cat.id = c.category_id WHERE (s.id = ? OR s.user_id = ?) AND cat.slug IN ('university', 'technology', 'tech') LIMIT 1");
        $stmtUni->execute([$studentIdOrUserId, $studentIdOrUserId]);
        if ((bool)$stmtUni->fetchColumn()) {
            return false;
        }

        return false;
    } catch (Exception $e) {
        error_log('Error checking Secondary student access: ' . $e->getMessage());
        return false;
    }
}

function get_student_track($userId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT student_type, academic_level, target_exam FROM students WHERE user_id = ? LIMIT 1");
        $stmt->execute([(int)$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['student_type'])) {
            return $row['student_type'];
        }
        return is_secondary_student($userId) ? 'secondary' : 'university';
    } catch (Exception $e) {
        return 'university';
    }
}

function get_secondary_subjects($status = 'active') {
    $pdo = getDBConnection();
    try {
        $sql = "SELECT s.*, 
                       (SELECT COUNT(*) FROM secondary_topics t WHERE t.subject_id = s.id AND t.status = 'active') AS topic_count,
                       (SELECT COUNT(*) FROM secondary_materials m WHERE m.subject_id = s.id AND m.status = 'published') AS material_count,
                       (SELECT COUNT(*) FROM past_questions pq WHERE pq.subject_slug = s.slug) AS question_count
                FROM secondary_subjects s ";
        if ($status) {
            $sql .= "WHERE s.status = ? ";
            $stmt = $pdo->prepare($sql . "ORDER BY s.sort_order ASC, s.name ASC");
            $stmt->execute([$status]);
        } else {
            $stmt = $pdo->query($sql . "ORDER BY s.sort_order ASC, s.name ASC");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in get_secondary_subjects: " . $e->getMessage());
        return [];
    }
}

function get_secondary_subject_by_slug($slug) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT * FROM secondary_subjects WHERE slug = ? OR code = ? LIMIT 1");
        $stmt->execute([$slug, strtoupper($slug)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log("Error in get_secondary_subject_by_slug: " . $e->getMessage());
        return null;
    }
}

function get_secondary_topics($subjectId = null, $status = 'active') {
    $pdo = getDBConnection();
    try {
        $where = [];
        $params = [];
        if ($subjectId) {
            $where[] = "t.subject_id = ?";
            $params[] = (int)$subjectId;
        }
        if ($status) {
            $where[] = "t.status = ?";
            $params[] = $status;
        }
        $sql = "SELECT t.*, s.name AS subject_name, s.slug AS subject_slug 
                FROM secondary_topics t
                JOIN secondary_subjects s ON t.subject_id = s.id ";
        if (!empty($where)) {
            $sql .= "WHERE " . implode(' AND ', $where) . " ";
        }
        $sql .= "ORDER BY t.sort_order ASC, t.id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in get_secondary_topics: " . $e->getMessage());
        return [];
    }
}

function get_secondary_materials($subjectId = null, $topicId = null) {
    $pdo = getDBConnection();
    try {
        $where = ["m.status = 'published'"];
        $params = [];
        if ($subjectId) {
            $where[] = "m.subject_id = ?";
            $params[] = (int)$subjectId;
        }
        if ($topicId) {
            $where[] = "m.topic_id = ?";
            $params[] = (int)$topicId;
        }
        $sql = "SELECT m.*, s.name AS subject_name, s.slug AS subject_slug, s.icon AS subject_icon, s.color AS subject_color,
                       t.title AS topic_title
                FROM secondary_materials m
                JOIN secondary_subjects s ON m.subject_id = s.id
                LEFT JOIN secondary_topics t ON m.topic_id = t.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY m.sort_order ASC, m.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in get_secondary_materials: " . $e->getMessage());
        return [];
    }
}

function get_secondary_practice_stats($studentId) {
    $pdo = getDBConnection();
    $stats = [
        'total_attempts'      => 0,
        'total_questions'     => 0,
        'correct_answers'     => 0,
        'wrong_answers'       => 0,
        'average_accuracy'    => 0,
        'total_time_minutes'  => 0,
        'waec_attempts'       => 0,
        'neco_attempts'       => 0,
        'jamb_attempts'       => 0,
    ];
    if (!$studentId) return $stats;

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total_attempts,
                   COALESCE(SUM(total_questions), 0) AS total_questions,
                   COALESCE(SUM(correct_answers), 0) AS correct_answers,
                   COALESCE(SUM(wrong_answers), 0) AS wrong_answers,
                   COALESCE(AVG(score_percentage), 0) AS average_accuracy,
                   COALESCE(SUM(time_spent_seconds), 0) / 60 AS total_time_minutes,
                   COALESCE(SUM(IF(exam_type = 'waec', 1, 0)), 0) AS waec_attempts,
                   COALESCE(SUM(IF(exam_type = 'neco', 1, 0)), 0) AS neco_attempts,
                   COALESCE(SUM(IF(exam_type = 'jamb' OR exam_type = 'utme', 1, 0)), 0) AS jamb_attempts
            FROM secondary_practice_attempts
            WHERE student_id = ? AND completed_at IS NOT NULL
        ");
        $stmt->execute([(int)$studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $stats['total_attempts']     = (int)$row['total_attempts'];
            $stats['total_questions']    = (int)$row['total_questions'];
            $stats['correct_answers']    = (int)$row['correct_answers'];
            $stats['wrong_answers']      = (int)$row['wrong_answers'];
            $stats['average_accuracy']   = round((float)$row['average_accuracy'], 1);
            $stats['total_time_minutes'] = round((float)$row['total_time_minutes']);
            $stats['waec_attempts']      = (int)$row['waec_attempts'];
            $stats['neco_attempts']      = (int)$row['neco_attempts'];
            $stats['jamb_attempts']      = (int)$row['jamb_attempts'];
        }
    } catch (Exception $e) {
        error_log("Error in get_secondary_practice_stats: " . $e->getMessage());
    }
    return $stats;
}

function get_secondary_practice_history($studentId, $limit = 20) {
    $pdo = getDBConnection();
    if (!$studentId) return [];
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, s.name AS subject_name, s.icon AS subject_icon, s.color AS subject_color
            FROM secondary_practice_attempts a
            LEFT JOIN secondary_subjects s ON a.subject_slug = s.slug
            WHERE a.student_id = ?
            ORDER BY a.started_at DESC
            LIMIT ?
        ");
        $stmt->execute([(int)$studentId, (int)$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in get_secondary_practice_history: " . $e->getMessage());
        return [];
    }
}

function enroll_student_in_course($studentId, $courseId) {
    $pdo = getDBConnection();
    try {
        $stmtCheck = $pdo->prepare("SELECT id FROM students WHERE id = ? LIMIT 1");
        $stmtCheck->execute([(int)$studentId]);
        $resolvedStudentId = (int)$stmtCheck->fetchColumn();
        if (!$resolvedStudentId) {
            $stmtUser = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
            $stmtUser->execute([(int)$studentId]);
            $resolvedStudentId = (int)$stmtUser->fetchColumn();
        }
        if (!$resolvedStudentId) {
            return [
                'success' => false,
                'message' => 'Student record not found.'
            ];
        }
        $studentId = $resolvedStudentId;

        $activeCourse = get_student_active_course($studentId);
        if ($activeCourse && (int)$activeCourse['course_id'] !== (int)$courseId) {
            return [
                'success' => false,
                'message' => 'Access Restricted: You are currently enrolled in "' . htmlspecialchars($activeCourse['course_title']) . '". Students are restricted to ONE course at a time.'
            ];
        }

        $stmtT = $pdo->prepare("SELECT teacher_id FROM courses WHERE id = ? LIMIT 1");
        $stmtT->execute([$courseId]);
        $courseTeacherId = $stmtT->fetchColumn();
        if (!$courseTeacherId) {
            $stmtT2 = $pdo->prepare("SELECT id FROM teachers WHERE assigned_course_id = ? LIMIT 1");
            $stmtT2->execute([$courseId]);
            $courseTeacherId = $stmtT2->fetchColumn() ?: null;
        }

        $stmt = $pdo->prepare("
            INSERT INTO enrollments (student_id, course_id, teacher_id, status, progress, enrolled_at)
            VALUES (?, ?, ?, 'active', 0.00, NOW())
            ON DUPLICATE KEY UPDATE status = 'active', teacher_id = IF(VALUES(teacher_id) IS NOT NULL, VALUES(teacher_id), teacher_id)
        ");
        $success = $stmt->execute([$studentId, $courseId, $courseTeacherId]);

        if ($success) {
            return [
                'success' => true,
                'message' => 'Successfully enrolled in course!'
            ];
        }

        return [
            'success' => false,
            'message' => 'Enrollment failed. Please try again.'
        ];
    } catch (Exception $e) {
        error_log("Error enrolling student: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error during enrollment.'
        ];
    }
}

function get_student_enrollments($studentId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT e.*, c.title, c.thumbnail, c.slug, c.academic_level, c.academic_year,
                   COALESCE(e.teacher_id, c.teacher_id) AS effective_teacher_id,
                   CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
                   u.avatar AS teacher_avatar,
                   t.qualification AS teacher_qualification
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            LEFT JOIN teachers t ON (e.teacher_id = t.id OR c.teacher_id = t.id)
            LEFT JOIN users u ON t.user_id = u.id
            WHERE e.student_id = ? AND e.status = 'active'
            ORDER BY e.enrolled_at DESC
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching enrollments: " . $e->getMessage());
        return [];
    }
}

function get_secondary_exam_counts() {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN exam_type = 'waec' THEN 1 ELSE 0 END) AS waec_count,
                SUM(CASE WHEN exam_type = 'neco' THEN 1 ELSE 0 END) AS neco_count,
                SUM(CASE WHEN exam_type = 'jamb' OR exam_type = 'utme' THEN 1 ELSE 0 END) AS jamb_count
            FROM past_questions
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'waec_count' => 0, 'neco_count' => 0, 'jamb_count' => 0];
    } catch (Exception $e) {
        return ['total' => 0, 'waec_count' => 0, 'neco_count' => 0, 'jamb_count' => 0];
    }
}

function get_secondary_exam_subjects($examType = null) {
    $pdo = getDBConnection();
    try {
        $where = [];
        $params = [];
        if (!empty($examType) && $examType !== 'all') {
            if ($examType === 'jamb' || $examType === 'utme') {
                $where[] = "(pq.exam_type = 'jamb' OR pq.exam_type = 'utme')";
            } else {
                $where[] = "pq.exam_type = ?";
                $params[] = $examType;
            }
        }
        $sql = "
            SELECT pq.subject_slug, 
                   COALESCE(s.name, pq.subject_name) AS subject_name,
                   COALESCE(s.icon, 'bi-book-half') AS icon,
                   COALESCE(s.color, '#087f5b') AS color,
                   COUNT(pq.id) AS question_count,
                   MIN(pq.year) AS min_year,
                   MAX(pq.year) AS max_year
            FROM past_questions pq
            LEFT JOIN secondary_subjects s ON pq.subject_slug = s.slug
        ";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " GROUP BY pq.subject_slug, subject_name, icon, color ORDER BY question_count DESC, subject_name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in get_secondary_exam_subjects: " . $e->getMessage());
        return [];
    }
}

function get_secondary_subject_years($examType = null, $subjectSlug = null) {
    $pdo = getDBConnection();
    try {
        $where = [];
        $params = [];
        if (!empty($examType) && $examType !== 'all') {
            if ($examType === 'jamb' || $examType === 'utme') {
                $where[] = "(exam_type = 'jamb' OR exam_type = 'utme')";
            } else {
                $where[] = "exam_type = ?";
                $params[] = $examType;
            }
        }
        if (!empty($subjectSlug)) {
            $where[] = "subject_slug = ?";
            $params[] = $subjectSlug;
        }
        $sql = "SELECT DISTINCT year, COUNT(*) AS count FROM past_questions ";
        if (!empty($where)) {
            $sql .= "WHERE " . implode(' AND ', $where) . " ";
        }
        $sql .= "GROUP BY year ORDER BY year DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function get_secondary_lesson_progress_stats($studentId) {
    $pdo = getDBConnection();
    $res = ['completed_count' => 0, 'total_materials' => 0, 'percentage' => 0];
    try {
        $totalStmt = $pdo->query("SELECT COUNT(*) FROM secondary_materials WHERE status = 'published'");
        $res['total_materials'] = (int)$totalStmt->fetchColumn();

        if ($studentId) {
            $compStmt = $pdo->prepare("SELECT COUNT(*) FROM secondary_lesson_progress WHERE student_id = ? AND completed = 1");
            $compStmt->execute([(int)$studentId]);
            $res['completed_count'] = (int)$compStmt->fetchColumn();
        }

        if ($res['total_materials'] > 0) {
            $res['percentage'] = round(($res['completed_count'] / $res['total_materials']) * 100, 1);
        }
    } catch (Exception $e) {
        error_log("Error in get_secondary_lesson_progress_stats: " . $e->getMessage());
    }
    return $res;
}

