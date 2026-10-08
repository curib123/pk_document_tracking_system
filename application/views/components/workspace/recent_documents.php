<template id="workspace-document-template">
    <button class="ws-document-row" type="button">
        <span class="ws-document-icon" data-document-icon></span>
        <span class="ws-document-title"><strong data-bind="title"></strong><small data-bind="reference"></small></span>
        <span class="ws-document-status"><b data-bind="status"></b><small data-bind="date"></small></span>
    </button>
</template>
<template id="workspace-empty-template"><div class="ws-empty"><span data-icon="folder"></span>No documents available yet.<br>Assigned and granted documents will appear here.</div></template>
