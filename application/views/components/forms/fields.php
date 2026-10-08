<template id="field-input-template">
    <p data-field><label data-field-label></label><br><input data-control><br data-description-break hidden><small data-description hidden></small></p>
</template>
<template id="field-textarea-template">
    <p data-field><label data-field-label></label><br><textarea data-control rows="3" cols="36"></textarea><br data-description-break hidden><small data-description hidden></small></p>
</template>
<template id="field-select-template">
    <p data-field><label data-field-label></label><br><select data-control></select><br data-description-break hidden><small data-description hidden></small></p>
</template>
<template id="field-lookup-template">
    <p data-field>
        <label data-field-label></label><br>
        <input type="search" placeholder="Type to search" data-lookup-search><br>
        <select data-control></select><br>
        <small data-field-hint role="status"></small>
        <br data-description-break hidden><small data-description hidden></small>
    </p>
</template>
<template id="field-upload-template">
    <p data-field>
        <label data-field-label></label><br>
        <input type="file" data-control accept=".pdf,.docx,.xlsx,.txt,.csv,.png,.jpg,.jpeg"><br>
        <small data-field-hint role="status"></small>
        <br data-description-break hidden><small data-description hidden></small>
    </p>
</template>
