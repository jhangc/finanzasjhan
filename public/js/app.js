/*
 * FinControl: utilidades sin build.
 * Los montos viajan como strings decimales ("1250.50") y se operan en céntimos enteros, nunca en float.
 */
window.fc = {
    /** "1250.5" | 125050 (céntimos) → 125050 */
    toCents(value) {
        if (typeof value === 'number' && Number.isInteger(value)) return value;
        let text = String(value ?? '').trim().replace(',', '.');
        if (!/^-?\d*(\.\d*)?$/.test(text) || text === '' || text === '-' || text === '.') return 0;
        const negative = text.startsWith('-');
        text = text.replace('-', '');
        const [int = '0', dec = ''] = text.split('.');
        const cents = parseInt(int || '0', 10) * 100 + parseInt((dec + '00').slice(0, 2), 10);
        return negative ? -cents : cents;
    },

    /** 125050 → "1250.50" */
    fromCents(cents) {
        const negative = cents < 0;
        const abs = Math.abs(cents);
        const text = Math.floor(abs / 100) + '.' + String(abs % 100).padStart(2, '0');
        return negative ? '-' + text : text;
    },

    /** "1250.5" → "S/ 1,250.50" */
    money(value, currency = 'PEN') {
        const cents = this.toCents(value);
        const [int, dec] = this.fromCents(Math.abs(cents)).split('.');
        const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        const symbol = currency === 'USD' ? '$' : 'S/';
        return (cents < 0 ? '-' : '') + symbol + ' ' + grouped + '.' + dec;
    },

    /** fetch con CSRF y JSON. */
    async api(url, { method = 'POST', body = null } = {}) {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : null,
        });
        let data = {};
        try { data = await response.json(); } catch (e) { /* sin cuerpo */ }
        return { ok: response.ok, status: response.status, data };
    },
};

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
