        </main>

        <footer class="p-4 border-top text-center text-muted small mt-auto d-flex flex-column flex-md-row justify-content-between align-items-center gap-2" style="border-color: var(--border-color) !important;">
            <div>
                &copy; <?= date('Y') ?> <strong>StudyMe</strong> &mdash; AI-Powered Learning Platform. All rights reserved.
            </div>
            <div class="d-flex gap-3 flex-wrap justify-content-center">
                <a href="<?= url('help.php') ?>" class="text-muted text-decoration-none hover-primary"><i class="bi bi-question-circle me-1"></i>Help Center</a>
                <a href="<?= url('faq.php') ?>" class="text-muted text-decoration-none hover-primary"><i class="bi bi-chat-square-text me-1"></i>FAQ</a>
                <a href="<?= url('contact.php') ?>" class="text-muted text-decoration-none hover-primary"><i class="bi bi-envelope me-1"></i>Contact Support</a>
                <a href="<?= url('privacy.php') ?>" class="text-muted text-decoration-none hover-primary">Privacy</a>
                <a href="<?= url('terms.php') ?>" class="text-muted text-decoration-none hover-primary">Terms</a>
            </div>
        </footer>
    </div>
</div>

<div class="offcanvas offcanvas-end border-0 shadow-lg" tabindex="-1" id="aiAssistantDrawer" aria-labelledby="aiAssistantLabel" style="width: 440px; background: #0F172A; color: #FFFFFF;">
    <div class="offcanvas-header border-bottom border-secondary border-opacity-25 p-4">
        <div class="d-flex align-items-center gap-3">
            <div class="p-2 bg-warning bg-opacity-20 text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                <i class="bi bi-robot fs-5"></i>
            </div>
            <div>
                <h5 class="offcanvas-title fw-bold text-white mb-0" id="aiAssistantLabel">StudyMe AI Tutor</h5>
                <small class="text-success">&bull; Active &amp; Ready to Assist</small>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-4">
        <div class="flex-grow-1 overflow-y-auto mb-3" id="aiChatLog" style="max-height: calc(100vh - 240px);">
            <div class="chat-bubble chat-bubble-ai mb-3">
                <div class="fw-bold mb-1 text-warning"><i class="bi bi-stars me-1"></i> Hello! How can I help your learning today?</div>
                Ask me to explain any difficult concept, provide a real-world code analogy, or generate practice questions!
            </div>
        </div>

        <form id="aiQueryForm" data-ajax-form="true" data-no-loader="true" class="mt-auto d-flex gap-2">
            <input type="text" id="aiQueryInput" class="form-control rounded-pill px-4 bg-dark text-white border-secondary border-opacity-50" placeholder="Ask AI anything..." required autocomplete="off">
            <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold" data-feedback="click">
                <i class="bi bi-send-fill"></i>
            </button>
        </form>
    </div>
</div>

<button class="btn btn-warning rounded-circle shadow-lg position-fixed d-flex align-items-center justify-content-center" 
        type="button" 
        data-bs-toggle="offcanvas" 
        data-bs-target="#aiAssistantDrawer" 
        aria-controls="aiAssistantDrawer"
        style="bottom: 24px; right: 24px; width: 56px; height: 56px; z-index: 1030;" 
        title="Open AI Tutor"
        data-feedback="click">
    <i class="bi bi-robot fs-4 text-dark"></i>
</button>

<script src="https://cdn.lordicon.com/lordicon.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/theme.js') ?>"></script>
<script src="<?= asset('js/skeleton.js') ?>"></script>
<script src="<?= asset('js/loader.js') ?>"></script>
<script src="<?= asset('js/feedback.js') ?>"></script>
<script src="<?= asset('js/ai.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>

<?php if (isset($_SESSION['auth_success_vibrate'])): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (typeof StudyMeFeedback !== 'undefined') {
            StudyMeFeedback.success();
        } else if (navigator.vibrate) {
            navigator.vibrate(150);
        }
    });
</script>
<?php unset($_SESSION['auth_success_vibrate']); endif; ?>

</body>
</html>
