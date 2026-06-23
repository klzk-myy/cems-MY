# Model & Factory Audit Findings

Generated: 2026-06-21

## Summary

| Issue type | Count |
|------------|-------|
| Missing factories | 26 |
| Relationship return-type gaps | 0 |
| Schema mismatches | 231 |
| Missing inverse relationships | 110 |
| Factory issues | 24 |

## Missing Factories

| Model | Why needed | Key fillable fields | Key relationships |
|-------|------------|---------------------|-------------------|
| AccountLedger | No matching factory class found | account_code, entry_date, journal_entry_id, debit, credit, running_balance | account, journalEntry |
| AmlRule | No matching factory class found | rule_code, rule_name, description, rule_type, conditions, action, risk_score, is_active, created_by | creator |
| BackupLog | No matching factory class found | user_id, backup_name, backup_type, disk, file_path, file_size, checksum, encryption_status, status, started_at | user |
| BankReconciliation | No matching factory class found | account_code, statement_date, reference, description, debit, credit, status, matched_to_journal_entry_id, created_by, matched_at | account, matchedEntry, creator |
| BranchClosureWorkflow | No matching factory class found | branch_id, initiated_by, status, checklist, settlement_at, finalized_at | branch, initiator |
| ComplianceCaseDocument | No matching factory class found | case_id, file_name, file_path, file_type, uploaded_by, uploaded_at, verified_at, verified_by | case, uploader, verifier |
| ComplianceCaseLink | No matching factory class found | case_id, linked_type, linked_id, created_at | case |
| CostCenter | No matching factory class found | code, name, description, is_active, department_id | department |
| CustomerBehavioralBaseline | No matching factory class found | customer_id, currency_codes, avg_transaction_size_myr, avg_transaction_frequency, preferred_counter_ids, registered_location, last_calculated_at, baseline_version | customer |
| CustomerRiskProfile | No matching factory class found | customer_id, risk_score, risk_tier, risk_factors, previous_score, score_changed_at, next_scheduled_recalculation, recalculation_trigger, locked_until, locked_by | customer, lockedByUser |
| Department | No matching factory class found | code, name, description, is_active | costCenters |
| DeviceComputations | No matching factory class found | user_id, device_name, device_fingerprint, ip_address, expires_at, last_used_at | user |
| EddDocumentRequest | No matching factory class found | edd_record_id, document_type, file_path, status, rejection_reason, uploaded_at, verified_at, verified_by | eddRecord, verifier |
| EddTemplate | No matching factory class found | name, type, description, questions, version, is_active, created_by | createdBy, enhancedDiligenceRecords |
| ExchangeRateHistory | No matching factory class found | branch_id, currency_code, rate, effective_date, created_by, notes | currency, creator, branch |
| HighRiskCountry | No matching factory class found | country_code, country_name, risk_level, source, list_date |  |
| MfaRecoveryCode | No matching factory class found | user_id, code_hash, used, used_at | user |
| PepApprovalRequest | No matching factory class found | customer_id, transaction_type, status, approval_level, requested_at, approved_by, approved_at, rejected_by, rejected_at, rejection_reason | customer, approver, rejector |
| RevaluationEntry | No matching factory class found | currency_code, till_id, old_rate, new_rate, position_amount, gain_loss_amount, revaluation_date, posted_by | currency, postedBy |
| SanctionImportLog | No matching factory class found | list_id, imported_at, records_added, records_updated, records_deactivated, status, error_message, triggered_by, user_id | sanctionList, user |
| SanctionsAnalysis | No matching factory class found | customer_id, analysis_type, transaction_count, total_amount, analyzed_at | customer |
| StockTransfer | No matching factory class found | transfer_number, type, status, source_branch_name, destination_branch_name, requested_by, requested_at, branch_manager_approved_by, branch_manager_approved_at, hq_approved_by | requestedBy, branchManagerApprovedBy, hqApprovedBy, items |
| StockTransferItem | No matching factory class found | stock_transfer_id, currency_code, quantity, rate, value_myr, quantity_received, quantity_in_transit, variance_notes | stockTransfer, currency |
| SystemLog | No matching factory class found | user_id, action, description, severity, entity_type, entity_id, old_values, new_values, ip_address, user_agent | user |
| ThresholdAudit | No matching factory class found | category, key, old_value, new_value, changed_by, change_reason, changed_at | user |
| TransactionImport | No matching factory class found | filename, original_filename, file_hash, file_size, status, total_rows, processed_rows, success_count, error_count, error_details | user |

## Relationship Return-Type Gaps

None found.

## Schema Mismatches

