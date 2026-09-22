<?php
require_once dirname(__DIR__, 2) . '/config/database.php';

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

function get_course_by_id_or_slug($idOrSlug) {
    if (is_numeric($idOrSlug)) {
        return get_course_by_id((int)$idOrSlug);
    }
    return get_course_by_slug($idOrSlug);
}

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

function get_secondary_bundle_course() {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, cat.name AS category_name, cat.slug AS category_slug,
                   CONCAT(u.first_name, ' ', u.last_name) AS teacher_name
            FROM courses c
            LEFT JOIN categories cat ON c.category_id = cat.id
            LEFT JOIN teachers t ON c.teacher_id = t.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE cat.slug IN ('secondary-waec-neco', 'secondary')
              AND c.status = 'published'
            ORDER BY c.id ASC
            LIMIT 1
        ");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Exception $e) {
        error_log("Error in get_secondary_bundle_course: " . $e->getMessage());
        return null;
    }
}

function get_course_thumbnail_url($thumbnail = null, $categorySlug = 'technology', $titleOrSlug = '') {
    $thumbnail = trim($thumbnail ?? '');
    $genericThumb = 'photo-1498050108023-c5249f4df085';
    
    static $courseImageMap = [
        'web-development'                   => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=600&q=80',
        'cybersecurity'                     => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=600&q=80',
        'cyber-security'                    => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=600&q=80',
        'app-development'                   => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?w=600&q=80',
        'mobile-app'                        => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?w=600&q=80',
        'data-analytics'                    => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=600&q=80',
        'data-science'                      => 'https://images.unsplash.com/photo-1504868584819-f8e8b4b6d7e3?w=600&q=80',
        'python-programming'                => 'https://images.unsplash.com/photo-1526379095098-d400fd0bf935?w=600&q=80',
        'python'                            => 'https://images.unsplash.com/photo-1526379095098-d400fd0bf935?w=600&q=80',
        'machine-learning-ai'               => 'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=600&q=80',
        'artificial-intelligence'           => 'https://images.unsplash.com/photo-1620712943543-bcc4688e7485?w=600&q=80',
        'cloud-devops'                      => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=600&q=80',
        'devops'                            => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=600&q=80',
        'ui-ux-design'                      => 'https://images.unsplash.com/photo-1581291518655-9523c932edcf?w=600&q=80',
        'ui-ux'                             => 'https://images.unsplash.com/photo-1581291518655-9523c932edcf?w=600&q=80',
        'blockchain-development'            => 'https://images.unsplash.com/photo-1639762681485-074b7f938ba0?w=600&q=80',
        'blockchain'                        => 'https://images.unsplash.com/photo-1639762681485-074b7f938ba0?w=600&q=80',
        'game-development'                  => 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=600&q=80',
        'product-management'                => 'https://images.unsplash.com/photo-1531403009284-440f080d1e12?w=600&q=80',
        'digital-marketing'                 => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=600&q=80',
        'linux-admin'                       => 'https://images.unsplash.com/photo-1629654297299-c8506221ca97?w=600&q=80',
        'database-design'                   => 'https://images.unsplash.com/photo-1544383835-bda2bc66a55d?w=600&q=80',
        'quality-assurance'                 => 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=600&q=80',
        'iot-embedded-systems'              => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&q=80',
        'networking-ccna'                   => 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?w=600&q=80',
        'technical-writing'                 => 'https://images.unsplash.com/photo-1455390582262-044cdead277a?w=600&q=80',
        'ar-vr-development'                 => 'https://images.unsplash.com/photo-1592478411213-6153e4ebc07d?w=600&q=80',
        'tech-sales'                        => 'https://images.unsplash.com/photo-1552664730-d307ca884978?w=600&q=80',
        'mathematics'                       => 'https://images.unsplash.com/photo-1635070041078-e363dbe005cb?w=600&q=80',
        'english-language'                  => 'https://images.unsplash.com/photo-1457369804613-52c61a468e7d?w=600&q=80',
        'english'                           => 'https://images.unsplash.com/photo-1457369804613-52c61a468e7d?w=600&q=80',
        'physics'                           => 'https://images.unsplash.com/photo-1636466497217-26a8cbeaf0aa?w=600&q=80',
        'chemistry'                         => 'https://images.unsplash.com/photo-1532187863486-abf9dbad1b69?w=600&q=80',
        'biology'                           => 'https://images.unsplash.com/photo-1530026405186-ed1f139313f8?w=600&q=80',
        'further-mathematics'               => 'https://images.unsplash.com/photo-1509228468518-180dd4864904?w=600&q=80',
        'economics'                         => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=600&q=80',
        'financial-accounting'              => 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=600&q=80',
        'commerce'                          => 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?w=600&q=80',
        'government'                        => 'https://images.unsplash.com/photo-1541872703-74c5e44368f9?w=600&q=80',
        'literature-in-english'             => 'https://images.unsplash.com/photo-1474932430478-367dbb6832c1?w=600&q=80',
        'literature'                        => 'https://images.unsplash.com/photo-1474932430478-367dbb6832c1?w=600&q=80',
        'geography'                         => 'https://images.unsplash.com/photo-1524661135-423995f22d0b?w=600&q=80',
        'agricultural-science'              => 'https://images.unsplash.com/photo-1500937386664-56d1dfef3854?w=600&q=80',
        'civic-education'                   => 'https://images.unsplash.com/photo-1540910419892-4a36d2c3266c?w=600&q=80',
        'computer-studies'                  => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=600&q=80',
        'technical-drawing'                 => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?w=600&q=80',
        'crk'                               => 'https://images.unsplash.com/photo-1490730141103-6cac27aaab94?w=600&q=80',
        'irk'                               => 'https://images.unsplash.com/photo-1584551246679-0daf3d275d0f?w=600&q=80',
        'animal-husbandry'                  => 'https://images.unsplash.com/photo-1527153857715-3908f2ae5e81?w=600&q=80',
        'civil-engineering'                 => 'https://images.unsplash.com/photo-1581092160607-ee22621dd758?w=600&q=80',
        'mechanical-engineering'            => 'https://images.unsplash.com/photo-1537462715879-360eeb61a0ad?w=600&q=80',
        'electrical-engineering'            => 'https://images.unsplash.com/photo-1473341304170-971dccb5ac1e?w=600&q=80',
        'electronics-engineering'           => 'https://images.unsplash.com/photo-1517077304055-6e89abbf09b0?w=600&q=80',
        'computer-engineering'              => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=600&q=80',
        'chemical-engineering'              => 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?w=600&q=80',
        'petroleum-engineering'             => 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=600&q=80',
        'agricultural-engineering'          => 'https://images.unsplash.com/photo-1586771107445-d3ca888129ff?w=600&q=80',
        'mechatronics-engineering'          => 'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=600&q=80',
        'aerospace-engineering'             => 'https://images.unsplash.com/photo-1517976487502-5f653457a346?w=600&q=80',
        'biomedical-engineering'            => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=600&q=80',
        'computer-science'                  => 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=600&q=80',
        'information-technology'            => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=600&q=80',
        'software-engineering'              => 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=600&q=80',
        'medicine-surgery'                  => 'https://images.unsplash.com/photo-1532938911079-1b06ac7ceec7?w=600&q=80',
        'medicine'                          => 'https://images.unsplash.com/photo-1532938911079-1b06ac7ceec7?w=600&q=80',
        'nursing-science'                   => 'https://images.unsplash.com/photo-1576765608535-5f04d1e3f289?w=600&q=80',
        'nursing'                           => 'https://images.unsplash.com/photo-1576765608535-5f04d1e3f289?w=600&q=80',
        'pharmacy'                          => 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=600&q=80',
        'medical-laboratory-science'        => 'https://images.unsplash.com/photo-1579154204601-01588f351e67?w=600&q=80',
        'anatomy'                           => 'https://images.unsplash.com/photo-1530497610245-94d3c16cda28?w=600&q=80',
        'physiology'                        => 'https://images.unsplash.com/photo-1559757175-5700dde675bc?w=600&q=80',
        'public-health'                     => 'https://images.unsplash.com/photo-1584515979956-d9f6e5d09982?w=600&q=80',
        'accounting'                        => 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=600&q=80',
        'finance-banking'                   => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=600&q=80',
        'business-administration'           => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=600&q=80',
        'mass-communication'                => 'https://images.unsplash.com/photo-1585829365295-ab7cd400c167?w=600&q=80',
        'political-science'                 => 'https://images.unsplash.com/photo-1541872703-74c5e44368f9?w=600&q=80',
        'sociology'                         => 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?w=600&q=80',
        'psychology'                        => 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?w=600&q=80',
        'law-llb'                           => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=600&q=80',
        'law'                               => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=600&q=80',
        'architecture'                      => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=600&q=80',
    ];

    $cleanKey = strtolower(trim($titleOrSlug));
    $cleanCat = strtolower(trim($categorySlug));

    if (!empty($cleanKey)) {
        if (isset($courseImageMap[$cleanKey])) {
            return $courseImageMap[$cleanKey];
        }
        foreach ($courseImageMap as $key => $imgUrl) {
            $formattedKey = str_replace('-', ' ', $key);
            if (strpos($cleanKey, $key) !== false || strpos($cleanKey, $formattedKey) !== false) {
                return $imgUrl;
            }
        }
    }

    if (!empty($thumbnail) && strpos($thumbnail, $genericThumb) === false) {
        if (strpos($thumbnail, 'http://') === 0 || strpos($thumbnail, 'https://') === 0) {
            return $thumbnail;
        }
        return url(ltrim($thumbnail, '/'));
    }

    $catFallbacks = [
        'technology'           => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=600&q=80',
        'tech'                 => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=600&q=80',
        'secondary-waec-neco'  => 'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=600&q=80',
        'secondary'            => 'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=600&q=80',
        'university'           => 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=600&q=80',
        'teacher'              => 'https://images.unsplash.com/photo-1524178232363-1fb2b075b655?w=600&q=80',
    ];

    return $catFallbacks[$cleanCat] ?? 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=600&q=80';
}

