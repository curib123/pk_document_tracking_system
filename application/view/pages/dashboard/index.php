<div class="page-heading">
  <div><span class="eyebrow">Overview</span><h1>Dashboard</h1>
       <p>My document requests and approval activity</p></div>
</div>
<div class="row g-3 mb-4">
<?php foreach (['draft'=>'Drafts','pending'=>'Pending','approved'=>'Approved','completed'=>'Completed'] as $key=>$label): ?>
  <div class="col-6 col-xl-3"><div class="stat-card">
    <span class="stat-label"><?= $label ?> Requests</span>
    <strong><?= (int) ($stats[$key] ?? 0) ?></strong>
    <small class="text-muted">My requests</small>
  </div></div>
<?php endforeach; ?>
</div>
<div class="workspace-card"><div class="workspace-card-header">
  <strong>Request Status</strong><p class="text-secondary small mb-0">Based on your saved requests in pk_dts</p>
</div><div class="chart-box"><canvas id="requestChart" aria-label="Request counts by status"></canvas></div></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
 const node = document.getElementById('requestChart');
 if (!node || !window.Chart) return;
 const statuses = <?= json_encode(array_keys($stats), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
 const counts = <?= json_encode(array_values($stats), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
 new Chart(node, {type:'bar',
   data:{labels:statuses.map(x => x.replaceAll('_',' ')),datasets:[{label:'Requests',data:counts,backgroundColor:'#d4263a',borderRadius:6}]},
   options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
});
</script>
