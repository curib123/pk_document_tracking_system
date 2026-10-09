'use strict';
document.querySelectorAll('[data-password-fields]').forEach(function (fields) {
    const password = fields.querySelector('[name="new_password"]');
    const confirmation = fields.querySelector('[name="confirm_password"]');
    function validate() {
        confirmation.setCustomValidity(confirmation.value && confirmation.value !== password.value
            ? 'The new password and confirmation do not match.' : '');
    }
    password.addEventListener('input', validate);
    confirmation.addEventListener('input', validate);
});
const requiredDialog = document.querySelector('[data-required-password]');
if (requiredDialog) {
    const controls = Array.from(requiredDialog.querySelectorAll('input:not([type="hidden"]),button'));
    if (controls.length) controls[0].focus();
    requiredDialog.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') event.preventDefault();
        if (event.key !== 'Tab' || !controls.length) return;
        const first = controls[0], last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
}
