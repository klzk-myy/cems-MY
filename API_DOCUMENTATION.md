# Transaction Wizard API Documentation

## Overview

The Transaction Wizard API provides a 3-step guided process for creating currency exchange transactions with automated CDD level determination, sanctions screening, and historical risk analysis.

**Base URL:** `/api/wizard/transactions`

**Authentication:** Required (Sanctum token)

**Authorization:** Teller role required

---

## Step 1: Transaction Details

### Endpoint
```
POST /api/wizard/transactions/step1
```

### Description
Submit initial transaction details to determine CDD level and required documentation. Triggers sanctions screening and historical risk analysis for returning customers.

### Request Headers
```http
Content-Type: application/json
Authorization: Bearer {token}
X-CSRF-TOKEN: {csrf_token}
```

### Request Body
```json
{
  "customer_id": 123,
  "type": "buy",
  "currency_code": "USD",
  "amount_foreign": "1000.00",
  "rate": "4.50",
  "till_id": "1",
  "purpose": "Travel",
  "source_of_funds": "Salary",
  "collect_additional_details": false
}
```

### Response 200 OK (Success)
```json
{
  "status": "success",
  "wizard_session_id": "550e8400-e29b-41d4-a716-446655440000",
  "cdd_level": "simplified",
  "cdd_description": "Simplified Due Diligence - Basic customer information required",
  "hold_required": false,
  "risk_flags": [],
  "required_documents": [
    {"type": "mykad_front", "required": true, "label": "MyKad (Front)"},
    {"type": "mykad_back", "required": true, "label": "MyKad (Back)"}
  ],
  "customer_is_returning": false,
  "next_step": "customer_details"
}
```

### Response 403 Forbidden (Sanctions Block)
```json
{
  "status": "blocked",
  "message": "Sanctions list match detected. Transaction blocked.",
  "reason": "sanctions"
}
```

### Response 422 Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "amount_foreign": ["Transaction amount must be at least RM 0.01"],
    "customer_id": ["Please select a customer"]
  }
}
```

---

## Step 2: Customer Information

### Endpoint
```
POST /api/wizard/transactions/step2
```

### Description
Submit customer details and required documents based on CDD level determined in Step 1.

### Request Headers
```http
Content-Type: multipart/form-data
Authorization: Bearer {token}
X-CSRF-TOKEN: {csrf_token}
```

### Request Body (Form Data)
```
wizard_session_id: 550e8400-e29b-41d4-a716-446655440000
cdd_level: standard
customer[occupation]: Engineer
customer[employer_name]: Tech Corp
customer[employer_address]: 123 Business Street
customer[annual_volume_estimate]: 50000
customer[proof_of_address]: [file]
customer[passport]: [file] (Enhanced CDD only)
customer[beneficial_owner]: Self (Enhanced CDD only)
customer[source_of_wealth]: Salary and investments (Enhanced CDD only)
transaction[expected_frequency]: monthly (Enhanced CDD only)
```

### Response 200 OK (Success)
```json
{
  "status": "success",
  "wizard_session_id": "550e8400-e29b-41d4-a716-446655440000",
  "transaction_summary": {
    "customer_name": "John Doe",
    "type": "buy",
    "currency": "USD",
    "amount_foreign": "1000.00",
    "rate": "4.50",
    "amount_local": "4500.00",
    "purpose": "Travel",
    "source_of_funds": "Salary",
    "cdd_level": "standard",
    "hold_required": false,
    "risk_flags": null
  },
  "next_step": "review_confirm"
}
```

### Response 400 Bad Request (Session Expired)
```json
{
  "status": "error",
  "message": "Wizard session expired or invalid"
}
```

---

## Step 3: Review & Confirm

### Endpoint
```
POST /api/wizard/transactions/step3
```

### Description
Confirm transaction details and create the transaction. For Enhanced CDD, transaction is held pending manager approval.

### Request Headers
```http
Content-Type: application/json
Authorization: Bearer {token}
X-CSRF-TOKEN: {csrf_token}
```

### Request Body
```json
{
  "wizard_session_id": "550e8400-e29b-41d4-a716-446655440000",
  "confirm_details": true,
  "idempotency_key": "txn_20250414_001"
}
```

### Response 200 OK (Standard/Simplified CDD)
```json
{
  "status": "success",
  "transaction_id": 12345,
  "transaction_number": "TXN-20250414-001",
  "status": "completed",
  "message": "Transaction completed successfully"
}
```

### Response 200 OK (Enhanced CDD)
```json
{
  "status": "success",
  "transaction_id": 12346,
  "transaction_number": "TXN-20250414-002",
  "status": "pending_approval",
  "message": "Transaction created and pending approval"
}
```

---

## Session Status

### Endpoint
```
GET /api/wizard/transactions/{sessionId}/status
```

### Response 200 OK
```json
{
  "status": "active",
  "current_step": 2,
  "expires_at": "2026-04-14T15:30:00Z"
}
```

### Response 404 Not Found
```json
{
  "status": "expired",
  "message": "Wizard session has expired"
}
```

---

## Cancel Session

### Endpoint
```
DELETE /api/wizard/transactions/{sessionId}
```

### Response 200 OK
```json
{
  "status": "cancelled",
  "message": "Wizard session cancelled"
}
```

---

## CDD Levels

| Level | Threshold | Documents Required | Hold Required |
|-------|-----------|-------------------|---------------|
| Simplified | < RM 3,000 | MyKad (front/back) | No |
| Standard | RM 3,000 - 49,999 | MyKad + Proof of Address | No |
| Enhanced | ≥ RM 50,000 or PEP/Sanction/High Risk | MyKad + Proof + Passport + Source of Wealth | Yes |

---

## Risk Flags

| Type | Severity | Description |
|------|----------|-------------|
| velocity | warning | >3 transactions in 24 hours |
| structuring | critical | Multiple transactions just below RM 3,000 |
| amount_escalation | warning | >200% above 90-day average |
| pattern_reversal | warning | Buy/sell pattern change |
| currency_switch | info | Multiple currency types |
| cumulative_amount | warning | 7-day aggregate > RM 50,000 |

---

## Error Codes

| HTTP | Code | Description |
|------|------|-------------|
| 400 | session_expired | Wizard session expired |
| 403 | sanctions | Customer matches sanctions list |
| 403 | unauthorized | User lacks teller role |
| 422 | validation_error | Input validation failed |
| 500 | server_error | Internal server error |

---

## Notes

1. **Idempotency:** Use unique `idempotency_key` in Step 3 to prevent duplicate transactions
2. **Session TTL:** Sessions expire after 1 hour of inactivity
3. **File Uploads:** Max 5MB per file, allowed: PDF, JPG, PNG
4. **Audit Trail:** Every step logged with tamper-evident hash chaining
5. **Deferred Bookkeeping:** Enhanced CDD transactions create journal entries only after approval
