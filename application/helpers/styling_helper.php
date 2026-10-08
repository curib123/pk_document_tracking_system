<?php
declare(strict_types=1);

function pk_styling_config(): array
{
    $config = require dirname(__DIR__) . '/config/styling.php';
    $override = getenv('PK_STYLING_ENABLED');

    // An invalid override stays off rather than interpreting "false" as truthy.
    if ($override !== false && $override !== '') {
        $config['enabled'] = filter_var(
            $override,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) === true;
    }

    $config['enabled'] = ($config['enabled'] ?? false) === true;
    $config['shell'] = ($config['shell'] ?? false) === true;
    $config['default_enabled'] = ($config['default_enabled'] ?? false) === true;
    $config['modules'] = is_array($config['modules'] ?? null)
        ? $config['modules'] : [];

    foreach ($config['modules'] as $module => $enabled) {
        $config['modules'][$module] = $enabled === true;
    }

    return $config;
}

function pk_module_styled(array $config, string $module): bool
{
    if (($config['enabled'] ?? false) !== true) {
        return false;
    }

    $modules = $config['modules'] ?? [];
    return array_key_exists($module, $modules)
        ? $modules[$module] === true
        : ($config['default_enabled'] ?? false) === true;
}
