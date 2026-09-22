<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/notifications.php';

secure_page(ROLE_STUDENT);

$user = current_user();
$userId = (int)($user['id'] ?? 0);

// Helper for relative time format
if (!function_exists('studyme_format_time_ago')) {
    function studyme_format_time_ago($timestamp) {
        $time = is_numeric($timestamp) ? (int)$timestamp : strtotime($timestamp);
        if (!$time) return date('M j, Y');
        $diff = time() - $time;
        if ($diff < 45) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $mins = max(1, (int)floor($diff / 60));
            return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = (int)floor($diff / 3600);
            return $hours . ' hr' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 172800) {
            return 'Yesterday at ' . date('g:i a', $time);
        } elseif ($diff < 604800) {
            $days = (int)floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('M j, Y \a\t g:i a', $time);
        }
    }
}

// Helper for type metadata
if (!function_exists('studyme_get_notification_meta')) {
    function studyme_get_notification_meta($type) {
        $t = strtolower(trim((string)$type));
        switch ($t) {
            case 'live_class':
            case 'live':
            case 'stream':
                return [
                    'label' => 'Live Class',
                    'icon' => 'bi-broadcast',
                    'color_class' => 'type-live',
                    'category' => 'live'
                ];
            case 'academic':
            case 'discussion':
            case 'question':
            case 'reply':
                return [
                    'label' => 'Academic Q&A',
                    'icon' => 'bi-chat-left-dots-fill',
                    'color_class' => 'type-academic',
                    'category' => 'academic'
                ];
            case 'course':
            case 'lesson':
            case 'enrollment':
                return [
                    'label' => 'Course Update',
                    'icon' => 'bi-book-half',
                    'color_class' => 'type-course',
                    'category' => 'academic'
                ];
            case 'quiz':
            case 'exam':
            case 'grade':
            case 'result':
                return [
                    'label' => 'Quiz & Score',
                    'icon' => 'bi-award-fill',
                    'color_class' => 'type-quiz',
                    'category' => 'academic'
                ];
            case 'announcement':
            case 'broadcast':
                return [
                    'label' => 'Announcement',
                    'icon' => 'bi-megaphone-fill',
                    'color_class' => 'type-announcement',
                    'category' => 'announcement'
                ];
            case 'system':
            case 'security':
            case 'account':
                return [
                    'label' => 'System',
                    'icon' => 'bi-shield-check',
                    'color_class' => 'type-system',
                    'category' => 'system'
                ];
            default:
                return [
                    'label' => 'Alert',
                    'icon' => 'bi-bell-fill',
                    'color_class' => 'type-general',
                    'category' => 'general'
                ];
        }
    }
}

// Handle open link with auto-read
if (isset($_GET['open_id'])) {
    $notifId = (int)$_GET['open_id'];
    $notif = get_notification_by_id($notifId, $userId);
    if ($notif) {
        mark_notification_read($notifId, $userId);
        if (!empty($notif['link'])) {
            redirect($notif['link']);
        }
    }
    redirect('student/notifications.php');
}

// Handle POST actions (supports AJAX & standard form submits)
if (is_post()) {
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_POST['ajax']);

    if (isset($_POST['mark_all_read'])) {
        mark_all_notifications_read($userId);
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'unread_count' => 0]);
            exit;
        }
        set_flash('success', 'All notifications marked as read.');
        redirect('student/notifications.php');
    }

    if (isset($_POST['mark_id'])) {
        $id = (int)$_POST['mark_id'];
        mark_notification_read($id, $userId);
        $unread = count_unread_notifications($userId);
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'unread_count' => $unread]);
            exit;
        }
        set_flash('success', 'Notification marked as read.');
        redirect('student/notifications.php');
    }

    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        delete_notification($id, $userId);
        $unread = count_unread_notifications($userId);
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'unread_count' => $unread]);
            exit;
        }
        set_flash('success', 'Notification removed.');
        redirect('student/notifications.php');
    }

    if (isset($_POST['delete_all_read'])) {
        delete_all_read_notifications($userId);
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        set_flash('success', 'All read notifications cleared.');
        redirect('student/notifications.php');
    }
}

$notifications = get_user_notifications($userId, 100);
$unreadCount = count_unread_notifications($userId);
$totalCount = count($notifications);
$readCount = max(0, $totalCount - $unreadCount);

