<div class="page-heading">
    <div><span class="eyebrow">Overview</span><h1>Dashboard</h1><p>Track your document requests and assigned actions at a glance.</p></div>
</div>
<div class="row g-3 mb-4">
    <?php foreach (['pending' => 'Pending', 'draft' => 'Drafts', 'approved' => 'Approved', 'returned' => 'Returned'] as $key => $label): ?>
        <div class="col-6 col-xl-3"><div class="stat-card"><span class="stat-label"><?= $label ?> Requests</span><strong><?= (int) $stats[$key] ?></strong><span class="badge-status status-<?= $key ?>"><?= $key === 'pending' ? 'Awaiting review' : 'My requests' ?></span></div></div>
    <?php endforeach; ?>
</div>
<div class="row g-3">
    <div class="col-lg-7"><div class="workspace-card h-100">
        <div class="workspace-card-header"><strong>My Request Status</strong><p class="text-secondary small mb-0">Distribution of your submitted and draft requests</p></div>
        <div class="chart-box"><canvas id="requestChart" aria-label="Chart showing requests by status" role="img"></canvas></div>
    </div></div>
    <div class="col-lg-5"><div class="workspace-card h-100">
        <div class="workspace-card-header"><strong>Quick Actions</strong><p class="text-secondary small mb-0">Frequently used document workflows</p></div>
        <div class="p-4 d-grid gap-3">
            <?php if (!empty($permissions['requests']['view']) || isset($permissions['*'])): ?>
                <a class="btn btn-light text-start" href="<?= site_url('my-requests/softcopy') ?>"><i class="fa-solid fa-paper-plane me-2 text-danger"></i> Open My Requests</a>
            <?php endif; ?>
            <?php if (!empty($permissions['tasks']['view']) || isset($permissions['*'])): ?>
                <a class="btn btn-light text-start" href="<?= site_url('my-tasks/softcopy') ?>"><i class="fa-solid fa-list-check me-2 text-danger"></i> Assigned Tasks <span class="float-end badge text-bg-danger"><?= (int) $assigned_count ?></span></a>
            <?php endif; ?>
            <?php if (!empty($permissions['hardcopy']['view']) || isset($permissions['*'])): ?>
                <a class="btn btn-light text-start" href="<?= site_url('documents/hardcopy') ?>"><i class="fa-regular fa-folder me-2 text-danger"></i> Browse Documents</a>
            <?php endif; ?>
        </div>
    </div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var canvas = document.getElementById('requestChart');
    if (!canvas || !window.Chart) return;
    var counts = <?= json_encode(array_values($stats), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    new Chart(canvas, {
        type: 'doughnut',
        data: {labels: <?= json_encode(array_map('ucfirst', array_keys($stats))) ?>,
            datasets: [{data: counts, backgroundColor: ['#d9a72d','#6f86a7','#9674da','#21aa7a','#de596a'], borderWidth: 0, hoverOffset: 5}]},
        options: {cutout: '72%', plugins: {legend: {position: 'bottom', labels: {usePointStyle: true, padding: 18}}}}
    });
});
</script>
