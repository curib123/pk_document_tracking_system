/* Native <details> keeps the folder hierarchy usable without JavaScript.
 * These optional controls expand or collapse multiple branches together.
 */
(function () {
    'use strict';
    document.addEventListener('click',function (event) {
        var control=event.target.closest('[data-tree-action]');
        if (!control) return;
        var tree=control.closest('.doc-folder-tree-mode');
        if (!tree) return;
        var expand=control.getAttribute('data-tree-action')==='expand';
        var collapse=control.getAttribute('data-tree-action')==='collapse';
        if (!expand && !collapse) return;
        tree.querySelectorAll('details[data-folder-node]').forEach(function (folder) {
            folder.open=expand;
        });
    });
})();
