<?php

require_once dirname(__DIR__) . '/config/main.php';

$pdo = getDBConnection();

$stmtCats = $pdo->query("SELECT * FROM categories WHERE slug IN ('technology', 'university') AND status = 'active' ORDER BY id ASC");
$categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

$coursesByCategory = [];
foreach ($categories as $cat) {
    $stmtC = $pdo->prepare("
        SELECT id, title, slug, academic_level, academic_year
        FROM courses
        WHERE category_id = ? AND status = 'published'
        ORDER BY title ASC
    ");
    $stmtC->execute([$cat['id']]);
    $cList = $stmtC->fetchAll(PDO::FETCH_ASSOC);

    $coursesByCategory[$cat['id']] = [
        'category_name' => $cat['name'],
        'category_slug' => $cat['slug'],
        'courses'       => $cList
    ];
}

$error = '';
if (is_post()) {
    $selectedCategoryId = (int)($_POST['category_id'] ?? 0);
    $courseChoiceType   = trim($_POST['course_choice_type'] ?? 'existing');
    $selectedCourseId   = (int)($_POST['course_id'] ?? 0);

    if ($selectedCategoryId <= 0) {
        $error = 'Please select your teaching track (Tech or University).';
    } else {

        $stmtV = $pdo->prepare("SELECT slug FROM categories WHERE id = ? AND slug IN ('technology', 'university') LIMIT 1");
        $stmtV->execute([$selectedCategoryId]);
        $validSlug = $stmtV->fetchColumn();

        if (!$validSlug) {
            $error = 'Invalid teaching track selected. Teachers may only instruct Tech or University courses.';
        } else {
            if ($courseChoiceType === 'new') {
                $_SESSION['teacher_reg_teaching_side'] = $validSlug;
                $_SESSION['teacher_reg_category_id']   = $selectedCategoryId;
                $_SESSION['teacher_reg_course_id']     = 0;
                $_SESSION['teacher_reg_create_new']    = true;
                redirect('auth/teacher-register.php');
            } elseif ($selectedCourseId <= 0) {
                $error = 'Please select an existing course to teach, or select "I want to create a new custom course".';
            } else {
                $_SESSION['teacher_reg_teaching_side'] = $validSlug;
                $_SESSION['teacher_reg_category_id']   = $selectedCategoryId;
                $_SESSION['teacher_reg_course_id']     = $selectedCourseId;
                $_SESSION['teacher_reg_create_new']    = false;
                redirect('auth/teacher-register.php');
            }
        }
    }
}

$seo_options = [
    'title'      => 'Select Teaching Course | Become an Instructor on StudyMe',
    'is_private' => true,
    'noindex'    => true
];
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle d-flex align-items-center" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-7 col-md-9">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="card-header bg-dark text-white p-4 p-md-5 text-center border-0" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%) !important;">
                        <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold text-uppercase small mb-2">
                            Step 1 of 2 — Teaching Track &amp; Course Option
                        </span>
                        <h2 class="display-6 fw-bold text-white mb-2">What do you want to teach?</h2>
                        <p class="text-white-50 lead fs-6 mb-0">Select your academic track (Technology or University) and choose whether to select an existing course or create a new custom course.</p>
                    </div>
                    <div class="card-body p-4 p-md-5">

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger rounded-3 mb-4">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($error) ?>
                            </div>
                        <?php endif; ?>

                        <form action="<?= url('auth/teacher-course-select.php') ?>" method="POST" id="teacherCourseForm">

                            <div class="mb-4">
                                <label for="category_id" class="form-label fw-bold text-dark fs-6">
                                    <span class="badge bg-primary rounded-circle me-1">1</span> Choose Teaching Track:
                                </label>
                                <select name="category_id" id="category_id" class="form-select form-select-lg py-3 rounded-3" required>
                                    <option value="">-- Select Track --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" data-slug="<?= e($cat['slug']) ?>">
                                            <?= $cat['slug'] === 'technology' ? '💻 Technology Bootcamps & Modern Skills' : '🎓 University Undergraduate Degree Courses' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text text-muted small mt-1">
                                    <i class="bi bi-info-circle me-1"></i> Teachers instruct either Technology Bootcamps or University Degree Courses.
                                </div>
                            </div>

                            <div class="mb-4" id="course_mode_wrapper" style="display: none;">
                                <label class="form-label fw-bold text-dark fs-6">
                                    <span class="badge bg-primary rounded-circle me-1">2</span> How would you like to assign your course?
                                </label>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-check p-3 border rounded-3 bg-light h-100">
                                            <input class="form-check-input" type="radio" name="course_choice_type" id="type_existing" value="existing" checked>
                                            <label class="form-check-label fw-bold text-dark" for="type_existing">
                                                <i class="bi bi-collection-play-fill text-primary me-1"></i> Select an Existing Course
                                                <div class="text-muted fw-normal small mt-1">Choose from our catalog of pre-configured courses.</div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check p-3 border rounded-3 bg-light h-100">
                                            <input class="form-check-input" type="radio" name="course_choice_type" id="type_new" value="new">
                                            <label class="form-check-label fw-bold text-dark" for="type_new">
                                                <i class="bi bi-plus-circle-fill text-success me-1"></i> Create a New Course
                                                <div class="text-muted fw-normal small mt-1">Specify custom University level (100L-600L) &amp; syllabus.</div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4" id="course_selection_wrapper" style="display: none;">
                                <label for="course_id" class="form-label fw-bold text-dark fs-6">
                                    <span class="badge bg-primary rounded-circle me-1">3</span> Select Existing Course:
                                </label>
                                <select name="course_id" id="course_id" class="form-select form-select-lg py-3 rounded-3">
                                    <option value="">-- Choose Course to Teach --</option>
                                </select>
                            </div>

                            <div class="p-3 bg-light rounded-4 mb-4 border small">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="fw-bold text-dark">Official Teacher Registration Fee:</span>
                                    <span class="fw-bold text-success fs-6">₦4,000</span>
                                </div>
                                <?php if (defined('FREE_TESTING_MODE') && FREE_TESTING_MODE): ?>
                                    <span class="badge bg-success rounded-pill px-2 py-1">
                                        <i class="bi bi-check-circle-fill me-1"></i> Free Testing Mode: ₦0
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="d-grid pt-2">
                                <button type="submit" id="btnContinue" class="btn btn-primary btn-lg rounded-pill fw-bold shadow py-3" disabled>
                                    Continue to Account Setup <i class="bi bi-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <p class="text-muted small mb-0">
                                Already registered as an instructor?
                                <a href="<?= url('auth/teacher-login.php') ?>" class="text-primary fw-bold text-decoration-none">Log in to Teacher Portal</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rawData = <?= json_encode($coursesByCategory) ?>;
    const catSelect = document.getElementById('category_id');
    const courseSelect = document.getElementById('course_id');
    const modeWrapper = document.getElementById('course_mode_wrapper');
    const courseWrapper = document.getElementById('course_selection_wrapper');
    const radioExisting = document.getElementById('type_existing');
    const radioNew = document.getElementById('type_new');
    const btnContinue = document.getElementById('btnContinue');

    function updateState() {
        const catId = catSelect.value;
        if (!catId) {
            modeWrapper.style.display = 'none';
            courseWrapper.style.display = 'none';
            btnContinue.disabled = true;
            return;
        }

        modeWrapper.style.display = 'block';

        if (radioNew.checked) {
            courseWrapper.style.display = 'none';
            courseSelect.required = false;
            btnContinue.disabled = false;
        } else {
            courseWrapper.style.display = 'block';
            courseSelect.required = true;
            btnContinue.disabled = !courseSelect.value;
        }
    }

    catSelect.addEventListener('change', function() {
        const catId = this.value;
        courseSelect.innerHTML = '<option value="">-- Choose Course to Teach --</option>';

        if (catId && rawData[catId] && rawData[catId].courses && rawData[catId].courses.length > 0) {
            rawData[catId].courses.forEach(function(course) {
                const opt = document.createElement('option');
                opt.value = course.id;
                const levelInfo = course.academic_level ? ` [${course.academic_level}]` : '';
                opt.textContent = course.title + levelInfo;
                courseSelect.appendChild(opt);
            });
        }
        updateState();
    });

    radioExisting.addEventListener('change', updateState);
    radioNew.addEventListener('change', updateState);
    courseSelect.addEventListener('change', updateState);
});
</script>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
