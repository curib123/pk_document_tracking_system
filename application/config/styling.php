<?php
declare(strict_types=1);

// Diri ra i-toggle ang design; workflows and permissions are not affected.
return [
    'enabled' => true,
    'shell' => true,
    'default_enabled' => false,
    'theme' => 'red',
    'modules' => [
        'account' => true,
        'dashboard' => true,
        'softcopy' => false,
        'hardcopy' => false,
        'requests' => false,
        'my_requests' => false,
        'my_tasks' => false,
        'transfers' => false,
        'access' => false,
        'assignments' => false,
        'disposals' => false,
        'files' => false,
        'workflows' => false,
        'users' => false,
        'roles' => false,
        'permissions' => false,
        'areas' => false,
        'specifics' => false,
        'assets' => false,
        'locations' => false,
        'categories' => false,
        'notifications' => false,
        'audit' => false,
        'history' => false,
        'sequences' => false,
        'settings' => false,
    ],
];