| Model | Issue type | Field | Migration column | Recommended fix |
|-------|------------|-------|------------------|-----------------|
| AccountLedger | column not fillable/cast | transaction_date | date | Add 'transaction_date' to $fillable or $casts |
| AccountLedger | column not fillable/cast | entry_type | string | Add 'entry_type' to $fillable or $casts |
| AccountLedger | column not fillable/cast | entry_id | unsignedBigInteger | Add 'entry_id' to $fillable or $casts |
| AccountLedger | column not fillable/cast | debit_amount | decimal | Add 'debit_amount' to $fillable or $casts |
| AccountLedger | column not fillable/cast | credit_amount | decimal | Add 'credit_amount' to $fillable or $casts |
| AccountLedger | column not fillable/cast | reference_type | string | Add 'reference_type' to $fillable or $casts |
| AccountLedger | column not fillable/cast | reference_id | unsignedBigInteger | Add 'reference_id' to $fillable or $casts |
| AccountLedger | column not fillable/cast | description | text | Add 'description' to $fillable or $casts |
| AccountLedger | column not fillable/cast | cost_center_id | foreignId | Add 'cost_center_id' to $fillable or $casts |
| AccountLedger | column not fillable/cast | department_id | foreignId | Add 'department_id' to $fillable or $casts |
| AccountLedger | column not fillable/cast | branch_id | foreignId | Add 'branch_id' to $fillable or $casts |
| AccountingPeriod | column not fillable/cast | is_adjustment_period | boolean | Add 'is_adjustment_period' to $fillable or $casts |
| AmlRule | column not fillable/cast | is_enabled | boolean | Add 'is_enabled' to $fillable or $casts |
| AmlRule | column not fillable/cast | flag_type | string | Add 'flag_type' to $fillable or $casts |
| AmlRule | column not fillable/cast | parameters | json | Add 'parameters' to $fillable or $casts |
| AmlRule | JSON not cast to array | parameters | json | Cast 'parameters' => 'array' (or AsArrayObject/AsCollection) |
| AmlRule | column not fillable/cast | priority | integer | Add 'priority' to $fillable or $casts |
| BankReconciliation | enum not cast to enum class | check_status | enum | Create an Enum and cast 'check_status' => EnumClass::class |
| BankReconciliation | column not fillable/cast | branch_id | foreignId | Add 'branch_id' to $fillable or $casts |
| BankReconciliation | column not fillable/cast | account_number | string | Add 'account_number' to $fillable or $casts |
| BankReconciliation | column not fillable/cast | bank_name | string | Add 'bank_name' to $fillable or $casts |
| BankReconciliation | column not fillable/cast | statement_balance | decimal | Add 'statement_balance' to $fillable or $casts |
| BankReconciliation | column not fillable/cast | book_balance | decimal | Add 'book_balance' to $fillable or $casts |
| BankReconciliation | column not fillable/cast | difference | decimal | Add 'difference' to $fillable or $casts |
| BankReconciliation | column not fillable/cast | prepared_by | foreignId | Add 'prepared_by' to $fillable or $casts |
| BankReconciliation | column not fillable/cast | reviewed_by | foreignId | Add 'reviewed_by' to $fillable or $casts |
| BankReconciliation | column not fillable/cast | reviewed_at | timestamp | Add 'reviewed_at' to $fillable or $casts |
| BranchPool | column not fillable/cast | total_balance | decimal | Add 'total_balance' to $fillable or $casts |
| ChartOfAccount | column not fillable/cast | normal_balance | string | Add 'normal_balance' to $fillable or $casts |
| ComplianceCase | column not fillable/cast | customer_id | unsignedBigInteger | Add 'customer_id' to $fillable or $casts |
| ComplianceCaseDocument | column not fillable/cast | document_type | string | Add 'document_type' to $fillable or $casts |
| ComplianceCaseDocument | column not fillable/cast | file_hash | string | Add 'file_hash' to $fillable or $casts |
| ComplianceCaseDocument | column not fillable/cast | file_size | integer | Add 'file_size' to $fillable or $casts |
| ComplianceCaseLink | column not fillable/cast | linked_entity_type | string | Add 'linked_entity_type' to $fillable or $casts |
| ComplianceCaseLink | column not fillable/cast | linked_entity_id | unsignedBigInteger | Add 'linked_entity_id' to $fillable or $casts |
| ComplianceCaseLink | column not fillable/cast | relationship | string | Add 'relationship' to $fillable or $casts |
| ComplianceCaseNote | column not fillable/cast | user_id | foreignId | Add 'user_id' to $fillable or $casts |
| ComplianceCaseNote | column not fillable/cast | note | text | Add 'note' to $fillable or $casts |
| ComplianceCaseNote | column not fillable/cast | type | string | Add 'type' to $fillable or $casts |
| Counter | column not fillable/cast | assigned_teller_id | foreignId | Add 'assigned_teller_id' to $fillable or $casts |
| CounterSession | column not fillable/cast | requested_amount_myr | decimal | Add 'requested_amount_myr' to $fillable or $casts |
| CounterSession | column not fillable/cast | daily_limit_myr | decimal | Add 'daily_limit_myr' to $fillable or $casts |
| CurrencyPosition | fillable without column | balance | - | Remove from $fillable or add column to 'currency_positions' |
| CurrencyPosition | fillable without column | avg_cost_rate | - | Remove from $fillable or add column to 'currency_positions' |
| CurrencyPosition | fillable without column | last_valuation_rate | - | Remove from $fillable or add column to 'currency_positions' |
| CurrencyPosition | fillable without column | unrealized_pnl | - | Remove from $fillable or add column to 'currency_positions' |
| CurrencyPosition | fillable without column | last_valuation_at | - | Remove from $fillable or add column to 'currency_positions' |
| CurrencyPosition | fillable without column | till_id | - | Remove from $fillable or add column to 'currency_positions' |
| Customer | fillable without column | sanction_hit | - | Remove from $fillable or add column to 'customers' |
| Customer | fillable without column | cdd_level | - | Remove from $fillable or add column to 'customers' |
| Customer | fillable without column | is_active | - | Remove from $fillable or add column to 'customers' |
| Customer | fillable without column | occupation | - | Remove from $fillable or add column to 'customers' |
| Customer | fillable without column | employer_name | - | Remove from $fillable or add column to 'customers' |
| Customer | fillable without column | employer_address | - | Remove from $fillable or add column to 'customers' |
| Customer | fillable without column | annual_volume_estimate | - | Remove from $fillable or add column to 'customers' |
| Customer | column not fillable/cast | sanctions_screened_at | timestamp | Add 'sanctions_screened_at' to $fillable or $casts |
| Customer | column not fillable/cast | customer_type | enum | Add 'customer_type' to $fillable or $casts |
| Customer | enum not cast to enum class | customer_type | enum | Create an Enum and cast 'customer_type' => EnumClass::class |
| Customer | column not fillable/cast | pep_type | string | Add 'pep_type' to $fillable or $casts |
| Customer | dead cast | sanction_hit | - | Remove cast 'sanction_hit' or add column to 'customers' |
| Customer | dead cast | is_active | - | Remove cast 'is_active' or add column to 'customers' |
| Customer | dead cast | annual_volume_estimate | - | Remove cast 'annual_volume_estimate' or add column to 'customers' |
| Customer | dead cast | cdd_level | - | Remove cast 'cdd_level' or add column to 'customers' |
| CustomerBehavioralBaseline | column not fillable/cast | metric_type | string | Add 'metric_type' to $fillable or $casts |
| CustomerBehavioralBaseline | column not fillable/cast | typical_amount | decimal | Add 'typical_amount' to $fillable or $casts |
| CustomerBehavioralBaseline | column not fillable/cast | average_amount | decimal | Add 'average_amount' to $fillable or $casts |
| CustomerBehavioralBaseline | column not fillable/cast | max_amount | decimal | Add 'max_amount' to $fillable or $casts |
| CustomerBehavioralBaseline | column not fillable/cast | transaction_count | integer | Add 'transaction_count' to $fillable or $casts |
| CustomerBehavioralBaseline | column not fillable/cast | typical_frequency | decimal | Add 'typical_frequency' to $fillable or $casts |
| CustomerBehavioralBaseline | column not fillable/cast | common_currency | string | Add 'common_currency' to $fillable or $casts |
| CustomerBehavioralBaseline | column not fillable/cast | common_purposes | json | Add 'common_purposes' to $fillable or $casts |
| CustomerBehavioralBaseline | JSON not cast to array | common_purposes | json | Cast 'common_purposes' => 'array' (or AsArrayObject/AsCollection) |
| CustomerBehavioralBaseline | column not fillable/cast | standard_deviation | decimal | Add 'standard_deviation' to $fillable or $casts |
| CustomerBehavioralBaseline | column not fillable/cast | computed_at | timestamp | Add 'computed_at' to $fillable or $casts |
| CustomerDocument | column not fillable/cast | status | string | Add 'status' to $fillable or $casts |
| CustomerRiskHistory | column not fillable/cast | previous_rating | string | Add 'previous_rating' to $fillable or $casts |
| CustomerRiskHistory | column not fillable/cast | previous_score | integer | Add 'previous_score' to $fillable or $casts |
| CustomerRiskHistory | column not fillable/cast | changed_by | foreignId | Add 'changed_by' to $fillable or $casts |
| CustomerRiskProfile | column not fillable/cast | overall_score | unsignedTinyInteger | Add 'overall_score' to $fillable or $casts |
| CustomerRiskProfile | column not fillable/cast | velocity_score | unsignedTinyInteger | Add 'velocity_score' to $fillable or $casts |
| CustomerRiskProfile | column not fillable/cast | structuring_score | unsignedTinyInteger | Add 'structuring_score' to $fillable or $casts |
| CustomerRiskProfile | column not fillable/cast | geographic_score | unsignedTinyInteger | Add 'geographic_score' to $fillable or $casts |
| CustomerRiskProfile | column not fillable/cast | amount_score | unsignedTinyInteger | Add 'amount_score' to $fillable or $casts |
| CustomerRiskProfile | column not fillable/cast | frequency_score | unsignedTinyInteger | Add 'frequency_score' to $fillable or $casts |
| CustomerRiskProfile | column not fillable/cast | risk_rating | string | Add 'risk_rating' to $fillable or $casts |
| CustomerRiskProfile | column not fillable/cast | trend | string | Add 'trend' to $fillable or $casts |
| CustomerRiskProfile | column not fillable/cast | last_evaluated_at | timestamp | Add 'last_evaluated_at' to $fillable or $casts |
| CustomerRiskProfile | column not fillable/cast | locked_at | timestamp | Add 'locked_at' to $fillable or $casts |
| DeviceComputations | column not fillable/cast | device_type | string | Add 'device_type' to $fillable or $casts |
| DeviceComputations | column not fillable/cast | public_key | string | Add 'public_key' to $fillable or $casts |
| DeviceComputations | column not fillable/cast | algorithm | string | Add 'algorithm' to $fillable or $casts |
| DeviceComputations | column not fillable/cast | counter | integer | Add 'counter' to $fillable or $casts |
| DeviceComputations | column not fillable/cast | credential_ip | text | Add 'credential_ip' to $fillable or $casts |
| DeviceComputations | column not fillable/cast | status | string | Add 'status' to $fillable or $casts |
| DeviceComputations | column not fillable/cast | registered_at | timestamp | Add 'registered_at' to $fillable or $casts |
| EddDocumentRequest | column not fillable/cast | customer_id | foreignId | Add 'customer_id' to $fillable or $casts |
| EddDocumentRequest | column not fillable/cast | case_id | foreignId | Add 'case_id' to $fillable or $casts |
| EddDocumentRequest | column not fillable/cast | description | text | Add 'description' to $fillable or $casts |
| EddDocumentRequest | column not fillable/cast | deadline | date | Add 'deadline' to $fillable or $casts |
| EddDocumentRequest | column not fillable/cast | received_at | date | Add 'received_at' to $fillable or $casts |
| EddDocumentRequest | column not fillable/cast | reviewed_by | foreignId | Add 'reviewed_by' to $fillable or $casts |
| EddDocumentRequest | column not fillable/cast | reviewed_at | timestamp | Add 'reviewed_at' to $fillable or $casts |
| EddQuestionnaireTemplate | column not fillable/cast | risk_level | string | Add 'risk_level' to $fillable or $casts |
| EddQuestionnaireTemplate | column not fillable/cast | description | text | Add 'description' to $fillable or $casts |
| EddQuestionnaireTemplate | column not fillable/cast | created_by | foreignId | Add 'created_by' to $fillable or $casts |
| EnhancedDiligenceRecord | column not fillable/cast | customer_id | foreignId | Add 'customer_id' to $fillable or $casts |
| EnhancedDiligenceRecord | column not fillable/cast | edd_level | string | Add 'edd_level' to $fillable or $casts |
| EnhancedDiligenceRecord | column not fillable/cast | started_at | timestamp | Add 'started_at' to $fillable or $casts |
| EnhancedDiligenceRecord | column not fillable/cast | completed_at | timestamp | Add 'completed_at' to $fillable or $casts |
| EnhancedDiligenceRecord | column not fillable/cast | assigned_to | foreignId | Add 'assigned_to' to $fillable or $casts |
| EnhancedDiligenceRecord | column not fillable/cast | notes | text | Add 'notes' to $fillable or $casts |
| ExchangeRate | column not fillable/cast | spread_applied | string | Add 'spread_applied' to $fillable or $casts |
| ExchangeRateHistory | column not fillable/cast | rate_buy | decimal | Add 'rate_buy' to $fillable or $casts |
| ExchangeRateHistory | column not fillable/cast | rate_sell | decimal | Add 'rate_sell' to $fillable or $casts |
| ExchangeRateHistory | column not fillable/cast | source | string | Add 'source' to $fillable or $casts |
| ExchangeRateHistory | column not fillable/cast | fetched_at | timestamp | Add 'fetched_at' to $fillable or $casts |
| ExchangeRateHistory | column not fillable/cast | spread_applied | string | Add 'spread_applied' to $fillable or $casts |
| FiscalYear | column not fillable/cast | is_closed | boolean | Add 'is_closed' to $fillable or $casts |
| JournalEntry | column not fillable/cast | created_by | foreignId | Add 'created_by' to $fillable or $casts |
| JournalEntry | column not fillable/cast | branch_id | foreignId | Add 'branch_id' to $fillable or $casts |
| JournalLine | column not fillable/cast | debit_amount | decimal | Add 'debit_amount' to $fillable or $casts |
| JournalLine | column not fillable/cast | credit_amount | decimal | Add 'credit_amount' to $fillable or $casts |
| JournalLine | column not fillable/cast | cost_center_id | foreignId | Add 'cost_center_id' to $fillable or $casts |
| JournalLine | column not fillable/cast | department_id | foreignId | Add 'department_id' to $fillable or $casts |
| JournalLine | column not fillable/cast | branch_id | foreignId | Add 'branch_id' to $fillable or $casts |
| MfaRecoveryCode | column not fillable/cast | code | string | Add 'code' to $fillable or $casts |
| ReportGenerated | fillable without column | version | - | Remove from $fillable or add column to 'reports_generated' |
| ReportGenerated | fillable without column | notes | - | Remove from $fillable or add column to 'reports_generated' |
| ReportGenerated | enum not cast to enum class | file_format | enum | Create an Enum and cast 'file_format' => EnumClass::class |
| ReportGenerated | column not fillable/cast | report_name | string | Add 'report_name' to $fillable or $casts |
| ReportGenerated | column not fillable/cast | format | string | Add 'format' to $fillable or $casts |
| ReportGenerated | column not fillable/cast | row_count | unsignedInteger | Add 'row_count' to $fillable or $casts |
| ReportGenerated | column not fillable/cast | error_message | text | Add 'error_message' to $fillable or $casts |
| ReportGenerated | dead cast | version | - | Remove cast 'version' or add column to 'reports_generated' |
| RevaluationEntry | column not fillable/cast | posted_at | timestamp | Add 'posted_at' to $fillable or $casts |
| RevaluationEntry | column not fillable/cast | branch_id | string | Add 'branch_id' to $fillable or $casts |
| RevaluationEntry | column not fillable/cast | quantity_before | decimal | Add 'quantity_before' to $fillable or $casts |
| RevaluationEntry | column not fillable/cast | rate_before | decimal | Add 'rate_before' to $fillable or $casts |
| RevaluationEntry | column not fillable/cast | value_before_myr | decimal | Add 'value_before_myr' to $fillable or $casts |
| RevaluationEntry | column not fillable/cast | quantity_after | decimal | Add 'quantity_after' to $fillable or $casts |
| RevaluationEntry | column not fillable/cast | rate_after | decimal | Add 'rate_after' to $fillable or $casts |
| RevaluationEntry | column not fillable/cast | value_after_myr | decimal | Add 'value_after_myr' to $fillable or $casts |
| RevaluationEntry | column not fillable/cast | gain_loss | decimal | Add 'gain_loss' to $fillable or $casts |
| RevaluationEntry | column not fillable/cast | created_by | foreignId | Add 'created_by' to $fillable or $casts |
| RevaluationEntry | column not fillable/cast | journal_entry_id | foreignId | Add 'journal_entry_id' to $fillable or $casts |
| SanctionEntry | JSON not cast to array | details | json | Cast 'details' => 'array' (or AsArrayObject/AsCollection) |
| SanctionImportLog | enum not cast to enum class | status | enum | Create an Enum and cast 'status' => EnumClass::class |
| SanctionImportLog | enum not cast to enum class | triggered_by | enum | Create an Enum and cast 'triggered_by' => EnumClass::class |
| SanctionList | fillable without column | source_url | - | Remove from $fillable or add column to 'sanction_lists' |
| SanctionList | fillable without column | source_format | - | Remove from $fillable or add column to 'sanction_lists' |
| SanctionList | fillable without column | auto_updated_by | - | Remove from $fillable or add column to 'sanction_lists' |
| SanctionList | fillable without column | last_updated_at | - | Remove from $fillable or add column to 'sanction_lists' |
| SanctionList | fillable without column | last_attempted_at | - | Remove from $fillable or add column to 'sanction_lists' |
| SanctionList | fillable without column | update_status | - | Remove from $fillable or add column to 'sanction_lists' |
| SanctionList | fillable without column | last_error_message | - | Remove from $fillable or add column to 'sanction_lists' |
| SanctionList | fillable without column | entry_count | - | Remove from $fillable or add column to 'sanction_lists' |
| SanctionList | fillable without column | last_checksum | - | Remove from $fillable or add column to 'sanction_lists' |
| SanctionList | dead cast | last_updated_at | - | Remove cast 'last_updated_at' or add column to 'sanction_lists' |
| SanctionList | dead cast | last_attempted_at | - | Remove cast 'last_attempted_at' or add column to 'sanction_lists' |
| SanctionList | dead cast | entry_count | - | Remove cast 'entry_count' or add column to 'sanction_lists' |
| SanctionList | dead cast | update_status | - | Remove cast 'update_status' or add column to 'sanction_lists' |
| ScreeningResult | enum not cast to enum class | action_taken | enum | Create an Enum and cast 'action_taken' => EnumClass::class |
| ScreeningResult | enum not cast to enum class | result | enum | Create an Enum and cast 'result' => EnumClass::class |
| StockTransfer | enum not cast to enum class | type | enum | Create an Enum and cast 'type' => EnumClass::class |
| StockTransfer | column not fillable/cast | from_branch | string | Add 'from_branch' to $fillable or $casts |
| StockTransfer | column not fillable/cast | to_branch | string | Add 'to_branch' to $fillable or $casts |
| StockTransfer | column not fillable/cast | approved_by | foreignId | Add 'approved_by' to $fillable or $casts |
| StockTransfer | column not fillable/cast | approved_at | timestamp | Add 'approved_at' to $fillable or $casts |
| StockTransfer | column not fillable/cast | received_by | foreignId | Add 'received_by' to $fillable or $casts |
| StockTransfer | column not fillable/cast | received_at | timestamp | Add 'received_at' to $fillable or $casts |
| StockTransferItem | column not fillable/cast | transfer_id | foreignId | Add 'transfer_id' to $fillable or $casts |
| SystemLog | enum not cast to enum class | severity | enum | Create an Enum and cast 'severity' => EnumClass::class |
| TellerAllocation | fillable without column | rejected_at | - | Remove from $fillable or add column to 'teller_allocations' |
| TellerAllocation | dead cast | rejected_at | - | Remove cast 'rejected_at' or add column to 'teller_allocations' |
| TestResult | fillable without column | run_id | - | Remove from $fillable or add column to 'test_results' |
| TestResult | fillable without column | test_suite | - | Remove from $fillable or add column to 'test_results' |
| TestResult | fillable without column | total_tests | - | Remove from $fillable or add column to 'test_results' |
| TestResult | fillable without column | passed | - | Remove from $fillable or add column to 'test_results' |
| TestResult | fillable without column | failed | - | Remove from $fillable or add column to 'test_results' |
| TestResult | fillable without column | skipped | - | Remove from $fillable or add column to 'test_results' |
| TestResult | fillable without column | assertions | - | Remove from $fillable or add column to 'test_results' |
| TestResult | fillable without column | duration | - | Remove from $fillable or add column to 'test_results' |
| TestResult | fillable without column | status | - | Remove from $fillable or add column to 'test_results' |
| TestResult | fillable without column | output | - | Remove from $fillable or add column to 'test_results' |
| TestResult | fillable without column | failures | - | Remove from $fillable or add column to 'test_results' |
| TestResult | fillable without column | errors | - | Remove from $fillable or add column to 'test_results' |
| TestResult | fillable without column | git_branch | - | Remove from $fillable or add column to 'test_results' |
| TestResult | fillable without column | git_commit | - | Remove from $fillable or add column to 'test_results' |
| TestResult | fillable without column | executed_by | - | Remove from $fillable or add column to 'test_results' |
| TestResult | fillable without column | started_at | - | Remove from $fillable or add column to 'test_results' |
| TestResult | fillable without column | completed_at | - | Remove from $fillable or add column to 'test_results' |
| TestResult | dead cast | duration | - | Remove cast 'duration' or add column to 'test_results' |
| TestResult | dead cast | total_tests | - | Remove cast 'total_tests' or add column to 'test_results' |
| TestResult | dead cast | passed | - | Remove cast 'passed' or add column to 'test_results' |
| TestResult | dead cast | failed | - | Remove cast 'failed' or add column to 'test_results' |
| TestResult | dead cast | skipped | - | Remove cast 'skipped' or add column to 'test_results' |
| TestResult | dead cast | assertions | - | Remove cast 'assertions' or add column to 'test_results' |
| TestResult | dead cast | started_at | - | Remove cast 'started_at' or add column to 'test_results' |
| TestResult | dead cast | completed_at | - | Remove cast 'completed_at' or add column to 'test_results' |
| TestResult | dead cast | failures | - | Remove cast 'failures' or add column to 'test_results' |
| TestResult | dead cast | errors | - | Remove cast 'errors' or add column to 'test_results' |
| TestResult | dead cast | status | - | Remove cast 'status' or add column to 'test_results' |
| Transaction | fillable without column | transition_history | - | Remove from $fillable or add column to 'transactions' |
| Transaction | fillable without column | failure_reason | - | Remove from $fillable or add column to 'transactions' |
| Transaction | fillable without column | rejection_reason | - | Remove from $fillable or add column to 'transactions' |
| Transaction | fillable without column | reversal_reason | - | Remove from $fillable or add column to 'transactions' |
| Transaction | fillable without column | journal_entry_id | - | Remove from $fillable or add column to 'transactions' |
| Transaction | fillable without column | deferred_journal_entry_id | - | Remove from $fillable or add column to 'transactions' |
| Transaction | fillable without column | journal_entries_created_at | - | Remove from $fillable or add column to 'transactions' |
| Transaction | fillable without column | has_deferred_accounting | - | Remove from $fillable or add column to 'transactions' |
| Transaction | column not fillable/cast | customer_id | foreignId | Add 'customer_id' to $fillable or $casts |
| Transaction | column not fillable/cast | user_id | foreignId | Add 'user_id' to $fillable or $casts |
| Transaction | column not fillable/cast | currency_code | string | Add 'currency_code' to $fillable or $casts |
| Transaction | column not fillable/cast | approved_by | foreignId | Add 'approved_by' to $fillable or $casts |
| Transaction | column not fillable/cast | approved_at | timestamp | Add 'approved_at' to $fillable or $casts |
| Transaction | column not fillable/cast | branch_id | foreignId | Add 'branch_id' to $fillable or $casts |
| Transaction | column not fillable/cast | approval_sync_failed | boolean | Add 'approval_sync_failed' to $fillable or $casts |
| Transaction | column not fillable/cast | approval_sync_failed_at | timestamp | Add 'approval_sync_failed_at' to $fillable or $casts |
| Transaction | column not fillable/cast | approval_sync_error | text | Add 'approval_sync_error' to $fillable or $casts |
| Transaction | dead cast | transition_history | - | Remove cast 'transition_history' or add column to 'transactions' |
| Transaction | dead cast | journal_entries_created_at | - | Remove cast 'journal_entries_created_at' or add column to 'transactions' |
| Transaction | dead cast | has_deferred_accounting | - | Remove cast 'has_deferred_accounting' or add column to 'transactions' |
| TransactionConfirmation | column not fillable/cast | user_id | foreignId | Add 'user_id' to $fillable or $casts |
| TransactionConfirmation | column not fillable/cast | notes | text | Add 'notes' to $fillable or $casts |
| TransactionConfirmation | column not fillable/cast | confirmation_method | string | Add 'confirmation_method' to $fillable or $casts |
| TransactionImport | column not fillable/cast | user_id | foreignId | Add 'user_id' to $fillable or $casts |
| TransactionImport | column not fillable/cast | errors | json | Add 'errors' to $fillable or $casts |
| TransactionImport | JSON not cast to array | errors | json | Cast 'errors' => 'array' (or AsArrayObject/AsCollection) |
| TransactionImport | column not fillable/cast | started_at | timestamp | Add 'started_at' to $fillable or $casts |
| User | fillable without column | password | - | Remove from $fillable or add column to 'users' |
| User | column not fillable/cast | mfa_secret | text | Add 'mfa_secret' to $fillable or $casts |

