<?php
/**
 * StudyMe AI Platform — Admin Categories Manager
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();

// Fetch categories
$categories = $pdo->query("
    SELECT cat.*,
           (SELECT COUNT(*) FROM courses c WHERE c.category_id = cat.id) AS course_count
    FROM categories cat
    ORDER BY cat.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

if (is_post() && isset($_POST['add_category'])) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!empty($name)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description, status, created_at) VALUES (?, ?, ?, 'active', NOW())");
            $stmt->execute([$name, $slug, $description]);
            set_flash('success', 'Category added!');
            redirect('admin/categories.php');
        } catch (Exception $e) {
            set_flash('error', 'Error: ' . $e->getMessage());
        }
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-tag-fill text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Course Categories</h2>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <h5 class="fw-bold mb-3">Add Category</h5>
            <form method="POST">
                <input type="hidden" name="add_category" value="1">
                <div class="mb-3">
                    <label class="form-label fw-bold">Category Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control rounded-3" placeholder="e.g. Data Science" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Description</label>
                    <textarea name="description" class="form-control rounded-3" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary rounded-pill w-100 fw-bold">Add Category</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Category</th>
                            <th>Slug</th>
                            <th>Courses</th>
                            <th class="text-end pe-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-main"><?= e($cat['name']) ?></td>
                                <td><code class="small text-muted"><?= e($cat['slug']) ?></code></td>
                                <td><span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1"><?= (int)$cat['course_count'] ?> courses</span></td>
                                <td class="text-end pe-4"><span class="badge bg-success rounded-pill">Active</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
