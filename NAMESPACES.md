# PHP Namespaces Report - CEMS-MY Laravel Application

## 1. App\Models Namespace

### Purpose
Contains all Eloquent models representing database entities for the currency exchange management system.

### Classes

| Class | File Path | Key Methods | Purpose |
|-------|-----------|-------------|---------|
| `AccountLedger` | `app/Models/AccountLedger.php` | | Tracks individual account ledger entries for double-entry accounting |
| `AccountingPeriod` | `app/Models/AccountingPeriod.php` | | Represents accounting periods for financial reporting |
| `Alert` | `app/Models/Alert.php` | `flaggedTransaction()`, `customer()`, `case()`, `scopeUnassigned()`, `scopeOpen()` | Compliance alerts generated from transaction monitoring |
| `AmlRule` | `app/Models/AmlRule.php` | | AML rule definitions for compliance monitoring |
| `Branch` | `app/Models/Branch.php` | `users()`, `counters()`, `transactions()`, `currencyPositions()`, `parent()`, `children()` | Represents branches (HQ, branches, sub-branches) |
| `Counter` | `app/Models/Counter.php` | | Represents counters/tills at branches |
| `CounterHandover` | `app/Models/CounterHandover.php` | | Tracks counter handover between users |
| `CounterSession` | `app/Models/CounterSession.php` | | Manages counter sessions with status tracking |
| `Currency` | `app/Models/Currency.php` | | Currency definitions |
| `CurrencyPosition` | `app/Models/CurrencyPosition.php` | | Tracks currency holdings per till/branch |
| `Customer` | `app/Models/Customer.php` | `transactions()`, `documents()`, `riskHistory()`, `pepRelations()`, `isPepAssociate()` | Customer records with encrypted PII, PEP/sanction status |
| `CustomerDocument` | `app/Models/CustomerDocument.php` | | Customer KYC documents (MyKad, Passport, etc.) |
| `CustomerRelation` | `app/Models/CustomerRelation.php` | | PEP relations between customers |
| `EddTemplate` | `app/Models/EddTemplate.php` | | Enhanced Due Diligence questionnaire templates |
| `ExchangeRate` | `app/Models/ExchangeRate.php` | | Exchange rate definitions |
| `ExchangeRateHistory` | `app/Models/ExchangeRateHistory.php` | | Historical exchange rates |
| `FiscalYear` | `app/Models/FiscalYear.php` | | Fiscal year definitions |
| `FlaggedTransaction` | `app/Models/FlaggedTransaction.php` | | Transactions flagged for AML compliance |
| `JournalEntry` | `app/Models/JournalEntry.php` | | Accounting journal entries |
| `JournalLine` | `app/Models/JournalLine.php` | | Individual lines in journal entries |
| `ReportGenerated` | `app/Models/ReportGenerated.php` | | Generated reports tracking |
| `RiskScoreSnapshot` | `app/Models/RiskScoreSnapshot.php` | | Customer risk score snapshots |
| `SanctionList` | `app/Models/SanctionList.php` | | Sanctions list definitions (OFAC, UN, MOHA, EU) |
| `StockTransfer` | `app/Models/StockTransfer.php` | | Inter-branch stock transfers |
| `StrReport` | `app/Models/StrReport.php` | | Suspicious Transaction Report for BNM |
| `SystemLog` | `app/Models/SystemLog.php` | | System audit logs |
| `TillBalance` | `app/Models/TillBalance.php` | | Till balance tracking (MYR and foreign currency) |
| `Transaction` | `app/Models/Transaction.php` | `customer()`, `user()`, `branch()`, `currency()`, `flags()` | Core transaction model for buy/sell foreign exchange |
| `TransactionError` | `app/Models/TransactionError.php` | | Transaction processing errors |
| `User` | `app/Models/User.php` | `isAdmin()`, `isManager()`, `isMfaVerified()`, `canViewTellerAllocation()` | User model with RBAC, MFA support |

### App\Models\Compliance Sub-namespace

