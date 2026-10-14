(function () {
    'use strict';
    var button = document.getElementById('printButton');
    var status = document.getElementById('printStatus');
    if (!button) return;
    var frame = document.getElementById('printFrame');
    var image = document.getElementById('printImage');
    function ready() {
        button.disabled = false;
        status.textContent = frame ? 'Use Print below or the PDF viewer’s printer icon.' : 'Preview ready. Choose Print to select your printer.';
    }
    button.addEventListener('click', function () {
        if (!frame) { window.print(); return; }
        try { frame.contentWindow.focus(); frame.contentWindow.print(); }
        catch (error) { status.textContent = 'Use the printer icon inside the PDF preview, or download the file to print.'; }
    });
    var resource = frame || image;
    if (resource) {
        resource.addEventListener('load', ready);
        resource.addEventListener('error', function () { status.textContent = 'Preview unavailable. Download the file to print.'; });
        if (image && image.complete && image.naturalWidth > 0) ready();
    } else ready();
})();
