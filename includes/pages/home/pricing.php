<section class="section-padding bg-light-subtle" id="pricing">
    <div class="container">
        <div class="section-header text-center">
            <span class="section-badge">Flexible Plans</span>
            <h2 class="section-title">Choose Your Learning Plan</h2>
            <p class="section-subtitle">
                Transparent, affordable pricing tailored for individual learners, students, and professionals.
            </p>
        </div>

        <div class="row g-4 align-items-center">
            <div class="col-md-6 col-lg-4">
                <div class="pricing-card">
                    <h4 class="fw-bold">Free Plan</h4>
                    <p class="text-muted small">Explore basic learning resources</p>
                    <div class="price-tag">
                        ₦0 <span>/ month</span>
                    </div>
                    <ul class="pricing-features">
                        <li><i class="bi bi-check-circle-fill"></i> Access to free introductory courses</li>
                        <li><i class="bi bi-check-circle-fill"></i> Basic progress tracking</li>
                        <li><i class="bi bi-check-circle-fill"></i> Community discussion forum</li>
                        <li class="text-muted"><i class="bi bi-x-circle text-muted"></i> AI Tutor assistant</li>
                        <li class="text-muted"><i class="bi bi-x-circle text-muted"></i> Certificates of completion</li>
                    </ul>
                    <a href="<?= function_exists('url') ? url('auth/register.php') : 'auth/register.php' ?>" class="btn btn-outline-primary rounded-pill w-100 py-3 fw-bold mt-auto" data-feedback="click">
                        Get Started Free
                    </a>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="pricing-card popular">
                    <span class="popular-badge">Most Popular</span>
                    <h4 class="fw-bold">Monthly Plan</h4>
                    <p class="text-muted small">Full access to premium features</p>
                    <div class="price-tag">
                        ₦5,000 <span>/ month</span>
                    </div>
                    <ul class="pricing-features">
                        <li><i class="bi bi-check-circle-fill"></i> Unlimited access to all 500+ courses</li>
                        <li><i class="bi bi-check-circle-fill"></i> 24/7 AI Learning Companion</li>
                        <li><i class="bi bi-check-circle-fill"></i> Verified digital certificates</li>
                        <li><i class="bi bi-check-circle-fill"></i> Quizzes, exams &amp; project feedback</li>
                        <li><i class="bi bi-check-circle-fill"></i> Offline downloadable notes</li>
                    </ul>
                    <a href="<?= function_exists('url') ? url('auth/register.php') : 'auth/register.php' ?>" class="btn btn-start rounded-pill w-100 py-3 fw-bold mt-auto" data-feedback="success">
                        Start 7-Day Free Trial
                    </a>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="pricing-card">
                    <h4 class="fw-bold">Yearly Plan</h4>
                    <p class="text-muted small">Maximum value with annual billing</p>
                    <div class="price-tag">
                        ₦50,000 <span>/ year</span>
                    </div>
                    <ul class="pricing-features">
                        <li><i class="bi bi-check-circle-fill"></i> All Monthly Plan features included</li>
                        <li><i class="bi bi-check-circle-fill"></i> 2 Months FREE discount</li>
                        <li><i class="bi bi-check-circle-fill"></i> Priority 1-on-1 teacher Q&amp;A</li>
                        <li><i class="bi bi-check-circle-fill"></i> Exclusive career mentorship sessions</li>
                        <li><i class="bi bi-check-circle-fill"></i> Early access to new releases</li>
                    </ul>
                    <a href="<?= function_exists('url') ? url('auth/register.php') : 'auth/register.php' ?>" class="btn btn-outline-primary rounded-pill w-100 py-3 fw-bold mt-auto" data-feedback="success">
                        Subscribe Yearly
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
