<?php
defined('BASEPATH') OR exit('No direct script access allowed');
// Shared fieldset for both workflow requests and authorized direct softcopy.
// Business logic, authorization and approval routing remain server-side.
$softcopyDirect = !empty($softcopyDirect);
$numbering_options = $numbering_options ?? [];
?>
<div class="col-md-6">
    <label class="form-label" for="reqType">Softcopy Action</label>
    <select class="form-select" name="type" id="reqType" required autocomplete="off">
        <option value="softcopy_create">Create Softcopy</option>
        <option value="softcopy_revise">Revise Softcopy</option>
    </select>
</div>
<div class="col-md-6">
    <label class="form-label" for="reqSubject">Subject / Reason</label>
    <textarea class="form-control" name="subject" id="reqSubject" rows="1" maxlength="255" required
        autocomplete="off"></textarea>
</div>
<div class="col-md-6">
    <label class="form-label" for="reqSoftcopy">Existing Softcopy Document</label>
    <select class="form-select" name="softcopy_id" id="reqSoftcopy" data-searchable autocomplete="off">
        <option value="">Choose a document</option>
        <?php foreach ($softcopy_options as $item): ?>
            <option value="<?= (int) $item['id'] ?>" data-softcopy-record="<?= html_escape(json_encode($item)) ?>"
                <?= !empty($item['file_locked_at']) ? 'disabled' : '' ?>>
                <?= html_escape($item['document_number'] . ' · ' . $item['title'] .
                    (!empty($item['file_locked_at']) ? ' (Locked - read-only)' : '')) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-6">
    <label class="form-label" for="reqTitle">Proposed Document Title</label>
    <input class="form-control" name="title" id="reqTitle" maxlength="255" autocomplete="off">
</div>
<div class="col-md-6">
    <label class="form-label" for="reqCategory">Category</label>
    <select class="form-select" name="category_id" id="reqCategory" data-searchable autocomplete="off">
        <option value="">Choose a category</option>
        <?php foreach ($category_options as $category): ?>
            <option value="<?= (int) $category['id'] ?>"
                data-code-pattern="<?= html_escape($category['code_pattern'] ?? 'DOC-001') ?>"
                    data-number-options="<?= html_escape(json_encode($numbering_options[(int) $category['id']] ?? [])) ?>"
                    data-next-number="<?= html_escape($number_previews[(int) $category['id']] ?? '') ?>">
                <?= html_escape($category['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-6" data-series-number-checker-wrap hidden>
    <label class="form-check mt-4">
        <input class="form-check-input" type="checkbox" name="series_number_checker" value="1"
            data-series-number-checker autocomplete="off">
        Need Series Number?
    </label>
</div>
<div class="col-md-6" data-document-number-auto hidden>
    <label class="form-label" for="generatedDocumentNumber">Generated Document Number</label>
    <input class="form-control" name="generated_document_number" id="generatedDocumentNumber" readonly
        autocomplete="off">
</div>
<div class="col-md-6" data-document-number-manual hidden>
    <label class="form-label" for="reqNumber">Document Number</label>
    <select class="form-select" name="document_number" id="reqNumber" data-searchable disabled autocomplete="off">
        <option value="">Choose an existing document number</option>
    </select>
</div>
<div class="col-md-6" data-series-pattern-setup hidden>
    <label class="form-label" for="reqSeriesPattern">Series No. Code Pattern (first-time setup)</label>
    <input class="form-control" name="series_code_pattern" id="reqSeriesPattern"
        placeholder="For example 001 or SER-001" maxlength="100" disabled autocomplete="off">
    <small class="form-text">Set this only once for the selected Document No. The original document receives the preceding series: 001 means original 000, new document 001.</small>
</div>
<div class="col-md-6" data-original-series-preview hidden>
    <label class="form-label" for="reqOriginalSeries">Original Document Series No.</label>
    <input class="form-control" id="reqOriginalSeries" readonly disabled autocomplete="off">
    <small class="form-text">Assigned to the existing original record on final approval.</small>
</div>
<div class="col-md-6" data-series-number-field hidden>
    <label class="form-label" for="reqSeries">Generated Series Number</label>
    <input class="form-control" name="generated_series_number" id="reqSeries" maxlength="100" readonly disabled autocomplete="off">
    <small class="form-text" data-series-number-guide>Preview only. The final series number is generated on approval.</small>
</div>
<?php if ($softcopyDirect): ?>
    <div class="col-md-6" data-direct-revision-level-wrap>
        <label class="form-label" for="directRevisionLevel">Revision Level</label>
        <input class="form-control" id="directRevisionLevel" name="new_revision_level"
            data-direct-revision-level maxlength="30" value="00" required autocomplete="off">
        <small class="form-text">Set the revision level to apply during direct approval.</small>
    </div>
<?php endif; ?>
<div class="col-md-6">
    <label class="form-label" for="revisionPages">Number of Pages</label>
    <input class="form-control" type="number" id="revisionPages" name="page_number" min="1" value="1" autocomplete="off">
</div>

<?php if ($softcopyDirect): ?>
    <div class="col-md-6">
        <label class="form-label" for="revisionEffective">Effective Date
            <span class="optional-label">(optional)</span></label>
        <input class="form-control" type="date" id="revisionEffective" name="effective_date" autocomplete="off">
        <small class="form-text">If blank, effective date defaults to approval day.</small>
    </div>
<?php endif; ?>

<div class="col-12">
    <label class="form-label" for="revisionFile">Document File<span class="optional-label">(required for Create and
            Revise · 15 MB maximum)</span></label>
    <input class="form-control" id="revisionFile" type="file" name="revision_attachment"
        accept=".pdf,.txt,.png,.jpg,.jpeg,.docx,.xlsx" autocomplete="off">
    <small class="text-secondary">
        <?= $softcopyDirect
            ? 'Required for direct creation or revision; the Document Fileis approved as part of the transaction.'
            : 'Attach the file for approver review. An existing uploaded file can be retained when editing the draft.' ?>
    </small>
    <small class="form-text">Approved locked documents remain available to view and download, but cannot be revised.</small>
    <small class="form-text" data-locked-attachment-message hidden>
        This attachment was locked by the designated approver. Keep the existing file; replacement is unavailable.
    </small>
</div>
<div class="col-12">
    <label class="form-label" for="reqRemark">Remarks <span class="optional-label">(optional)</span></label>
    <textarea class="form-control" id="reqRemark" name="remarks" rows="3" autocomplete="off"></textarea>
</div>