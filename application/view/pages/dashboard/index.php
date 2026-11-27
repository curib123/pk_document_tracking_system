<?php
// Display in the office's local time zone, independent of PHP/host locale.
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$hour = (int) $now->format('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$firstName = trim((string) ($user['first_name'] ?? ''));
$displayName = $firstName !== '' ? $firstName : ($user['username'] ?? 'there');
$canView = static fn($module) => isset($permissions['*']) || !empty($permissions[$module]['view']);
$documentCounts = array_filter($document_counts, $canView, ARRAY_FILTER_USE_KEY);
$documentLabels = array_map('ucfirst', array_keys($documentCounts));
$canViewRequests = $canView('requests');
$requestLabel = static fn($status) => $status === 'submitted'
  ? 'In Progress' : ucwords(str_replace('_', ' ', $status));
$handoffLink = $canView('transfer') || $metrics['transfers'] > 0
  ? site_url('my-tasks') . '?request_category=hardcopy-transfer&status=handoffs' : NULL;
$metricCards = [];
if ($documentCounts) {
  $metricCards['documents'] = ['Accessible documents', implode(' and ', $documentLabels), 'fa-folder-open', NULL];
  $metricCards['created'] = ['Created by you', 'Within your document access', 'fa-file-circle-plus', NULL];
}
$metricCards['pending_tasks'] = ['Pending approvals', 'View tasks', 'fa-clipboard-check', site_url('my-tasks') . '?status=submitted'];
if ($handoffLink) {
  $metricCards['transfers'] = ['Outstanding handoffs', 'View handoffs', 'fa-arrow-right-arrow-left', $handoffLink];
}
?>
<div class="dashboard-page">
  <section class="dashboard-welcome" aria-labelledby="dashboardGreeting">
    <h1 id="dashboardGreeting"><?= html_escape($greeting) ?>, <?= html_escape($displayName) ?>!</h1>
    <time class="dashboard-date" datetime="<?= $now->format('Y-m-d') ?>">
      <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
      <?= html_escape($now->format('l, F j, Y')) ?>
    </time>
  </section>

  <div class="dashboard-metric-row">
    <?php foreach ($metricCards as $key => [$label, $detail, $icon, $url]): ?>
      <div class="dashboard-stat-card" data-stat-kind="<?= html_escape($key) ?>">
        <span class="dashboard-stat-label"><?= html_escape($label) ?></span>
        <strong data-metric="<?= html_escape($key) ?>"><?= number_format($metrics[$key]) ?></strong>
        <span class="dashboard-stat-icon" aria-hidden="true"><i class="fa-solid <?= html_escape($icon) ?>"></i></span>
        <div class="dashboard-stat-detail">
          <?php if ($url): ?>
            <a class="dashboard-link" href="<?= html_escape($url) ?>">
              <?= html_escape($detail) ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
          <?php else: ?>
            <?= html_escape($detail) ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($documentCounts || $canViewRequests): ?>
    <div class="dashboard-analytics-row">
      <?php if ($documentCounts): ?>
        <section class="dashboard-chart-card" aria-labelledby="documentChartTitle">
          <header class="dashboard-section-header">
            <span class="dashboard-section-icon" aria-hidden="true"><i class="fa-solid fa-folder-tree"></i></span>
            <div>
              <h2 id="documentChartTitle">Document register</h2>
              <p><?= html_escape(implode(' and ', $documentLabels)) ?> records you can access</p>
            </div>
          </header>
          <?php if ($metrics['documents']): ?>
            <div class="chart-box dashboard-chart-box dashboard-chart-box-doughnut" hidden>
              <canvas id="documentTypeChart" role="img" aria-label="Accessible document counts by type"
                data-chart-type="doughnut" data-chart-labels="<?= html_escape(json_encode($documentLabels)) ?>"
                data-chart-counts="<?= html_escape(json_encode(array_values($documentCounts))) ?>"></canvas>
            </div>
          <?php else: ?>
            <p class="dashboard-empty">No accessible documents yet.</p>
          <?php endif; ?>
          <table class="table mb-0 dashboard-breakdown-table" aria-label="Document distribution counts and percentages">
            <thead>
              <tr>
                <th scope="col">Type</th>
                <th scope="col" class="text-end">Count</th>
                <th scope="col" class="text-end">Share</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($documentCounts as $kind => $count): ?>
                <tr>
                  <th scope="row">
                    <a class="dashboard-link" href="<?= html_escape(site_url('documents/' . $kind) . '?folder=all&layout=table') ?>">
                      <?= html_escape(ucfirst($kind)) ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                  </th>
                  <td class="text-end"><?= number_format($count) ?></td>
                  <td class="text-end"><?= $metrics['documents'] ? round(100 * $count / $metrics['documents'], 1) . '%' : '—' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </section>
      <?php endif; ?>

      <?php if ($canViewRequests): ?>
        <section class="dashboard-chart-card dashboard-requests-card" aria-labelledby="requestChartTitle">
          <header class="dashboard-section-header">
            <span class="dashboard-section-icon" aria-hidden="true"><i class="fa-solid fa-chart-column"></i></span>
            <div>
              <h2 id="requestChartTitle">My request status</h2>
              <p>
                <?= $metrics['decided_requests']
                  ? (int) $metrics['completion_rate'] . '% of decided requests approved or completed'
                  : 'No approval decisions yet' ?>
              </p>
            </div>
          </header>
          <?php if ($stats): ?>
            <div class="chart-box dashboard-chart-box dashboard-chart-box-bars" hidden>
              <canvas id="requestChart" role="img" aria-label="My requests by current status" data-chart-type="bar"
                data-chart-labels="<?= html_escape(json_encode(array_map($requestLabel, array_keys($stats)))) ?>"
                data-chart-counts="<?= html_escape(json_encode(array_values($stats))) ?>"></canvas>
            </div>
          <?php else: ?>
            <p class="dashboard-empty">No requests yet.</p>
          <?php endif; ?>
          <footer class="dashboard-request-summary">
            <?php if ($stats): ?>
              <dl class="dashboard-status-summary" aria-label="Request counts">
                <?php foreach ($stats as $status => $count): ?>
                  <div class="badge-status status-<?= html_escape($status) ?>">
                    <dt><?= html_escape($requestLabel($status)) ?></dt>
                    <dd><?= number_format($count) ?></dd>
                  </div>
                <?php endforeach; ?>
              </dl>
            <?php endif; ?>
            <a class="dashboard-link" href="<?= html_escape(site_url('my-requests')) ?>">
              My Requests <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
          </footer>
        </section>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
