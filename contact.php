<?php
/**
 * StudyMe AI Platform — Contact Us Page
 */
require_once __DIR__ . '/config/main.php';

$errors = [];
$successMessage = '';
$name = '';
$email = '';
$subject = '';
$message = '';

if (is_post()) {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name)) {
        $errors[] = 'Please provide your name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (empty($subject)) {
        $errors[] = 'Please provide a subject.';
    }
    if (strlen($message) < 10) {
        $errors[] = 'Message must be at least 10 characters long.';
    }

    if (empty($errors)) {
        $pdo = getDBConnection();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO contact_messages (name, email, subject, message, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $email, $subject, $message]);

            $successMessage = 'Thank you, ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '! Your message has been received. Our support team will review your inquiry and respond to ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . ' shortly.';
            
            // Reset form variables
            $name = $email = $subject = $message = '';
            $_SESSION['auth_success_vibrate'] = true;
        } catch (Exception $e) {
            $errors[] = 'Failed to submit message. Please try again or reach out directly via email.';
            error_log("Contact form error: " . $e->getMessage());
        }
    }
}

include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <!-- Header -->
        <div class="text-center max-w-700 mx-auto mb-5 animate-fade-in">
            <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex mb-3">
                <i class="bi bi-envelope-paper-fill fs-1"></i>
            </div>
            <h1 class="display-5 fw-bold mb-2">Get in Touch</h1>
            <p class="lead text-muted">Have questions about our AI Tutors, courses, teacher applications, or platform features? We're here to help.</p>
        </div>

        <div class="row g-5 justify-content-center">
            <!-- Contact Details Column -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 h-100">
                    <h3 class="fw-bold mb-4">Contact Information</h3>
                    <div class="d-flex gap-3 mb-4">
                        <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle fs-4 h-100">
                            <i class="bi bi-envelope-fill"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Email Support</h6>
                            <p class="text-muted small mb-0">
                                <a href="mailto:support@studyme.ng" class="text-decoration-none">studyme910@gmail.com</a><br>
                                <a href="mailto:instructors@studyme.ng" class="text-decoration-none">instructors@studyme.com</a>
                            </p>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-4">
                        <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-circle fs-4 h-100">
                            <i class="bi bi-telephone-fill"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Phone &amp; WhatsApp</h6>
                            <p class="text-muted small mb-0">+234 9026849170<br>Mon &ndash; Fri, 8:00 AM &ndash; 6:00 PM WAT</p>
                        </div>
                    </div>

                    <div class="mt-auto pt-4 border-top">
                        <h6 class="fw-bold mb-3">Quick Navigation</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="<?= url('faq.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="bi bi-question-circle me-1"></i> Read FAQ</a>
                            <a href="<?= url('help.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="bi bi-life-preserver me-1"></i> Help Center</a>
                            <a href="<?= url('community.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="bi bi-people me-1"></i> Community Hub</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Column -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5">
                    <h3 class="fw-bold mb-4">Send Us a Message</h3>

                    <?php if (!empty($successMessage)): ?>
                        <div class="alert alert-success alert-dismissible fade show rounded-4 p-4 mb-4 shadow-sm" role="alert">
                            <div class="d-flex align-items-center gap-3">
                                <i class="bi bi-check-circle-fill fs-2 text-success"></i>
                                <div>
                                    <h5 class="alert-heading fw-bold mb-1">Message Sent!</h5>
                                    <p class="mb-0 small"><?= e($successMessage) ?></p>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger rounded-4 p-3 mb-4 shadow-sm" role="alert">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-exclamation-octagon-fill text-danger fs-5"></i>
                                <span class="fw-bold">Please correct the following:</span>
                            </div>
                            <ul class="mb-0 small ps-3">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= e($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="<?= url('contact.php') ?>" method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="contactName" class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="contactName" class="form-control py-2 rounded-3" placeholder="e.g. Alex Johnson" value="<?= e($name) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="contactEmail" class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="contactEmail" class="form-control py-2 rounded-3" placeholder="name@example.com" value="<?= e($email) ?>" required>
                            </div>
                            <div class="col-12">
                                <label for="contactSubject" class="form-label fw-semibold small">Subject <span class="text-danger">*</span></label>
                                <input type="text" name="subject" id="contactSubject" class="form-control py-2 rounded-3" placeholder="e.g. Question about AI Tutor or Course Enrollment" value="<?= e($subject) ?>" required>
                            </div>
                            <div class="col-12">
                                <label for="contactMessage" class="form-label fw-semibold small">Message <span class="text-danger">*</span></label>
                                <textarea name="message" id="contactMessage" rows="5" class="form-control rounded-3" placeholder="Tell us how we can help you..." required><?= e($message) ?></textarea>
                                <div class="form-text">Minimum 10 characters.</div>
                            </div>
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow-sm" data-feedback="click">
                                    <i class="bi bi-send-fill me-2"></i> Send Message
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
