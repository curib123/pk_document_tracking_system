export class ApiClient {
  constructor(endpoint) { this.endpoint = endpoint; this.csrf = ''; }
  url(operation, data = {}) {
    const url = new URL(this.endpoint, location.href);
    url.searchParams.set('op', operation);
    for (const [key, value] of Object.entries(data)) if (value !== null && value !== undefined) url.searchParams.set(key, String(value));
    return url;
  }
  async request(operation, data = {}, mutation = false, signal) {
    const multipart = data instanceof FormData;
    const options = { method: mutation ? 'POST' : 'GET', credentials: 'same-origin', signal, headers: { Accept: 'application/json' } };
    if (mutation) {
      options.headers['X-CSRF-Token'] = this.csrf;
      if (!multipart) options.headers['Content-Type'] = 'application/json';
      options.body = multipart ? data : JSON.stringify(data);
    }
    const response = await fetch(this.url(operation, mutation ? {} : data), options);
    let payload;
    try { payload = await response.json(); } catch { throw new Error('The server returned an invalid response. Check the connection and server configuration.'); }
    if (payload.csrf) this.csrf = payload.csrf;
    if (!response.ok || !payload.ok) {
      const error = new Error(payload.error?.message || `Request failed (${response.status}).`);
      error.status = response.status; error.fields = payload.error?.fields || {};
      throw error;
    }
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