| Class | File Path | Key Methods | Purpose |
|-------|-----------|-------------|---------|
| `ComplianceCase` | `app/Models/Compliance/ComplianceCase.php` | `generateCaseNumber()`, `addNote()`, `assignTo()`, `close()`, `escalate()`, `customer()`, `notes()`, `documents()` | Compliance case management with SLA tracking |
| `ComplianceCaseDocument` | `app/Models/Compliance/ComplianceCaseDocument.php` | | Document attachments for compliance cases |
| `ComplianceCaseLink` | `app/Models/Compliance/ComplianceCaseLink.php` | | Polymorphic links from cases to other entities |
| `ComplianceCaseNote` | `app/Models/Compliance/ComplianceCaseNote.php` | | Case notes with internal/external visibility |
| `ComplianceFinding` | `app/Models/Compliance/ComplianceFinding.php` | | Compliance findings from monitoring |
| `CustomerRiskProfile` | `app/Models/Compliance/CustomerRiskProfile.php` | | Detailed customer risk profiles |
| `EddQuestionnaireTemplate` | `app/Models/Compliance/EddQuestionnaireTemplate.php` | | EDD questionnaire templates |

---

## 2. App\Services Namespace

### Purpose
Business logic services encapsulating complex operations.

### Classes

| Class | File Path | Key Methods | Purpose |
|-------|-----------|-------------|---------|
| `AccountingService` | `app/Services/AccountingService.php` | | Double-entry accounting operations |
| `AlertTriageService` | `app/Services/AlertTriageService.php` | | Alert triage and assignment workflow |
| `AuditService` | `app/Services/AuditService.php` | | Comprehensive audit logging |
| `BranchPoolService` | `app/Services/BranchPoolService.php` | | Branch pool management |
| `CashFlowService` | `app/Services/CashFlowService.php` | | Cash flow reporting |
| `ComplianceService` | `app/Services/ComplianceService.php` | `determineCDDLevel()`, `checkSanctionMatch()`, `checkVelocity()`, `checkStructuring()` | Core compliance operations |
| `CounterHandoverService` | `app/Services/CounterHandoverService.php` | | Counter handover workflow |
| `CounterService` | `app/Services/CounterService.php` | | Counter/till management |
| `CtosReportService` | `app/Services/CtosReportService.php` | | Cash Transaction Report generation |
| `CurrencyPositionService` | `app/Services/CurrencyPositionService.php` | | Currency position tracking |
| `CustomerRiskScoringService` | `app/Services/CustomerRiskScoringService.php` | | Customer risk score calculations |
| `CustomerScreeningService` | `app/Services/CustomerScreeningService.php` | | Sanctions list fuzzy matching |
| `CustomerService` | `app/Services/CustomerService.php` | `createCustomer()`, `screenCustomer()`, `encryptCustomerData()` | Customer management with encryption |
| `EddService` | `app/Services/EddService.php` | | Enhanced Due Diligence management |
| `FinancialRatioService` | `app/Services/FinancialRatioService.php` | | Financial ratio calculations |
| `FiscalYearService` | `app/Services/FiscalYearService.php` | | Fiscal year management |
| `LedgerService` | `app/Services/LedgerService.php` | | Ledger operations |
| `MathService` | `app/Services/MathService.php` | | BCMath precision arithmetic |
| `MfaService` | `app/Services/MfaService.php` | | Multi-factor authentication |
| `RateApiService` | `app/Services/RateApiService.php` | | External rate API integration |
| `RateManagementService` | `app/Services/RateManagementService.php` | | Exchange rate management |
| `ReportingService` | `app/Services/ReportingService.php` | | General reporting operations |
| `RevaluationService` | `app/Services/RevaluationService.php` | | Currency revaluation |
| `RiskCalculationService` | `app/Services/RiskCalculationService.php` | `calculateVelocityRisk()`, `calculateStructuringRisk()`, `getOverallRiskScore()` | Multi-factor risk score calculation |
| `SanctionsDownloadService` | `app/Services/SanctionsDownloadService.php` | | Sanctions list downloads |
| `StockTransferService` | `app/Services/StockTransferService.php` | | Inter-branch stock transfers |
| `StrReportService` | `app/Services/StrReportService.php` | | STR generation and submission |
| `TellerAllocationService` | `app/Services/TellerAllocationService.php` | | Teller currency allocation management |
| `TransactionMonitoringService` | `app/Services/TransactionMonitoringService.php` | | Real-time transaction monitoring |
| `TransactionService` | `app/Services/TransactionService.php` | `preValidate()`, `createTransaction()`, `approveTransaction()` | Core transaction business logic |
| `UserService` | `app/Services/UserService.php` | | User management |

### App\Services\Compliance Sub-namespace

| Class | File Path | Key Methods | Purpose |
|-------|-----------|-------------|---------|
| `CaseManagementService` | `app/Services/Compliance/CaseManagementService.php` | `createCaseFromFinding()`, `assignCase()`, `closeCase()`, `escalateCase()` | Compliance case lifecycle management |
| `ComplianceReportingService` | `app/Services/Compliance/ComplianceReportingService.php` | | Compliance report generation |
| `MonitoringEngine` | `app/Services/Compliance/MonitoringEngine.php` | | Compliance monitoring orchestration |

