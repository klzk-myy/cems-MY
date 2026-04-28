import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '30s', target: 10 },
    { duration: '1m', target: 20 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'],
    http_req_failed: ['rate<0.01'],
  },
};

export default function () {
  const url = 'http://localhost/api/v1/transactions';
  const payload = JSON.stringify({
    type: 'buy',
    currency_code: 'USD',
    amount: '1000.00',
    rate: '4.50',
    customer_id: 1,
  });

  const params = {
    headers: {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer test-token',
    },
  };

  const res = http.post(url, payload, params);

  check(res, {
    'status is 200 or 201': (r) => r.status === 200 || r.status === 201,
    'response time < 500ms': (r) => r.timings.duration < 500,
  });

  sleep(1);
}