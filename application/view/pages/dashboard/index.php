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
<?php foreach ([
 'documents'=>['Accessible documents','All authorized records'],
 'created'=>['Created by you','Within your document access'],
 'pending_tasks'=>['Pending approvals','Assigned to you or your role'],
 'transfers'=>['Outstanding handoffs','Your dispatch / receipt tasks']
] as $key=>$info): ?>
 <div class="col-6 col-xl-3"><div class="stat-card h-100">
  <span class="stat-label"><?= html_escape($info[0]) ?></span>
  <strong data-metric="<?= $key ?>"><?= (int)$metrics[$key] ?></strong>
  <small class="text-muted"><?= html_escape($info[1]) ?></small>
 </div></div>
<?php endforeach; ?>
</div>
<div class="row g-4 mb-4">
 <section class="col-12 col-xl-5" aria-labelledby="documentChartTitle">
  <div class="workspace-card h-100">
   <div class="workspace-card-header"><h2 class="fs-6 fw-bold mb-1" id="documentChartTitle">Your document register</h2>
    <p class="text-secondary small mb-0">All time · same access rules as the document registers</p></div>
   <?php if (array_sum($document_counts)): ?>
   <div class="chart-box"><canvas id="documentTypeChart" role="img" aria-label="Accessible hardcopy and softcopy document counts"
       data-chart-type="doughnut" data-chart-labels='["Hardcopy","Softcopy"]'
       data-chart-counts="<?= html_escape(json_encode(array_values($document_counts))) ?>"></canvas></div>
   <?php else: ?><p class="empty-state mb-0">No accessible documents yet.</p><?php endif; ?>
   <table class="table mb-0" aria-label="Document distribution counts and percentages">
    <thead><tr><th>Type</th><th class="text-end">Count</th><th class="text-end">Share</th></tr></thead>
    <tbody><?php foreach ($document_counts as $kind=>$count): ?>
     <tr><td><?= ucfirst($kind) ?></td><td class="text-end"><?= (int)$count ?></td>
      <td class="text-end"><?= $metrics['documents']?round(100*$count/$metrics['documents'],1):0 ?>%</td></tr>
    <?php endforeach; ?></tbody>
   </table>
  </div>
 </section>
 <section class="col-12 col-xl-7" aria-labelledby="requestChartTitle">
  <div class="workspace-card h-100">
   <div class="workspace-card-header"><h2 class="fs-6 fw-bold mb-1" id="requestChartTitle">My request status</h2>
    <p class="text-secondary small mb-0">All time · <?= (int)$metrics['completion_rate'] ?>% of finalized requests approved or completed</p></div>
   <?php if (array_sum($stats)): ?>
   <div class="chart-box"><canvas id="requestChart" role="img" aria-label="My requests by current status"
      data-chart-type="bar" data-chart-labels="<?= html_escape(json_encode(array_map('ucfirst',array_keys($stats)))) ?>"
      data-chart-counts="<?= html_escape(json_encode(array_values($stats))) ?>"></canvas></div>
   <?php else: ?><p class="empty-state mb-0">No requests submitted yet.</p><?php endif; ?>
   <div class="dashboard-status-summary p-3" aria-label="Request counts">
    <?php foreach ($stats as $status=>$count): ?>
     <span class="badge-status status-<?= html_escape($status) ?>"><?= html_escape(ucfirst($status)) ?>: <?= (int)$count ?></span>
    <?php endforeach; ?>
   </div>
  </div>
 </section>
</div>
<section class="workspace-card" aria-labelledby="recentActivityTitle">
 <div class="workspace-card-header"><h2 class="fs-6 fw-bold mb-0" id="recentActivityTitle">Recent document activity</h2></div>
 <?php if (!$recent_activities): ?><p class="empty-state mb-0">No recent document activity in your authorized scope.</p>
 <?php else: ?>
 <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Document</th><th>Type</th><th>Action</th><th>Date</th></tr></thead>
 <tbody><?php foreach ($recent_activities as $activity): ?><tr>
  <td><?= html_escape($activity['title']) ?></td><td><?= html_escape(ucfirst($activity['domain'])) ?></td>
  <td><?= html_escape(ucwords(str_replace('_',' ',$activity['action']))) ?></td>
  <td><?= html_escape($activity['created_at']) ?></td>
 </tr><?php endforeach; ?></tbody></table></div>
 <?php endif; ?>
</section>
