<?php
/**
 * StudyMe AI Platform — File Upload Helper Functions
 *
 * Provides secure file upload handling for teacher resources.
 */

// Upload directory (relative to BASE_PATH)
define('UPLOAD_DIR', BASE_PATH . '/uploads');
define('UPLOAD_URL', APP_URL . '/uploads');

// Allowed MIME types by category
const ALLOWED_DOCUMENTS = [
    'application/pdf'                                                              => 'pdf',
    'application/msword'                                                           => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'      => 'docx',
    'application/vnd.ms-powerpoint'                                                => 'ppt',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation'   => 'pptx',
    'text/plain'                                                                   => 'txt',
];

const ALLOWED_IMAGES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

const ALLOWED_VIDEOS = [
    'video/mp4'  => 'mp4',
    'video/webm' => 'webm',
    'video/ogg'  => 'ogv',
    'video/quicktime' => 'mov',
];

define('MAX_DOCUMENT_SIZE', 50 * 1024 * 1024);  // 50 MB
define('MAX_VIDEO_SIZE',    500 * 1024 * 1024); // 500 MB
define('MAX_IMAGE_SIZE',    10 * 1024 * 1024);  // 10 MB

/**
 * Ensure the upload subdirectory exists.
 */
function ensure_upload_dir($subdir) {
    $path = UPLOAD_DIR . '/' . $subdir;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
        // Create .htaccess to prevent PHP execution in upload dirs
        file_put_contents($path . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.php$\">\n  Deny from all\n</FilesMatch>\n");
    }
    return $path;
}

/**
 * Detect the real MIME type of an uploaded file using PHP's finfo.
 */
function detect_mime_type($filePath) {
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        return $mime;
    }
    return mime_content_type($filePath) ?: 'application/octet-stream';
}

/**
 * Generate a secure random filename preserving the extension.
 */
function generate_secure_filename($extension) {
    return bin2hex(random_bytes(16)) . '.' . strtolower($extension);
}

/**
 * Handle document upload (PDF, DOC, DOCX, PPT, PPTX, TXT).
 *
 * @param array $file    $_FILES['field']
 * @param int   $teacherId  for ownership validation
 * @return array  ['success' => bool, 'filename' => string, 'path' => string, 'mime' => string, 'size' => int, 'needs_conversion' => bool, 'error' => string]
 */
function upload_document($file, $teacherId) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed: ' . upload_error_message($file['error'] ?? UPLOAD_ERR_NO_FILE)];
    }

    if ($file['size'] > MAX_DOCUMENT_SIZE) {
        return ['success' => false, 'error' => 'File is too large. Maximum size is 50 MB.'];
    }

    $realMime = detect_mime_type($file['tmp_name']);
    if (!array_key_exists($realMime, ALLOWED_DOCUMENTS)) {
        return ['success' => false, 'error' => 'File type not allowed. Allowed: PDF, DOC, DOCX, PPT, PPTX, TXT'];
    }

    $ext = ALLOWED_DOCUMENTS[$realMime];
    $storedFilename = generate_secure_filename($ext);
    $uploadPath = ensure_upload_dir('documents/teacher_' . (int)$teacherId);
    $destPath = $uploadPath . '/' . $storedFilename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file.'];
    }

    $needsConversion = in_array($realMime, [
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ]);

    return [
        'success'          => true,
        'filename'         => $storedFilename,
        'path'             => $destPath,
        'relative_path'    => 'uploads/documents/teacher_' . (int)$teacherId . '/' . $storedFilename,
        'mime'             => $realMime,
        'size'             => $file['size'],
        'needs_conversion' => $needsConversion,
        'error'            => null,
    ];
}

/**
 * Handle video upload.
 */
function upload_video($file, $teacherId) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed: ' . upload_error_message($file['error'] ?? UPLOAD_ERR_NO_FILE)];
    }

    if ($file['size'] > MAX_VIDEO_SIZE) {
        return ['success' => false, 'error' => 'Video is too large. Maximum size is 500 MB.'];
    }

    $realMime = detect_mime_type($file['tmp_name']);
    if (!array_key_exists($realMime, ALLOWED_VIDEOS)) {
        return ['success' => false, 'error' => 'Video type not allowed. Allowed: MP4, WebM, MOV, OGG.'];
    }

    $ext = ALLOWED_VIDEOS[$realMime];
    $storedFilename = generate_secure_filename($ext);
    $uploadPath = ensure_upload_dir('videos/teacher_' . (int)$teacherId);
    $destPath = $uploadPath . '/' . $storedFilename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['success' => false, 'error' => 'Failed to move uploaded video.'];
    }

    return [
        'success'       => true,
        'filename'      => $storedFilename,
        'path'          => $destPath,
        'relative_path' => 'uploads/videos/teacher_' . (int)$teacherId . '/' . $storedFilename,
        'mime'          => $realMime,
        'size'          => $file['size'],
        'error'         => null,
    ];
}

