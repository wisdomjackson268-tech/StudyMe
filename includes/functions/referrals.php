<?php
/**
 * StudyMe AI Platform — Comprehensive Referral, Wallet & Bonus Engine
 * Handles unique referral links, category restriction checks, configurable admin rates,
 * post-payment bonus distribution, student wallet tracking, and withdrawal requests.
 */

if (!function_exists('get_user_referral_code')) {
    /**
     * Retrieve or generate unique referral code for a user
     */
    function get_user_referral_code($userId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT referral_code, role, first_name FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        if (!empty($user['referral_code'])) {
            return $user['referral_code'];
        }

        // Generate unique referral code
        $prefix = strtoupper(substr($user['role'], 0, 3));
        $code = $prefix . '-' . strtoupper(substr(md5($userId . $user['first_name'] . time()), 0, 6));

        $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?")->execute([$code, $userId]);
        
        // Ensure wallet exists
        $pdo->prepare("INSERT IGNORE INTO wallets (user_id, available_balance, pending_balance, total_earned, total_withdrawn) VALUES (?, 0, 0, 0, 0)")
            ->execute([$userId]);

        return $code;
    }
}

if (!function_exists('get_user_by_referral_code')) {
    /**
     * Look up user by referral code
     */
    function get_user_by_referral_code($code) {
        if (empty($code)) return null;
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE referral_code = ? LIMIT 1");
        $stmt->execute([trim($code)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('get_referral_bonus_rates')) {
    /**
     * Fetch configurable bonus rates from settings database table
     */
    function get_referral_bonus_rates() {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'bonus_rate_%'");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'teacher'    => (float)($rows['bonus_rate_teacher']    ?? 1000.00),
            'university' => (float)($rows['bonus_rate_university'] ?? 1000.00),
            'secondary'  => (float)($rows['bonus_rate_secondary']  ?? 500.00),
            'technology' => (float)($rows['bonus_rate_technology'] ?? 1500.00),
        ];
    }
}

if (!function_exists('capture_incoming_referral')) {
    /**
     * Detect and preserve ?ref=... parameter in session and cookie for localhost / production
     */
    function capture_incoming_referral() {
        if (!empty($_GET['ref'])) {
            $refCode = trim($_GET['ref']);
            $_SESSION['pending_ref_code'] = $refCode;
            @setcookie('study_ref_code', $refCode, time() + (86400 * 30), '/');
        }
    }
}

if (!function_exists('get_pending_referral_code')) {
    /**
     * Retrieve stored referral code from session or cookie
     */
    function get_pending_referral_code() {
        return $_SESSION['pending_ref_code'] ?? $_COOKIE['study_ref_code'] ?? null;
    }
}

if (!function_exists('clear_pending_referral_code')) {
    /**
     * Clear referral session & cookie after registration
     */
    function clear_pending_referral_code() {
        unset($_SESSION['pending_ref_code']);
        @setcookie('study_ref_code', '', time() - 3600, '/');
    }
}

if (!function_exists('record_pending_referral')) {
    /**
     * Create pending referral record upon new user registration
     */
    function record_pending_referral($newUserId) {
        $refCode = get_pending_referral_code();
        if (empty($refCode)) {
            return false;
        }

        $referrer = get_user_by_referral_code($refCode);
        if (!$referrer || (int)$referrer['id'] === (int)$newUserId) {
            return false;
        }

        $pdo = getDBConnection();
        $referrerId   = (int)$referrer['id'];
        $referrerRole = $referrer['role'];

        // Determine referrer's primary category
        $referrerCategory = null;
        if ($referrerRole === 'teacher') {
            $stmtTch = $pdo->prepare("
                SELECT cat.slug 
                FROM teachers t 
                LEFT JOIN categories cat ON t.assigned_category_id = cat.id 
                WHERE t.user_id = ? LIMIT 1
            ");
            $stmtTch->execute([$referrerId]);
            $referrerCategory = $stmtTch->fetchColumn() ?: 'technology';
        } else {
            // Student referrer category from their enrolled course
            $stmtSt = $pdo->prepare("
                SELECT cat.slug
                FROM students s
                JOIN enrollments e ON s.id = e.student_id
                JOIN courses c ON e.course_id = c.id
                JOIN categories cat ON c.category_id = cat.id
                WHERE s.user_id = ? AND e.status = 'active'
                ORDER BY e.enrolled_at DESC LIMIT 1
            ");
            $stmtSt->execute([$referrerId]);
            $referrerCategory = $stmtSt->fetchColumn() ?: 'technology';
        }

        // Insert pending referral
        $stmtIns = $pdo->prepare("
            INSERT INTO referrals (referrer_id, referrer_role, referrer_category, referred_user_id, referral_code, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ");
        $stmtIns->execute([$referrerId, $referrerRole, $referrerCategory, $newUserId, $refCode]);

        clear_pending_referral_code();
        return true;
    }
}

if (!function_exists('process_verified_payment_referral')) {
    /**
     * Evaluate and award referral bonus ONLY AFTER payment is verified.
     * Enforces strict category matching and server-side bonus calculations.
     */
    function process_verified_payment_referral($paymentId, $referredUserId, $courseId, $paymentAmount) {
        $pdo = getDBConnection();

        // 1. Fetch pending referral record
        $stmtRef = $pdo->prepare("
            SELECT * FROM referrals 
            WHERE referred_user_id = ? AND status = 'pending'
            ORDER BY id DESC LIMIT 1
        ");
        $stmtRef->execute([$referredUserId]);
        $referral = $stmtRef->fetch(PDO::FETCH_ASSOC);

        if (!$referral) {
            return false; // No pending referral found
        }

        $referrerId = (int)$referral['referrer_id'];

        // Fetch referred course details
        $stmtCourse = $pdo->prepare("
            SELECT c.id, c.title, cat.slug AS category_slug
            FROM courses c
            JOIN categories cat ON c.category_id = cat.id
            WHERE c.id = ? LIMIT 1
        ");
        $stmtCourse->execute([$courseId]);
        $course = $stmtCourse->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            return false;
        }

        $courseCategory = $course['category_slug']; // e.g. 'technology', 'university', 'secondary-waec-neco'

        // Fetch Referrer user & role details
        $stmtReferrer = $pdo->prepare("SELECT id, role, first_name, last_name FROM users WHERE id = ? LIMIT 1");
        $stmtReferrer->execute([$referrerId]);
        $referrer = $stmtReferrer->fetch(PDO::FETCH_ASSOC);

        if (!$referrer) {
            return false;
        }

        // Determine Referrer's course category
        $referrerCategory = $referral['referrer_category'];
        if ($referrer['role'] === 'teacher') {
            $stmtTch = $pdo->prepare("
                SELECT cat.slug 
                FROM teachers t 
                LEFT JOIN categories cat ON t.assigned_category_id = cat.id 
                WHERE t.user_id = ? LIMIT 1
            ");
            $stmtTch->execute([$referrerId]);
            $referrerCategory = $stmtTch->fetchColumn() ?: 'technology';
        } else {
            $stmtStCat = $pdo->prepare("
                SELECT cat.slug
                FROM students s
                JOIN enrollments e ON s.id = e.student_id
                JOIN courses c ON e.course_id = c.id
                JOIN categories cat ON c.category_id = cat.id
                WHERE s.user_id = ? AND e.status = 'active'
                ORDER BY e.enrolled_at DESC LIMIT 1
            ");
            $stmtStCat->execute([$referrerId]);
            $refCatFound = $stmtStCat->fetchColumn();
            if ($refCatFound) {
                $referrerCategory = $refCatFound;
            }
        }

        $rates = get_referral_bonus_rates();
        $bonusAmount = 0.00;
        $appliedRule = null;        // ── CATEGORY RESTRICTION & BONUS RULE EVALUATION ────────────────
        // Normalize category slugs
        $normRefCat = ($referrerCategory === 'technology' || $referrerCategory === 'tech') ? 'technology' :
                      (($referrerCategory === 'university' || $referrerCategory === 'uni') ? 'university' :
                      (($referrerCategory === 'secondary-waec-neco' || $referrerCategory === 'secondary') ? 'secondary' : ''));
        
        $normCourseCat = ($courseCategory === 'technology' || $courseCategory === 'tech') ? 'technology' :
                         (($courseCategory === 'university' || $courseCategory === 'uni') ? 'university' :
                         (($courseCategory === 'secondary-waec-neco' || $courseCategory === 'secondary') ? 'secondary' : ''));

        if ($referrer['role'] === 'teacher') {
            // Rule 1: University Teacher -> University Student = ₦1,500
            if ($normRefCat === 'university' && $normCourseCat === 'university') {
                $bonusAmount = 1500.00;
                $appliedRule = 'university_teacher_bonus';
            } elseif ($normRefCat === 'technology' && $normCourseCat === 'technology') {
                $bonusAmount = 1500.00;
                $appliedRule = 'technology_teacher_bonus';
            } else {
                // Secondary teacher does not exist / invalid combination = ₦0
                $bonusAmount = 0.00;
                $appliedRule = null;
            }
        } elseif ($referrer['role'] === 'student') {
            // Rule 2: University Student -> University Student = ₦1,000
            if ($normRefCat === 'university' && $normCourseCat === 'university') {
                $bonusAmount = 1000.00;
                $appliedRule = 'university_student_bonus';
            }
            // Rule 3: Secondary Student -> Secondary Student = ₦1,000
            elseif ($normRefCat === 'secondary' && $normCourseCat === 'secondary') {
                $bonusAmount = 1000.00;
                $appliedRule = 'secondary_student_bonus';
            }
            // Tech Student -> Tech Student = ₦1,500
            elseif ($normRefCat === 'technology' && $normCourseCat === 'technology') {
                $bonusAmount = 1500.00;
                $appliedRule = 'technology_student_bonus';
            } else {
                // Unauthorized cross-type (e.g. Sec -> Uni, Uni -> Sec) = ₦0
                $bonusAmount = 0.00;
                $appliedRule = null;
            }
        }

        // If no valid bonus rule matched or category restriction failed:
        if ($bonusAmount <= 0 || empty($appliedRule)) {
            $pdo->prepare("
                UPDATE referrals 
                SET status = 'cancelled', 
                    payment_id = ?, 
                    course_id = ?, 
                    course_category = ?, 
                    payment_amount = ?, 
                    applied_rule = 'category_mismatch_void'
                WHERE id = ?
            ")->execute([$paymentId, $courseId, $courseCategory, $paymentAmount, $referral['id']]);

            return false;
        }

        // Award Bonus to Referrer
        $pdo->beginTransaction();
        try {
            // 1. Mark referral completed
            $stmtUpd = $pdo->prepare("
                UPDATE referrals 
                SET status = 'completed',
                    referrer_category = ?,
                    payment_id = ?,
                    course_id = ?,
                    course_category = ?,
                    payment_amount = ?,
                    bonus_amount = ?,
                    applied_rule = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtUpd->execute([
                $referrerCategory,
                $paymentId,
                $courseId,
                $courseCategory,
                $paymentAmount,
                $bonusAmount,
                $appliedRule,
                $referral['id']
            ]);

            // 2. Credit referrer's wallet
            $stmtWallet = $pdo->prepare("
                INSERT INTO wallets (user_id, available_balance, pending_balance, total_earned, total_withdrawn)
                VALUES (?, ?, 0, ?, 0)
                ON DUPLICATE KEY UPDATE
                    available_balance = available_balance + VALUES(available_balance),
                    total_earned = total_earned + VALUES(total_earned),
                    updated_at = NOW()
            ");
            $stmtWallet->execute([$referrerId, $bonusAmount, $bonusAmount]);

            // 3. Record transaction in wallet_transactions table
            $curBalStmt = $pdo->prepare("SELECT available_balance FROM wallets WHERE user_id = ?");
            $curBalStmt->execute([$referrerId]);
            $curBal = (float)$curBalStmt->fetchColumn();

            $stmtTx = $pdo->prepare("
                INSERT INTO wallet_transactions (user_id, type, amount, description, balance_after, reference, created_at)
                VALUES (?, 'referral_bonus', ?, ?, ?, ?, NOW())
            ");
            $txDesc = "REFERRAL_BONUS +₦" . number_format($bonusAmount, 2) . " (" . ($course['title'] ?? 'Course Enrollment') . ")";
            $stmtTx->execute([$referrerId, $bonusAmount, $txDesc, $curBal, 'REF-' . $referral['id']]);

            // 4. Notify referrer
            $dashUrl = ($referrer['role'] === 'teacher') ? 'teacher/earnings.php' : 'student/referrals.php';
            $stmtNotif = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, link, created_at)
                VALUES (?, ?, ?, 'referral', ?, NOW())
            ");
            $notifMsg = "Congratulations! You earned a ₦" . number_format($bonusAmount, 2) . " referral bonus for referring a student to " . $course['title'] . ".";
            $stmtNotif->execute([$referrerId, 'Referral Bonus Earned! 🎉', $notifMsg, $dashUrl]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error processing referral payment bonus: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('get_user_wallet')) {
    /**
     * Retrieve wallet metrics for a student or teacher
     */
    function get_user_wallet($userId) {
        $pdo = getDBConnection();
        
        // Ensure wallet row exists
        $pdo->prepare("INSERT IGNORE INTO wallets (user_id, available_balance, pending_balance, total_earned, total_withdrawn) VALUES (?, 0, 0, 0, 0)")
            ->execute([$userId]);

        $stmt = $pdo->prepare("SELECT * FROM wallets WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        // Compute pending bonus from pending referrals
        $stmtPending = $pdo->prepare("
            SELECT COALESCE(SUM(bonus_amount), 0) 
            FROM referrals 
            WHERE referrer_id = ? AND status = 'pending'
        ");
        $stmtPending->execute([$userId]);
        $wallet['pending_balance'] = (float)$stmtPending->fetchColumn();

        return $wallet;
    }
}

if (!function_exists('request_withdrawal')) {
    /**
     * Process student or teacher withdrawal request
     */
    function request_withdrawal($userId, $amount, $bankName, $accountNumber, $accountName) {
        $pdo = getDBConnection();
        $wallet = get_user_wallet($userId);
        
        $amount = (float)$amount;
        if ($amount < 500) {
            return ['success' => false, 'message' => 'Minimum withdrawal amount is ₦500.00'];
        }

        if ($amount > (float)$wallet['available_balance']) {
            return ['success' => false, 'message' => 'Insufficient available balance. Available: ₦' . number_format($wallet['available_balance'], 2)];
        }

        $pdo->beginTransaction();
        try {
            // Deduct available balance
            $stmtUpd = $pdo->prepare("
                UPDATE wallets 
                SET available_balance = available_balance - ?, 
                    total_withdrawn = total_withdrawn + ?
                WHERE user_id = ? AND available_balance >= ?
            ");
            $stmtUpd->execute([$amount, $amount, $userId, $amount]);

            if ($stmtUpd->rowCount() === 0) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Failed to process withdrawal balance deduction.'];
            }

            // Create withdrawal request record
            $stmtIns = $pdo->prepare("
                INSERT INTO withdrawals (user_id, amount, bank_name, account_number, account_name, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmtIns->execute([$userId, $amount, trim($bankName), trim($accountNumber), trim($accountName)]);
            $withdrawalId = (int)$pdo->lastInsertId();

            // Record transaction ledger
            $remBalStmt = $pdo->prepare("SELECT available_balance FROM wallets WHERE user_id = ?");
            $remBalStmt->execute([$userId]);
            $remBal = (float)$remBalStmt->fetchColumn();

            $stmtTx = $pdo->prepare("
                INSERT INTO wallet_transactions (user_id, type, amount, description, balance_after, reference, created_at)
                VALUES (?, 'withdrawal', ?, ?, ?, ?, NOW())
            ");
            $txDesc = "WITHDRAWAL -₦" . number_format($amount, 2) . " (" . trim($bankName) . " " . trim($accountNumber) . ")";
            $stmtTx->execute([$userId, -$amount, $txDesc, $remBal, 'WTH-' . $withdrawalId]);

            $pdo->commit();
            return ['success' => true, 'message' => 'Withdrawal request submitted successfully! Funds will be transferred after admin verification.'];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Error submitting withdrawal: ' . $e->getMessage()];
        }
    }
}

// Automatically capture incoming referral parameter on page load
capture_incoming_referral();