### App\Services\Compliance\Monitors Sub-sub-namespace

| Class | File Path | Key Methods | Purpose |
|-------|-----------|-------------|---------|
| `BaseMonitor` | `app/Services/Compliance/Monitors/BaseMonitor.php` | | Abstract base for compliance monitors |
| `StructuringMonitor` | `app/Services/Compliance/Monitors/StructuringMonitor.php` | | Structuring (smurfing) detection |
| `VelocityMonitor` | `app/Services/Compliance/Monitors/VelocityMonitor.php` | | Transaction velocity monitoring |
| `SanctionsRescreeningMonitor` | `app/Services/Compliance/Monitors/SanctionsRescreeningMonitor.php` | | Periodic sanctions rescreening |

### App\Services\Risk Sub-namespace

| Class | File Path | Key Methods | Purpose |
|-------|-----------|-------------|---------|
| `AmountRiskService` | `app/Services/Risk/AmountRiskService.php` | | Amount-based risk scoring |
| `GeographicRiskService` | `app/Services/Risk/GeographicRiskService.php` | | Geographic risk assessment |
| `PatternRiskService` | `app/Services/Risk/PatternRiskService.php` | | Transaction pattern risk |
| `VelocityRiskService` | `app/Services/Risk/VelocityRiskService.php` | | Velocity-based risk scoring |

---

## 3. App\Livewire Namespace

### Purpose
Livewire components for the frontend - reactive UI components with server-side logic.

### Classes

| Class | File Path | Purpose |
|-------|-----------|---------|
| `BaseComponent` | `app/Livewire/BaseComponent.php` | Abstract base with `success()`, `error()`, `dispatchBrowserEvent()` |
| `Dashboard` | `app/Livewire/Dashboard.php` | Main dashboard |
| `Performance` | `app/Livewire/Performance.php` | Performance monitoring |

### App\Livewire\Accounting Sub-namespace

| Class | File Path | Purpose |
|-------|-----------|---------|
| `BalanceSheet` | `app/Livewire/Accounting/BalanceSheet.php` | Balance sheet report |
| `BudgetReport` | `app/Livewire/Accounting/BudgetReport.php` | Budget report |
| `CashFlow` | `app/Livewire/Accounting/CashFlow.php` | Cash flow statement |
| `FiscalYears` | `app/Livewire/Accounting/FiscalYears.php` | Fiscal year management |
| `Index` | `app/Livewire/Accounting/Index.php` | Accounting dashboard |
| `ProfitLoss` | `app/Livewire/Accounting/ProfitLoss.php` | Profit/Loss report |
| `Ratios` | `app/Livewire/Accounting/Ratios.php` | Financial ratios |
| `TrialBalance` | `app/Livewire/Accounting/TrialBalance.php` | Trial balance report |
| `Periods` | `app/Livewire/Accounting/Periods.php` | Accounting periods |
| `Journal/Create` | `app/Livewire/Accounting/Journal/Create.php` | Journal entry creation |
| `Journal/Index` | `app/Livewire/Accounting/Journal/Index.php` | Journal entries list |
| `Journal/Show` | `app/Livewire/Accounting/Journal/Show.php` | Journal entry details |
| `Ledger/Account` | `app/Livewire/Accounting/Ledger/Account.php` | Account ledger view |
| `Ledger/Index` | `app/Livewire/Accounting/Ledger/Index.php` | Ledger index |
| `Reconciliation/Index` | `app/Livewire/Accounting/Reconciliation/Index.php` | Reconciliation index |
| `Reconciliation/Report` | `app/Livewire/Accounting/Reconciliation/Report.php` | Reconciliation report |
| `Revaluation/History` | `app/Livewire/Accounting/Revaluation/History.php` | Revaluation history |
| `Revaluation/Index` | `app/Livewire/Accounting/Revaluation/Index.php` | Revaluation index |
| `FiscalYears/Report` | `app/Livewire/Accounting/FiscalYears/Report.php` | Fiscal year report |

### App\Livewire\Audit Sub-namespace

| Class | File Path | Purpose |
|-------|-----------|---------|
| `Dashboard` | `app/Livewire/Audit/Dashboard.php` | Audit dashboard |
| `Index` | `app/Livewire/Audit/Index.php` | Audit log index |
| `Rotate` | `app/Livewire/Audit/Rotate.php` | Log rotation |
| `Show` | `app/Livewire/Audit/Show.php` | Audit entry details |

