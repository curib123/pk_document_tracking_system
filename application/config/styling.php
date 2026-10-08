<?php
declare(strict_types=1);

// Diri ra i-toggle ang design; workflows and permissions are not affected.
return [
    'enabled' => true,
    'shell' => true,
    'default_enabled' => true,
    'theme' => 'red',
    'modules' => [
        'account' => true, 'dashboard' => true, 'softcopy' => true, 'hardcopy' => true, 'requests' => true, 'my_requests' => true, 'my_tasks' => true, 'transfers' => true, 'access' => true, 'assignments' => true, 'disposals' => true, 'files' => true, 'workflows' => true, 'users' => true, 'roles' => true, 'permissions' => true, 'areas' => true, 'specifics' => true, 'assets' => true, 'locations' => true, 'categories' => true, 'notifications' => true, 'audit' => true, 'history' => true, 'sequences' => true, 'settings' => true,
    ],
];