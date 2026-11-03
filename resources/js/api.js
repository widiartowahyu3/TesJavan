const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

async function request(method, url, body) {
    const res = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    if (res.status === 204) return null;

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
        throw new Error(firstError || data.message || `Request gagal (${res.status})`);
    }
    return data;
}

export const api = {
    lookup: (name) => request('GET', `/api/lookup?name=${encodeURIComponent(name)}`),
    list: () => request('GET', '/api/predictions'),
    save: (payload) => request('POST', '/api/predictions', payload),
    remove: (id) => request('DELETE', `/api/predictions/${id}`),
};
