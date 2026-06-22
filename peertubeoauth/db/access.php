<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'repository/peertubeoauth:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
            // IMPORTANT: 'user' is the base role automatically assigned
            // to every authenticated user, independent of any course
            // role. Without this, teachers cannot create a personal
            // repository instance in their own profile (CONTEXT_USER),
            // because that context falls back to the 'user' role's
            // capabilities, not the course-level 'editingteacher' role.
            // This mirrors how repository_nextcloud grants its :view
            // capability to 'user' for the same reason.
            'user' => CAP_ALLOW,
        ],
    ],
];
