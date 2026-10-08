<template id="workflow-version-editor-template">
    <div>
        <p>Approval steps: <span data-bind="step-count"></span></p>
        <div data-step-actions></div>
        <div data-step-table></div>
        <p data-no-steps hidden>This draft has no approval steps yet. Add at least one step before publishing.</p>
    </div>
</template>
