</section>
</main>
</div>

<?php
// Flash messages are presented as one accessible modal, never as a page-top banner.
$flashNotice = $this->session->flashdata('notice');
$flashKind = (string) ($this->session->flashdata('notice_type') ?: 'success');
if (!in_array($flashKind, ['success', 'danger', 'warning', 'info'], TRUE))
    $flashKind = 'info';
?>
<?php if ($flashNotice !== NULL && $flashNotice !== ''): ?>
    <div class="modal fade app-alert-modal" data-bs-backdrop="static" data-bs-keyboard="false" id="appAlertModal"
        tabindex="-1" role="alertdialog" aria-modal="true" aria-labelledby="appAlertTitle"
        aria-describedby="appAlertMessage" aria-hidden="true" data-app-alert>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content app-alert-content app-alert-<?= html_escape($flashKind) ?>">
                <div class="modal-header border-0">
                    <div class="app-alert-symbol" aria-hidden="true">
                        <?php $this->load->view('reusable_components/alert_icon', ['alert_kind' => $flashKind]); ?></div>
                    <button class="btn-close" type="button" data-bs-dismiss="modal"
                        aria-label="Close notification"></button>
                </div>
                <div class="modal-body pt-0">
                    <h2 class="h5 fw-bold mb-2" id="appAlertTitle">
                        <?= $flashKind === 'success' ? 'Operation successful' : ($flashKind === 'danger' ? 'Action could not be completed' : ($flashKind === 'warning' ? 'Attention required' : 'Notification')) ?>
                    </h2>
                    <p class="text-secondary mb-0" id="appAlertMessage"><?= html_escape((string) $flashNotice) ?></p>
                </div>
                <div class="modal-footer border-0">
                    <button class="btn btn-primary" type="button" data-bs-dismiss="modal">Got it</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="modal fade app-alert-modal" data-bs-backdrop="static" data-bs-keyboard="false" id="taskNotificationModal"
    tabindex="-1" role="alertdialog" aria-modal="true" aria-labelledby="taskNotificationTitle"
    aria-describedby="taskNotificationMessage" aria-hidden="true"
    data-pending-url="<?= html_escape(site_url('notifications/tasks')) ?>"
    data-claim-url="<?= html_escape(site_url('notifications/tasks/claim')) ?>"
    data-csrf-name="<?= html_escape($this->security->get_csrf_token_name()) ?>"
    data-csrf-value="<?= html_escape($this->security->get_csrf_hash()) ?>"
    data-csrf-cookie="<?= html_escape(config_item('cookie_prefix') . config_item('csrf_cookie_name')) ?>">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content app-alert-content app-alert-info">
            <div class="modal-header border-0">
                <div class="app-alert-symbol" aria-hidden="true">
                    <?php $this->load->view('reusable_components/alert_icon', ['alert_kind' => 'info']); ?></div>
                <button class="btn-close" type="button" data-bs-dismiss="modal"
                    aria-label="Close notification"></button>
            </div>
            <div class="modal-body pt-0">
                <h2 class="h5 fw-bold mb-2" id="taskNotificationTitle">Approval needed</h2>
                <p class="text-secondary" id="taskNotificationMessage">New tasks are waiting for your approval.</p>
                <ol class="task-notification-list" id="taskNotificationList"></ol>
            </div>
            <div class="modal-footer border-0"><button class="btn btn-primary" type="button" data-bs-dismiss="modal">Got
                    it</button></div>
        </div>
    </div>
</div>

<!-- Waiting feedback is a live status; native forms and returned results stay authoritative. -->
<div class="action-feedback" id="appActionFeedback" hidden role="status" aria-live="polite" aria-atomic="true">
    <div class="action-feedback-card">
        <div class="action-feedback-animation" aria-hidden="true">
            <span class="action-feedback-halo"></span>
            <span class="action-feedback-paper"><span class="action-feedback-face"><span
                        class="action-feedback-smile"></span></span></span>
            <span class="action-feedback-dots"><span></span><span></span><span></span></span>
        </div>
        <h2 id="actionFeedbackTitle">Working on it</h2>
        <p id="actionFeedbackMessage">Please wait while we prepare the result of your action.</p>
        <button class="btn btn-light btn-sm" type="button" id="actionFeedbackDismiss" hidden>Dismiss notice</button>
    </div>
</div>

