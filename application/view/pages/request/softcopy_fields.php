<?php
defined('BASEPATH') OR exit('No direct script access allowed');
// Shared fieldset for both workflow requests and authorized direct softcopy.
// Business logic, authorization and approval routing remain server-side.
$softcopyDirect = !empty($softcopyDirect);
?>
<div class="col-md-6">
    <label class="form-label" for="reqType">Softcopy Action</label>
    <select class="form-select" name="type" id="reqType" required>
        <option value="softcopy_create">Create Softcopy</option>
        <option value="softcopy_revise">Revise Softcopy</option>
        <option value="softcopy_cancel">Cancel Softcopy</option>
    </select>
</div>
<div class="col-md-6">
    <label class="form-label" for="reqSubject">Subject / Reason</label>
    <input class="form-control" name="subject" id="reqSubject" maxlength="255" required>
</div>
<div class="col-md-6">
    <label class="form-label" for="reqSoftcopy">Existing Softcopy Document</label>
    <select class="form-select" name="softcopy_id" id="reqSoftcopy" data-searchable>
        <option value="">Choose a document</option>
        <?php foreach ($softcopy_options as $item): ?>
            <option value="<?= (int)$item['id'] ?>">
                <?= html_escape($item['document_number'].' · '.$item['title']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-6">
    <label class="form-label" for="reqTitle">Proposed Document Title</label>
    <input class="form-control" name="title" id="reqTitle" maxlength="255">
</div>
<div class="col-md-6">
    <label class="form-label" for="reqNumber">Document Number</label>
    <input class="form-control" name="document_number" id="reqNumber" maxlength="100">
</div>
<div class="col-md-6">
    <label class="form-label" for="reqSeries">Series Number <span class="optional-label">(optional)</span></label>
    <input class="form-control" name="series_number" id="reqSeries" maxlength="100">
</div>
<div class="col-md-6">
    <label class="form-label" for="reqCategory">Category</label>
    <select class="form-select" name="category_id" id="reqCategory" data-searchable>
        <option value="">Choose a category</option>
        <?php foreach ($category_options as $category): ?>
            <option value="<?= (int)$category['id'] ?>"><?= html_escape($category['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-6">
    <label class="form-label" for="newRevisionLevel">New Revision Level</label>
    <input class="form-control" id="newRevisionLevel" name="new_revision_level" maxlength="30">
</div>
<div class="col-md-6">
    <label class="form-label" for="revisionPages">Number of Pages</label>
    <input class="form-control" type="number" id="revisionPages" name="page_number" min="1" value="1">
</div>
<div class="col-md-6">
    <label class="form-label" for="revisionEffective">Effective Date</label>
    <input class="form-control" type="date" id="revisionEffective" name="effective_date">
</div>
<div class="col-md-6">
    <label class="form-label" for="revisionReceived">Received Date</label>
    <input class="form-control" type="date" id="revisionReceived" name="date_received">
</div>
<div class="col-md-6">
    <label class="form-label" for="revisionReleased">Released Date</label>
    <input class="form-control" type="date" id="revisionReleased" name="date_released">
</div>
<div class="col-12">
    <label class="form-label" for="revisionFile">Controlled Revision File <span class="optional-label">(15 MB maximum)</span></label>
    <input class="form-control" id="revisionFile" type="file" name="revision_attachment"
           accept=".pdf,.txt,.png,.jpg,.jpeg,.docx,.xlsx">
    <small class="text-secondary">
        <?= $softcopyDirect
            ? 'Required to revise directly; the file is approved immediately after saving.'
            : 'Required on new revision requests. When editing, an already-staged file can be retained.' ?>
    </small>
</div>
<div class="col-12">
    <label class="form-label" for="reqRemark">Remarks <span class="optional-label">(optional)</span></label>
    <textarea class="form-control" id="reqRemark" name="remarks" rows="3"></textarea>
</div>