### App\Livewire\Branches Sub-namespace

| Class | File Path | Purpose |
|-------|-----------|---------|
| `Create` | `app/Livewire/Branches/Create.php` | Branch creation |
| `Edit` | `app/Livewire/Branches/Edit.php` | Branch editing |
| `Index` | `app/Livewire/Branches/Index.php` | Branch list |
| `Show` | `app/Livewire/Branches/Show.php` | Branch details |

### App\Livewire\BranchOpenings Sub-namespace

| Class | File Path | Purpose |
|-------|-----------|---------|
| `Complete` | `app/Livewire/BranchOpenings/Complete.php` | Branch opening completion |
| `Index` | `app/Livewire/BranchOpenings/Index.php` | Branch openings list |
| `Step1` | `app/Livewire/BranchOpenings/Step1.php` | Opening step 1 |
| `Step2` | `app/Livewire/BranchOpenings/Step2.php` | Opening step 2 |
| `Step3` | `app/Livewire/BranchOpenings/Step3.php` | Opening step 3 |

### App\Livewire\Compliance Sub-namespace

| Class | File Path | Purpose |
|-------|-----------|---------|
| `Dashboard` | `app/Livewire/Compliance/Dashboard.php` | Compliance dashboard with alerts summary |
| `Alerts/Index` | `app/Livewire/Compliance/Alerts/Index.php` | Alert list |
| `Alerts/Show` | `app/Livewire/Compliance/Alerts/Show.php` | Alert details |
| `Cases/Index` | `app/Livewire/Compliance/Cases/Index.php` | Case list |
| `Cases/Show` | `app/Livewire/Compliance/Cases/Show.php` | Case details |
| `Ctos/Index` | `app/Livewire/Compliance/Ctos/Index.php` | CTOS report list |
| `Edd/Form` | `app/Livewire/Compliance/Edd/Form.php` | EDD form |
| `Edd/Index` | `app/Livewire/Compliance/Edd/Index.php` | EDD list |
| `Edd/Templates/Index` | `app/Livewire/Compliance/Edd/Templates/Index.php` | EDD templates |
| `Reporting/Index` | `app/Livewire/Compliance/Reporting/Index.php` | Compliance reports |
| `RiskDashboard/Index` | `app/Livewire/Compliance/RiskDashboard/Index.php` | Risk dashboard |
| `Rules/Form` | `app/Livewire/Compliance/Rules/Form.php` | AML rule form |
| `Rules/Index` | `app/Livewire/Compliance/Rules/Index.php` | AML rules list |
| `Sanctions/Index` | `app/Livewire/Compliance/Sanctions/Index.php` | Sanctions list |
| `Sanctions/Show` | `app/Livewire/Compliance/Sanctions/Show.php` | Sanction entry details |

### App\Livewire\Counters Sub-namespace

| Class | File Path | Purpose |
|-------|-----------|---------|
| `AcknowledgeHandover` | `app/Livewire/Counters/AcknowledgeHandover.php` | Handover acknowledgment |
| `Close` | `app/Livewire/Counters/Close.php` | Counter close |
| `Emergency/Closure` | `app/Livewire/Counters/Emergency/Closure.php` | Emergency closure |
| `Emergency/Index` | `app/Livewire/Counters/Emergency/Index.php` | Emergency counter index |
| `Handover` | `app/Livewire/Counters/Handover.php` | Counter handover |
| `History` | `app/Livewire/Counters/History.php` | Counter history |
| `Index` | `app/Livewire/Counters/Index.php` | Counter list |
| `Open` | `app/Livewire/Counters/Open.php` | Counter open |

### App\Livewire\Customers Sub-namespace

| Class | File Path | Purpose |
|-------|-----------|---------|
| `Create` | `app/Livewire/Customers/Create.php` | Customer creation |
| `Edit` | `app/Livewire/Customers/Edit.php` | Customer editing |
| `Export` | `app/Livewire/Customers/Export.php` | Customer export |
| `History` | `app/Livewire/Customers/History.php` | Customer history |
| `Index` | `app/Livewire/Customers/Index.php` | Customer list |
| `Show` | `app/Livewire/Customers/Show.php` | Customer details |

### App\Livewire\Layout Sub-namespace

| Class | File Path | Purpose |
|-------|-----------|---------|
| `AppShell` | `app/Livewire/Layout/AppShell.php` | Application shell layout |

