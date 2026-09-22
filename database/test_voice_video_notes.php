<?php
/**
 * Test Suite for Voice & Video Notes
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/main.php';
require_once __DIR__ . '/../includes/functions/voice_video_notes.php';
require_once __DIR__ . '/../includes/functions/uploads.php';

echo "=== Running Voice & Video Notes Test Suite ===\n";

$pdo = getDBConnection();

try {
    // 1. Find a test teacher and university course
    $stmt = $pdo->query("
        SELECT t.id as teacher_id, c.id as course_id, c.title as course_title
        FROM teachers t
        JOIN courses c ON (c.teacher_id = t.id OR t.assigned_course_id = c.id)
        LIMIT 1
    ");
    $testTarget = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$testTarget) {
        echo "[SKIP] No teacher with assigned course found. Creating temporary test teacher & course...\n";
        // Create dummy course and teacher for test
        $pdo->exec("INSERT INTO users (role, first_name, last_name, email, password) VALUES ('teacher', 'Test', 'Lecturer', 'testlecturer_" . time() . "@studyme.test', 'secret')");
        $testUserId = (int)$pdo->lastInsertId();
        $pdo->exec("INSERT INTO teachers (user_id, teacher_number, status) VALUES ($testUserId, 'TCH-TEST-" . time() . "', 'active')");
        $testTeacherId = (int)$pdo->lastInsertId();
        $pdo->exec("INSERT INTO courses (title, slug, teacher_id, academic_level, status) VALUES ('Test Univ Course " . time() . "', 'test-univ-course-" . time() . "', $testTeacherId, '100 Level', 'published')");
        $testCourseId = (int)$pdo->lastInsertId();
        $testTarget = [
            'teacher_id' => $testTeacherId,
            'course_id' => $testCourseId,
            'course_title' => 'Test Univ Course'
        ];
    }

    $teacherId = (int)$testTarget['teacher_id'];
    $courseId = (int)$testTarget['course_id'];

    echo "[PASS] Using Teacher ID: $teacherId, Course ID: $courseId ({$testTarget['course_title']})\n";

    // 2. Test can_teacher_record_for_course
    $canRecord = can_teacher_record_for_course($teacherId, $courseId);
    assert($canRecord !== false, "Teacher should be authorized for assigned course");
    echo "[PASS] can_teacher_record_for_course verified.\n";

    // 3. Test create_voice_video_note (Voice Note)
    $createVoiceRes = create_voice_video_note([
        'teacher_id'       => $teacherId,
        'course_id'        => $courseId,
        'title'            => 'Automated Test Voice Note ' . time(),
        'description'      => 'Testing voice note creation',
        'media_type'       => 'voice',
        'file_path'        => 'uploads/media-notes/teacher_' . $teacherId . '/test_voice.webm',
        'file_size'        => 102400,
        'mime_type'        => 'audio/webm',
        'duration_seconds' => 45,
        'status'           => 'published'
    ]);

    assert($createVoiceRes['success'] === true, "Voice note creation failed: " . ($createVoiceRes['error'] ?? ''));
    $voiceNoteId = $createVoiceRes['id'];
    echo "[PASS] Voice note created with ID: $voiceNoteId\n";

    // 4. Test create_voice_video_note (Video Note)
    $createVidRes = create_voice_video_note([
        'teacher_id'       => $teacherId,
        'course_id'        => $courseId,
        'title'            => 'Automated Test Video Note ' . time(),
        'description'      => 'Testing video note creation',
        'media_type'       => 'video',
        'file_path'        => 'uploads/media-notes/teacher_' . $teacherId . '/test_video.webm',
        'file_size'        => 512000,
        'mime_type'        => 'video/webm',
        'duration_seconds' => 120,
        'status'           => 'published'
    ]);

    assert($createVidRes['success'] === true, "Video note creation failed: " . ($createVidRes['error'] ?? ''));
    $videoNoteId = $createVidRes['id'];
    echo "[PASS] Video note created with ID: $videoNoteId\n";

    // 5. Test get_voice_video_note_by_id
    $noteData = get_voice_video_note_by_id($voiceNoteId);
    assert($noteData !== null, "get_voice_video_note_by_id returned null");
    assert($noteData['media_type'] === 'voice', "media_type mismatch");
    assert((int)$noteData['duration_seconds'] === 45, "duration mismatch");
    echo "[PASS] get_voice_video_note_by_id verified.\n";

    // 6. Test update_voice_video_note
    $updateRes = update_voice_video_note($voiceNoteId, $teacherId, [
        'title' => 'Updated Voice Note Title',
        'status' => 'published'
    ]);
    assert($updateRes['success'] === true, "Update failed: " . ($updateRes['error'] ?? ''));
    $updatedNote = get_voice_video_note_by_id($voiceNoteId);
    assert($updatedNote['title'] === 'Updated Voice Note Title', "Title was not updated");
    echo "[PASS] update_voice_video_note verified.\n";

    // 7. Test get_teacher_voice_video_notes with filter
    $teacherNotes = get_teacher_voice_video_notes($teacherId, ['media_type' => 'voice']);
    assert(count($teacherNotes) >= 1, "Should return at least 1 voice note");
    echo "[PASS] get_teacher_voice_video_notes with media_type filter verified (" . count($teacherNotes) . " notes found).\n";

    // 8. Test get_teacher_notes_stats
    $stats = get_teacher_notes_stats($teacherId);
    assert($stats['total_notes'] >= 2, "Stats should show at least 2 notes");
    assert($stats['total_voice'] >= 1, "Stats should show at least 1 voice note");
    assert($stats['total_video'] >= 1, "Stats should show at least 1 video note");
    echo "[PASS] get_teacher_notes_stats verified (Total: {$stats['total_notes']}, Voice: {$stats['total_voice']}, Video: {$stats['total_video']}).\n";

    // 9. Test delete_voice_video_note
    $deleteRes = delete_voice_video_note($voiceNoteId, $teacherId);
    assert($deleteRes['success'] === true, "Delete failed: " . ($deleteRes['error'] ?? ''));
    $deletedCheck = get_voice_video_note_by_id($voiceNoteId);
    assert($deletedCheck === null, "Deleted note should not be found");
    echo "[PASS] delete_voice_video_note verified.\n";

    // Clean up video test note
    delete_voice_video_note($videoNoteId, $teacherId);

    // 10. Test format_note_duration
    assert(format_note_duration(45) === '00:45', "Duration 45 failed");
    assert(format_note_duration(125) === '02:05', "Duration 125 failed");
    assert(format_note_duration(3665) === '01:01:05', "Duration 3665 failed");
    echo "[PASS] format_note_duration unit tests passed.\n";

    echo "\n=== ALL VOICE & VIDEO NOTES TESTS PASSED SUCCESSFULLY! ===\n";

} catch (Exception $e) {
    echo "[FAIL] Test error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
