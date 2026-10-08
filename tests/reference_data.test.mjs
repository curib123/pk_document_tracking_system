import {test} from 'node:test';
import assert from 'node:assert/strict';
import {existsSync} from 'node:fs';
const path = new URL('../public/assets/js/workspace-data.js', import.meta.url);
test('reference dashboard uses a tested live-data adapter', async () => {
  assert.ok(existsSync(path), 'The live dashboard adapter is not implemented');
  const {dashboardSummary} = await import(path);
  const summary = dashboardSummary({softcopy:[{status:'active',total:3},{status:'disposed',total:1}],hardcopy:[{status:'active',total:6}],my_requests:[{status:'pending',total:2},{status:'completed',total:7}]});
  assert.equal(summary.total,10);
  assert.equal(summary.active,9);
  assert.equal(summary.pending,2);
  assert.equal(summary.percent,90);
  assert.equal(summary.softcopy+summary.hardcopy+summary.disposed,summary.total);
  assert.deepEqual(dashboardSummary({}),{total:0,active:0,pending:0,percent:0,softcopy:0,hardcopy:0,disposed:0});
});
