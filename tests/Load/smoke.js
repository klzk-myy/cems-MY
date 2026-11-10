import { check, sleep } from 'k6';
import http from 'k6/http';
import { BASE_URL, creds, get } from './lib/common.js';

// Smoke: health probe + one authenticated API read per VU. The cheapest
// possible "is the app alive and auth wired" check — run this before the
// heavier scenarios to catch a dead server/bad token instead of 30s of 401s.
//
//   npm run load:smoke        (or: k6 run tests/Load/smoke.js)
//   BASE_URL=http://local.host k6 run tests/Load/smoke.js

export const options = {
    vus: 5,
    duration: '30s',
    thresholds: {
        http_req_duration: ['p(95)<500'],
        http_req_failed: ['rate<0.01'],
    },
};

export default function () {
    const health = http.get(`${BASE_URL}/health`);
    check(health, { 'health 200': (r) => r.status === 200 });

    const me = get('/api/v1/user', creds('teller').token);
    check(me, { 'user 200': (r) => r.status === 200 });

    sleep(1);
}
