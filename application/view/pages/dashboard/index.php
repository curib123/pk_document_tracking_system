<?php
// Display in the office's local time zone, independent of PHP/host locale.
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$hour = (int) $now->format('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$greetingIcon = $hour < 12 ? 'fa-sun' : ($hour < 17 ? 'fa-cloud-sun' : 'fa-moon');
$firstName = trim((string) ($user['first_name'] ?? ''));
$displayName = $firstName !== '' ? $firstName : ($user['username'] ?? 'there');
?>
<section class="dashboard-welcome" aria-labelledby="dashboardGreeting">
  <div class="dashboard-welcome-content">
    <span class="dashboard-welcome-kicker">
      <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
      PK Document Control Workspace
    </span>
    <h1 id="dashboardGreeting">
      <?= html_escape($greeting) ?>, <span><?= html_escape($displayName) ?>!</span>
      <i class="fa-solid <?= html_escape($greetingIcon) ?> dashboard-welcome-symbol" aria-hidden="true"></i>
    </h1>
    <p>Welcome back. Here's your document activity and requests at a glance.</p>
  </div>
  <div class="dashboard-welcome-date" aria-label="Today's date">
    <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
    <div>
      <strong><?= html_escape($now->format('l')) ?></strong>
      <small><?= html_escape($now->format('F j, Y')) ?></small>
    </div>
  </div>
</section>
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
