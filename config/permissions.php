<?php

function user_can($permission, $role = null) {
    if ($role === null) {
        $role = function_exists('current_user_role') ? current_user_role() : 'guest';
    }

    if ($role === 'admin') {
        return true;
    }

    $permissions = [
        'teacher' => [
            'create_course', 'edit_course', 'create_lesson', 'create_quiz',
            'grade_assignments', 'view_earnings', 'upload_resources'
        ],
        'student' => [
            'view_enrolled_courses', 'take_quizzes', 'submit_assignments',
            'ask_ai', 'download_certificate', 'edit_profile'
        ]
    ];

    return in_array($permission, $permissions[$role] ?? [], true);
}