### App\Livewire\Mfa Sub-namespace

| Class | File Path | Purpose |
|-------|-----------|---------|
| `Recovery` | `app/Livewire/Mfa/Recovery.php` | MFA recovery codes |
| `Setup` | `app/Livewire/Mfa/Setup.php` | MFA setup |
| `TrustedDevices` | `app/Livewire/Mfa/TrustedDevices.php` | Trusted devices |
| `Verify` | `app/Livewire/Mfa/Verify.php` | MFA verification |

### App\Livewire\Rates Sub-namespace

| Class | File Path | Purpose |
|-------|-----------|---------|
| `Index` | `app/Livewire/Rates/Index.php` | Exchange rates |

### App\Livewire\Reports Sub-namespace

| Class | File Path | Purpose |
|-------|-----------|---------|
| `Index` | `app/Livewire/Reports/Index.php` | Reports index |
| `Analytics/ComplianceSummary` | `app/Livewire/Reports/Analytics/ComplianceSummary.php` | Compliance summary |
| `Analytics/CustomerAnalysis` | `app/Livewire/Reports/Analytics/CustomerAnalysis.php` | Customer analysis |
| `Analytics/Lctr` | `app/Livewire/Reports/Analytics/Lctr.php` | LCTR analytics |
| `Analytics/Lmca` | `app/Livewire/Reports/Analytics/Lmca.php` | LMCA analytics |
| `Analytics/MonthlyTrends` | `app/Livewire/Reports/Analytics/MonthlyTrends.php` | Monthly trends |
| `Analytics/PositionLimit` | `app/Livewire/Reports/Analytics/PositionLimit.php` | Position limit analytics |
| `Analytics/Profitability` | `app/Livewire/Reports/Analytics/Profitability.php` | Profitability analytics |
| `Analytics/QuarterlyLvr` | `app/Livewire/Reports/Analytics/QuarterlyLvr.php` | Quarterly LVR |
| `Compare/Index` | `app/Livewire/Reports/Compare/Index.php` | Report comparison |
| `History/Index` | `app/Livewire/Reports/History/Index.php` | Report history |
| `Lctr/Index` | `app/Livewire/Reports/Lctr/Index.php` | LCTR report |
| `Lmca/Index` | `app/Livewire/Reports/Lmca/Index.php` | LMCA report |
| `Msb2/Index` | `app/Livewire/Reports/Msb2/Index.php` | MSB(2) report |
| `PositionLimit/Index` | `app/Livewire/Reports/PositionLimit/Index.php` | Position limit report |
| `QuarterlyLvr/Index` | `app/Livewire/Reports/QuarterlyLvr/Index.php` | Quarterly LVR report |

### App\Livewire\Stock Sub-namespace

| Class | File Path | Purpose |
|-------|-----------|---------|
| `Index` | `app/Livewire/Stock/Index.php` | Stock index |
| `Position` | `app/Livewire/Stock/Position.php` | Stock position |
| `Reconciliation` | `app/Livewire/Stock/Reconciliation.php` | Stock reconciliation |
| `TillReport` | `app/Livewire/Stock/TillReport.php` | Till report |
| `Transfer/Create` | `app/Livewire/Stock/Transfer/Create.php` | Stock transfer creation |
| `Transfer/Index` | `app/Livewire/Stock/Transfer/Index.php` | Stock transfer list |
| `Transfer/Show` | `app/Livewire/Stock/Transfer/Show.php` | Stock transfer details |

### App\Livewire\StockTransfers Sub-namespace

| Class | File Path | Purpose |
|-------|-----------|---------|
| `ApproveBm` | `app/Livewire/StockTransfers/ApproveBm.php` | Branch manager approval |
| `ApproveHq` | `app/Livewire/StockTransfers/ApproveHq.php` | HQ approval |
| `Cancel` | `app/Livewire/StockTransfers/Cancel.php` | Transfer cancellation |
| `Complete` | `app/Livewire/StockTransfers/Complete.php` | Transfer completion |
| `Dispatch` | `app/Livewire/StockTransfers/Dispatch.php` | Transfer dispatch |
| `Receive` | `app/Livewire/StockTransfers/Receive.php` | Transfer reception |

### App\Livewire\Str Sub-namespace

| Class | File Path | Purpose |
|-------|-----------|---------|
| `Create` | `app/Livewire/Str/Create.php` | STR creation |
| `Edit` | `app/Livewire/Str/Edit.php` | STR editing |
| `Index` | `app/Livewire/Str/Index.php` | STR list |
| `Show` | `app/Livewire/Str/Show.php` | STR details |

