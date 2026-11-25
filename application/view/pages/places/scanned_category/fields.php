<div class="col-12">
  <label class="form-label" for="scannedFolderName">Folder Name</label>
  <input class="form-control" id="scannedFolderName" name="name" maxlength="150" required <?= !$can('edit') && !$can('add') ? 'readonly' : '' ?>>
</div>
<div class="col-12">
  <label class="form-label" for="scannedFolderParent">Parent Folder (optional)</label>
  <?php if ($can('edit') || $can('add')): ?>
    <select class="form-select" id="scannedFolderParent" name="parent_id" data-searchable>
      <option value="">No parent - create a root folder</option>
      <?php foreach ($options['scanned_categories'] as $category): ?>
        <option value="<?= (int) $category['id'] ?>"><?= html_escape($category['path']) ?></option>
      <?php endforeach; ?>
    </select>
  <?php else: ?><input type="hidden" name="parent_id"><p class="form-text">The existing parent folder is preserved.</p><?php endif; ?>
</div>
<?php if ($can('allocate')): ?>
<div class="col-12">
  <input type="hidden" name="save_allocations" value="1">
  <span class="form-label d-block" id="scannedAllocationLabel">Allocate to Users</span>
  <div class="border rounded p-2 overflow-auto" style="max-height:16rem" role="group" aria-labelledby="scannedAllocationLabel">
    <?php foreach ($options['users'] as $target): ?>
      <label class="form-check d-block">
        <input class="form-check-input" type="checkbox" name="user_ids[]" value="<?= (int) $target['id'] ?>">
        <?= html_escape($target['name']) ?>
      </label>
    <?php endforeach; ?>
  </div>
  <p class="form-text">Selected users can choose this folder for scanned uploads. Nested folders inherit this allocation unless they have their own. Leave all unchecked to inherit the parent allocation, or share a root folder with everyone. Document access remains controlled by document permissions.</p>
</div>
<?php endif; ?>
