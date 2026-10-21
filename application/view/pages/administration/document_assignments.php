<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$domain = $selected_domain;
$folderQuery = ['domain' => $domain];
if ($folder_value !== '')
  $folderQuery['folder'] = $folder_value;
?>

<section class="document-assignments-page" aria-label="Document assignments">

<nav class="tab-bar assignments-tabs" aria-label="Assignment document type">
  <?php foreach (['hardcopy' => 'Hardcopy / Places', 'softcopy' => 'Softcopy / Categories'] as $key => $label): ?>
    <a class="tab-link <?= $domain === $key ? 'active' : '' ?>"
      href="<?= site_url('admin/document-assignments') . '?domain=' . $key ?>" <?= $domain === $key ? 'aria-current="page"' : '' ?>>
      <?= html_escape($label) ?>
    </a>
  <?php endforeach; ?>
</nav>

  <div class="d-flex justify-content-end mb-3">
    <button type="button" class="btn btn-primary js-edit" data-bs-toggle="modal" data-bs-target="#assignmentModal"
      data-target="#assignmentForm" data-title="Assign Document"
      data-record="<?= html_escape(json_encode(['recipient_id' => (int) $table['owner']])) ?>">Assign Document</button>
  </div>

  <?php if ($domain === 'hardcopy'): ?>
    <section class="workspace-card assignments-location-card">
      <form class="assignments-location-form" method="post"
        action="<?= site_url('admin/document-assignments/location') ?>"
        data-confirm="Assign all active Hardcopy documents in this Location to the selected user?" autocomplete="off">
        <div class="assignments-location-fields row g-3 align-items-end">
          <div class="col-lg-5">
            <label class="form-label" for="locationAssignmentPlace">Location</label>
            <select class="form-select" id="locationAssignmentPlace" name="location_id" data-searchable required
              autocomplete="off">
              <option value="">Choose Location</option>
              <?php foreach ($location_options as $place): ?>
                <option value="<?= (int) $place['id'] ?>"><?= html_escape($place['name'] . ' · Sequence ' . $place['sequence_number']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-lg-5">
            <label class="form-label" for="locationAssignmentUser">Assigned User</label>
            <select class="form-select" id="locationAssignmentUser" name="recipient_id" data-searchable required
              autocomplete="off">
              <option value="">Choose active user</option>
              <?php foreach ($users_list as $person): ?>
                <option value="<?= (int) $person['id'] ?>" <?= (int) $table['owner'] === (int) $person['id'] ? 'selected' : '' ?>><?= html_escape($person['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-lg-2">
            <button class="btn btn-primary w-100" type="submit">
              <i class="fa-solid fa-user-check me-1" aria-hidden="true"></i> Assign All
            </button>
          </div>
        </div>
        <input type="hidden" name="confirmed" value="no">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>"
          value="<?= $this->security->get_csrf_hash() ?>">
      </form>
    </section>
  <?php else: ?>
    <section class="workspace-card assignments-location-card">
      <form class="assignments-location-form" method="post" id="categoryAssignmentForm"
        action="<?= site_url('admin/document-assignments/category') ?>"
        data-confirm="Assign all active Softcopy documents in this category and all its subcategories to this user?" autocomplete="off">
        <div class="assignments-location-fields row g-3 align-items-end">
          <div class="col-lg-5">
            <label class="form-label" for="categoryAssignmentCategory">Category / Subcategory</label>
            <select class="form-select" id="categoryAssignmentCategory" name="category_id" data-searchable required>
              <option value="">Choose a parent category or subcategory</option>
              <?php foreach ($category_options['nodes'] as $key => $category):
                $chain = [$category['name']];
                $parent = $category['parent'];
                while ($parent !== '' && isset($category_options['nodes'][$parent])) {
                  array_unshift($chain, $category_options['nodes'][$parent]['name']);
                  $parent = $category_options['nodes'][$parent]['parent'];
                }
                $categoryId = (int) substr($key, 9); ?>
                <option value="<?= $categoryId ?>" <?= $folder_value === $key ? 'selected' : '' ?>><?= html_escape(implode(' / ', $chain)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-lg-5">
            <label class="form-label" for="categoryAssignmentUser">Assigned User</label>
            <select class="form-select" id="categoryAssignmentUser" name="recipient_id" data-searchable required>
              <option value="">Choose an active user</option>
              <?php foreach ($users_list as $person): ?>
                <option value="<?= (int) $person['id'] ?>" <?= (int) $table['owner'] === (int) $person['id'] ? 'selected' : '' ?>><?= html_escape($person['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-lg-2">
            <button class="btn btn-primary w-100" type="submit">
              <i class="fa-solid fa-user-check me-1" aria-hidden="true"></i> Assign All
            </button>
          </div>
        </div>
        <input type="hidden" name="confirmed" value="no">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
      </form>
    </section>
  <?php endif; ?>
  <div class="assignments-register">
    <?php
    $dt_path = 'admin/document-assignments';
    $dt_extra_params = ['domain' => $domain, 'folder' => $folder_value, 'layout' => $table['layout']];
    $dt_columns = ['title' => 'Document', 'assignee' => 'Assigned To', 'assigned_date' => 'Updated'];
    $dt_rows = [];
    foreach ($assignment_rows as $item) {
      $details = [
        'Document Type' => ucfirst($domain),
        'Document' => $item['title'],
        'Assigned To' => $item['assignee'],
        'Updated' => $item['assigned_date']
      ];
      $dt_rows[] = [
        'id' => $item['document_id'],
        'cells' => $item,
        'display' => $details,
        'record' => [
          'document_domain' => $domain,
          'softcopy_id' => $domain === 'softcopy' ? $item['document_id'] : '',
          'hardcopy_id' => $domain === 'hardcopy' ? $item['document_id'] : '',
          'recipient_id' => $item['recipient_id']
        ],
        'buttons' => [['type' => 'view'], ['type' => 'edit', 'modal' => '#assignmentModal', 'form' => '#assignmentForm', 'title' => 'Edit Assignment']]
      ];
    }
    $dt_q = $table['q'];
    $dt_filter = '';
    $dt_page = $table['page'];
    $dt_limit = $table['limit'];
    $dt_total = $total;
    $dt_sort = $table['sort'];
    $dt_dir = strtolower($table['dir']);
    $dt_sortable = array_keys($dt_columns);
    $dt_filters = ['owner' => ['' => 'All Assigned Users'] + array_column($users_list, 'name', 'id')];
    $dt_filter_values = ['owner' => $table['owner']];
    $dt_badges = [];
    $dt_create = '';
    $this->load->view('reusable_components/reusable_datatable', compact(
      'dt_path',
      'dt_extra_params',
      'dt_columns',
      'dt_rows',
      'dt_q',
      'dt_filter',
      'dt_page',
      'dt_limit',
      'dt_total',
      'dt_sort',
      'dt_dir',
      'dt_sortable',
      'dt_filters',
      'dt_filter_values',
      'dt_badges',
      'dt_create'
    ));
    ?>
  </div>
</section>

<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="assignmentModal" tabindex="-1"
  aria-labelledby="assignmentModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form id="assignmentForm" method="post" action="<?= site_url('admin/document-assignments/save') ?>"
        data-confirm="Assign this document to the selected user?" autocomplete="off">
        <div class="modal-header">
          <div class="assignment-modal-heading">
            <span class="assignment-modal-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="7" r="3"></circle><path d="M3 20v-2a6 6 0 0 1 10-4.5M15 17l2 2 4-5"></path>
              </svg>
            </span>
            <div>
              <h2 class="modal-title" id="assignmentModalTitle">Assign Document</h2>
              <p class="assignment-modal-context"><?= $domain === 'hardcopy' ? 'Hardcopy / Places' : 'Softcopy / Categories' ?></p>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="assignments-modal-grid">
            <input type="hidden" name="document_domain" value="<?= html_escape($domain) ?>">
            <input type="hidden" name="folder" value="<?= html_escape($folder_value) ?>">
            <div class="assignment-field" data-document-domain="<?= html_escape($domain) ?>">
              <label class="form-label" for="<?= $domain === 'hardcopy' ? 'assignmentHardcopy' : 'assignmentSoftcopy' ?>"><?= ucfirst($domain) ?> Document</label>
              <select class="form-select" id="<?= $domain === 'hardcopy' ? 'assignmentHardcopy' : 'assignmentSoftcopy' ?>"
                name="<?= $domain ?>_id" data-searchable required autocomplete="off">
                <option value="">Choose <?= $domain ?> from this folder</option>
                <?php foreach ($domain === 'hardcopy' ? $hardcopy_options : $softcopy_options as $doc): ?>
                  <option value="<?= (int) $doc['id'] ?>"><?= html_escape(($domain === 'softcopy' ? $doc['document_number'] . ' · ' : '') . $doc['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="assignment-field">
              <label class="form-label" for="assignmentUser">Assigned User</label>
              <select class="form-select" id="assignmentUser" name="recipient_id" data-searchable required autocomplete="off">
                <option value="">Choose an active user</option>
                <?php foreach ($users_list as $person): ?>
                  <option value="<?= (int) $person['id'] ?>"><?= html_escape($person['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="assignments-help">
              <p class="form-text mb-0">Use the page search or a specific folder to narrow the document choices.</p>
            </div>
          </div>
        </div>
        <input type="hidden" name="confirmed" value="no">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
        <div class="modal-footer">
          <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit">Save Assignment</button>
        </div>
      </form>
    </div>
  </div>
</div>
