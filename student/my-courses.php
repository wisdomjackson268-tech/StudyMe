<?php
/**
 * StudyMe AI Platform — Student's Enrolled Courses
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';

secure_page(ROLE_STUDENT);
$user = current_user();
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$user['id']]);
$studentRow = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId = $studentRow ? (int)$studentRow['id'] : 0;

$enrollments = [];
if ($studentId) {
    $enrollments = get_student_enrollments($studentId);
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-5">
    <div>
        <h1 class="greeting-title mb-1">My Enrolled Courses</h1>
        <p class="text-muted mb-0">Continue learning and tracking your syllabus progress.</p>
    </div>
    <a href="<?= url('courses/index.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold" data-feedback="click">
        <i class="bi bi-search me-1"></i> Browse More Courses
    </a>
</div>

<?php if (!empty($enrollments)): ?>
    <div class="row g-4 mb-5">
        <?php foreach ($enrollments as $e): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden d-flex flex-column hover-lift">
                    <?php $myThumb = function_exists('get_course_thumbnail_url') ? get_course_thumbnail_url($e['thumbnail'] ?? '', 'technology') : ($e['thumbnail'] ?? 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=400&q=80'); ?>
                    <img src="<?= e($myThumb) ?>" 
                         class="card-img-top" style="height: 160px; object-fit: cover;" 
                         alt="<?= e($e['title']) ?>"
                         onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=400&q=80';">
                    
                    <div class="card-body p-4 d-flex flex-column">
                        <h5 class="fw-bold mb-2 line-clamp-2"><?= e($e['title']) ?></h5>
                        <p class="text-muted small mb-3">Instructor: <?= e($e['teacher_name']) ?></p>
                        
                        <!-- Progress bar -->
                        <div class="mt-auto">
                            <div class="d-flex justify-content-between align-items-center mb-1 small text-muted">
                                <span>Progress</span>
                                <span><?= (int)$e['progress'] ?>%</span>
                            </div>
                            <div class="progress-bar-custom mb-3">
                                <div class="progress-bar-fill" style="width: <?= (int)$e['progress'] ?>%;"></div>
                            </div>
                            
                            <a href="<?= url('student/course.php?id=' . (int)$e['course_id']) ?>" 
                               class="btn btn-primary rounded-pill w-100 fw-bold" 
                               data-feedback="click">
                                Resume Course <i class="bi bi-play-circle-fill ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center mb-5">
        <i class="bi bi-journal-x text-muted" style="font-size: 3rem;"></i>
        <h5 class="fw-bold mt-3 mb-1">No enrolled courses.</h5>
        <p class="text-muted mb-4">You haven't enrolled in any courses yet. Discover our AI-powered courses to start learning.</p>
        <a href="<?= url('courses/index.php') ?>" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow" data-feedback="click">
            Explore Courses
        </a>
    </div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
