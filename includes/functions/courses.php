<?php
/**
 * StudyMe AI Platform — Course Data Access Helpers
 */
require_once dirname(__DIR__, 2) . '/config/database.php';

/**
 * Get all active categories.
 */
function get_course_categories() {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in get_course_categories: " . $e->getMessage());
        return [];
    }
}

/**
 * Get courses filtered by category, level, search term, instructor, and sorting.
 */
function get_all_courses($categoryId = null, $level = null, $search = null, $instructorId = null, $sortBy = 'newest') {
    $pdo = getDBConnection();
    $sql = "
        SELECT c.*, cat.name AS category_name, cat.slug AS category_slug,
               CONCAT(u.first_name, ' ', u.last_name) AS teacher_name, t.rating AS teacher_rating
        FROM courses c
        LEFT JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN teachers t ON c.teacher_id = t.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE c.status = 'published'
    ";
    
    $params = [];

    if ($categoryId) {
        $sql .= " AND c.category_id = ?";
        $params[] = $categoryId;
    }
    if ($level) {
        $sql .= " AND c.level = ?";
        $params[] = $level;
    }
    if ($instructorId) {
        $sql .= " AND c.teacher_id = ?";
        $params[] = $instructorId;
    }
    if ($search) {
        $sql .= " AND (c.title LIKE ? OR c.short_description LIKE ? OR c.description LIKE ? OR cat.name LIKE ?)";
        $term = "%$search%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    switch ($sortBy) {
        case 'a-z':
            $sql .= " ORDER BY c.title ASC";
            break;
        case 'price-asc':
            $sql .= " ORDER BY c.price ASC, c.title ASC";
            break;
        case 'price-desc':
            $sql .= " ORDER BY c.price DESC, c.title ASC";
            break;
        case 'newest':
        default:
            $sql .= " ORDER BY c.featured DESC, c.created_at DESC, c.id DESC";
            break;
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in get_all_courses: " . $e->getMessage());
        return [];
    }
}

/**
 * Flexible lookup by ID or slug.
 */
function get_course_by_id_or_slug($idOrSlug) {
    if (is_numeric($idOrSlug)) {
        return get_course_by_id((int)$idOrSlug);
    }
    return get_course_by_slug($idOrSlug);
}

/**
 * Fetch a single course by its ID.
 */
function get_course_by_id($courseId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, cat.name AS category_name, cat.slug AS category_slug,
                   CONCAT(u.first_name, ' ', u.last_name) AS teacher_name, t.rating AS teacher_rating, t.bio AS teacher_bio
            FROM courses c
            LEFT JOIN categories cat ON c.category_id = cat.id
            LEFT JOIN teachers t ON c.teacher_id = t.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE c.id = ?
            LIMIT 1
        ");
        $stmt->execute([$courseId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log("Error in get_course_by_id: " . $e->getMessage());
        return null;
    }
}

/**
 * Fetch a single course by its slug.
 */
function get_course_by_slug($slug) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, cat.name AS category_name, cat.slug AS category_slug,
                   CONCAT(u.first_name, ' ', u.last_name) AS teacher_name, t.rating AS teacher_rating, t.bio AS teacher_bio
            FROM courses c
            LEFT JOIN categories cat ON c.category_id = cat.id
            LEFT JOIN teachers t ON c.teacher_id = t.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE c.slug = ?
            LIMIT 1
        ");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log("Error in get_course_by_slug: " . $e->getMessage());
        return null;
    }
}

/**
 * Retrieve sections for a given course.
 */
function get_course_sections($courseId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT * FROM course_sections WHERE course_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in get_course_sections: " . $e->getMessage());
        return [];
    }
}

/**
 * Retrieve lessons for a given section.
 */
function get_section_lessons($sectionId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT * FROM lessons WHERE section_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$sectionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in get_section_lessons: " . $e->getMessage());
        return [];
    }
}

/**
 * Resolve a reliable, responsive, full URL for any course thumbnail or description image.
 * Handles:
 * 1. Absolute URLs (http/https)
 * 2. Relative file paths (uploads/thumbnails/..., assets/images/...)
 * 3. Empty values -> category-tailored graceful fallbacks
 * 
 * @param string|null $thumbnail
 * @param string $categorySlug
 * @return string
 */
function get_course_thumbnail_url($thumbnail = null, $categorySlug = 'technology') {
    $thumbnail = trim($thumbnail ?? '');
    
    if (!empty($thumbnail)) {
        // If already an absolute web URL, return directly
        if (strpos($thumbnail, 'http://') === 0 || strpos($thumbnail, 'https://') === 0) {
            return $thumbnail;
        }
        // If relative path, prepend url() to ensure correct path from any nested route
        return url(ltrim($thumbnail, '/'));
    }

    // Graceful Category-Matched Fallback Images
    $fallbacks = [
        'technology'           => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=600&q=80',
        'tech'                 => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=600&q=80',
        'secondary-waec-neco'  => 'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=600&q=80',
        'secondary'            => 'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=600&q=80',
        'university'           => 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=600&q=80',
        'teacher'              => 'https://images.unsplash.com/photo-1524178232363-1fb2b075b655?w=600&q=80',
    ];

    $cleanCat = strtolower(trim($categorySlug));
    return $fallbacks[$cleanCat] ?? 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=600&q=80';
}

