<template id="permission-editor-template">
    <div>
        <input type="search" aria-label="Filter permissions" placeholder="Filter permissions" data-permission-search>
        <div data-permission-rows></div>
        <p><label for="permission-change-reason">Remarks</label><br>
            <textarea id="permission-change-reason" name="reason" rows="3" cols="36"></textarea><br>
            <small>Optional.</small>
        </p>
    </div>
</template>
<template id="permission-row-template"><p><label><input type="checkbox"><span data-bind="label"></span></label></p></template>
