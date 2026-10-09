        </section>
    </main>
</div>

<!-- Shared action confirmation: no operation runs until the user confirms. -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-6" id="confirmTitle">Confirm Action</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><p class="mb-0" id="confirmMessage">Continue with this action?</p></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" id="confirmProceed">Confirm</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form id="actionForm" method="post" data-confirm="Confirm this action?">
            <div class="modal-header"><h2 class="modal-title fs-6" id="actionTitle">Confirm Action</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p id="actionDescription" class="text-secondary"></p>
                <label for="actionRemark" class="form-label">Remark <span class="optional-label">(optional)</span></label>
                <textarea class="form-control" id="actionRemark" name="remark" rows="3"></textarea>
                <div class="row g-3 mt-2" id="actionDisposalFields" hidden>
                    <?php $this->load->view('pages/request/disposal_fields'); ?>
                </div>
            </div>
            <input type="hidden" name="id" value="">
            <input type="hidden" name="decision" value="">
            <input type="hidden" name="step_key" value="">
            <input type="hidden" name="direction" value="">
            <input type="hidden" name="confirmed" value="no">
            <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger" type="submit">Continue</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-6" id="viewModalTitle">Details</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><dl class="detail-grid mb-0" id="viewDetails"></dl></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>

<div class="modal fade" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form action="<?= site_url('change-password') ?>" method="post" data-confirm="Change your account password?">
            <div class="modal-header"><h2 class="modal-title fs-6" id="passwordModalTitle">Change Password</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <label class="form-label" for="currentPassword">Current Password</label><input class="form-control mb-3" id="currentPassword" type="password" name="current_password" required autocomplete="current-password">
                <label class="form-label" for="newPassword">New Password</label><input class="form-control" id="newPassword" type="password" name="new_password" required minlength="12" autocomplete="new-password">
                <div class="form-text">Use at least 12 characters.</div>
            </div>
            <input type="hidden" name="confirmed" value="no"><input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Update Password</button></div>
        </form>
    </div></div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
</body>
</html>