// Categorization counts for tabs
$categoryCounts = [
    'all' => $totalCount,
    'unread' => $unreadCount,
    'live' => 0,
    'academic' => 0,
    'announcement' => 0,
    'system' => 0,
];

foreach ($notifications as $n) {
    $meta = studyme_get_notification_meta($n['type'] ?? '');
    $cat = $meta['category'];
    if (isset($categoryCounts[$cat])) {
        $categoryCounts[$cat]++;
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<!-- Scoped Custom CSS for Notifications Inbox -->
<style>
/* Base Notifications Container */
.notif-container {
    max-width: 1140px;
    margin: 0 auto;
}

/* Header Banner Card */
.notif-hero-card {
    background: linear-gradient(135deg, rgba(30, 64, 175, 0.08) 0%, rgba(67, 56, 202, 0.04) 50%, rgba(248, 250, 252, 0.9) 100%);
    border: 1px solid var(--border-color);
    border-radius: 1.25rem;
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(8px);
}

[data-bs-theme="dark"] .notif-hero-card,
body.dark .notif-hero-card {
    background: linear-gradient(135deg, rgba(30, 64, 175, 0.2) 0%, rgba(15, 23, 42, 0.8) 100%);
    border-color: rgba(255, 255, 255, 0.08);
}

.notif-hero-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.45rem;
    background: linear-gradient(135deg, #1E40AF 0%, #3B82F6 100%);
    color: #ffffff;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
    flex-shrink: 0;
}

/* Stat Cards */
.notif-stat-card {
    background: var(--bg-card, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 1rem;
    padding: 1rem 1.15rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    height: 100%;
    position: relative;
}

.notif-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
    border-color: rgba(37, 99, 235, 0.3);
}

.notif-stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.notif-stat-icon.total { background: rgba(37, 99, 235, 0.12); color: #2563eb; }
.notif-stat-icon.unread { background: rgba(239, 68, 68, 0.12); color: #ef4444; }
.notif-stat-icon.read { background: rgba(16, 185, 129, 0.12); color: #10b981; }
.notif-stat-icon.live { background: rgba(245, 158, 11, 0.12); color: #d97706; }

/* Filter Tabs and Search Bar */
.notif-filter-wrapper {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    overflow-x: auto;
    padding-bottom: 0.25rem;
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.notif-filter-wrapper::-webkit-scrollbar {
    display: none;
}

.notif-filter-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.95rem;
    font-size: 0.825rem;
    font-weight: 600;
    border-radius: 9999px;
    border: 1px solid var(--border-color);
    background: var(--bg-card, #ffffff);
    color: var(--text-muted, #64748b);
    white-space: nowrap;
    transition: all 0.2s ease;
    cursor: pointer;
    text-decoration: none;
}

.notif-filter-btn:hover {
    color: var(--text-main, #0f172a);
    border-color: #cbd5e1;
    background: var(--bg-surface-alt, #f8fafc);
}

.notif-filter-btn.active {
    background: #1e40af;
    color: #ffffff;
    border-color: #1e40af;
    box-shadow: 0 2px 8px rgba(30, 64, 175, 0.28);
}

.notif-filter-btn .badge {
    font-size: 0.7rem;
    padding: 0.2em 0.55em;
}

.notif-filter-btn.active .badge {
    background: rgba(255, 255, 255, 0.25) !important;
    color: #ffffff !important;
}

/* Search Box */
.notif-search-box {
    position: relative;
    min-width: 220px;
}

.notif-search-box .bi-search {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted, #94a3b8);
    font-size: 0.875rem;
    pointer-events: none;
}

.notif-search-input {
    padding-left: 36px;
    padding-right: 32px;
    height: 38px;
    border-radius: 9999px;
    font-size: 0.85rem;
    border: 1px solid var(--border-color);
    background: var(--bg-card, #ffffff);
    color: var(--text-main, #0f172a);
    transition: all 0.2s ease;
}

.notif-search-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.18);
    outline: none;
}

.notif-search-clear {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: var(--text-muted, #94a3b8);
    font-size: 0.85rem;
    display: none;
}

/* Notification Items Card Container */
.notif-main-card {
    background: var(--bg-card, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 1.25rem;
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

/* Individual Notification Item */
.notif-item {
    padding: 1.15rem 1.25rem;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    transition: background 0.18s ease, transform 0.18s ease, opacity 0.25s ease;
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    background: transparent;
}

.notif-item:last-child {
    border-bottom: none;
}

.notif-item:hover {
    background: rgba(248, 250, 252, 0.85);
}

[data-bs-theme="dark"] .notif-item:hover,
body.dark .notif-item:hover {
    background: rgba(30, 41, 59, 0.45);
}

/* Unread Notification Styling */
.notif-item.is-unread {
    background: rgba(37, 99, 235, 0.035);
    border-left: 4px solid #2563eb;
}

[data-bs-theme="dark"] .notif-item.is-unread,
body.dark .notif-item.is-unread {
    background: rgba(37, 99, 235, 0.09);
    border-left: 4px solid #3b82f6;
}

/* Type Icons with Curated Theme Gradients */
.notif-icon-bubble {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
    transition: transform 0.2s ease;
}

.notif-item:hover .notif-icon-bubble {
    transform: scale(1.05);
}

.type-live {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(244, 63, 94, 0.25));
    color: #e11d48;
}
.type-academic {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(79, 70, 229, 0.25));
    color: #4f46e5;
}
.type-course {
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.15), rgba(30, 64, 175, 0.25));
    color: #2563eb;
}
.type-quiz {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.25));
    color: #059669;
}
.type-announcement {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.25));
    color: #d97706;
}
.type-system {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(124, 58, 237, 0.25));
    color: #7c3aed;
}
.type-general {
    background: linear-gradient(135deg, rgba(14, 165, 233, 0.15), rgba(2, 132, 199, 0.25));
    color: #0284c7;
}

/* Pulsing dot for unread status */
.unread-pulse {
    display: inline-block;
    width: 8px;
    height: 8px;
    background-color: #2563eb;
    border-radius: 50%;
    margin-right: 6px;
    box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.7);
    animation: pulse-ring 2s infinite cubic-bezier(0.66, 0, 0, 1);
}

@keyframes pulse-ring {
    0% {
        box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.6);
    }
    70% {
        box-shadow: 0 0 0 6px rgba(37, 99, 235, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(37, 99, 235, 0);
    }
}

/* Content Area */
.notif-content-area {
    flex-grow: 1;
    min-width: 0;
}

.notif-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-main, #0f172a);
    margin-bottom: 0.35rem;
    line-height: 1.4;
    word-break: break-word;
}

.notif-message {
    font-size: 0.875rem;
    color: var(--text-muted, #475569);
    margin-bottom: 0.5rem;
    line-height: 1.55;
    word-break: break-word;
}

[data-bs-theme="dark"] .notif-message,
body.dark .notif-message {
    color: #94a3b8;
}

.notif-meta-tags {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.65rem;
    font-size: 0.775rem;
    color: var(--text-light, #94a3b8);
}

.notif-badge {
    padding: 0.2rem 0.6rem;
    border-radius: 6px;
    font-size: 0.725rem;
    font-weight: 600;
    text-transform: capitalize;
}

/* Actions Column / Group */
.notif-actions-group {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-shrink: 0;
}

.notif-action-btn {
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid var(--border-color, #e2e8f0);
    background: var(--bg-card, #ffffff);
    color: var(--text-muted, #64748b);
    transition: all 0.18s ease;
    text-decoration: none;
    cursor: pointer;
    font-size: 0.85rem;
    padding: 0;
}

.notif-action-btn:hover {
    background: var(--bg-surface-alt, #f1f5f9);
    color: var(--text-main, #0f172a);
    border-color: #cbd5e1;
}

.notif-action-btn.btn-open {
    width: auto;
    padding: 0 0.75rem;
    font-size: 0.8rem;
    font-weight: 600;
    gap: 0.35rem;
    background: rgba(37, 99, 235, 0.08);
    border-color: rgba(37, 99, 235, 0.25);
    color: #2563eb;
}

.notif-action-btn.btn-open:hover {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
}

.notif-action-btn.btn-read:hover {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
    border-color: rgba(16, 185, 129, 0.3);
}

.notif-action-btn.btn-delete:hover {
    background: rgba(239, 68, 68, 0.12);
    color: #dc2626;
    border-color: rgba(239, 68, 68, 0.3);
}

/* Empty State */
.notif-empty-state {
    padding: 4rem 1.5rem;
    text-align: center;
}

.notif-empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    background: rgba(37, 99, 235, 0.08);
    color: #3b82f6;
    border: 2px dashed rgba(37, 99, 235, 0.25);
}

/* Mobile & iPad Responsive Overrides */
@media (max-width: 767.98px) {
    .notif-hero-card {
        padding: 1.25rem 1rem;
    }
    
    .notif-hero-header {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 1rem;
    }

    .notif-hero-actions {
        width: 100%;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .notif-hero-actions .btn {
        flex: 1 1 auto;
        min-height: 42px;
        justify-content: center;
    }

    .notif-item {
        padding: 1rem;
        flex-direction: column;
        gap: 0.85rem;
    }

    .notif-item-top {
        display: flex;
        align-items: flex-start;
        gap: 0.85rem;
        width: 100%;
    }

    .notif-actions-group {
        width: 100%;
        justify-content: flex-end;
        padding-top: 0.5rem;
        border-top: 1px dashed var(--border-subtle, #f1f5f9);
    }

    .notif-action-btn {
        min-width: 40px;
        min-height: 40px;
    }

    .notif-action-btn.btn-open {
        min-height: 40px;
        flex: 1;
        justify-content: center;
    }

    .notif-search-box {
        width: 100%;
    }
}

/* iPad & Tablet Landscape / Portrait Adjustments */
@media (min-width: 768px) and (max-width: 1024px) {
    .notif-container {
        padding: 0 0.5rem;
    }
    
    .notif-hero-card {
        padding: 1.5rem;
    }

    .notif-item {
        padding: 1.15rem 1.25rem;
    }
}
</style>

<div class="notif-container mb-5">

    <!-- Hero / Header Section -->
    <div class="notif-hero-card mb-4 shadow-sm">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 notif-hero-header">
            <div class="d-flex align-items-center gap-3">
                <div class="notif-hero-icon">
                    <i class="bi bi-bell-fill"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h1 class="h3 fw-bold mb-0 text-main">Notifications Inbox</h1>
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge bg-danger rounded-pill px-2 py-1 fw-bold" id="headerUnreadPill">
                                <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem; vertical-align: middle;"></i>
                                <?= $unreadCount ?> New
                            </span>
                        <?php else: ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 fw-semibold">
                                <i class="bi bi-check2-all me-1"></i> All caught up
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted small mb-0 mt-1">
                        System updates, live stream alerts, instructor answers, and quiz results in real time.
                    </p>
                </div>
            </div>

            <!-- Top Action Buttons -->
            <div class="d-flex align-items-center gap-2 flex-wrap notif-hero-actions">
                <?php if ($unreadCount > 0): ?>
                    <form method="POST" action="<?= url('student/notifications.php') ?>" class="d-inline" id="formMarkAllRead">
                        <input type="hidden" name="mark_all_read" value="1">
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill fw-bold px-3 py-2 d-inline-flex align-items-center gap-2 shadow-sm" data-feedback="click">
                            <i class="bi bi-check2-all fs-6"></i>
                            <span>Mark All Read</span>
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($readCount > 0): ?>
                    <form method="POST" action="<?= url('student/notifications.php') ?>" class="d-inline" onsubmit="return confirm('Clear all read notifications?');">
                        <input type="hidden" name="delete_all_read" value="1">
                        <button type="submit" class="btn btn-outline-secondary btn-sm rounded-pill fw-semibold px-3 py-2 d-inline-flex align-items-center gap-2" title="Delete all read notifications">
                            <i class="bi bi-trash3 fs-6"></i>
                            <span>Clear Read</span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stat Row -->
        <div class="row g-2 g-sm-3 mt-3 pt-3 border-top border-subtle">
            <div class="col-6 col-md-3">
                <div class="notif-stat-card">
                    <div class="notif-stat-icon total">
                        <i class="bi bi-inbox-fill"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-5 text-main lh-1" id="statTotalCount"><?= $totalCount ?></div>
                        <div class="text-muted small" style="font-size: 0.775rem;">Total Alerts</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="notif-stat-card">
                    <div class="notif-stat-icon unread">
                        <i class="bi bi-envelope-open-fill"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-5 text-danger lh-1" id="statUnreadCount"><?= $unreadCount ?></div>
                        <div class="text-muted small" style="font-size: 0.775rem;">Unread</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="notif-stat-card">
                    <div class="notif-stat-icon read">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-5 text-success lh-1" id="statReadCount"><?= $readCount ?></div>
                        <div class="text-muted small" style="font-size: 0.775rem;">Read</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="notif-stat-card">
                    <div class="notif-stat-icon live">
                        <i class="bi bi-broadcast"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-5 text-warning lh-1"><?= $categoryCounts['live'] + $categoryCounts['academic'] ?></div>
                        <div class="text-muted small" style="font-size: 0.775rem;">Live &amp; Academic</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Pills & Search Bar -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
        <!-- Filter Tabs -->
        <div class="notif-filter-wrapper flex-grow-1">
            <button type="button" class="notif-filter-btn active" data-filter="all">
                <i class="bi bi-grid-fill"></i> All
                <span class="badge bg-secondary-subtle text-secondary rounded-pill"><?= $totalCount ?></span>
            </button>

            <button type="button" class="notif-filter-btn" data-filter="unread">
                <i class="bi bi-envelope-fill"></i> Unread
                <span class="badge bg-danger-subtle text-danger rounded-pill" id="filterUnreadBadge"><?= $unreadCount ?></span>
            </button>

            <button type="button" class="notif-filter-btn" data-filter="live">
                <i class="bi bi-broadcast"></i> Live Classes
                <?php if ($categoryCounts['live'] > 0): ?>
                    <span class="badge bg-warning-subtle text-warning rounded-pill"><?= $categoryCounts['live'] ?></span>
                <?php endif; ?>
            </button>

            <button type="button" class="notif-filter-btn" data-filter="academic">
                <i class="bi bi-mortarboard-fill"></i> Academic &amp; Q&amp;A
                <?php if ($categoryCounts['academic'] > 0): ?>
                    <span class="badge bg-primary-subtle text-primary rounded-pill"><?= $categoryCounts['academic'] ?></span>
                <?php endif; ?>
            </button>

            <button type="button" class="notif-filter-btn" data-filter="announcement">
                <i class="bi bi-megaphone-fill"></i> Announcements
                <?php if ($categoryCounts['announcement'] > 0): ?>
                    <span class="badge bg-info-subtle text-info rounded-pill"><?= $categoryCounts['announcement'] ?></span>
                <?php endif; ?>
            </button>

            <button type="button" class="notif-filter-btn" data-filter="system">
                <i class="bi bi-gear-fill"></i> System
            </button>
        </div>

        <!-- Instant Keyword Search -->
        <div class="notif-search-box">
            <i class="bi bi-search"></i>
            <input type="text" id="notifSearchInput" class="form-control notif-search-input" placeholder="Search notifications..." autocomplete="off">
            <i class="bi bi-x-circle-fill notif-search-clear" id="notifSearchClear" title="Clear search"></i>
        </div>
    </div>

    <!-- Notification Cards Container -->
    <div class="notif-main-card">
        <?php if (!empty($notifications)): ?>
            <div id="notifListContainer">
                <?php foreach ($notifications as $n):
                    $notifId = (int)$n['id'];
                    $isRead = !empty($n['is_read']);
                    $meta = studyme_get_notification_meta($n['type'] ?? '');
                    $timeAgo = studyme_format_time_ago($n['created_at']);
                    $hasLink = !empty($n['link']);
                ?>
                    <div class="notif-item <?= $isRead ? 'is-read' : 'is-unread' ?>"
                         id="notif-item-<?= $notifId ?>"
                         data-id="<?= $notifId ?>"
                         data-status="<?= $isRead ? 'read' : 'unread' ?>"
                         data-category="<?= e($meta['category']) ?>">
                        
                        <!-- Top portion (Icon + Text) -->
                        <div class="notif-item-top flex-grow-1">
                            <div class="notif-icon-bubble <?= e($meta['color_class']) ?>">
                                <i class="bi <?= e($meta['icon']) ?>"></i>
                            </div>

                            <div class="notif-content-area">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                                    <h6 class="notif-title mb-0">
                                        <?php if (!$isRead): ?>
                                            <span class="unread-pulse" title="Unread notification"></span>
                                        <?php endif; ?>
                                        <?= e($n['title']) ?>
                                    </h6>
                                    
                                    <div class="notif-meta-tags d-none d-md-flex">
                                        <span class="badge notif-badge <?= e($meta['color_class']) ?>">
                                            <?= e($meta['label']) ?>
                                        </span>
                                        <span class="text-muted" title="<?= date('M j, Y - g:i a', strtotime($n['created_at'])) ?>">
                                            <i class="bi bi-clock me-1"></i><?= e($timeAgo) ?>
                                        </span>
                                    </div>
                                </div>

                                <p class="notif-message mb-2">
                                    <?= nl2br(e($n['message'])) ?>
                                </p>

                                <div class="notif-meta-tags d-md-none mb-1">
                                    <span class="badge notif-badge <?= e($meta['color_class']) ?>">
                                        <?= e($meta['label']) ?>
                                    </span>
                                    <span class="text-muted" title="<?= date('M j, Y - g:i a', strtotime($n['created_at'])) ?>">
                                        <i class="bi bi-clock me-1"></i><?= e($timeAgo) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Action buttons -->
                        <div class="notif-actions-group">
                            <?php if ($hasLink): ?>
                                <a href="<?= url('student/notifications.php?open_id=' . $notifId) ?>" class="notif-action-btn btn-open" title="Open related link">
                                    <span>Open</span>
                                    <i class="bi bi-arrow-right-short fs-6"></i>
                                </a>
                            <?php endif; ?>

                            <?php if (!$isRead): ?>
                                <button type="button" 
                                        class="notif-action-btn btn-read js-mark-read" 
                                        data-id="<?= $notifId ?>" 
                                        title="Mark as read"
                                        aria-label="Mark as read">
                                    <i class="bi bi-check2"></i>
                                </button>
                            <?php endif; ?>

                            <button type="button" 
                                    class="notif-action-btn btn-delete js-delete-notif" 
                                    data-id="<?= $notifId ?>" 
                                    title="Delete notification"
                                    aria-label="Delete notification">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Empty Search Results Placeholder (shown via JS when filter/search yields 0) -->
            <div id="notifNoSearchResults" class="notif-empty-state d-none">
                <div class="notif-empty-icon">
                    <i class="bi bi-search"></i>
                </div>
                <h5 class="fw-bold text-main">No notifications found</h5>
                <p class="text-muted small mb-3">No alerts match your current filter or search criteria.</p>
                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold" id="btnResetSearch">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Clear Filters
                </button>
            </div>

        <?php else: ?>
            <!-- Completely empty state -->
            <div class="notif-empty-state">
                <div class="notif-empty-icon">
                    <i class="bi bi-bell-slash"></i>
                </div>
                <h4 class="fw-bold text-main mb-2">You're all caught up!</h4>
                <p class="text-muted small mb-4 mx-auto" style="max-width: 440px;">
                    There are no notifications in your inbox right now. When instructors post course materials, reply to your questions, or initiate live streams, you'll see them right here.
                </p>
                <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
                    <a href="<?= url('student/dashboard.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                        <i class="bi bi-house-door me-1"></i> Student Dashboard
                    </a>
                    <a href="<?= url('courses/index.php') ?>" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">
                        <i class="bi bi-compass me-1"></i> Explore Courses
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Interactive Client-side Scripting -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.notif-filter-btn');
    const searchInput = document.getElementById('notifSearchInput');
    const searchClear = document.getElementById('notifSearchClear');
    const items = document.querySelectorAll('.notif-item');
    const noResults = document.getElementById('notifNoSearchResults');
    const resetBtn = document.getElementById('btnResetSearch');

    let activeFilter = 'all';
    let searchQuery = '';

    function applyFilters() {
        let visibleCount = 0;
        const q = searchQuery.toLowerCase().trim();

        items.forEach(function(item) {
            const status = item.getAttribute('data-status');
            const category = item.getAttribute('data-category');
            const text = item.textContent.toLowerCase();

            let matchesCategory = false;
            if (activeFilter === 'all') {
                matchesCategory = true;
            } else if (activeFilter === 'unread') {
                matchesCategory = (status === 'unread');
            } else {
                matchesCategory = (category === activeFilter);
            }

            let matchesSearch = (!q || text.indexOf(q) !== -1);

            if (matchesCategory && matchesSearch) {
                item.style.display = 'flex';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        if (noResults) {
            if (visibleCount === 0 && items.length > 0) {
                noResults.classList.remove('d-none');
            } else {
                noResults.classList.add('d-none');
            }
        }
    }

    // Filter Buttons Click
    filterButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            activeFilter = this.getAttribute('data-filter') || 'all';
            applyFilters();
        });
    });

    // Search Input
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            searchQuery = this.value;
            if (searchClear) {
                searchClear.style.display = searchQuery ? 'block' : 'none';
            }
            applyFilters();
        });
    }

    if (searchClear) {
        searchClear.addEventListener('click', function() {
            if (searchInput) {
                searchInput.value = '';
                searchQuery = '';
                this.style.display = 'none';
                applyFilters();
                searchInput.focus();
            }
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            if (searchInput) {
                searchInput.value = '';
                searchQuery = '';
            }
            if (searchClear) {
                searchClear.style.display = 'none';
            }
            activeFilter = 'all';
            filterButtons.forEach(b => {
                if (b.getAttribute('data-filter') === 'all') {
                    b.classList.add('active');
                } else {
                    b.classList.remove('active');
                }
            });
            applyFilters();
        });
    }

    // Helper: update counters after mark read or delete
    function updateCounters(unreadCount) {
        const statUnread = document.getElementById('statUnreadCount');
        const statRead = document.getElementById('statReadCount');
        const badgeUnread = document.getElementById('filterUnreadBadge');
        const headerPill = document.getElementById('headerUnreadPill');

        if (statUnread) statUnread.textContent = unreadCount;
        if (badgeUnread) badgeUnread.textContent = unreadCount;

        const currentTotal = parseInt(document.getElementById('statTotalCount')?.textContent || '0', 10);
        if (statRead) statRead.textContent = Math.max(0, currentTotal - unreadCount);

        if (headerPill) {
            if (unreadCount <= 0) {
                headerPill.style.display = 'none';
            } else {
                headerPill.innerHTML = '<i class="bi bi-circle-fill me-1" style="font-size: 0.5rem; vertical-align: middle;"></i> ' + unreadCount + ' New';
            }
        }
    }

    // Single Notification Mark as Read
    document.querySelectorAll('.js-mark-read').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const item = document.getElementById('notif-item-' + id);
            const buttonEl = this;

            buttonEl.disabled = true;
            buttonEl.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:12px;height:12px;"></span>';

            const formData = new FormData();
            formData.append('mark_id', id);
            formData.append('ajax', '1');

            fetch('<?= url("student/notifications.php") ?>', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (item) {
                        item.classList.remove('is-unread');
                        item.classList.add('is-read');
                        item.setAttribute('data-status', 'read');
                        const pulse = item.querySelector('.unread-pulse');
                        if (pulse) pulse.remove();
                    }
                    buttonEl.remove();
                    updateCounters(data.unread_count);
                } else {
                    buttonEl.disabled = false;
                    buttonEl.innerHTML = '<i class="bi bi-check2"></i>';
                }
            })
            .catch(() => {
                buttonEl.disabled = false;
                buttonEl.innerHTML = '<i class="bi bi-check2"></i>';
            });
        });
    });

    // Single Notification Delete
    document.querySelectorAll('.js-delete-notif').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const item = document.getElementById('notif-item-' + id);
            const buttonEl = this;

            if (!confirm('Remove this notification?')) {
                return;
            }

            buttonEl.disabled = true;
            buttonEl.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:12px;height:12px;"></span>';

            const formData = new FormData();
            formData.append('delete_id', id);
            formData.append('ajax', '1');

            fetch('<?= url("student/notifications.php") ?>', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (item) {
                        item.style.transition = 'opacity 0.2s ease, transform 0.2s ease, max-height 0.3s ease';
                        item.style.opacity = '0';
                        item.style.transform = 'translateX(20px)';
                        setTimeout(function() {
                            item.remove();
                            // Update total stat
                            const totalEl = document.getElementById('statTotalCount');
                            if (totalEl) {
                                const currentTotal = parseInt(totalEl.textContent || '0', 10);
                                totalEl.textContent = Math.max(0, currentTotal - 1);
                            }
                            updateCounters(data.unread_count);
                            applyFilters();
                        }, 220);
                    }
                } else {
                    buttonEl.disabled = false;
                    buttonEl.innerHTML = '<i class="bi bi-trash3"></i>';
                }
            })
            .catch(() => {
                buttonEl.disabled = false;
                buttonEl.innerHTML = '<i class="bi bi-trash3"></i>';
            });
        });
    });
});
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