<!-- Shared action confirmation: no operation runs until the user confirms. -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="confirmModal" tabindex="-1"
    aria-labelledby="confirmTitle" aria-describedby="confirmMessage" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <div class="confirmation-heading">
                    <span class="confirmation-symbol"
                        aria-hidden="true"><?php $this->load->view('reusable_components/alert_icon', ['alert_kind' => 'warning']); ?></span>
                    <h2 class="modal-title" id="confirmTitle">Confirm Action</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="confirmMessage">Continue with this action?</p>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light"
                    data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger"
                    id="confirmProceed">Confirm</button></div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="actionModal" tabindex="-1"
    aria-labelledby="actionTitle" aria-describedby="actionDescription" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="actionForm" method="post" data-confirm="Confirm this action?">
                <div class="modal-header">
                    <div class="confirmation-heading">
                        <span class="confirmation-symbol"
                            aria-hidden="true"><?php $this->load->view('reusable_components/alert_icon', ['alert_kind' => 'warning']); ?></span>
                        <h2 class="modal-title" id="actionTitle">Confirm Action</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="actionDescription"></p>
                    <div id="actionRemarkFields">
                        <label for="actionRemark" class="form-label">Remark <span
                                class="optional-label">(optional)</span></label>
                        <textarea class="form-control" id="actionRemark" name="remark" rows="3"></textarea>
                    </div>
                    <div class="row g-3 mt-2" id="actionDisposalFields" hidden>
                        <?php $this->load->view('pages/request/disposal_fields', ['disposal_field_prefix' => 'directDisposal']); ?>
                    </div>
                </div>
                <input type="hidden" name="id" value="">
                <input type="hidden" name="decision" value="">
                <input type="hidden" name="step_key" value="">
                <input type="hidden" name="direction" value="">
                <input type="hidden" name="confirmed" value="no">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>"
                    value="<?= $this->security->get_csrf_hash() ?>">
                <div class="modal-footer"><button type="button" class="btn btn-light"
                        data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger"
                        type="submit">Continue</button></div>
            </form>
        </div>
    </div>
</div>

<!-- One shared read-only viewer for documents, requests and administration records. -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="viewModal" tabindex="-1"
    aria-labelledby="viewModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header doc-modal-header">
                <div class="doc-modal-heading">
                    <div class="doc-modal-eyebrow" id="viewDocumentEyebrow" hidden>DOCUMENT RECORD</div>
                    <div class="doc-modal-title-line">
                        <span class="doc-modal-kind-icon" id="viewDocumentIcon" aria-hidden="true" hidden>
                            <i class="fa-regular fa-file-lines"></i>
                        </span>
                        <div class="doc-modal-title-wrap">
                            <h2 class="modal-title" id="viewModalTitle">Details</h2>
                            <p class="doc-modal-reference" id="viewDocumentRef" hidden></p>
                        </div>
                    </div>
                    <dl class="doc-modal-custody" id="viewDocumentCustody" hidden></dl>
                </div>
                <div class="doc-modal-top-actions">
                    <span class="doc-modal-status" id="viewDocumentStatus" hidden></span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body doc-modal-body">
                <!-- Document overview is always above the concern tabs and scrolls with them. -->
                <div id="viewDocumentSummary" class="doc-modal-overview" hidden></div>
                <div id="viewDetails"></div>
            </div>
            <div class="modal-footer doc-modal-footer">
                <p class="doc-modal-footnote" id="viewDocumentFootnote" hidden><i class="fa-solid fa-lock"
                        aria-hidden="true"></i> Read-only details within your access permissions</p>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="passwordModal" tabindex="-1"
    aria-labelledby="passwordModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= site_url('change-password') ?>" method="post"
                data-confirm="Change your account password?">
                <div class="modal-header">
                    <h2 class="modal-title fs-6" id="passwordModalTitle">Change Password</h2><button type="button"
                        class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php $this->load->view('pages/authentication/password_fields', ['password_prefix' => 'account']); ?>
                </div>
                <input type="hidden" name="confirmed" value="no"><input type="hidden"
                    name="<?= $this->security->get_csrf_token_name() ?>"
                    value="<?= $this->security->get_csrf_hash() ?>">
                <div class="modal-footer"><button type="button" class="btn btn-light"
                        data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Update
                        Password</button></div>
            </form>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
<script src="<?= base_url('assets/js/task-notifications.js') ?>"></script>
<script src="<?= base_url('assets/js/row-actions.js') ?>"></script>
<script src="<?= base_url('assets/js/notification-inbox.js') ?>"></script>
<script src="<?= base_url('assets/js/logout-transition.js') ?>"></script>
<script src="<?= base_url('assets/js/action-feedback.js') ?>"></script>
<script src="<?= base_url('assets/js/document-view.js') ?>"></script>
<script src="<?= base_url('assets/js/document-tree.js') ?>"></script>
<script src="<?= base_url('assets/js/auth.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
<script src="<?= base_url('assets/js/plan-controls.js') ?>"></script>
<script src="<?= base_url('assets/js/live-table.js') ?>"></script>
<script src="<?= base_url('assets/js/motion.js') ?>"></script>
</body>

</html>