## Missing Inverse Relationships

| Model | Method | Should add to target model |
|-------|--------|----------------------------|
| AccountingPeriod | closedBy | User::hasMany() / hasOne() |
| Alert | flaggedTransaction | FlaggedTransaction::hasMany() / hasOne() |
| Alert | customer | Customer::hasMany() / hasOne() |
| Alert | assignedTo | User::hasMany() / hasOne() |
| AmlRule | creator | User::hasMany() / hasOne() |
| BackupLog | user | User::hasMany() / hasOne() |
| BankReconciliation | account | ChartOfAccount::hasMany() / hasOne() |
| BankReconciliation | matchedEntry | JournalEntry::hasMany() / hasOne() |
| BankReconciliation | creator | User::hasMany() / hasOne() |
| Branch | counters | Counter::belongsTo() |
| Branch | transactions | Transaction::belongsTo() |
| Branch | journalEntries | JournalEntry::belongsTo() |
| Branch | tillBalances | TillBalance::belongsTo() |
| BranchClosureWorkflow | branch | Branch::hasMany() / hasOne() |
| BranchClosureWorkflow | initiator | User::hasMany() / hasOne() |
| BranchPool | branch | Branch::hasMany() / hasOne() |
| Budget | account | ChartOfAccount::hasMany() / hasOne() |
| Budget | creator | User::hasMany() / hasOne() |
| Budget | period | AccountingPeriod::hasMany() / hasOne() |
| ChartOfAccount | costCenter | CostCenter::hasMany() / hasOne() |
| ChartOfAccount | department | Department::hasMany() / hasOne() |
| ComplianceCase | primaryFlag | FlaggedTransaction::hasMany() / hasOne() |
| ComplianceCase | primaryFinding | ComplianceFinding::hasMany() / hasOne() |
| ComplianceCase | assignee | User::hasMany() / hasOne() |
| ComplianceCaseDocument | uploader | User::hasMany() / hasOne() |
| ComplianceCaseDocument | verifier | User::hasMany() / hasOne() |
| ComplianceCaseNote | author | User::hasMany() / hasOne() |
| CounterHandover | fromUser | User::hasMany() / hasOne() |
| CounterHandover | toUser | User::hasMany() / hasOne() |
| CounterHandover | supervisor | User::hasMany() / hasOne() |
| CounterSession | user | User::hasMany() / hasOne() |
| CounterSession | tellerAllocation | TellerAllocation::hasMany() / hasOne() |
| CounterSession | openedByUser | User::hasMany() / hasOne() |
| CounterSession | closedByUser | User::hasMany() / hasOne() |
| Currency | transactions | Transaction::belongsTo() |
| CurrencyPosition | currency | Currency::hasMany() / hasOne() |
| Customer | transactions | Transaction::belongsTo() |
| Customer | latestTransaction | Transaction::belongsTo() |
| CustomerBehavioralBaseline | customer | Customer::hasMany() / hasOne() |
| CustomerDocument | uploader | User::hasMany() / hasOne() |
| CustomerDocument | verifier | User::hasMany() / hasOne() |
| CustomerNote | creator | User::hasMany() / hasOne() |
| CustomerRiskHistory | assessor | User::hasMany() / hasOne() |
| CustomerRiskProfile | customer | Customer::hasMany() / hasOne() |
| CustomerRiskProfile | lockedByUser | User::hasMany() / hasOne() |
| EddDocumentRequest | eddRecord | EnhancedDiligenceRecord::hasMany() / hasOne() |
| EddDocumentRequest | verifier | User::hasMany() / hasOne() |
| EddTemplate | createdBy | User::hasMany() / hasOne() |
| EddTemplate | enhancedDiligenceRecords | EnhancedDiligenceRecord::belongsTo() |
| EmergencyClosure | counter | Counter::hasMany() / hasOne() |
| EmergencyClosure | session | CounterSession::hasMany() / hasOne() |
| EmergencyClosure | teller | User::hasMany() / hasOne() |
| EmergencyClosure | acknowledgedBy | User::hasMany() / hasOne() |
| EnhancedDiligenceRecord | flaggedTransaction | FlaggedTransaction::hasMany() / hasOne() |
| EnhancedDiligenceRecord | reviewer | User::hasMany() / hasOne() |
| EnhancedDiligenceRecord | approvedBy | User::hasMany() / hasOne() |
| EnhancedDiligenceRecord | questionnaireCompletedBy | User::hasMany() / hasOne() |
| EnhancedDiligenceRecord | template | EddQuestionnaireTemplate::hasMany() / hasOne() |
| ExchangeRate | branch | Branch::hasMany() / hasOne() |
| ExchangeRateHistory | currency | Currency::hasMany() / hasOne() |
| ExchangeRateHistory | creator | User::hasMany() / hasOne() |
| ExchangeRateHistory | branch | Branch::hasMany() / hasOne() |
| FiscalYear | closedBy | User::hasMany() / hasOne() |
| FlaggedTransaction | customer | Customer::hasMany() / hasOne() |
| FlaggedTransaction | assignedTo | User::hasMany() / hasOne() |
| FlaggedTransaction | reviewer | User::hasMany() / hasOne() |
| JournalEntry | postedBy | User::hasMany() / hasOne() |
| JournalEntry | reversedBy | User::hasMany() / hasOne() |
| JournalEntry | approver | User::hasMany() / hasOne() |
| JournalEntry | costCenter | CostCenter::hasMany() / hasOne() |
| JournalEntry | department | Department::hasMany() / hasOne() |
| PepApprovalRequest | customer | Customer::hasMany() / hasOne() |
| PepApprovalRequest | approver | User::hasMany() / hasOne() |
| PepApprovalRequest | rejector | User::hasMany() / hasOne() |
| ReportGenerated | generatedBy | User::hasMany() / hasOne() |
| ReportGenerated | submittedBy | User::hasMany() / hasOne() |
| ReportRun | generatedBy | User::hasMany() / hasOne() |
| ReportSchedule | createdBy | User::hasMany() / hasOne() |
| RevaluationEntry | currency | Currency::hasMany() / hasOne() |
| RevaluationEntry | postedBy | User::hasMany() / hasOne() |
| SanctionImportLog | user | User::hasMany() / hasOne() |
| SanctionList | uploadedBy | User::hasMany() / hasOne() |
| SanctionList | autoUpdatedBy | User::hasMany() / hasOne() |
| SanctionsAnalysis | customer | Customer::hasMany() / hasOne() |
| ScreeningResult | customer | Customer::hasMany() / hasOne() |
| ScreeningResult | transaction | Transaction::hasMany() / hasOne() |
| ScreeningResult | sanctionEntry | SanctionEntry::hasMany() / hasOne() |
| StockReservation | creator | User::hasMany() / hasOne() |
| StockTransfer | requestedBy | User::hasMany() / hasOne() |
| StockTransfer | branchManagerApprovedBy | User::hasMany() / hasOne() |
| StockTransfer | hqApprovedBy | User::hasMany() / hasOne() |
| StockTransferItem | currency | Currency::hasMany() / hasOne() |
| SystemAlert | acknowledgedBy | User::hasMany() / hasOne() |
| SystemLog | user | User::hasMany() / hasOne() |
| TellerAllocation | user | User::hasMany() / hasOne() |
| TellerAllocation | counter | Counter::hasMany() / hasOne() |
| TellerAllocation | approver | User::hasMany() / hasOne() |
| TestResult | executedBy | User::hasMany() / hasOne() |
| ThresholdAudit | user | User::hasMany() / hasOne() |
| TillBalance | currency | Currency::hasMany() / hasOne() |
| TillBalance | opener | User::hasMany() / hasOne() |
| TillBalance | closer | User::hasMany() / hasOne() |
| TillBalance | counter | Counter::hasMany() / hasOne() |
| TillBalance | tellerAllocation | TellerAllocation::hasMany() / hasOne() |
| Transaction | journalEntry | JournalEntry::hasMany() / hasOne() |
| Transaction | deferredJournalEntry | JournalEntry::hasMany() / hasOne() |
| TransactionConfirmation | transaction | Transaction::hasMany() / hasOne() |
| TransactionConfirmation | confirmer | User::hasMany() / hasOne() |
| TransactionError | resolver | User::hasMany() / hasOne() |
| TransactionImport | user | User::hasMany() / hasOne() |

