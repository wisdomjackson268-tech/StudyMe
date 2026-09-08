<!-- Unified Landing Page -->
<main class="landing-page bg-body">
    <!-- Hero Section -->
    <section class="hero-section text-center py-5" style="background: var(--gradient-primary); color: var(--text-inverse);">
        <div class="container">
            <h1 class="display-4 fw-bold mb-3">Learn Smarter. Build Your Future.</h1>
            <p class="lead mb-4">AI‑powered courses, real‑time tutoring, and personalized pathways for every learner.</p>
            <a href="<?= url('auth/register.php') ?>" class="btn btn-warning btn-lg rounded-pill fw-bold" data-feedback="success">Get Started <i class="bi bi-arrow-right-short ms-1"></i></a>
        </div>
    </section>

    <!-- Trusted By Section -->
    <section class="trusted-section py-5">
        <div class="container">
            <h2 class="h4 text-center fw-semibold mb-4">Trusted By Schools &amp; Companies</h2>
            <div class="row justify-content-center g-4">
                <div class="col-4 col-md-2 text-center"><img src="<?= asset('images/logo1.png') ?>" alt="Partner 1" class="img-fluid opacity-75"></div>
                <div class="col-4 col-md-2 text-center"><img src="<?= asset('images/logo2.png') ?>" alt="Partner 2" class="img-fluid opacity-75"></div>
                <div class="col-4 col-md-2 text-center"><img src="<?= asset('images/logo3.png') ?>" alt="Partner 3" class="img-fluid opacity-75"></div>
                <div class="col-4 col-md-2 text-center"><img src="<?= asset('images/logo4.png') ?>" alt="Partner 4" class="img-fluid opacity-75"></div>
                <div class="col-4 col-md-2 text-center"><img src="<?= asset('images/logo5.png') ?>" alt="Partner 5" class="img-fluid opacity-75"></div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section py-5 bg-light-subtle">
        <div class="container">
            <div class="row g-4 text-center">
                <div class="col-md-4">
                    <div class="p-4 bg-white rounded-lg shadow-sm h-100">
                        <i class="bi bi-robot text-primary fs-2 mb-3"></i>
                        <h3 class="h5 fw-semibold mb-2">AI Tutor</h3>
                        <p class="small text-muted">Instant explanations, code reviews, and practice questions powered by our proprietary AI engine.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-4 bg-white rounded-lg shadow-sm h-100">
                        <i class="bi bi-journal-code text-success fs-2 mb-3"></i>
                        <h3 class="h5 fw-semibold mb-2">Interactive Courses</h3>
                        <p class="small text-muted">Video lessons, live labs, and progress tracking that adapt to your pace.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-4 bg-white rounded-lg shadow-sm h-100">
                        <i class="bi bi-award text-warning fs-2 mb-3"></i>
                        <h3 class="h5 fw-semibold mb-2">Certificates</h3>
                        <p class="small text-muted">Earn verifiable digital certificates recognized by industry partners.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="cta-section py-5 text-center" style="background: var(--gradient-gold); color: var(--text-inverse);">
        <div class="container">
            <h2 class="display-5 fw-bold mb-3">Ready to Transform Your Learning?</h2>
            <p class="lead mb-4">Choose a plan that fits your goals and start today.</p>
            <a href="<?= url('payments/activate.php') ?>" class="btn btn-light btn-lg rounded-pill fw-bold" data-feedback="click">View Plans &amp; Activate</a>
        </div>
    </section>
</main>
