import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '30s', target: 30 },
    { duration: '1m', target: 60 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_duration: ['p(95)<200'],
    http_req_failed: ['rate<0.01'],
  },
};

export default function () {
  const currencies = ['USD', 'EUR', 'GBP', 'SGD', 'JPY'];
  const currency = currencies[Math.floor(Math.random() * currencies.length)];
  const url = `http://localhost/api/v1/rates/${currency}`;

  const params = {
    headers: {
      'Authorization': 'Bearer test-token',
    },
  };

  const res = http.get(url, params);

  check(res, {
    'status is 200': (r) => r.status === 200,
    'response time < 200ms': (r) => r.timings.duration < 200,
  });

  sleep(0.5);
}