### App\Livewire\TestResults Sub-namespace

| Class | File Path | Purpose |
|-------|-----------|---------|
| `Compare` | `app/Livewire/TestResults/Compare.php` | Test comparison |
| `Index` | `app/Livewire/TestResults/Index.php` | Test results list |
| `Show` | `app/Livewire/TestResults/Show.php` | Test result details |
| `Statistics` | `app/Livewire/TestResults/Statistics.php` | Test statistics |

### App\Livewire\Transactions Sub-namespace

| Class | File Path | Purpose |
|-------|-----------|---------|
| `Approve` | `app/Livewire/Transactions/Approve.php` | Transaction approval |
| `BatchUpload` | `app/Livewire/Transactions/BatchUpload.php` | Bulk upload |
| `Cancel` | `app/Livewire/Transactions/Cancel.php` | Transaction cancellation |
| `Create` | `app/Livewire/Transactions/Create.php` | Transaction creation |
| `Index` | `app/Livewire/Transactions/Index.php` | Transaction list |
| `Receipt/Index` | `app/Livewire/Transactions/Receipt/Index.php` | Transaction receipt |
| `Show` | `app/Livewire/Transactions/Show.php` | Transaction details |

### App\Livewire\Users Sub-namespace

| Class | File Path | Purpose |
|-------|-----------|---------|
| `Create` | `app/Livewire/Users/Create.php` | User creation |
| `Edit` | `app/Livewire/Users/Edit.php` | User editing |
| `Index` | `app/Livewire/Users/Index.php` | User list |
| `ResetPassword` | `app/Livewire/Users/ResetPassword.php` | Password reset |
| `Show` | `app/Livewire/Users/Show.php` | User details |

---

## 4. App\Http\Controllers Namespace

### Classes

| Class | File Path | Purpose |
|-------|-----------|---------|
| `Controller` | `app/Http/Controllers/Controller.php` | Base controller with authorization helpers |
| `AccountingController` | `app/Http/Controllers/AccountingController.php` | Accounting operations |
| `CounterController` | `app/Http/Controllers/CounterController.php` | Counter management |
| `CustomerController` | `app/Http/Controllers/CustomerController.php` | Customer management |
| `DashboardController` | `app/Http/Controllers/DashboardController.php` | Main dashboard |
| `HealthCheckController` | `app/Http/Controllers/HealthCheckController.php` | Health checks |
| `MfaController` | `app/Http/Controllers/MfaController.php` | MFA operations |
| `SetupController` | `app/Http/Controllers/SetupController.php` | System setup |
| `StockCashController` | `app/Http/Controllers/StockCashController.php` | Stock/cash operations |
| `StockTransferController` | `app/Http/Controllers/StockTransferController.php` | Stock transfers |
| `StrController` | `app/Http/Controllers/StrController.php` | STR management |
| `TransactionController` | `app/Http/Controllers/TransactionController.php` | Transaction management |
| `TransactionBatchController` | `app/Http/Controllers/TransactionBatchController.php` | Batch transactions |
| `UserController` | `app/Http/Controllers/UserController.php` | User management |

### App\Http\Controllers\Api\V1 Sub-namespace

| Class | File Path | Purpose |
|-------|-----------|---------|
| `BranchController` | `app/Http/Controllers/Api/V1/BranchController.php` | Branch API |
| `BulkImportController` | `app/Http/Controllers/Api/V1/BulkImportController.php` | Bulk import API |
| `CustomerController` | `app/Http/Controllers/Api/V1/CustomerController.php` | Customer API |
| `RateController` | `app/Http/Controllers/Api/V1/RateController.php` | Rate API |
| `ReportController` | `app/Http/Controllers/Api/V1/ReportController.php` | Report API |
| `SanctionController` | `app/Http/Controllers/Api/V1/SanctionController.php` | Sanction API |
| `ScreeningController` | `app/Http/Controllers/Api/V1/ScreeningController.php` | Screening API |
| `StrController` | `app/Http/Controllers/Api/V1/StrController.php` | STR API |
| `TransactionController` | `app/Http/Controllers/Api/V1/TransactionController.php` | Core transaction API |
| `CounterHandoverController` | `app/Http/Controllers/Api/V1/CounterHandoverController.php` | Counter handover API |
| `CounterOpeningController` | `app/Http/Controllers/Api/V1/CounterOpeningController.php` | Counter opening API |
| `EodReconciliationController` | `app/Http/Controllers/Api/V1/EodReconciliationController.php` | EOD reconciliation API |
| `MonthEndCloseController` | `app/Http/Controllers/Api/V1/MonthEndCloseController.php` | Month-end close API |
| `TransactionApprovalController` | `app/Http/Controllers/Api/V1/TransactionApprovalController.php` | Transaction approval API |
| `TransactionCancellationController` | `app/Http/Controllers/Api/V1/TransactionCancellationController.php` | Transaction cancellation API |

