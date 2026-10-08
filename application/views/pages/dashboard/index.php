<template id="dashboard-workspace-template">
    <div class="ws-dashboard">
        <section class="ws-welcome">
            <span class="ws-welcome-icon" data-icon="sparkle"></span>
            <div class="ws-welcome-content">
                <span class="ws-eyebrow" data-bind="greeting"></span>
                <h3 data-bind="welcome"></h3>
                <p>You have <span data-bind="total-copy"></span> document records available in your workspace.</p>
                <div class="ws-date"><span data-icon="calendar"></span><span data-bind="date"></span></div>
            </div>
            <div class="ws-welcome-actions">
                <button class="ws-primary" type="button" data-nav="documents"><span data-icon="search"></span><span>Search documents</span></button>
                <button class="ws-refresh" type="button" aria-label="Refresh dashboard" data-dashboard-refresh><span data-icon="refresh"></span></button>
            </div>
        </section>
        <div class="ws-kpis">
            <section class="ws-kpi ws-kpi-featured">
                <span class="ws-kpi-icon" data-icon="folder"></span>
                <div><small>Documents available to you</small><strong data-bind="total"></strong></div>
            </section>
            <section class="ws-kpi"><small>Active documents</small><strong data-bind="active"></strong><span><span data-icon="check"></span><span data-bind="percent-copy"></span></span></section>
            <section class="ws-kpi"><small>My requests in workflow</small><strong data-bind="pending"></strong><span><span data-icon="clock"></span>Awaiting approval</span></section>
        </div>
        <nav class="ws-quicklinks" aria-label="Dashboard shortcuts">
            <button type="button" data-nav="documents"><span data-icon="file"></span><span>Documents</span></button>
            <button type="button" data-nav="my_requests"><span data-icon="send"></span><span>My requests</span></button>
            <button type="button" data-nav="locations"><span data-icon="storage"></span><span>Storage</span></button>
            <button type="button" data-nav="users"><span data-icon="users"></span><span>Users</span></button>
        </nav>
        <div class="ws-overview">
            <section class="ws-panel">
                <div class="ws-panel-heading"><div><span class="ws-eyebrow">Overview</span><h3>Document library</h3></div><small>Live records</small></div>
                <div class="ws-library">
                    <div class="ws-donut" role="img" data-library-chart>
                        <svg viewBox="0 0 120 120" aria-hidden="true">
                            <circle class="ws-ring-track" cx="60" cy="60" r="52" pathLength="100"></circle>
                            <circle class="ws-ring-hardcopy" cx="60" cy="60" r="52" pathLength="100"></circle>
                            <circle class="ws-ring-softcopy" cx="60" cy="60" r="52" pathLength="100"></circle>
                            <circle class="ws-ring-disposed" cx="60" cy="60" r="52" pathLength="100"></circle>
                        </svg>
                        <div class="ws-donut-label"><strong data-bind="percent"></strong><small>active</small></div>
                    </div>
                    <div class="ws-legend">
                        <div><span class="ws-dot ws-dot-hardcopy"></span>Hardcopy<b data-bind="hardcopy"></b></div>
                        <div><span class="ws-dot ws-dot-softcopy"></span>Softcopy<b data-bind="softcopy"></b></div>
                        <div><span class="ws-dot ws-dot-disposed"></span>Disposed<b data-bind="disposed"></b></div>
                    </div>
                </div>
            </section>
            <section class="ws-panel">
                <div class="ws-panel-heading"><div><span class="ws-eyebrow">Recent</span><h3>Latest documents</h3></div><button class="ws-text-link" type="button" data-nav="documents"><span>View all</span><span data-icon="arrow"></span></button></div>
                <div class="ws-latest-list" data-recent-documents></div>
            </section>
        </div>
    </div>
</template>