/**
 * Handle image upload (thumbnails, avatars).
 */
function upload_image($file, $subdir = 'images') {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed: ' . upload_error_message($file['error'] ?? UPLOAD_ERR_NO_FILE)];
    }

    if ($file['size'] > MAX_IMAGE_SIZE) {
        return ['success' => false, 'error' => 'Image is too large. Maximum size is 10 MB.'];
    }

    $realMime = detect_mime_type($file['tmp_name']);
    if (!array_key_exists($realMime, ALLOWED_IMAGES)) {
        return ['success' => false, 'error' => 'Image type not allowed. Allowed: JPEG, PNG, GIF, WebP.'];
    }

    $ext = ALLOWED_IMAGES[$realMime];
    $storedFilename = generate_secure_filename($ext);
    $uploadPath = ensure_upload_dir($subdir);
    $destPath = $uploadPath . '/' . $storedFilename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['success' => false, 'error' => 'Failed to move uploaded image.'];
    }

    return [
        'success'       => true,
        'filename'      => $storedFilename,
        'path'          => $destPath,
        'relative_path' => $subdir . '/' . $storedFilename,
        'mime'          => $realMime,
        'size'          => $file['size'],
        'error'         => null,
    ];
}

/**
 * Try to convert a document to PDF using LibreOffice (if available).
 * Returns the path to the generated PDF or false.
 */
function try_convert_to_pdf($inputPath, $outputDir) {
    $loPath = null;
    $candidates = [
        'soffice',
        'libreoffice',
        'C:/Program Files/LibreOffice/program/soffice.exe',
        'C:/Program Files (x86)/LibreOffice/program/soffice.exe',
    ];
    foreach ($candidates as $c) {
        $check = shell_exec(escapeshellcmd($c) . ' --version 2>&1');
        if ($check && stripos($check, 'libreoffice') !== false) {
            $loPath = $c;
            break;
        }
    }

    if (!$loPath) {
        return ['success' => false, 'error' => 'LibreOffice is not installed on this server. Please install LibreOffice to enable document conversion.'];
    }

    $command = escapeshellcmd($loPath) . ' --headless --convert-to pdf '
        . escapeshellarg($inputPath) . ' --outdir ' . escapeshellarg($outputDir) . ' 2>&1';
    $output = shell_exec($command);

    $baseName = pathinfo($inputPath, PATHINFO_FILENAME);
    $pdfPath = rtrim($outputDir, '/') . '/' . $baseName . '.pdf';

    if (file_exists($pdfPath)) {
        return ['success' => true, 'pdf_path' => $pdfPath, 'output' => $output];
    }

    return ['success' => false, 'error' => 'Conversion failed. Output: ' . $output];
}

/**
 * Save a resource record to the DB.
 */
function save_resource($teacherId, $data) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO resources (teacher_id, course_id, lesson_id, title, description, resource_type, original_filename, stored_filename, mime_type, file_size, file_path, conversion_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $teacherId,
            $data['course_id'] ?? null,
            $data['lesson_id'] ?? null,
            $data['title'],
            $data['description'] ?? null,
            $data['resource_type'] ?? 'document',
            $data['original_filename'],
            $data['stored_filename'],
            $data['mime_type'],
            $data['file_size'],
            $data['file_path'],
            $data['conversion_status'] ?? 'none',
        ]);
        return (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("save_resource error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get resources for a teacher.
 */
function get_teacher_resources($teacherId, $type = null) {
    $pdo = getDBConnection();
    try {
        $where = ["teacher_id = ?"];
        $params = [$teacherId];
        if ($type) { $where[] = "resource_type = ?"; $params[] = $type; }
        $stmt = $pdo->prepare("SELECT * FROM resources WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get a resource by ID (with ownership check).
 */
function get_resource_by_id($resourceId, $teacherId = null) {
    $pdo = getDBConnection();
    try {
        $where = "id = ?";
        $params = [$resourceId];
        if ($teacherId !== null) { $where .= " AND teacher_id = ?"; $params[] = $teacherId; }
        $stmt = $pdo->prepare("SELECT * FROM resources WHERE $where LIMIT 1");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Delete a resource (with ownership check).
 */
function delete_resource($resourceId, $teacherId) {
    $resource = get_resource_by_id($resourceId, $teacherId);
    if (!$resource) return false;

    // Delete physical file
    if (file_exists($resource['file_path'])) {
        @unlink($resource['file_path']);
    }

    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("DELETE FROM resources WHERE id = ? AND teacher_id = ?");
        return $stmt->execute([$resourceId, $teacherId]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Human-readable upload error messages.
 */
function upload_error_message($errorCode) {
    $messages = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server maximum upload size.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form maximum upload size.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temporary directory is missing.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
    ];
    return $messages[$errorCode] ?? 'Unknown upload error.';
}

/**
 * Format file size to human-readable string.
 */
function format_file_size($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
