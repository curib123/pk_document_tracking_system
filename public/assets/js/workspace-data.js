// Presentation calculations only. All input rows are already permission-scoped.
export function dashboardSummary(data = {}) {
  const rows = key => Array.isArray(data[key]) ? data[key] : [];
  const count = (key, accept = () => true) => rows(key).reduce((sum, row) => {
    const value = Number(row.total);
    return sum + (accept(row.status) && Number.isFinite(value) ? Math.max(0, value) : 0);
  }, 0);
  const total = count('softcopy') + count('hardcopy');
  const active = count('softcopy', s => s === 'active') + count('hardcopy', s => s === 'active');
  return {
    total, active,
    pending: count('my_requests', s => s === 'pending'),
    percent: total ? Math.round(active / total * 1000) / 10 : 0,
    softcopy: count('softcopy', s => s !== 'disposed'),
    hardcopy: count('hardcopy', s => s !== 'disposed'),
    disposed: count('softcopy', s => s === 'disposed') + count('hardcopy', s => s === 'disposed')
  };
}

export function formatNumber(value) {
  return new Intl.NumberFormat('en-US').format(Number(value) || 0);
}

export function greeting(now = new Date()) {
  const hour = now.getHours();
  return hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
}
