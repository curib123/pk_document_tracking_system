// Native CI3 routes ra atong gamiton; no hidden fallback endpoint para predictable ang flow.
function screenEvent(module) {
  document.documentElement.dataset.pkModule = module;
  document.dispatchEvent(new CustomEvent('pk:screen', { detail: { module } }));
}

export class ApiClient {
  constructor() {
    this.csrf = '';
    this.endpointRoutes = JSON.parse(document.querySelector('meta[name=endpoint-routes]')?.content || '{}');
    this.apiRoot = document.querySelector('meta[name=api-root]')?.content || '';
  }
  url(operation, data = {}, includeQuery = true) {
    const selector = ['list','detail','catalog.save','catalog.delete'].includes(operation) ? 'module' : operation === 'documents.direct' ? 'domain' : null;
    const key = operation + (selector ? `@${data[selector]}` : '');
    const path = this.endpointRoutes[key];
    if (!this.apiRoot || !path) throw new Error('Unknown application endpoint. Refresh the page.');
    const url = new URL(path, this.apiRoot);
    if (includeQuery && !(data instanceof FormData)) {
      for (const [key, value] of Object.entries(data)) if (value !== null && value !== undefined) url.searchParams.set(key, String(value));
    }
    return url;
  }
  async request(operation, data = {}, mutation = false, signal) {
    // UI events never modify request data, authorization, or endpoints.
    if (!mutation && operation === 'list') screenEvent(String(data.module || ''));
    if (!mutation && operation === 'dashboard') screenEvent('dashboard');
    if (!mutation && operation === 'detail') {
      document.dispatchEvent(new CustomEvent('pk:detail', { detail: { module: data.module } }));
    }
    const multipart = data instanceof FormData;
    const options = { method: mutation ? 'POST' : 'GET', credentials: 'same-origin', signal, headers: { Accept: 'application/json' } };
    if (mutation) {
      options.headers['X-CSRF-Token'] = this.csrf;
      if (!multipart) options.headers['Content-Type'] = 'application/json';
      options.body = multipart ? data : JSON.stringify(data);
    }
    const response = await fetch(this.url(operation, data, !mutation), options);
    let payload;
    try { payload = await response.json(); } catch { throw new Error('The server returned an invalid response. Check the connection and server configuration.'); }
    if (payload.csrf) this.csrf = payload.csrf;
    if (!response.ok || !payload.ok) {
      const error = new Error(payload.error?.message || `Request failed (${response.status}).`);
      error.status = response.status; error.fields = payload.error?.fields || {};
      throw error;
    }
    if (operation === 'metadata') {
      document.dispatchEvent(new CustomEvent('pk:metadata', { detail: { modules: payload.data?.modules || [] } }));
    }
    if (operation === 'session' && !payload.data?.user) screenEvent('account');
    return payload.data;
  }
  async download(id, filename = 'document') {
    const response = await fetch(this.url('files.download', { id }), { credentials: 'same-origin' });
    if (!response.ok) {
      const error = await response.json().catch(() => ({}));
      if (error.csrf) this.csrf = error.csrf;
      throw new Error(error.error?.message || 'Download was not allowed.');
    }
    const blob = await response.blob(); const url = URL.createObjectURL(blob);
    const link = document.createElement('a'); link.href = url; link.download = filename;
    document.body.append(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 60000);
    return { message: 'Download started. Treat downloaded files according to your document-control policy.' };
  }
}
