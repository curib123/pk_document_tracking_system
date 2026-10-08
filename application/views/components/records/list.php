<div data-module-actions></div>
<form data-record-search>
    <label for="table-search">Search</label>
    <input type="search" id="table-search" placeholder="Search records">
    <button type="submit">Search</button>
</form>
<div id="table-filters">
    <label for="table-sort">Sort by</label><select id="table-sort"></select>
    <button type="button" data-reverse-order>Reverse order</button>
    <button type="button" data-refresh-records>Refresh records</button>
    <label for="status-filter" data-status-control>Status</label>
    <select id="status-filter" data-status-control>
        <option value="">All statuses</option>
        <option value="active">Active</option><option value="draft">Draft</option>
        <option value="pending">Pending</option><option value="returned">Returned</option>
        <option value="approved">Approved</option><option value="rejected">Rejected</option>
        <option value="cancelled">Cancelled</option><option value="disposed">Disposed</option>
        <option value="completed">Completed</option>
    </select>
    <label for="layout-select" data-layout-control>Layout</label>
    <select id="layout-select" data-layout-control>
        <option value="table">Table</option><option value="grid">Grid</option><option value="folder">Folder</option>
    </select>
</div>
<p id="table-status" role="status"></p>
<div id="table-container"></div>
<div id="table-footer">
    <span id="page-summary"></span>
    <label for="page-size">Rows per page</label>
    <select id="page-size"><option>10</option><option selected>25</option><option>50</option><option>100</option></select>
    <span id="pagination"></span>
</div>
