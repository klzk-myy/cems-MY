import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { BASE_URL, creds, get, ROLES } from './lib/common.js';

// Read-surface load: a weighted mix of the API's read endpoints across the
// four seeded users. These routes are throttled at 60 req/min per route per
// user — with four armed users the aggregate budget is ~4 req/s, so the
// default pacing stays just under the ceiling and `throttled` counts the
// limiter's 429s when it does trip (expected behaviour, not a failure).
//
//   npm run load:reads
//   BASE_URL=... LOAD_VUS=12 k6 run tests/Load/reads.js

const throttled = new Counter('throttled');
const okDuration = new Counter('ok_requests');

export const options = {
    scenarios: {
        read_mix: {
            executor: 'ramping-vus',
            startVUs: 1,
            stages: [
                { duration: '20s', target: Number(__ENV.LOAD_VUS || 8) },
                { duration: '40s', target: Number(__ENV.LOAD_VUS || 8) },
                { duration: '10s', target: 0 },
            ],
        },
    },
    thresholds: {
        // Measured baseline on local.host (8 VUs, shared staging MySQL):
        // med ~800ms / p95 ~1.3s — index endpoints are query-heavy. The
        // threshold is a regression tripwire above that baseline, not a
        // latency SLO; non-throttled responses only.
        'http_req_duration{expected_response:true}': ['p(95)<2000'],
        http_req_failed: ['rate<0.05'],
    },
};

const roleNames = Object.keys(ROLES);

export default function () {
    // Spread VUs across the four users so the per-user limiter isn't the
    // bottleneck before the app is.
    const role = roleNames[(__VU - 1) % roleNames.length];
    const token = creds(role).token;

    // Weighted endpoint pick: 50% index, 30% customers, 20% user.
    const pick = Math.random();
    const expected = [200, 429];
    let res;
    if (pick < 0.5) {
        res = get('/api/v1/transactions?per_page=10', token, { name: 'GET /transactions' }, expected);
    } else if (pick < 0.8) {
        res = get('/api/v1/customers?per_page=5', token, { name: 'GET /customers' }, expected);
    } else {
        res = get('/api/v1/user', token, { name: 'GET /user' }, expected);
    }

    if (res.status === 429) {
        throttled.add(1);
    } else {
        okDuration.add(1);
        check(res, { 'status 200': (r) => r.status === 200 });
    }

    sleep(1);
}