### App\Http\Controllers\Api\V1\Compliance Sub-sub-namespace

| Class | File Path | Purpose |
|-------|-----------|---------|
| `AlertController` | `app/Http/Controllers/Api/V1/Compliance/AlertController.php` | Alert management |
| `CaseController` | `app/Http/Controllers/Api/V1/Compliance/CaseController.php` | Case management |
| `CtosReportController` | `app/Http/Controllers/Api/V1/Compliance/CtosReportController.php` | CTOS reports |
| `DashboardController` | `app/Http/Controllers/Api/V1/Compliance/DashboardController.php` | Compliance dashboard |
| `EddController` | `app/Http/Controllers/Api/V1/Compliance/EddController.php` | EDD management |
| `FindingController` | `app/Http/Controllers/Api/V1/Compliance/FindingController.php` | Findings |
| `RiskController` | `app/Http/Controllers/Api/V1/Compliance/RiskController.php` | Risk management |

### App\Http\Controllers\Compliance Sub-namespace

| Class | File Path | Purpose |
|-------|-----------|---------|
| `AlertTriageController` | `app/Http/Controllers/Compliance/AlertTriageController.php` | Alert triage |
| `CaseManagementController` | `app/Http/Controllers/Compliance/CaseManagementController.php` | Case management |
| `ComplianceReportingController` | `app/Http/Controllers/Compliance/ComplianceReportingController.php` | Compliance reporting |
| `ComplianceWorkspaceController` | `app/Http/Controllers/Compliance/ComplianceWorkspaceController.php` | Compliance workspace |
| `CtosController` | `app/Http/Controllers/Compliance/CtosController.php` | CTOS management |
| `EddTemplateController` | `app/Http/Controllers/Compliance/EddTemplateController.php` | EDD templates |
| `FindingController` | `app/Http/Controllers/Compliance/FindingController.php` | Findings |
| `RiskDashboardController` | `app/Http/Controllers/Compliance/RiskDashboardController.php` | Risk dashboard |
| `SanctionListController` | `app/Http/Controllers/Compliance/SanctionListController.php` | Sanction list |
| `ScreeningController` | `app/Http/Controllers/Compliance/ScreeningController.php` | Screening |
| `UnifiedAlertController` | `app/Http/Controllers/Compliance/UnifiedAlertController.php` | Unified alerts |

---

## 5. App\Enums Namespace

### Purpose
PHP enums for type-safe constants.

| Class | File Path | Cases | Purpose |
|-------|-----------|-------|---------|
| `AccountCode` | `app/Enums/AccountCode.php` | Various | Chart of account codes |
| `AlertPriority` | `app/Enums/AlertPriority.php` | Critical, High, Medium, Low | Alert priority levels |
| `AmlRuleType` | `app/Enums/AmlRuleType.php` | Various | AML rule types |
| `CddLevel` | `app/Enums/CddLevel.php` | Simplified, Specific, Standard, Enhanced | Customer Due Diligence levels |
| `ComplianceCasePriority` | `app/Enums/ComplianceCasePriority.php` | Critical, High, Medium, Low | Compliance case priorities |
| `ComplianceCaseStatus` | `app/Enums/ComplianceCaseStatus.php` | Open, UnderReview, PendingApproval, Escalated, Closed | Compliance case statuses |
| `ComplianceCaseType` | `app/Enums/ComplianceCaseType.php` | Various | Compliance case types |
| `CounterSessionStatus` | `app/Enums/CounterSessionStatus.php` | Various | Counter session states |
| `FindingSeverity` | `app/Enums/FindingSeverity.php` | Critical, High, Medium, Low | Finding severity levels |
| `FlagStatus` | `app/Enums/FlagStatus.php` | Open, UnderReview, Resolved, Dismissed | Flag statuses |
| `RiskRating` | `app/Enums/RiskRating.php` | Low, Medium, High | Risk ratings |
| `StockTransferStatus` | `app/Enums/StockTransferStatus.php` | Various | Stock transfer statuses |
| `StrStatus` | `app/Enums/StrStatus.php` | Various | STR statuses |
| `TransactionStatus` | `app/Enums/TransactionStatus.php` | Draft, PendingApproval, Approved, Processing, Completed, Finalized, Cancelled, Reversed, Failed, Rejected, Pending, OnHold, PendingCancellation | 13-state transaction state machine |
| `TransactionType` | `app/Enums/TransactionType.php` | Buy, Sell | Transaction types |
| `UserRole` | `app/Enums/UserRole.php` | Teller, Manager, ComplianceOfficer, Admin | User roles with permission methods |