## Factory Issues

| Factory | Issue | Recommended fix |
|---------|-------|-----------------|
| AccountingPeriodFactory | raw enum string for 'period_type' | Use EnumName::Case->value instead of 'month' |
| AccountingPeriodFactory | raw enum string for 'status' | Use EnumName::Case->value instead of 'open' |
| ChartOfAccountFactory | missing convenience states | Add state() methods for common enum/status values |
| CounterFactory | missing convenience states | Add state() methods for common enum/status values |
| CounterSessionFactory | raw enum string for 'status' | Use EnumName::Case->value instead of 'open' |
| CounterSessionFactory | missing convenience states | Add state() methods for common enum/status values |
| CustomerDocumentFactory | raw enum string for 'document_type' | Use EnumName::Case->value instead of 'MyKad' |
| CustomerDocumentFactory | missing convenience states | Add state() methods for common enum/status values |
| CustomerRiskHistoryFactory | missing convenience states | Add state() methods for common enum/status values |
| FiscalYearFactory | raw enum string for 'status' | Use EnumName::Case->value instead of 'Open' |
| JournalEntryFactory | raw enum string for 'status' | Use EnumName::Case->value instead of 'Draft' |
| JournalEntryFactory | raw enum string for 'reference_type' | Use EnumName::Case->value instead of 'Manual' |
| ReportGeneratedFactory | raw enum string for 'file_format' | Use EnumName::Case->value instead of 'CSV' |
| ReportGeneratedFactory | raw enum string for 'status' | Use EnumName::Case->value instead of 'Generated' |
| ReportGeneratedFactory | missing convenience states | Add state() methods for common enum/status values |
| RiskScoreSnapshotFactory | raw enum string for 'trend' | Use EnumName::Case->value instead of 'stable' |
| RiskScoreSnapshotFactory | missing convenience states | Add state() methods for common enum/status values |
| SanctionEntryFactory | raw enum string for 'status' | Use EnumName::Case->value instead of 'active' |
| SanctionEntryFactory | missing convenience states | Add state() methods for common enum/status values |
| StockReservationFactory | missing convenience states | Add state() methods for common enum/status values |
| TestResultFactory | missing convenience states | Add state() methods for common enum/status values |
| TransactionConfirmationFactory | missing convenience states | Add state() methods for common enum/status values |
| TransactionFactory | raw enum string for 'cdd_level' | Use EnumName::Case->value instead of 'Standard' |
| UserNotificationPreferenceFactory | missing convenience states | Add state() methods for common enum/status values |

