#!/usr/bin/env bash
# Arm k6 load scenarios: mints a Sanctum bearer token AND a trusted-device
# token per seeded user, written to tests/Load/armed.json (gitignored).
#
#   - bearer token   → Authorization header for all API calls
#   - device token   → `mfa_device_token` cookie; bypasses the mfa.verified
#                      gate on write routes without a session (30-day trust)
#
# NOTE: simulation:issue-token revokes the user's previous API tokens.
# Run once per environment refresh, then point k6 at armed.json.
set -euo pipefail
cd "$(dirname "$0")/../.."

OUT="$(pwd)/tests/Load/armed.json"
PHP_BIN="${PHP_BIN:-/www/server/php/83/bin/php}"

USERS=(
    "teller1@cems.my"
    "manager1@cems.my"
    "compliance1@cems.my"
    "admin@cems.my"
)

echo '{}' > "$OUT"

for email in "${USERS[@]}"; do
    token=$("$PHP_BIN" artisan simulation:issue-token --email="$email" --file="$OUT.token.tmp" 2>/dev/null | tail -1 | tr -d '[:space:]')
    device=$("$PHP_BIN" artisan tinker --execute="
        \$u = App\Models\User::where('email', '$email')->first();
        echo \$u ? app(App\Services\System\MfaService::class)->rememberDevice(\$u, 'k6-load') : '';
    " 2>/dev/null | tail -1 | tr -d '[:space:]')

    if [[ -z "$token" || -z "$device" ]]; then
        echo "arm.sh: failed to arm $email (token='${token:0:8}…' device='${device:0:8}…')" >&2
        exit 1
    fi

    "$PHP_BIN" -r '
        $f = $argv[1]; $e = $argv[2];
        $d = json_decode(file_get_contents($f), true) ?: [];
        $d[$e] = ["token" => $argv[3], "device" => $argv[4]];
        file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    ' "$OUT" "$email" "$token" "$device"

    echo "armed $email"
done

rm -f "$OUT.token.tmp"

# Write-path fixture: booking needs a till_balances row for TODAY at the
# teller's open counter for both the booking currency and MYR. Insert them
# if missing (domain check rejects otherwise). Also records the teller's
# counter code in armed.json so booking.js can report what it used.
"$PHP_BIN" artisan tinker --execute='
$u = App\Models\User::where("username", "teller1")->first();
$mgr = App\Models\User::where("username", "manager1")->first();
$s = DB::table("counter_sessions")->where("user_id", $u->id)->whereNull("closed_at")->first();
if (! $s) { echo "NO_COUNTER"; exit; }
$c = DB::table("counters")->find($s->counter_id);
foreach (["USD" => "0", "MYR" => "100000"] as $ccy => $bal) {
    $exists = DB::table("till_balances")->where("till_id", $c->code)
        ->where("currency_code", $ccy)->whereDate("date", today())
        ->whereNull("closed_at")->exists();
    if (! $exists) {
        DB::table("till_balances")->insert([
            "till_id" => $c->code, "currency_code" => $ccy, "opening_balance" => $bal,
            "variance" => "0", "date" => today(), "opened_by" => $u->id,
            "transaction_total_myr" => "0", "total_quantity" => "0",
            "branch_id" => $c->branch_id, "buy_quantity" => "0", "sell_quantity" => "0",
        ]);
    }
}

// Booking also requires an ACTIVE teller allocation (stock custody) for
// the currency — provision one through the service so the pool earmark
// stays consistent. Pool must hold USD for the teller branch.
$svc = app(App\Services\Branch\TellerAllocationService::class);
if (! $svc->getActiveAllocation($u, "USD")) {
    $alloc = $svc->requestAllocation($u, $mgr, "USD", "10000", "1000000", App\Models\Counter::find($c->id));
    $alloc = $svc->approveAllocation($alloc, $mgr, "10000", "1000000");
    $svc->activateAllocation($alloc);
    echo "ALLOCATED ";
}

// Standard CDD requires address/phone/occupation/employer on the customer —
// provision a dedicated load-test customer via CustomerService so the ID
// encryption, blind indexes, and sanctions screening run properly.
$hash = App\Services\Customer\CustomerService::computeBlindIndex("K6LOAD0001");
$cust = App\Models\Customer::where("id_number_hash", $hash)->first();
if (! $cust) {
    $cust = app(App\Services\Customer\CustomerService::class)->createCustomer([
        "full_name" => "K6 Load Customer", "id_type" => "Others",
        "id_number" => "K6LOAD0001", "date_of_birth" => "1985-06-15",
        "nationality" => "MY", "address" => "1 Load Street, Kuala Lumpur",
        "phone" => "+60199998888", "email" => "k6-load@example.test",
        "occupation" => "Load Tester", "employer_name" => "k6",
    ], $u->id);
    echo "CUST_CREATED ";
}
echo "CUST:".$cust->id." ";
echo "COUNTER:".$c->code;
' 2>/dev/null | tail -1 > /tmp/arm-counter.txt

COUNTER=$(grep -o 'COUNTER:[A-Za-z0-9_-]*' /tmp/arm-counter.txt | cut -d: -f2)
CUST=$(grep -o 'CUST:[0-9]*' /tmp/arm-counter.txt | cut -d: -f2)
if [[ "$COUNTER" == "NO_COUNTER" || -z "$COUNTER" ]]; then
    echo "arm.sh: warning — teller1 has no open counter session; booking.js writes will fail validation" >&2
else
    "$PHP_BIN" -r '
        $f = $argv[1];
        $d = json_decode(file_get_contents($f), true) ?: [];
        $d["_write"] = ["till" => $argv[2], "currency" => "USD", "customer_id" => (int) $argv[3]];
        file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    ' "$OUT" "$COUNTER" "$CUST"
    echo "till fixtures ready at counter $COUNTER (customer $CUST)"
fi

echo "wrote $OUT"
