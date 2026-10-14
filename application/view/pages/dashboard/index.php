<?php
// Display in the office's local time zone, independent of PHP/host locale.
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$hour = (int) $now->format('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$greetingIcon = $hour < 12 ? 'fa-sun' : ($hour < 17 ? 'fa-cloud-sun' : 'fa-moon');
$firstName = trim((string) ($user['first_name'] ?? ''));
$displayName = $firstName !== '' ? $firstName : ($user['username'] ?? 'there');
?>
<div class="dashboard-page">
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
      <span class="dashboard-date-icon"><i class="fa-regular fa-calendar-days" aria-hidden="true"></i></span>
      <div>
        <strong><?= html_escape($now->format('l')) ?></strong>
        <small><?= html_escape($now->format('F j, Y')) ?></small>
      </div>
    </div>
    <span class="dashboard-welcome-decoration" aria-hidden="true"></span>
  </section>
  <div class="row g-3 mb-4 dashboard-metric-row">
    <?php $metricIcons = [
      'documents' => 'fa-folder-open',
      'created' => 'fa-file-circle-plus',
      'pending_tasks' => 'fa-clipboard-check',
      'transfers' => 'fa-arrow-right-arrow-left'
    ]; ?>
    <?php foreach ([
      'documents' => ['Accessible documents', 'All authorized records'],
      'created' => ['Created by you', 'Within your document access'],
      'pending_tasks' => ['Pending approvals', 'Assigned to you or your role'],
      'transfers' => ['Outstanding handoffs', 'Your dispatch / receipt tasks']
    ] as $key => $info): ?>
      <div class="col-6 col-xl-3">
        <div class="stat-card dashboard-stat-card h-100" data-stat-kind="<?= html_escape($key) ?>">
          <span class="dashboard-stat-icon" aria-hidden="true"><i
              class="fa-solid <?= html_escape($metricIcons[$key]) ?>"></i></span>
          <span class="stat-label"><?= html_escape($info[0]) ?></span>
          <strong data-metric="<?= $key ?>"><?= (int) $metrics[$key] ?></strong>
          <small class="text-muted"><?= html_escape($info[1]) ?></small>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="row g-3 g-xxl-4 mb-4 dashboard-analytics-row">
    <section class="col-12 col-xl-5" aria-labelledby="documentChartTitle">
      <div class="workspace-card dashboard-chart-card h-100">
        <div class="workspace-card-header dashboard-section-header">
          <span class="dashboard-section-icon" aria-hidden="true"><i class="fa-solid fa-folder-tree"></i></span>
          <div>
            <h2 class="fs-6 fw-bold mb-1" id="documentChartTitle">Your document register</h2>
            <p class="text-secondary small mb-0">All time · same access rules as the document registers</p>
          </div>
        </div>
        <?php if (array_sum($document_counts)): ?>
          <div class="chart-box dashboard-chart-box dashboard-chart-box-doughnut"><canvas id="documentTypeChart"
              role="img" aria-label="Accessible hardcopy and softcopy document counts" data-chart-type="doughnut"
              data-chart-labels='["Hardcopy","Softcopy"]'
              data-chart-counts="<?= html_escape(json_encode(array_values($document_counts))) ?>"></canvas></div>
        <?php else: ?>
          <p class="empty-state mb-0">No accessible documents yet.</p><?php endif; ?>
        <table class="table mb-0 dashboard-breakdown-table" aria-label="Document distribution counts and percentages">
          <thead>
            <tr>
              <th>Type</th>
              <th class="text-end">Count</th>
              <th class="text-end">Share</th>
            </tr>
          </thead>
          <tbody><?php foreach ($document_counts as $kind => $count): ?>
              <tr>
                <td><?= ucfirst($kind) ?></td>
                <td class="text-end"><?= (int) $count ?></td>
                <td class="text-end"><?= $metrics['documents'] ? round(100 * $count / $metrics['documents'], 1) : 0 ?>%</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <section class="col-12 col-xl-7" aria-labelledby="requestChartTitle">
      <div class="workspace-card dashboard-chart-card dashboard-requests-card h-100">
        <div class="workspace-card-header dashboard-section-header">
          <span class="dashboard-section-icon" aria-hidden="true"><i class="fa-solid fa-chart-column"></i></span>
          <div>
            <h2 class="fs-6 fw-bold mb-1" id="requestChartTitle">My request status</h2>
            <p class="text-secondary small mb-0">All time · <?= (int) $metrics['completion_rate'] ?>% of finalized
              requests approved or completed</p>
          </div>
        </div>
        <?php if (array_sum($stats)): ?>
          <div class="chart-box dashboard-chart-box dashboard-chart-box-bars"><canvas id="requestChart" role="img"
              aria-label="My requests by current status" data-chart-type="bar"
              data-chart-labels="<?= html_escape(json_encode(array_map('ucfirst', array_keys($stats)))) ?>"
              data-chart-counts="<?= html_escape(json_encode(array_values($stats))) ?>"></canvas></div>
        <?php else: ?>
          <p class="empty-state mb-0">No requests submitted yet.</p><?php endif; ?>
        <div class="dashboard-status-summary" aria-label="Request counts">
          <?php foreach ($stats as $status => $count): ?>
            <span class="badge-status status-<?= html_escape($status) ?>"><?= html_escape(ucfirst($status)) ?>:
              <?= (int) $count ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </div>
  <section class="workspace-card dashboard-activity-card" aria-labelledby="recentActivityTitle">
    <div class="workspace-card-header dashboard-section-header">
      <span class="dashboard-section-icon" aria-hidden="true"><i class="fa-solid fa-clock-rotate-left"></i></span>
      <h2 class="fs-6 fw-bold mb-0" id="recentActivityTitle">Recent document activity</h2>
    </div>
    <?php if (!$recent_activities): ?>
      <p class="empty-state mb-0">No recent document activity in your authorized scope.</p>
    <?php else: ?>
      <div class="table-responsive dashboard-activity-scroll">
        <table class="table mb-0 dashboard-activity-table">
          <thead>
            <tr>
              <th scope="col">Document</th>
              <th scope="col">Type</th>
              <th scope="col">Action</th>
              <th scope="col">Date</th>
            </tr>
          </thead>
          <tbody><?php foreach ($recent_activities as $activity): ?>
              <tr>
                <td class="dashboard-activity-title">
                  <div class="dashboard-activity-label"><span class="dashboard-activity-file-icon" aria-hidden="true"><i
                        class="fa-regular <?= $activity['domain'] === 'softcopy' ? 'fa-file-lines' : 'fa-folder-closed' ?>"></i></span><span><?= html_escape($activity['title']) ?></span>
                  </div>
                </td>
                <td><span
                    class="dashboard-type-chip dashboard-type-<?= html_escape($activity['domain']) ?>"><?= html_escape(ucfirst($activity['domain'])) ?></span>
                </td>
                <td><span
                    class="dashboard-action-name"><?= html_escape(ucwords(str_replace('_', ' ', $activity['action']))) ?></span>
                </td>
                <td class="dashboard-activity-date"><?= html_escape($activity['created_at']) ?></td>
              </tr><?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>