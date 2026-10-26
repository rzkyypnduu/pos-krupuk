/**
 * Helper HTTP POS: kirim JSON / form ke endpoint Laravel dengan token CSRF.
 */

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

export function posJson(url, payload) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(payload),
    }).then((res) => res.json());
}

export function posFormPost(url, fields) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: new URLSearchParams(fields),
    }).then((res) => res.json());
}
