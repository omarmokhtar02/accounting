// k6 load test for a deployed environment (https://k6.io).
//   php artisan loadtest:seed --tenants=200        (on a THROWAWAY database)
//   k6 run -e BASE_URL=https://staging.example.com -e USERS=200 tests/Stress/k6-load.js
//
// Ramps virtual users through stages, each VU logging in as its own tenant owner, then mixing
// reads (dashboard, lists) with journal writes. Thresholds fail the run if p95 > 800 ms or
// more than 1 % of requests fail.
import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const USERS = parseInt(__ENV.USERS || '50');
const PREFIX = __ENV.PREFIX || 'loadtest';

export const options = {
    stages: [
        { duration: '1m', target: 25 },
        { duration: '2m', target: 100 },
        { duration: '3m', target: 250 },
        { duration: '3m', target: 500 },
        { duration: '1m', target: 0 },
    ],
    thresholds: {
        http_req_failed: ['rate<0.01'],
        http_req_duration: ['p(95)<800'],
        'http_req_duration{kind:write}': ['p(95)<1500'],
    },
};

function token(html) {
    const m = html.match(/name="_token" value="([^"]+)"/);
    return m ? m[1] : '';
}

export default function () {
    const n = ((__VU - 1) % USERS) + 1;

    if (__ITER === 0) {
        const login = http.get(`${BASE}/login`);
        const res = http.post(`${BASE}/login`, { _token: token(login.body), email: `${PREFIX}${n}@example.com`, password: 'password' });
        check(res, { 'logged in': (r) => r.status === 200 });
    }

    const pages = ['/dashboard', '/accounting/accounts', '/accounting/journal-entries', '/accounting/fiscal-years'];
    const page = pages[Math.floor(Math.random() * pages.length)];
    check(http.get(`${BASE}${page}`, { tags: { kind: 'read' } }), { 'read ok': (r) => r.status === 200 });

    if (Math.random() < 0.2) {
        const form = http.get(`${BASE}/accounting/journal-entries/create`);
        const ids = [...form.body.matchAll(/<option value="(\d+)"[^>]*>\s*(1000|4000) /g)];
        const cash = ids.find((m) => m[2] === '1000');
        const sales = ids.find((m) => m[2] === '4000');
        if (cash && sales) {
            const amount = (Math.floor(Math.random() * 5000) + 1).toFixed(2);
            const res = http.post(`${BASE}/accounting/journal-entries`, {
                _token: token(form.body), entry_date: '2026-03-15', description: 'k6',
                idempotency_key: `${__VU}-${__ITER}-${Date.now()}`,
                'lines[0][account_id]': cash[1], 'lines[0][debit]': amount,
                'lines[1][account_id]': sales[1], 'lines[1][credit]': amount,
            }, { tags: { kind: 'write' } });
            check(res, { 'write ok': (r) => r.status === 200 });
        }
    }

    sleep(Math.random() * 2 + 0.5); // think time
}
