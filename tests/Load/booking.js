import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { post, get, creds, BASE_URL } from './lib/common.js';

// Write-path load: POST /api/v1/transactions bookings as teller1.
//
// WRITES REAL DATA — every successful booking creates a transaction row,
// stock reservation, journal stubs, and queued compliance jobs in the
// target database. The scenario is disabled unless LOAD_WRITE=1 is set,
// and should only run against a scratch/seed DB you are willing to dirty
// (PendingApproval reservations expire via `reservation:expire`).
//
//   LOAD_WRITE=1 npm run load:write
//   LOAD_WRITE=1 LOAD_VUS=4 k6 run tests/Load/booking.js
//
// Throttling: POST /transactions is 10 req/min per user. teller1 is the
// only seeded teller, so default pacing (sleep 7s per VU) stays under the
// ceiling at 1–8 VUs; `throttled` counts 429s when it does trip.
// mfa.verified is satisfied by the armed trusted-device cookie.

const throttled = new Counter('throttled');
const booked = new Counter('booked_txns');
const rejected = new Counter('rejected_txns');

export const options = {
    scenarios: {
        booking: {
            executor: 'constant-vus',
            vus: Number(__ENV.LOAD_VUS || 2),
            duration: __ENV.LOAD_DURATION || '1m',
        },
    },
    thresholds: {
        'http_req_duration{expected_response:true}': ['p(95)<1500'],
    },
};

export function setup() {
    if (__ENV.LOAD_WRITE !== '1') {
        throw new Error(
            'Refusing to run the write scenario without LOAD_WRITE=1 — ' +
            'bookings insert real rows into the target DB. Re-run with LOAD_WRITE=1 to confirm.'
        );
    }

    // The CDD-complete customer arm.sh provisioned (Standard CDD requires
    // address/phone/occupation/employer — random seeded customers lack them).
    const customerId = creds('_write').customer_id;
    if (!customerId) {
        throw new Error('No _write.customer_id in armed.json — re-run load:arm');
    }

    // Tellers may only book within 0.5% of the board rate — resolve the
    // current USD buy rate via the manager token (tellers lack access_rates).
    const rates = get('/api/v1/rates', creds('manager').token);
    const usd = (rates.json('data') || []).find((r) => r.currency_code === 'USD');
    if (!usd) {
        throw new Error(`Could not resolve the USD board rate (status ${rates.status})`);
    }

    return { customerId, rate: String(usd.rate_buy) };
}

export default function (data) {
    // >= auto-approve threshold (RM10k): lands in PendingApproval — reserves
    // stock without consuming custody, the least destructive real write.
    const quantity = (11000 + Math.floor(Math.random() * 4000)) / Number(data.rate);
    const res = post(
        '/api/v1/transactions',
        {
            customer_id: data.customerId,
            type: 'Buy',
            currency_code: 'USD',
            quantity: quantity.toFixed(2),
            rate: data.rate,
            purpose: 'Load test booking',
            source_of_funds: 'Load test',
            idempotency_key: `k6-${__VU}-${__ITER}-${Date.now()}`,
        },
        creds('teller'),
        { name: 'POST /transactions' },
        [200, 201, 422, 429]
    );

    if (res.status === 429) {
        throttled.add(1);
    } else if (res.status === 200 || res.status === 201) {
        booked.add(1);
    } else {
        rejected.add(1);
        check(res, { 'booking accepted': () => false });
    }

    sleep(7); // ~8.5 bookings/min/VU — under the 10/min per-user limiter
}
