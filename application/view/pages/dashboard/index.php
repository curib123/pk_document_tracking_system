<?php
// Display in the office's local time zone, independent of PHP/host locale.
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$hour = (int) $now->format('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$firstName = trim((string) ($user['first_name'] ?? ''));
$displayName = $firstName !== '' ? $firstName : ($user['username'] ?? 'there');
$canView = static fn($module) => isset($permissions['*']) || !empty($permissions[$module]['view']);
$documentCounts = $document_counts;
$documentLabels = array_map('ucfirst', array_keys($documentCounts));
$canViewRequests = $canView('requests');
$requestLabel = static fn($status) => $status === 'submitted'
  ? 'In Progress' : ucwords(str_replace('_', ' ', $status));
$handoffLink = $canView('transfer') || $metrics['transfers'] > 0
  ? site_url('my-tasks') . '?request_category=hardcopy-transfer&status=handoffs' : NULL;
$metricCards = [
  'pending_tasks' => ['Pending approvals', 'View tasks', 'fa-clipboard-check', site_url('my-tasks') . '?status=submitted']
];
if ($handoffLink) {
  $metricCards['transfers'] = ['Outstanding handoffs', 'View handoffs', 'fa-arrow-right-arrow-left', $handoffLink];
}
if ($documentCounts) {
  $metricCards['documents'] = ['Accessible documents', NULL, 'fa-folder-open', NULL];
  $metricCards['created'] = ['Created by you', 'Accessible documents', 'fa-file-circle-plus', NULL];
}
?>
<div class="dashboard-page">
  <section class="dashboard-welcome" aria-labelledby="dashboardGreeting">
    <h1 id="dashboardGreeting"><?= html_escape($greeting) ?>, <span><?= html_escape($displayName) ?></span></h1>
    <div class="dashboard-welcome-actions">
      <time class="dashboard-date" datetime="<?= $now->format('Y-m-d') ?>">
        <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
        <?= html_escape($now->format('l, F j, Y')) ?>
      </time>
      <?php if ($canView('backups')): ?>
        <a class="dashboard-link dashboard-register-link" href="<?= html_escape(site_url('admin/backups')) ?>">
          <i class="fa-solid fa-database" aria-hidden="true"></i>Backup &amp; Restore
        </a>
      <?php endif; ?>
    </div>
  </section>

  <div class="dashboard-metric-row">
    <?php foreach ($metricCards as $key => [$label, $detail, $icon, $url]): ?>
      <div class="dashboard-stat-card<?= $url ? ' dashboard-stat-action' : '' ?>" data-stat-kind="<?= html_escape($key) ?>">
        <div class="dashboard-stat-heading">
          <span class="dashboard-stat-icon" aria-hidden="true"><i class="fa-solid <?= html_escape($icon) ?>"></i></span>
          <span class="dashboard-stat-label"><?= html_escape($label) ?></span>
        </div>
        <strong data-metric="<?= html_escape($key) ?>"><?= number_format($metrics[$key]) ?></strong>
        <?php if ($detail): ?>
          <div class="dashboard-stat-detail">
            <?php if ($url): ?>
              <a class="dashboard-link" href="<?= html_escape($url) ?>">
                <?= html_escape($detail) ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
              </a>
            <?php else: ?>
              <?= html_escape($detail) ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($documentCounts || $canViewRequests): ?>
    <div class="dashboard-analytics-row">
      <?php if ($documentCounts): ?>
        <section class="dashboard-chart-card dashboard-documents-card" aria-labelledby="documentChartTitle">
          <header class="dashboard-section-header">
            <div class="dashboard-section-heading">
              <span class="dashboard-section-icon" aria-hidden="true"><i class="fa-solid fa-folder-tree"></i></span>
              <h2 id="documentChartTitle">Document register</h2>
            </div>
          </header>
          <div class="dashboard-document-breakdown">
            <?php if ($metrics['documents']): ?>
              <div class="chart-box dashboard-chart-box dashboard-chart-box-doughnut" hidden>
                <canvas id="documentTypeChart" role="img" aria-label="Accessible document counts by type"
                  data-chart-type="doughnut" data-chart-labels="<?= html_escape(json_encode($documentLabels)) ?>"
                  data-chart-counts="<?= html_escape(json_encode(array_values($documentCounts))) ?>"></canvas>
                <div class="dashboard-chart-total" aria-hidden="true">
                  <strong><?= number_format($metrics['documents']) ?></strong>
                  <span>Records</span>
                </div>
              </div>
            <?php else: ?>
              <p class="dashboard-empty"><i class="fa-regular fa-folder-open" aria-hidden="true"></i>No accessible documents yet.</p>
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
                  <?php
                    $query = '?folder=all&layout=table' . ($kind === 'external' ? '&status=all' : '');
                    $share = $metrics['documents'] ? round(100 * $count / $metrics['documents'], 1) : NULL;
                  ?>
                  <tr class="dashboard-document-row" data-document-kind="<?= html_escape($kind) ?>">
                    <th scope="row">
                      <a class="dashboard-link" href="<?= html_escape(site_url('documents/' . $kind) . $query) ?>">
                        <span class="dashboard-type-marker" aria-hidden="true"></span>
                        <?= html_escape(ucfirst($kind)) ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                      </a>
                    </th>
                    <td class="text-end"><?= number_format($count) ?></td>
                    <td class="text-end">
                      <div class="dashboard-document-share">
                        <?php if ($share !== NULL): ?>
                          <span class="progress" aria-hidden="true"><span class="progress-bar" style="width: <?= $share ?>%"></span></span>
                        <?php endif; ?>
                        <span><?= $share !== NULL ? $share . '%' : '—' ?></span>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($canViewRequests): ?>
        <section class="dashboard-chart-card dashboard-requests-card" aria-labelledby="requestChartTitle">
          <header class="dashboard-section-header">
            <div class="dashboard-section-heading">
              <span class="dashboard-section-icon" aria-hidden="true"><i class="fa-solid fa-chart-column"></i></span>
              <div>
                <h2 id="requestChartTitle">My request status</h2>
                <p>
                  <?= $metrics['decided_requests']
                    ? (int) $metrics['completion_rate'] . '% of decided requests approved or completed'
                    : 'No approval decisions yet' ?>
                </p>
              </div>
            </div>
            <a class="dashboard-link dashboard-register-link" href="<?= html_escape(site_url('my-requests')) ?>">
              My Requests <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
          </header>
          <?php if ($stats): ?>
            <div class="chart-box dashboard-chart-box dashboard-chart-box-bars" style="--dashboard-series: <?= count($stats) ?>" hidden>
              <canvas id="requestChart" role="img" aria-label="My requests by current status" data-chart-type="bar"
                data-chart-labels="<?= html_escape(json_encode(array_map($requestLabel, array_keys($stats)))) ?>"
                data-chart-counts="<?= html_escape(json_encode(array_values($stats))) ?>"></canvas>
            </div>
          <?php else: ?>
            <p class="dashboard-empty"><i class="fa-regular fa-file-lines" aria-hidden="true"></i>No requests yet.</p>
          <?php endif; ?>
          <?php if ($stats): ?>
            <footer class="dashboard-request-summary">
              <dl class="dashboard-status-summary" aria-label="Request counts">
                <?php foreach ($stats as $status => $count): ?>
                  <div class="badge-status status-<?= html_escape($status) ?>">
                    <dt><?= html_escape($requestLabel($status)) ?></dt>
                    <dd><?= number_format($count) ?></dd>
                  </div>
                <?php endforeach; ?>
              </dl>
            </footer>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
