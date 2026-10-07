<template id="module-page-template">
<section>
<h2 data-module-title></h2>
<div data-module-content></div>
</section>
</template>
<template id="navigation-group-template">
<details open>
<summary data-navigation-title></summary>
<div data-navigation-items></div>
</details>
</template>
<template id="record-card-template">
<article>
<h3 data-record-title></h3>
<div data-record-fields></div>
<div data-record-actions></div>
</article>
</template>
<template id="data-table-template"><table><thead></thead><tbody></tbody></table></template>
<template id="modal-shell">
<dialog>
<h2 data-dialog-title></h2>
<p data-dialog-explanation hidden></p>
<form>
<fieldset></fieldset>
<p data-dialog-error role="alert" tabindex="-1"></p>
<p data-dialog-progress role="status" aria-live="polite"></p>
<footer></footer>
</form>
</dialog>
</template>
<noscript>JavaScript is required for the modal forms. Enable JavaScript and reload this page.</noscript>
</body>
</html>
