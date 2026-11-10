import http from 'k6/http';

// Shared helpers for the k6 load scenarios. Credentials come from
// tests/Load/armed.json — minted by `npm run load:arm` (Sanctum bearer +
// trusted-device token per seeded user). The file is gitignored.

// open() resolves relative to THIS module's directory — ../armed.json is
// tests/Load/armed.json regardless of the k6 working directory. Override
// with ARMED_FILE=/abs/path/armed.json if the file lives elsewhere.
const armedPath = __ENV.ARMED_FILE || '../armed.json';

let armed;
try {
    armed = JSON.parse(open(armedPath));
} catch (e) {
    throw new Error(
        `tests/Load/armed.json not readable — run \`npm run load:arm\` first (${e})`
    );
}

export const BASE_URL = __ENV.BASE_URL || 'http://local.host';

// The four seeded users, keyed by role name.
export const ROLES = {
    teller: 'teller1@cems.my',
    manager: 'manager1@cems.my',
    compliance: 'compliance1@cems.my',
    admin: 'admin@cems.my',
};

export function creds(role) {
    const email = ROLES[role] || role;
    const entry = armed[email];
    if (!entry) {
        throw new Error(`No armed credentials for ${role} (${email}) — re-run load:arm`);
    }
    return entry;
}

// Bearer-only headers for read-only (non-mfa.verified) endpoints.
export function authHeaders(token) {
    return {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
        'Content-Type': 'application/json',
    };
}

// Headers for mfa.verified write routes: bearer + trusted-device cookie.
export function writeHeaders(cred) {
    return {
        ...authHeaders(cred.token),
        Cookie: `mfa_device_token=${cred.device}`,
    };
}

// expectedStatuses marks the given codes as "expected": they are excluded
// from http_req_failed and tagged expected_response:true (so thresholds
// like `http_req_duration{expected_response:true}` cover only real traffic).
export function get(url, token, tags, expected = [200]) {
    return http.get(`${BASE_URL}${url}`, {
        headers: authHeaders(token),
        tags,
        responseCallback: http.expectedStatuses(...expected),
    });
}

export function post(url, body, cred, tags, expected = [200, 201]) {
    return http.post(`${BASE_URL}${url}`, JSON.stringify(body), {
        headers: writeHeaders(cred),
        tags,
        responseCallback: http.expectedStatuses(...expected),
    });
}
