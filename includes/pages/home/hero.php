<section class="hero-section" id="home">
    <div class="container">
        <div class="row align-items-center g-5">
            <!-- Left Column: Copywriting & CTAs -->
            <div class="col-lg-6">
                <div class="hero-badge">
                    <span>🚀</span> AI Powered Learning Platform
                </div>

                <h1 class="hero-title">
                    Learn Smarter.<br>
                    Build Your Future<br>
                    With <span class="highlight">StudyMe</span>.
                </h1>

                <p class="hero-text">
                    Join thousands of students learning from experienced teachers through interactive courses, AI assistance, quizzes, certificates, and real-time progress tracking.
                </p>

                <div class="hero-buttons">
                    <a href="<?= function_exists('url') ? url('auth/register.php') : 'auth/register.php' ?>" class="btn btn-hero-primary" data-feedback="success">
                        Get Started Free
                        <i class="bi bi-arrow-right ms-2"></i>
                    </a>
                    <a href="#courses" class="btn btn-hero-secondary" data-feedback="click">
                        <i class="bi bi-play-circle me-2"></i>
                        Explore Courses
                    </a>
                </div>

                <div class="hero-stats-row">
                    <div class="hero-stat-item">
                        <h4>20K+</h4>
                        <p>Active Students</p>
                    </div>
                    <div class="hero-stat-item">
                        <h4>4.9/5</h4>
                        <p>User Rating</p>
                    </div>
                    <div class="hero-stat-item">
                        <h4>500+</h4>
                        <p>Courses Available</p>
                    </div>
                    <div class="hero-stat-item">
                        <h4>100+</h4>
                        <p>Expert Teachers</p>
                    </div>
                </div>
            </div>

            <!-- Right Column: Visual & Floating UI Cards -->
            <div class="col-lg-6">
                <div class="hero-visual-wrapper">
                    <?php 
                    $heroImagePath = 'assets/images/ChatGPT Image Aug 7, 2026, 04_09_46 PM.png';
                    $heroImageFile = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3)) . '/' . $heroImagePath;
                    if (file_exists($heroImageFile)): 
                    ?>
                        <img src="<?= function_exists('asset') ? asset('images/ChatGPT Image Aug 7, 2026, 04_09_46 PM.png') : $heroImagePath ?>" class="img-fluid hero-main-img" alt="StudyMe Student Learning">
                    <?php else: ?>
                        <!-- SVG Illustration Fallback -->
                        <div class="p-5 text-center bg-white bg-opacity-10 rounded-4 border border-white border-opacity-25 w-100">
                            <i class="bi bi-mortarboard-fill display-1 text-warning mb-3"></i>
                            <h3>StudyMe Learning Hub</h3>
                            <p class="text-white-50">Interactive AI Tutor &amp; Live Skill Courses</p>
                        </div>
                    <?php endif; ?>

                    <!-- Floating Glass UI Cards -->
                    <div class="floating-card card-rating">
                        <i class="bi bi-star-fill"></i>
                        <div>
                            <h6>4.9 Rating</h6>
                            <small>12,000+ Reviews</small>
                        </div>
                    </div>

                    <div class="floating-card card-ai">
                        <i class="bi bi-robot"></i>
                        <div>
                            <h6>AI Tutor</h6>
                            <small>Online &amp; Ready</small>
                        </div>
                    </div>

                    <div class="floating-card card-lessons">
                        <i class="bi bi-book-half"></i>
                        <div>
                            <h6>12 Courses</h6>
                            <small>In Progress</small>
                        </div>
                    </div>

                    <div class="floating-card card-certificates">
                        <i class="bi bi-patch-check-fill"></i>
                        <div>
                            <h6>Certificate</h6>
                            <small>Verified Achievement</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>