<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<h1 class="h3 mb-2">Dashboard</h1>
<p class="text-body-secondary">Your document control workspace, rendered directly by CodeIgniter.</p>
<div class="row g-3 mb-4">
    <?php
    $titles = [
        'softcopy' => 'Softcopy documents',
        'hardcopy' => 'Hardcopy documents',
        'my_requests' => 'My requests',
    ];
    foreach ($titles as $key => $title):
        if (!isset($summary[$key]) || !is_array($summary[$key])) {
            continue;
        }
        $count = 0;
        foreach ($summary[$key] as $entry) {
            $count += (int) ($entry['total'] ?? 0);
        }
    ?>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-body-secondary mb-1"><?= ui_escape($title) ?></p>
                    <p class="display-6 mb-0"><?= $count ?></p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<div class="card">
    <div class="card-body">
        <h2 class="h5">Browse your records</h2>
        <p class="mb-0 text-body-secondary">Use the navigation to open permitted records. Catalogue management uses ordinary PHP forms, with validation and database transactions in existing services.</p>
    </div>
</div>
