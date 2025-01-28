<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/coursesoverview:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW, // Admins haben standardmäßig Zugriff.
        ],
    ],
];