---

## 6. App\Notifications Namespace

### Purpose
Laravel notification classes for user alerts.

| Class | File Path | Channels | Purpose |
|-------|-----------|----------|---------|
| `ComplianceCaseAssignedNotification` | `app/Notifications/ComplianceCaseAssignedNotification.php` | database, broadcast, mail | Alert when case is assigned |
| `ComplianceFindingNotification` | `app/Notifications/Compliance/ComplianceFindingNotification.php` | | New compliance finding |
| `StrEscalationNotification` | `app/Notifications/Compliance/StrEscalationNotification.php` | | STR escalation alert |
| `LargeTransactionNotification` | `app/Notifications/LargeTransactionNotification.php` | | Large transaction alert |
| `SanctionsMatchNotification` | `app/Notifications/SanctionsMatchNotification.php` | | Sanctions match alert |
| `StrDeadlineApproachingNotification` | `app/Notifications/StrDeadlineApproachingNotification.php` | | STR deadline warning |
| `SystemHealthAlertNotification` | `app/Notifications/SystemHealthAlertNotification.php` | | System health alerts |
| `TransactionApprovedNotification` | `app/Notifications/TransactionApprovedNotification.php` | | Transaction approved |
| `TransactionFlaggedNotification` | `app/Notifications/TransactionFlaggedNotification.php` | | Transaction flagged |

---

## 7. App\Exceptions Namespace

### Purpose
Exception handling.

| Class | File Path | Purpose |
|-------|-----------|---------|
| `Handler` | `app/Exceptions/Handler.php` | Exception handler |
| `Domain\*` | `app/Exceptions/Domain/*.php` | Domain-specific exceptions |

---

## 8. App\Jobs Namespace

### Purpose
Background job processing.

| Class | File Path | Purpose |
|-------|-----------|---------|
| `ComplianceScreeningJob` | `app/Jobs/ComplianceScreeningJob.php` | Compliance screening |
| `ImportSanctionsJob` | `app/Jobs/ImportSanctionsJob.php` | Sanctions import |
| `ProcessCustomerImport` | `app/Jobs/ProcessCustomerImport.php` | Customer import processing |
| `ProcessTransactionImport` | `app/Jobs/ProcessTransactionImport.php` | Transaction import processing |
| `Accounting/ReconcileDeferredAccountingJob` | `app/Jobs/Accounting/ReconcileDeferredAccountingJob.php` | Deferred accounting reconciliation |
| `Audit/SealAuditHashJob` | `app/Jobs/Audit/SealAuditHashJob.php` | Audit hash sealing |
| `Compliance\CounterfeitAlertJob` | `app/Jobs/Compliance/CounterfeitAlertJob.php` | Counterfeit monitoring |
| `Compliance\CurrencyFlowJob` | `app/Jobs/Compliance/CurrencyFlowJob.php` | Currency flow monitoring |
| `Compliance\StrDeadlineMonitorJob` | `app/Jobs/Compliance/StrDeadlineMonitorJob.php` | STR deadline monitoring |
| `Compliance\StructuringMonitorJob` | `app/Jobs/Compliance/StructuringMonitorJob.php` | Structuring detection |
| `Compliance\VelocityMonitorJob` | `app/Jobs/Compliance/VelocityMonitorJob.php` | Velocity monitoring |
| `Compliance\SanctionsRescreeningJob` | `app/Jobs/Compliance/SanctionsRescreeningJob.php` | Sanctions rescreening |

---

## Summary Statistics

| Namespace | Count |
|----------|-------|
| App\Models | ~50 classes |
| App\Models\Compliance | ~8 classes |
| App\Services | ~60 classes |
| App\Services\Compliance | ~4 classes |
| App\Services\Risk | ~5 classes |
| App\Livewire | ~80 components |
| App\Http\Controllers | ~25 controllers |
| App\Http\Controllers\Api\V1 | ~18 controllers |
| App\Enums | ~30 enums |
| App\Notifications | ~12 notifications |
| App\Jobs | ~15 jobs |
