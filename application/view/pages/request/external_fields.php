<div class="col-md-6">
    <label class="form-label" for="reqType">External Document Action</label>
    <select class="form-select" name="type" id="reqType" required autocomplete="off">
        <option value="external_create">External Create Request</option>
        <option value="external_revise">External Revised Request</option>
    </select>
</div>
<div class="col-md-6">
    <label class="form-label" for="reqSubject">Subject / Reason</label>
    <input class="form-control" name="subject" id="reqSubject" maxlength="255" required autocomplete="off">
</div>
<div class="col-12" data-external-existing hidden>
    <label class="form-label" for="reqExternal">Existing External Document</label>
    <select class="form-select" name="external_document_id" id="reqExternal" data-searchable disabled autocomplete="off">
        <option value="">Choose an external document</option>
        <?php foreach ($external_options as $item): ?>
            <option value="<?= (int) $item['id'] ?>"
                data-external-record="<?= html_escape(json_encode($item, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
                <?= html_escape($item['title'] . ' · Revision ' . ($item['revision_level'] ?? '00')) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-6">
    <label class="form-label" for="externalRequestTitle">Document Title</label>
    <input class="form-control" id="externalRequestTitle" name="title" maxlength="255" required autocomplete="off">
</div>
<div class="col-md-6">
    <label class="form-label" for="externalRequestRetention">Retention Period</label>
    <input class="form-control" id="externalRequestRetention" name="retention_period" maxlength="100" required
        autocomplete="off" placeholder="For example: 5 years">
</div>
<div class="col-12">
    <label class="form-label" for="externalRequestAttachment">Attach File <span class="optional-label">(maximum 15 MB)</span></label>
    <input class="form-control" id="externalRequestAttachment" type="file" name="external_attachment"
        accept=".pdf,.txt,.png,.jpg,.jpeg,.docx,.xlsx" autocomplete="off">
</div>
<div class="col-12">
    <small class="form-text">The revision level is assigned automatically: new documents start at 00 and each revision increments the previous level.</small>
</div>
<div class="col-12">
    <label class="form-label" for="externalRequestRemarks">Remarks <span class="optional-label">(optional)</span></label>
    <textarea class="form-control" id="externalRequestRemarks" name="remarks" rows="3" autocomplete="off"></textarea>
</div>
