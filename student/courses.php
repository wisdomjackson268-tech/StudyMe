<?php
/**
 * StudyMe AI Platform — Student Enrolled Courses Directory
 */
require_once dirname(__DIR__) . '/config/main.php';
secure_page(ROLE_STUDENT);

$user = current_user();
$pdo = getDBConnection();
$userId = $user['id'];

// Fetch student ID
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId = $student ? (int)$student['id'] : 0;

$enrolledCourses = [];
if ($studentId) {
    $stmt = $pdo->prepare("
        SELECT c.*, cat.name AS category_name, 
               CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
               e.progress, e.status AS enrollment_status, e.enrolled_at
        FROM enrollments e
        JOIN courses c ON c.id = e.course_id
        LEFT JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN teachers t ON c.teacher_id = t.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE e.student_id = ?
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute([$studentId]);
    $enrolledCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-play-circle-fill text-primary me-2"></i> My Enrolled Courses</h1>
        <p class="text-muted mb-0">Continue learning and tracking your course completion progress.</p>
    </div>
    <a href="<?= url('courses/index.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold" data-feedback="click">
        <i class="bi bi-plus-lg me-1"></i> Browse More Courses
    </a>
</div>

<?php if (!empty($enrolledCourses)): ?>
    <div class="row g-4 mb-5">
        <?php foreach ($enrolledCourses as $c): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden hover-lift d-flex flex-column">
                    <?php $scThumb = function_exists('get_course_thumbnail_url') ? get_course_thumbnail_url($c['thumbnail'] ?? '', 'technology') : ($c['thumbnail'] ?? 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=400&q=80'); ?>
                    <img src="<?= e($scThumb) ?>" 
                         class="card-img-top" style="height: 180px; object-fit: cover;" 
                         alt="<?= e($c['title']) ?>"
                         onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=400&q=80';">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill small px-3">
                                <?= e($c['category_name'] ?: 'General') ?>
                            </span>
                            <span class="badge bg-<?= (float)$c['progress'] >= 100 ? 'success' : 'info' ?> rounded-pill small px-2 py-1">
                                <?= (float)$c['progress'] >= 100 ? 'Completed' : 'In Progress' ?>
                            </span>
                        </div>
                        <h5 class="fw-bold mb-2 line-clamp-2"><?= e($c['title']) ?></h5>
                        <p class="text-muted small mb-3">Instructor: <?= e($c['teacher_name']) ?></p>

                        <!-- Progress Bar -->
                        <div class="mt-auto pt-3">
                            <div class="d-flex justify-content-between small text-muted mb-1 fw-bold">
                                <span>Progress</span>
                                <span><?= number_format($c['progress'], 0) ?>%</span>
                            </div>
                            <div class="progress rounded-pill mb-3" style="height: 8px;">
                                <div class="progress-bar bg-success rounded-pill" role="progressbar" style="width: <?= (float)$c['progress'] ?>%" aria-valuenow="<?= (float)$c['progress'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <a href="<?= url('student/course.php?id=' . (int)$c['id']) ?>" class="btn btn-primary rounded-pill w-100 py-2 fw-bold" data-feedback="click">
                                <i class="bi bi-play-fill me-1"></i> Continue Course
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4">
        <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex mb-3 fs-2 mx-auto">
            <i class="bi bi-journal-album"></i>
        </div>
        <h4 class="fw-bold">No course enrollments yet</h4>
        <p class="text-muted max-w-500 mx-auto mb-4">Explore our catalog of AI-powered courses across software engineering, science, and exam preparation to begin learning.</p>
        <div>
            <a href="<?= url('courses/index.php') ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-bold" data-feedback="click">
                <i class="bi bi-compass me-1"></i> Explore Courses Catalog
            </a>
        </div>
    </div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
