/* Regression: presentation-only dashboard chart polishing must keep its
 * original server-supplied labels, datasets, fallbacks and accessibility.
 */
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');

function harness(present=true) {
    const created=[];
    const canvases=[
        {
            dataset:{chartType:'doughnut',chartLabels:'["Hardcopy","Softcopy"]',chartCounts:'[8,4]'},
            closest(){return this.box;},
            box:{hidden:false}
        },
        {
            dataset:{chartType:'bar',chartLabels:'["Draft","Submitted","Completed"]',chartCounts:'[2,3,5]'},
            closest(){return this.box;},
            box:{hidden:false}
        }
    ];
    const document={
        addEventListener(name,listener) {if(name==='DOMContentLoaded') this.ready=listener;},
        querySelectorAll(selector){
            assert.equal(selector,'.dashboard-page canvas[data-chart-type]');
            return canvases;
        }
    };
    const window={
        Chart:present?function(canvas,configuration){created.push({canvas,configuration});}:undefined,
        matchMedia(){return {matches:false};}
    };
    vm.runInNewContext(fs.readFileSync('public/assets/js/dashboard.js','utf8'),
        {document,window,JSON,Number,Array}, {filename:'dashboard.js'});
    document.ready();
    return {created,canvases};
}

test('charts retain original typed data and labels with polished presentation',()=>{
    const result=harness();
    assert.equal(result.created.length,2);
    const pie=result.created[0].configuration;
    const bar=result.created[1].configuration;
    assert.equal(pie.type,'doughnut');
    assert.deepEqual(Array.from(pie.data.labels),['Hardcopy','Softcopy']);
    assert.deepEqual(Array.from(pie.data.datasets[0].data),[8,4]);
    assert.equal(pie.options.plugins.legend.display,true);
    assert.equal(typeof pie.options.plugins.tooltip.callbacks.label,'function');
    assert.equal(pie.options.plugins.tooltip.callbacks.label({dataIndex:0}),'Hardcopy: 8 (66.7%)');
    assert.equal(bar.type,'bar');
    assert.deepEqual(Array.from(bar.data.labels),['Draft','Submitted','Completed']);
    assert.deepEqual(Array.from(bar.data.datasets[0].data),[2,3,5]);
    assert.equal(bar.options.indexAxis,'y');
    assert.equal(bar.options.scales.x.beginAtZero,true);
    assert.equal(bar.options.scales.x.ticks.precision,0);
    assert.equal(bar.options.plugins.legend.display,false);
});

test('missing Chart.js hides only graph canvases, leaving count tables visible',()=>{
    const result=harness(false);
    assert.equal(result.created.length,0);
    result.canvases.forEach(canvas=>assert.equal(canvas.box.hidden,true));
});

test('dashboard icons do not replace original accessible metric text',()=>{
    const view=fs.readFileSync('application/view/pages/dashboard/index.php','utf8');
    const compact=view.replace(/\s+/g,'');
    assert.ok(compact.includes('dashboard-page'.replace(/\s+/g, '')));
    assert.ok(compact.includes('data-metric="<?= $key ?>"'.replace(/\s+/g, '')));
    assert.ok(compact.includes('dashboard-stat-icon'.replace(/\s+/g, '')));
    assert.ok(compact.includes('aria-hidden="true"'.replace(/\s+/g, '')));
    assert.ok(compact.includes("<?= (int)$metrics[$key] ?>".replace(/\s+/g, '')));
    assert.ok(compact.includes('data-chart-counts="<?= html_escape(json_encode(array_values($document_counts))) ?>"'.replace(/\s+/g, '')));
    assert.ok(compact.includes('data-chart-counts="<?= html_escape(json_encode(array_values($stats))) ?>"'.replace(/\s+/g, '')));
});