function course_has_active_teacher($courseId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, cat.slug AS category_slug, cat.name AS category_name,
                   t.id AS teacher_table_id, t.status AS teacher_status, t.qualification,
                   u.id AS teacher_user_id, u.first_name, u.last_name, u.email AS teacher_email, u.status AS user_status
            FROM courses c
            LEFT JOIN categories cat ON c.category_id = cat.id
            LEFT JOIN teachers t ON c.teacher_id = t.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE c.id = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$courseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [
                'is_functional'      => false,
                'has_active_teacher' => false,
                'can_enroll'         => false,
                'teacher'            => null,
                'course'             => null,
                'reason'             => 'Course does not exist in catalog.'
            ];
        }

        $isPublished = ($row['status'] === 'published');
        if (!$isPublished) {
            return [
                'is_functional'      => false,
                'has_active_teacher' => false,
                'can_enroll'         => false,
                'teacher'            => null,
                'course'             => $row,
                'reason'             => 'Course is currently ' . htmlspecialchars($row['status']) . ' and not open for student enrollment.'
            ];
        }

        $catSlug = strtolower($row['category_slug'] ?? '');
        $isSecondary = ($catSlug === 'secondary-waec-neco' || $catSlug === 'secondary');

        if ($isSecondary) {
            return [
                'is_functional'      => true,
                'has_active_teacher' => true,
                'can_enroll'         => true,
                'teacher'            => ['name' => 'StudyMe Academic Curriculum'],
                'course'             => $row,
                'reason'             => 'Secondary course operates under central curriculum.'
            ];
        }

        $hasTeacher = !empty($row['teacher_id']) && !empty($row['teacher_user_id']);
        $teacherActive = $hasTeacher && ($row['teacher_status'] === 'active') && ($row['user_status'] === 'active');

        if (!$hasTeacher || !$teacherActive) {
            return [
                'is_functional'      => true,
                'has_active_teacher' => false,
                'can_enroll'         => false,
                'teacher'            => null,
                'course'             => $row,
                'reason'             => 'University and platform regulations require every enrolled student to have an active assigned instructor. This course currently has no active teacher assigned.'
            ];
        }

        return [
            'is_functional'      => true,
            'has_active_teacher' => true,
            'can_enroll'         => true,
            'teacher'            => [
                'id'            => (int)$row['teacher_table_id'],
                'user_id'       => (int)$row['teacher_user_id'],
                'name'          => trim($row['first_name'] . ' ' . $row['last_name']),
                'email'         => $row['teacher_email'],
                'qualification' => $row['qualification']
            ],
            'course'             => $row,
            'reason'             => 'Course is published and assigned to an active instructor.'
        ];
    } catch (Exception $e) {
        error_log("Error in course_has_active_teacher: " . $e->getMessage());
        return [
            'is_functional'      => false,
            'has_active_teacher' => false,
            'can_enroll'         => false,
            'teacher'            => null,
            'course'             => null,
            'reason'             => 'Database verification error.'
        ];
    }
}
