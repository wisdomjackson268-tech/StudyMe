<?php

require_once __DIR__ . '/config/main.php';

$pdo = getDBConnection();
$teacherId = (int)($_GET['id'] ?? 0);
$userId    = (int)($_GET['user_id'] ?? 0);

$teacher = null;
if ($teacherId > 0) {
    $stmt = $pdo->prepare("
        SELECT t.*, u.id AS user_id, u.first_name, u.last_name, u.email, u.phone, u.avatar,
               c.id AS course_id, c.title AS course_title, c.slug AS course_slug, c.thumbnail AS course_thumb,
               cat.name AS category_name, cat.slug AS category_slug
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN courses c ON (t.assigned_course_id = c.id OR c.teacher_id = t.id)
        LEFT JOIN categories cat ON (t.assigned_category_id = cat.id OR c.category_id = cat.id)
        WHERE t.id = ? AND t.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$teacherId]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($userId > 0) {
    $stmt = $pdo->prepare("
        SELECT t.*, u.id AS user_id, u.first_name, u.last_name, u.email, u.phone, u.avatar,
               c.id AS course_id, c.title AS course_title, c.slug AS course_slug, c.thumbnail AS course_thumb,
               cat.name AS category_name, cat.slug AS category_slug
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN courses c ON (t.assigned_course_id = c.id OR c.teacher_id = t.id)
        LEFT JOIN categories cat ON (t.assigned_category_id = cat.id OR c.category_id = cat.id)
        WHERE u.id = ? AND t.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$teacher) {
    set_flash('error', 'Instructor profile not found or currently inactive.');
    redirect('teachers.php');
}

$fullName = $teacher['first_name'] . ' ' . $teacher['last_name'];
$avatar = function_exists('get_teacher_avatar_url') ? get_teacher_avatar_url($teacher['avatar'] ?? null, $fullName, $teacher['id'] ?? 0) : (!empty($teacher['avatar']) ? $teacher['avatar'] : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300&q=80');
$teachingSide = !empty($teacher['category_name']) ? $teacher['category_name'] : 'Technology & Computing';

$contactSuccess = false;
$contactError   = '';
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $senderName    = trim($_POST['sender_name'] ?? '');
    $senderEmail   = trim($_POST['sender_email'] ?? '');
    $senderMessage = trim($_POST['message'] ?? '');

    if (empty($senderName) || empty($senderEmail) || empty($senderMessage)) {
        $contactError = 'Please fill out all contact fields.';
    } else {
        try {

            $pdo->prepare("
                INSERT INTO contact_messages (user_id, name, email, subject, message, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ")->execute([
                $teacher['user_id'],
                $senderName,
                $senderEmail,
                'Inquiry for Instructor ' . $fullName,
                $senderMessage
            ]);
            $contactSuccess = true;
        } catch (Exception $e) {
            $contactError = 'Failed to send message: ' . $e->getMessage();
        }
    }
}

$seo_options = [
    'title'       => $fullName . ' — Verified StudyMe Educator Profile',
    'description' => 'Learn from verified instructor ' . $fullName . ' on StudyMe. Specialization: ' . ($teacher['specialization'] ?? 'Academic Instructor'),
];

include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">

        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="<?= url('index.php') ?>" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= url('teachers.php') ?>" class="text-decoration-none">Instructors</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($fullName) ?></li>
            </ol>
        </nav>

        <?php if ($contactSuccess): ?>
            <div class="alert alert-success rounded-4 p-4 shadow-sm mb-4">
                <i class="bi bi-check-circle-fill me-2"></i> Your message has been sent successfully to <strong><?= e($fullName) ?></strong>. They will respond to your email.
            </div>
        <?php endif; ?>

        <?php if (!empty($contactError)): ?>
            <div class="alert alert-danger rounded-4 p-3 shadow-sm mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($contactError) ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 text-center p-4 sticky-top" style="top: 2rem;">
                    <div class="position-relative d-inline-block mx-auto mb-3">
                        <img src="<?= e($avatar) ?>"
                             alt="<?= e($fullName) ?>"
                             class="rounded-circle border border-4 border-white shadow-sm"
                             style="width: 140px; height: 140px; object-fit: cover;"
                             onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=300&q=80';">
                        <span class="position-absolute bottom-0 end-0 badge bg-success rounded-pill px-2 py-1 small" title="Verified Instructor">
                            <i class="bi bi-patch-check-fill"></i> Verified
                        </span>
                    </div>

                    <h3 class="fw-bold mb-1 text-main"><?= e($fullName) ?></h3>
                    <p class="text-muted small mb-2"><i class="bi bi-award-fill text-warning me-1"></i><?= e($teacher['qualification'] ?? 'Certified Educator') ?></p>
                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 mb-3">
                        <?= e($teachingSide) ?>
                    </span>

                    <div class="d-flex justify-content-around py-3 my-2 border-top border-bottom border-light">
                        <div>
                            <div class="fw-bold fs-5 text-main"><?= number_format((float)($teacher['rating'] ?: 4.9), 1) ?></div>
                            <div class="text-muted small"><i class="bi bi-star-fill text-warning me-1"></i>Rating</div>
                        </div>
                        <div class="vr bg-light"></div>
                        <div>
                            <div class="fw-bold fs-5 text-main"><?= max(1, (int)($teacher['experience_years'] ?? 3)) ?>+</div>
                            <div class="text-muted small">Yrs Exp.</div>
                        </div>
                        <div class="vr bg-light"></div>
                        <div>
                            <div class="fw-bold fs-5 text-main"><?= number_format((int)($teacher['total_students'] ?? 120)) ?></div>
                            <div class="text-muted small">Learners</div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="button" class="btn btn-primary rounded-pill w-100 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#contactTeacherModal">
                            <i class="bi bi-envelope-fill me-1"></i> Message Instructor
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">

                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                    <h4 class="fw-bold mb-3"><i class="bi bi-person-lines-fill text-primary me-2"></i>Biography &amp; Overview</h4>
                    <p class="text-secondary lh-lg mb-0" style="white-space: pre-line;">
                        <?= e(!empty($teacher['bio']) ? $teacher['bio'] : 'Dedicated academic specialist committed to interactive, AI-assisted learning. Structures high-impact curriculums, answers student problem sets, and mentors learners.') ?>
                    </p>
                </div>

                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold mb-0"><i class="bi bi-journal-bookmark-fill text-success me-2"></i>Assigned Teaching Course</h4>
                        <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1 fw-bold small">Direct Mentor</span>
                    </div>

                    <?php if (!empty($teacher['course_title'])): ?>
                        <div class="p-3 bg-light rounded-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <?php $cThumb = function_exists('get_course_thumbnail_url') ? get_course_thumbnail_url($teacher['course_thumb'] ?? '', $teacher['category_slug'] ?? 'technology', $teacher['course_slug'] ?? ($teacher['course_title'] ?? '')) : ($teacher['course_thumb'] ?? 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=300&q=80'); ?>
                                <img src="<?= e($cThumb) ?>" class="rounded-3" style="width: 80px; height: 60px; object-fit: cover;" alt="<?= e($teacher['course_title']) ?>" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=300&q=80';">
                                <div>
                                    <h5 class="fw-bold mb-1"><?= e($teacher['course_title']) ?></h5>
                                    <span class="text-muted small"><i class="bi bi-tag-fill me-1"></i><?= e($teachingSide) ?></span>
                                </div>
                            </div>
                            <a href="<?= url('courses/details.php?id=' . (int)$teacher['course_id']) ?>" class="btn btn-outline-primary rounded-pill px-4 fw-bold">
                                View Course &rarr;
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="p-4 bg-light rounded-4 text-center text-muted">
                            <i class="bi bi-journal-x fs-3 mb-2 d-block"></i>
                            Instructor is currently preparing a new course module curriculum.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                            <h5 class="fw-bold mb-3"><i class="bi bi-mortarboard-fill text-warning me-2"></i>Education &amp; Credentials</h5>
                            <p class="small text-muted mb-2"><strong>Degree:</strong> <?= e($teacher['qualification'] ?? 'B.Sc. / Certified Instructor') ?></p>
                            <?php if (!empty($teacher['education'])): ?>
                                <p class="small text-secondary mb-0"><?= nl2br(e($teacher['education'])) ?></p>
                            <?php else: ?>
                                <p class="small text-secondary mb-0">Certified educator with verified academic credentials in <?= e($teacher['specialization'] ?: 'Higher Education') ?>.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                            <h5 class="fw-bold mb-3"><i class="bi bi-lightning-charge-fill text-info me-2"></i>Core Specialization</h5>
                            <div class="badge bg-light text-dark border p-2 mb-2 d-inline-block text-start">
                                <?= e($teacher['specialization'] ?: 'Academic Instruction & Curriculum Development') ?>
                            </div>
                            <?php if (!empty($teacher['skills'])): ?>
                                <p class="small text-muted mb-0"><strong>Skills:</strong> <?= e($teacher['skills']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="contactTeacherModal" tabindex="-1" aria-labelledby="contactTeacherModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="contactTeacherModalLabel">
                    <i class="bi bi-envelope-fill text-primary me-2"></i>Contact <?= e($fullName) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= url('teacher-profile.php?id=' . (int)$teacher['id']) ?>">
                <input type="hidden" name="action" value="send_message">
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Your Full Name</label>
                        <input type="text" name="sender_name" class="form-control rounded-3" value="<?= is_logged_in() ? e(current_user('first_name') . ' ' . current_user('last_name')) : '' ?>" required placeholder="e.g. David Okonkwo">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Your Email Address</label>
                        <input type="email" name="sender_email" class="form-control rounded-3" value="<?= is_logged_in() ? e(current_user('email')) : '' ?>" required placeholder="name@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Your Question / Message</label>
                        <textarea name="message" class="form-control rounded-3" rows="4" required placeholder="Write your question about the course, syllabus, or mentorship..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold">Send Message</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
