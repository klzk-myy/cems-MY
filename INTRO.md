# CEMS-MY Introduction

**Currency Exchange Management System for Malaysian Money Services Businesses**

A comprehensive Laravel 10.x application for managing foreign currency trading, till operations, AML/CFT compliance, and double-entry accounting for Bank Negara Malaysia (BNM) regulated MSB operations.

---

## Table of Contents

1. [System Architecture](#system-architecture)
2. [Routes](#routes)
3. [Middleware](#middleware)
4. [Controllers](#controllers)
5. [Services](#services)
6. [Models](#models)
7. [Views](#views)
8. [Enums](#enums)
9. [Jobs](#jobs)
10. [Events](#events)
11. [Console Commands](#console-commands)
12. [Domain Exceptions](#domain-exceptions)

---

## System Architecture

```
CEMS-MY (Laravel 10.x / PHP 8.1+)
├── routes/              # HTTP routing
├── app/
│   ├── Console/Commands/ # 41 Artisan commands
│   ├── Enums/           # 34 PHP 8.1 enums
│   ├── Events/          # 13 event classes
│   ├── Exceptions/Domain/ # 43 typed domain exceptions
│   ├── Http/
│   │   ├── Controllers/ # 71 controllers
│   │   └── Middleware/  # 21 middleware classes
│   ├── Jobs/            # 23 background jobs
│   ├── Models/          # 62 Eloquent models
│   └── Services/        # 83 business services
├── resources/views/     # ~90 Blade templates (Livewire)
└── config/              # Centralized configuration
```

---

## Routes

**~294 routes across 4 route files**

| File | Routes | Purpose |
|------|--------|---------|
| `routes/web.php` | ~180 | Web UI routes (session auth) |
| `routes/api_v1.php` | ~110 | REST API v1 (Sanctum auth) |
| `routes/auth.php` | 3 | Login/logout |
| `routes/console.php` | 1 | Console commands |
| `routes/channels.php` | 1 | Broadcasting |

### Route Organization

```
┌─────────────────────────────────────────────┐
│                  web.php                     │
├─────────────────────────────────────────────┤
│ / (root)          → Dashboard               │
│ /setup/*          → Initial setup wizard     │
│ /dashboard        → Main dashboard           │
│ /transactions/*   → Currency exchange        │
│ /customers/*      → Customer management      │
│ /counters/*      → Till/counter operations  │
│ /stock-cash/*    → Stock & cash management  │
│ /stock-transfers/* → Inter-branch transfers  │
│ /compliance/*    → AML/CFT compliance       │
│ /accounting/*    → Double-entry ledger      │
│ /reports/*       → BNM regulatory reports   │
│ /branches/*      → Branch management        │
│ /users/*         → User administration       │
│ /audit/*         → Audit log viewing         │
│ /mfa/*           → Multi-factor auth         │
│ /test-results/*  → Test execution results   │
│ /performance     → Performance monitoring   │
├─────────────────────────────────────────────┤
│                api_v1.php                   │
├─────────────────────────────────────────────┤
│ /api/transactions/*  → Transaction API      │
│ /api/customers/*     → Customer API         │
│ /api/rates/*        → Exchange rate API     │
│ /api/allocations/*  → Teller float API      │
│ /api/compliance/*   → Compliance API        │
│ /api/eod/*          → EOD reconciliation     │
│ /api/branches/*     → Branch API            │
│ /api/reports/*      → Report generation     │
│ /api/sanctions/*    → Sanctions management  │
│ /api/screening/*    → Customer screening    │
│ /api/risk/*         → Risk scoring API      │
│ /api/accounting/*   → Accounting API        │
└─────────────────────────────────────────────┘
```

### Middleware Stack

```
Request → [Middleware Chain] → Controller
```

| Middleware | Purpose |
|------------|---------|
| `auth` | Authentication enforcement |
| `session.timeout` | Idle session timeout (15 min BNM) |
| `mfa.verified` | MFA verification for sensitive ops |
| `role:xxx` | Role-based access (teller/manager/compliance/admin) |
| `throttle:*` | Rate limiting per endpoint type |
| `CheckBranchAccess` | Branch-level authorization |
| `StrictRateLimit` | BNM-compliant rate limiting |
| `IpBlocker` | IP blocking after 10 failed attempts |
| `SecurityHeaders` | CSP, HSTS, X-Frame-Options |
| `LogRequests` | Request logging with timing |
| `QueryPerformanceMonitor` | N+1 detection |

---

## Middleware (21 Classes)

| Category | Middleware | Purpose |
|----------|------------|---------|
| **Auth** | `Authenticate` | Redirect unauthenticated users |
| | `RedirectIfAuthenticated` | Redirect authenticated users |
| **Access** | `CheckRole` | OR-based role authorization |
| | `CheckRoleAny` | OR-based role authorization (alias) |
| | `CheckBranchAccess` | Branch-level access control |
| **MFA** | `EnsureMfaEnabled` | Force MFA setup by role |
| | `EnsureMfaVerified` | Require MFA verification |
| **Security** | `IpBlocker` | IP-based blocking |
| | `StrictRateLimit` | BNM rate limiting |
| | `SecurityHeaders` | OWASP security headers |
| | `VerifyCsrfToken` | CSRF protection |
| | `EncryptCookies` | Cookie encryption |
| **Session** | `SessionTimeout` | Inactivity timeout |
| **Performance** | `LogRequests` | Request logging |
| | `QueryLogging` | Database query logging |
| | `QueryPerformanceMonitor` | N+1 detection |
| | `PerformanceTrackingMiddleware` | Slow endpoint detection |
| **Infrastructure** | `PreventRequestsDuringMaintenance` | Maintenance mode |
| | `TrimStrings` | Input trimming |
| | `TrustProxies` | Proxy IP detection |
| | `ValidateSignature` | URL signature validation |

---

## Controllers (71 Controllers)

Organized by domain module:

### 1. Transaction Management (7)
| Controller | Purpose |
|------------|---------|
| `TransactionController` | Core transaction CRUD, receipt generation |
| `TransactionBatchController` | CSV bulk upload |
| `TransactionReportController` | Customer history exports |
| `TransactionWizardController` | Multi-step transaction with CDD |
| `Transaction/TransactionApprovalController` | Manager approval workflow |
| `Transaction/TransactionCancellationController` | Cancellation request/approval |
| `Api/V1/TransactionController` | REST API endpoints |

### 2. Customer Management (3)
| Controller | Purpose |
|------------|---------|
| `CustomerController` | Customer CRUD, search, KYC upload |
| `Customer/CustomerKycController` | KYC document verification |
| `Api/V1/CustomerController` | Customer API |

### 3. Counter/Till Operations (5)
| Controller | Purpose |
|------------|---------|
| `CounterController` | Open/close/handover, emergency closure |
| `StockCashController` | Till balance, variance, reconciliation |
| `Api/V1/CounterOpeningController` | Counter opening workflow API |
| `Api/V1/CounterHandoverController` | Handover acknowledgement API |
| `Api/V1/EmergencyCounterController` | Emergency closure API |

### 4. Teller Allocation (1)
| Controller | Purpose |
|------------|---------|
| `Api/V1/TellerAllocationController` | Float allocation request/approval |

### 5. Compliance & AML (20)
| Controller | Purpose |
|------------|---------|
| `Compliance/AlertTriageController` | Alert assignment & resolution |
| `Compliance/UnifiedAlertController` | Unified alerts view |
| `Compliance/CaseManagementController` | Case lifecycle |
| `Compliance/SanctionListController` | Sanctions list CRUD |
| `Compliance/CtosController` | CTOS report management |
| `Compliance/RiskDashboardController` | Risk scoring dashboard |
| `Compliance/ScreeningController` | Customer screening status |
| `Compliance/EddTemplateController` | EDD questionnaire templates |
| `Compliance/FindingController` | Compliance findings |
| `Compliance/ComplianceReportingController` | Report scheduling |
| `Compliance/ComplianceWorkspaceController` | Compliance dashboard |
| `StrController` | STR creation/submission |
| `AmlRuleController` | AML rule configuration |
| `EnhancedDiligenceController` | EDD record management |
| `Api/V1/Compliance/AlertController` | Alerts API |
| `Api/V1/Compliance/CaseController` | Cases API |
| `Api/V1/Compliance/EddController` | EDD API |
| `Api/V1/Compliance/CtosReportController` | CTOS API |
| `Api/V1/Compliance/DashboardController` | Compliance KPIs API |
| `Api/V1/Compliance/FindingController` | Findings API |

### 6. Accounting & Finance (11)
| Controller | Purpose |
|------------|---------|
| `AccountingController` | Journal entries, periods, budget |
| `LedgerController` | General ledger accounts |
| `FinancialStatementController` | Trial balance, P&L, balance sheet |
| `RevaluationController` | Currency revaluation |
| `FiscalYearController` | Fiscal year closing |
| `MonthEndCloseController` | Month-end workflow |
| `DashboardController` (accounting) | Accounting stats |
| `Api/V1/MonthEndCloseController` | Month-end API |
| `Api/V1/EodReconciliationController` | EOD reconciliation API |

### 7. Stock Transfers (2)
| Controller | Purpose |
|------------|---------|
| `StockTransferController` | Inter-branch transfers, approval workflow |
| `Api/V1/StockTransferController` | Transfer API |

### 8. Exchange Rates (2)
| Controller | Purpose |
|------------|---------|
| `RateController` | Rate management UI |
| `Api/V1/RateController` | Rate API (fetch, copy, override) |

### 9. Branch Management (5)
| Controller | Purpose |
|------------|---------|
| `BranchController` | Branch CRUD |
| `BranchOpeningController` | Multi-step opening wizard |
| `BranchClosingController` | Closure workflow |
| `Api/V1/BranchController` | Branch API |

### 10. User & Auth (3)
| Controller | Purpose |
|------------|---------|
| `UserController` | User management |
| `Auth/LoginController` | Login with MFA |
| `MfaController` | MFA setup/verify/recovery |

### 11. Reports & Analytics (5)
| Controller | Purpose |
|------------|---------|
| `Report/RegulatoryReportController` | LCTR, MSB2, LMCA, QLVR |
| `Report/AnalyticsController` | Trends, profitability analysis |
| `ReportController` | Report history & downloads |
| `Api/V1/ReportController` | Report API |
| `DashboardController` | Main dashboard |

### 12. System & Infrastructure (7)
| Controller | Purpose |
|------------|---------|
| `SetupController` | Application setup wizard |
| `HealthCheckController` | System health |
| `TestResultsController` | Test execution results |
| `PerformanceMonitoringController` | Cache/performance metrics |
| `AuditController` | Audit log viewing |
| `Api/SanctionsWebhookController` | Sanctions webhook receiver |
| `BulkImportController` | Bulk CSV import |

---

## Services (83 Services)

Business logic layer organized in `app/Services/`:

### Core Transaction Services (15)
| Service | Purpose |
|---------|---------|
| `TransactionService` | Core transaction creation/processing |
| `TransactionApprovalService` | Approval workflow |
| `TransactionCancellationService` | Cancellation workflow |
| `TransactionStateMachine` | State transitions |
| `TransactionMonitoringService` | Compliance monitors trigger |
| `TransactionImportService` | Transaction CSV import |
| `TransactionErrorHandler` | Error recovery |
| `TransactionRecoveryService` | Failed transaction recovery |
| `CounterService` | Counter lifecycle |
| `CounterSessionService` | Session tracking |
| `CounterHandoverService` | Custody transfer |
| `CounterOpeningWorkflowService` | Opening workflow |
| `EmergencyCounterService` | Emergency closure |
| `TellerAllocationService` | Float allocation |
| `StockTransferService` | Inter-branch transfers |

### Customer Services (7)
| Service | Purpose |
|---------|---------|
| `CustomerService` | Customer CRUD & search |
| `CustomerDocumentService` | KYC document handling |
| `CustomerRelationService` | PEP relations |
| `CustomerRiskScoringService` | Risk score calculation |
| `CustomerRiskReviewService` | Risk review process |
| `CustomerScreeningService` | Sanctions screening |
| `EddService` | Enhanced Due Diligence |

### Compliance Services (11)
| Service | Purpose |
|---------|---------|
| `ComplianceService` | CDD determination, CTOS |
| `AlertTriageService` | Alert prioritization |
| `CaseManagementService` | Compliance cases |
| `ComplianceReportingService` | Compliance reports |
| `RiskScoringEngine` | Risk scoring engine |
| `SanctionsImportService` | Sanctions import |
| `SanctionsDownloadService` | Sanctions download |
| `CtosReportService` | CTOS report generation |
| `CtrReportService` | CTR report generation |
| `StrReportService` | STR report generation |
| `KycDocumentExpiryService` | KYC expiry tracking |

### Accounting Services (8)
| Service | Purpose |
|---------|---------|
| `AccountingService` | Journal entry creation |
| `LedgerService` | Trial balance, account ledger |
| `CurrencyPositionService` | Stock/position management |
| `RevaluationService` | Monthly currency revaluation |
| `MonthEndCloseService` | Month-end close |
| `FiscalYearService` | Fiscal year management |
| `PeriodCloseService` | Period closing |
| `BankReconciliationService` | Bank reconciliation |

### Rate Services (3)
| Service | Purpose |
|---------|---------|
| `RateManagementService` | Daily rate workflow |
| `RateApiService` | External rate API fetch |
| `RateLimitService` | Rate limiting |

### Risk Services (5) - `Services/Risk/`
| Service | Purpose |
|---------|---------|
| `AmountRiskService` | Amount-based risk |
| `GeographicRiskService` | Country risk |
| `PatternRiskService` | Pattern detection |
| `StructuringRiskService` | Smurfing detection |
| `VelocityRiskService` | Velocity-based risk |

### Compliance Monitors (8) - `Services/Compliance/Monitors/`
| Monitor | Purpose |
|---------|---------|
| `VelocityMonitor` | Transaction velocity |
| `StructuringMonitor` | Structuring patterns |
| `SanctionsRescreeningMonitor` | Periodic rescreening |
| `StrDeadlineMonitor` | STR deadline tracking |
| `CustomerLocationAnomalyMonitor` | Location anomalies |
| `CurrencyFlowMonitor` | Currency flow patterns |
| `CounterfeitAlertMonitor` | Counterfeit detection |
| `BaseMonitor` | Abstract base class |

### Reporting Services (6)
| Service | Purpose |
|---------|---------|
| `ReportingService` | Report generation |
| `ReportSchedulingService` | Scheduled reports |
| `BranchStockReportingService` | Stock position reports |
| `CashFlowService` | Cash flow statements |
| `FinancialRatioService` | Financial ratios |
| `NarrativeGenerator` | STR narrative generation |

### Infrastructure Services (12)
| Service | Purpose |
|---------|---------|
| `AuditService` | Audit trail with hash chain |
| `EncryptionService` | PII encryption |
| `MathService` | BCMath precision |
| `ThresholdService` | Centralized thresholds |
| `SystemHealthService` | Health monitoring |
| `SystemAlertService` | System alerts |
| `CacheOptimizationService` | Cache management |
| `QueryLoggingService` | Query logging |
| `QueryOptimizerService` | Query optimization |
| `BackupService` | Backup operations |
| `ExportService` | Data export |
| `BulkImportService` | Bulk data import |

### Support Services (7)
| Service | Purpose |
|---------|---------|
| `UserService` | User management |
| `MfaService` | MFA operations |
| `BranchService` | Branch management |
| `BranchPoolService` | Branch pool |
| `BranchClosingService` | Branch closure |
| `BudgetService` | Budget management |
| `LogRotationService` | Log rotation |

### Utility Services (6)
| Service | Purpose |
|---------|---------|
| `GoAmlXmlGenerator` | GoAML XML output |
| `TestRunnerService` | Test execution |
| `WizardSessionService` | Transaction wizard |
| `PreValidationResult` | Validation helper |
| `BranchScopeService` | Branch scope |
| `ComprehensiveLogService` | Logging |

---

## Models (62 Eloquent Models)

Organized by domain:

### Transaction Domain (4)
| Model | Purpose |
|-------|---------|
| `Transaction` | Foreign currency buy/sell |
| `TransactionError` | Error tracking for retry |
| `TransactionImport` | Bulk import tracking |
| `TransactionConfirmation` | Approval confirmations |

### Customer Domain (4)
| Model | Purpose |
|-------|---------|
| `Customer` | KYC, risk tracking |
| `CustomerDocument` | ID documents |
| `CustomerRelation` | PEP relations |
| `CustomerRiskHistory` | Risk score history |

### Compliance Domain (13)
| Model | Purpose |
|-------|---------|
| `Alert` | Compliance alerts |
| `FlaggedTransaction` | Flagged transactions |
| `AmlRule` | AML detection rules |
| `SanctionList` | Sanctions list |
| `SanctionEntry` | Individual entries |
| `SanctionImportLog` | Import history |
| `ScreeningResult` | Screening results |
| `HighRiskCountry` | High-risk countries |
| `StrReport` | Suspicious transaction reports |
| `CtosReport` | Cash transaction reports |
| `EnhancedDiligenceRecord` | EDD records |
| `EddTemplate` | EDD questionnaires |
| `ComplianceCase` | Compliance cases |

### Case Management (9)
| Model | Purpose |
|-------|---------|
| `ComplianceCaseNote` | Case notes |
| `ComplianceCaseDocument` | Case documents |
| `ComplianceCaseLink` | Polymorphic links |
| `ComplianceFinding` | Findings |
| `CustomerRiskProfile` | Risk profiles |
| `CustomerBehavioralBaseline` | Behavior patterns |
| `EddDocumentRequest` | EDD document requests |
| `EddQuestionnaireTemplate` | Questionnaire templates |
| `CustomerRiskProfile` | Risk tier classification |

### Accounting Domain (11)
| Model | Purpose |
|-------|---------|
| `JournalEntry` | Journal entries |
| `JournalLine` | Debit/credit lines |
| `AccountLedger` | General ledger |
| `ChartOfAccount` | Account codes |
| `AccountingPeriod` | Fiscal periods |
| `FiscalYear` | Annual years |
| `CostCenter` | Cost centers |
| `Department` | Departments |
| `BankReconciliation` | Bank reconciliation |
| `Budget` | Budget tracking |
| `RevaluationEntry` | Revaluation entries |

### Branch & Counter (9)
| Model | Purpose |
|-------|---------|
| `Branch` | Branch/head office |
| `BranchPool` | Branch currency pool |
| `Counter` | Teller counters |
| `CounterSession` | Counter sessions |
| `CounterHandover` | Handover records |
| `TellerAllocation` | Float allocations |
| `TillBalance` | EOD balance tracking |
| `BranchClosureWorkflow` | Closure workflow |
| `EmergencyClosure` | Emergency closures |

### User & Auth (4)
| Model | Purpose |
|-------|---------|
| `User` | System users |
| `MfaRecoveryCode` | MFA recovery codes |
| `DeviceComputations` | Trusted devices |
| `UserNotificationPreference` | Notification preferences |

### Currency & Exchange (4)
| Model | Purpose |
|-------|---------|
| `Currency` | Currency definitions |
| `CurrencyPosition` | Position balances |
| `ExchangeRate` | Daily rates |
| `ExchangeRateHistory` | Rate history |

### Stock Transfer (3)
| Model | Purpose |
|-------|---------|
| `StockTransfer` | Transfer workflow |
| `StockTransferItem` | Transfer line items |
| `StockReservation` | Stock reservations |

### Reporting (4)
| Model | Purpose |
|-------|---------|
| `ReportSchedule` | Scheduled reports |
| `ReportRun` | Report runs |
| `ReportTemplate` | Report templates |
| `ReportGenerated` | Generated reports |

### System (6)
| Model | Purpose |
|-------|---------|
| `SystemAlert` | System alerts |
| `SystemHealthCheck` | Health checks |
| `SystemLog` | Audit logs |
| `BackupLog` | Backup history |
| `ThresholdAudit` | Threshold changes |
| `TestResult` | Test results |

---

## Views (100+ Blade Templates)

Organized by module:

### Core Modules (50+ views)
| Module | Views | Purpose |
|--------|-------|---------|
| Transactions | 17 | index, show, create, wizard, receipt, cancel, confirm |
| Accounting | 20 | balance-sheet, profit-loss, trial-balance, cash-flow, journal, ledger |
| Compliance | 13 | cases, ctos, rules, sanctions |
| Reports | 12 | lctr, lmca, msb2, eod-reconciliation |
| Counters | 8 | open, close, handover, history, emergency |
| Stock Transfers | 9 | dispatch, receive, approve-bm/hq, complete, cancel |
| Branches | 8 | index, show, create, edit, opening wizard, closing |
| Customers | 6 | index, show, create, edit, kyc, history |

### Support Modules (30+ views)
| Module | Views | Purpose |
|--------|-------|---------|
| Dashboard | 4 | main, accounting, compliance, reports |
| STR | 4 | index, show, create, edit |
| MFA | 5 | setup, verify, recovery, trusted-devices |
| Users | 4 | index, show, create, edit |
| Stock Cash | 4 | index, position, till-report, reconciliation |
| Audit | 5 | index, dashboard, show, rotate, pdf |
| Test Results | 4 | index, show, compare, statistics |
| Setup | 1 | index |
| Performance | 1 | index |
| Rates | 1 | index |
| Components | 5 | icon, loading, notifications, sidebar-* |

### Layout
| File | Purpose |
|------|---------|
| `layouts/base.blade.php` | Base template with sidebar/nav |

---

## Enums (34 PHP 8.1 Enums)

### Transaction (5)
| Enum | Values |
|------|--------|
| `TransactionStatus` | Draft, PendingApproval, Approved, Processing, Completed, Finalized, Cancelled, Reversed, Failed, Rejected, Pending, OnHold, PendingCancellation |
| `TransactionType` | Buy, Sell |
| `TransactionImportStatus` | Pending, Processing, Completed, CompletedWithErrors, Failed |
| `JournalEntryStatus` | Draft, Pending, Posted, Rejected, Reversed |
| `ReportStatus` | Scheduled, Running, Completed, Failed |

### Compliance/AML (22)
| Enum | Values |
|------|--------|
| `CddLevel` | Simplified, Specific, Standard, Enhanced |
| `EddStatus` | Incomplete, PendingQuestionnaire, QuestionnaireSubmitted, PendingReview, Approved, Rejected, Expired |
| `EddRiskLevel` | Low, Medium, High, Critical |
| `EddTemplateType` | PEP, HighRiskCountry, UnusualPattern, SanctionMatch, LargeTransaction, HighRiskIndustry |
| `EddDocumentStatus` | Pending, Received, Verified, Rejected |
| `ComplianceFlagType` | 18 flag types (LargeAmount, SanctionsHit, Velocity, Structuring, etc.) |
| `FlagStatus` | Open, UnderReview, Resolved, Escalated, Rejected |
| `FindingType` | 9 types (VelocityExceeded, StructuringPattern, etc.) |
| `FindingStatus` | New, Reviewed, Dismissed, CaseCreated |
| `FindingSeverity` | Low, Medium, High, Critical |
| `ComplianceCaseType` | Investigation, Edd, Str, SanctionReview, Counterfeit |
| `ComplianceCaseStatus` | Open, UnderReview, PendingApproval, Closed, Escalated |
| `ComplianceCasePriority` | Low, Medium, High, Critical |
| `CaseStatus` | Open, InProgress, PendingReview, Resolved, Closed |
| `CaseResolution` | NoConcern, WarningIssued, EddRequired, StrFiled, ClosedNoAction |
| `CaseNoteType` | Investigation, Update, Decision, Escalation |
| `AlertPriority` | Critical (4h), High (8h), Medium (24h), Low (72h) |
| `AmlRuleType` | Velocity, Structuring, AmountThreshold, Frequency, Geographic |
| `StrStatus` | Draft, PendingReview, PendingApproval, Submitted, Acknowledged, Failed |
| `CtosStatus` | Draft, Submitted, Acknowledged, Rejected |
| `RecalculationTrigger` | Manual, Scheduled, EventDriven |
| `RiskRating` | Low, Medium, High |
| `RiskTrend` | Improving, Stable, Deteriorating |

### User (1)
| Enum | Values |
|------|--------|
| `UserRole` | Teller, Manager, ComplianceOfficer, Admin |

### Accounting (1)
| Enum | Values |
|------|--------|
| `AccountCode` | 1000-6200 (18 codes) |

### Inventory (2)
| Enum | Values |
|------|--------|
| `StockTransferStatus` | Requested, BranchManagerApproved, HqApproved, Rejected, Cancelled |
| `StockReservationStatus` | Pending, Consumed, Released |

### Counter (2)
| Enum | Values |
|------|--------|
| `CounterSessionStatus` | Open, Closed, HandedOver, PendingHandover, EmergencyClosed |
| `TellerAllocationStatus` | PENDING, APPROVED, ACTIVE, RETURNED, CLOSED, AUTO_RETURNED, REJECTED |

---

## Jobs (23 Background Jobs)

### Import (2)
| Job | Purpose |
|-----|---------|
| `ProcessTransactionImport` | Process transaction CSV imports |
| `ProcessCustomerImport` | Process customer CSV imports |

### Transaction (1)
| Job | Purpose |
|-----|---------|
| `ProcessTransactionRetry` | Retry failed transactions |

### Reporting (1)
| Job | Purpose |
|-----|---------|
| `ReportGenerationJob` | Generate scheduled reports |

### Compliance (8)
| Job | Purpose |
|-----|---------|
| `ComplianceScreeningJob` | Screen customers |
| `RescreenHighRiskCustomersJob` | Rescreen high-risk customers |
| `Compliance/SanctionsRescreeningJob` | Rescreen on new sanctions |
| `Compliance/StrDeadlineMonitorJob` | Track STR deadlines |
| `Compliance/CounterfeitAlertJob` | Detect counterfeits |
| `Compliance/StructuringMonitorJob` | Detect structuring |
| `Compliance/VelocityMonitorJob` | Monitor velocity |
| `Compliance/CustomerLocationAnomalyJob` | Detect location anomalies |
| `Compliance/CurrencyFlowJob` | Monitor currency flow |

### Accounting (1)
| Job | Purpose |
|-----|---------|
| `Accounting/ReconcileDeferredAccountingJob` | Auto-create deferred entries |

### Notifications (1)
| Job | Purpose |
|-----|---------|
| `SendNotificationJob` | Send async notifications |

### Audit (1)
| Job | Purpose |
|-----|---------|
| `Audit/SealAuditHashJob` | Seal audit hash chain |

### Sanctions (8)
| Job | Purpose |
|-----|---------|
| `Sanctions/BaseSanctionsDownloadJob` | Abstract base for downloads |
| `Sanctions/DownloadMohaSanctionsList` | MOHA list |
| `Sanctions/DownloadEuSanctionsList` | EU list |
| `Sanctions/DownloadOfacSanctionsList` | OFAC list |
| `Sanctions/DownloadUnSanctionsList` | UN list |
| `ImportSanctionsJob` | Import sanctions |
| `SubmitStrToGoAmlJob` | Submit STR to goAML |

---

## Events (13 Events)

### Transaction (3)
| Event | Fired When |
|-------|------------|
| `TransactionCreated` | New transaction created |
| `TransactionApproved` | Transaction approved |
| `TransactionCancelled` | Transaction cancelled |

### Customer (4)
| Event | Fired When |
|-------|------------|
| `CustomerRecordUpdated` | Customer updated |
| `CustomerRelationAdded` | PEP relation added |
| `CustomerRelationRemoved` | PEP relation removed |
| `RiskScoreCalculated` | Risk score calculated (broadcast) |

### Compliance (4)
| Event | Fired When |
|-------|------------|
| `AlertCreated` | New alert created (broadcast) |
| `CaseOpened` | Compliance case opened (broadcast) |
| `RiskScoreUpdated` | Risk score updated (broadcast) |
| `PendingCancellationRequested` | Cancellation requested |

### Sanctions (1)
| Event | Fired When |
|-------|------------|
| `SanctionsListUpdated` | Sanctions list updated |

### Reporting (1)
| Event | Fired When |
|-------|------------|
| `ReportGenerated` | Report completed (broadcast) |

---

## Console Commands (41 Artisan Commands)

### Financial/Reporting (7)
| Command | Purpose |
|---------|---------|
| `report:msb2` | Daily transaction summary |
| `report:lctr` | Monthly LCTR (≥ RM 50k) |
| `report:lmca` | Monthly LMCA |
| `report:qlvr` | Quarterly LVR |
| `report:eod` | End-of-day reconciliation |
| `report:trial-balance` | Trial balance |
| `report:position-limit` | Position limits |

### Compliance/KYC (5)
| Command | Purpose |
|---------|---------|
| `compliance:rescreen` | Rescreen customers |
| `sanctions:import` | Import sanctions |
| `sanctions:status` | Check status |
| `sanctions:update` | Update lists |
| `customer:risk-review` | Risk review |

### Monitoring (5)
| Command | Purpose |
|---------|---------|
| `monitor:check` | Run monitors |
| `monitor:status` | Monitor status |
| `alert:send` | Send alerts |
| `alert:daily-summary` | Daily summary |
| `notification:digest` | Notification digest |

### Backup (6)
| Command | Purpose |
|---------|---------|
| `backup:run` | Run backup |
| `backup:verify` | Verify backup |
| `backup:list` | List backups |
| `backup:clean` | Clean old |
| `backup:restore` | Restore backup |
| `backup:monitor` | Monitor status |

### Maintenance (6)
| Command | Purpose |
|---------|---------|
| `reservation:expire` | Expire stock reservations |
| `month-end:close` | Month-end close |
| `revaluation:run` | Currency revaluation |
| `route:validate` | Route consistency |
| `reports:archive` | Archive old reports |
| `audit:rotate` | Rotate audit logs |

### Queue (3)
| Command | Purpose |
|---------|---------|
| `queue:health` | Queue health |
| `queue:clear-stuck` | Clear stuck jobs |
| `queue:retry-failed` | Retry failed jobs |

### User (1)
| Command | Purpose |
|---------|---------|
| `user:create` | Create user |

### Security (1)
| Command | Purpose |
|---------|---------|
| `ip:blocker` | Manage IP blocks |

### Setup (2)
| Command | Purpose |
|---------|---------|
| `setup:business` | Business setup |
| `setup:comprehensive` | Full setup |

### Testing (4)
| Command | Purpose |
|---------|---------|
| `test:notification` | Test notifications |
| `test:scenarios` | Test scenarios |
| `test:reset-db` | Reset test DB |
| `tests:run` | Run test suite |

---

## Domain Exceptions (43 Typed Exceptions)

Business rule enforcement in `app/Exceptions/Domain/`:

### Transaction Exceptions
| Exception | Rule |
|-----------|------|
| `DuplicateTransactionException` | Prevent duplicate submissions |
| `InvalidTransactionStateException` | State transition validation |
| `TransactionAlreadyProcessedException` | Prevent re-processing |
| `PendingTransactionException` | Cannot modify pending |

### Accounting Exceptions
| Exception | Rule |
|-----------|------|
| `ClosedPeriodException` | Cannot post to closed period |
| `FiscalYearClosedException` | Cannot post to closed year |
| `OpenPeriodsException` | Periods must close before year |
| `UnbalancedJournalException` | Debits must equal credits |
| `EntryNotPostedException` | Must be posted to reverse |
| `EntryAlreadyReversedException` | Cannot reverse twice |
| `InvalidDeferralException` | Only EDD supports deferral |

### Counter/Till Exceptions
| Exception | Rule |
|-----------|------|
| `TillAlreadyOpenException` | Till already open |
| `TillClosedException` | Cannot use closed till |
| `TillBalanceMissingException` | No balance record |
| `SessionClosedException` | Session not open |
| `SessionOwnershipException` | Wrong user session |
| `CounterSessionMismatchException` | Session mismatch |
| `UserAlreadyAtCounterException` | User at another counter |
| `EmergencyCloseCooldownException` | 4-hour cooldown |
| `EmergencyCloseSessionTooNewException` | 30-min minimum session |

### Stock/Inventory Exceptions
| Exception | Rule |
|-----------|------|
| `InsufficientStockException` | Insufficient currency |
| `InsufficientPoolBalanceException` | Pool insufficient |
| `StockReservationExpiredException` | Reservation expired |
| `PendingAllocationNotFoundException` | No pending allocation |
| `InvalidAllocationStateException` | Must be approved to activate |
| `PoolAllocationException` | Pool allocation failed |
| `VarianceThresholdException` | Variance exceeds threshold |

### Permission Exceptions
| Exception | Rule |
|-----------|------|
| `SelfApprovalException` | Cannot approve own transaction |
| `SupervisorRequiredException` | Supervisor required |
| `PermissionDeniedException` | Access denied |
| `UnauthorizedException` | Unauthenticated |

### State Exceptions
| Exception | Rule |
|-----------|------|
| `InvalidStateException` | Generic state error |
| `InvalidFiscalYearStateException` | Invalid FY state |
| `BranchClosingChecklistIncompleteException` | Checklist incomplete |

### Validation Exceptions
| Exception | Rule |
|-----------|------|
| `InvalidCurrencyException` | Invalid currency |
| `InvalidRateException` | Invalid rate |
| `InvalidIpAddressException` | Invalid IP format |
| `DuplicateTransactionException` | Already exists |

### Other Domain Exceptions
| Exception | Rule |
|-----------|------|
| `AccountNotFoundException` | Account not found |
| `CddDocumentExpiredException` | KYC expired |
| `MonthEndPreCheckFailedException` | Pre-checks failed |
| `TillBalanceMissingException` | Balance record missing |
| `TellerBranchRequiredException` | Teller needs branch |
| `InvalidAllocationStateException` | Invalid allocation state |

---

## Request Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                        HTTP Request                             │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                     Middleware Stack                            │
│  SecurityHeaders → IpBlocker → Auth → Session → CheckRole      │
│  → EnsureMfaEnabled → EnsureMfaVerified → StrictRateLimit       │
│  → CheckBranchAccess → QueryPerformanceMonitor → LogRequests   │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Route Matching                             │
│              web.php / api_v1.php / auth.php                    │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Controller                                 │
│              (Dependency Injection of Services)                 │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Service Layer                              │
│         TransactionService, ComplianceService, etc.            │
│                          │                                      │
│         ┌────────────────┼────────────────┐                   │
│         ▼                ▼                ▼                     │
│   ┌──────────┐    ┌──────────────┐  ┌──────────┐             │
│   │  Models  │    │   Enums      │  │  Jobs    │             │
│   │ (Eloquent)│    │ (Status,Role)│  │ (Queued) │             │
│   └──────────┘    └──────────────┘  └──────────┘             │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                       View Rendering                            │
│                   Blade Templates                               │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                       HTTP Response                             │
└─────────────────────────────────────────────────────────────────┘
```

---

## Data Flow Examples

### Transaction Creation Flow
```
1. Teller submits POST /transactions (MFA verified)
2. TransactionController@store
3. TransactionService::createTransaction()
   - CustomerService::determineCddLevel()
   - ComplianceService::screenCustomer()
   - CurrencyPositionService::reserveStock()
   - AccountingService::createJournalEntry()
   - TransactionMonitoringService::checkVelocity()
   - TransactionMonitoringService::checkStructuring()
4. TransactionStateMachine::transition(Draft → PendingApproval)
5. Fire TransactionCreated event
6. Return transaction JSON/redirect
```

### Counter Opening Flow
```
1. Manager POST /api/counters/{id}/approve-and-open
2. CounterOpeningController@approveAndOpen
3. CounterOpeningWorkflowService::approveAndOpen()
   - CounterService::open()
   - CurrencyPositionService::initializePosition()
   - TillBalance::create()
   - AccountingService::createJournalEntry() for MYR float
4. Return session JSON
```

### Compliance Screening Flow
```
1. Background: ComplianceScreeningJob dispatched
2. CustomerScreeningService::screenCustomer()
   - SanctionsImportService::checkSanctions()
   - CustomerRiskScoringService::calculateScore()
3. If hit: ComplianceCase::create()
4. Fire AlertCreated event (broadcast)
5. AlertTriageService::prioritizeAndAssign()
```

---

## Technology Stack

| Component | Technology |
|-----------|------------|
| Framework | Laravel 10.x |
| Language | PHP 8.1+ |
| Database | MySQL 8.0 |
| Cache/Queue | Redis |
| Queue UI | Laravel Horizon |
| Auth | Laravel Sanctum (API) / Session (Web) |
| PDF | DomPDF |
| Excel | Maatwebsite Excel |
| QR/Barcode | simple-qrcode, php-barcode-generator |
| Frontend | Tailwind CSS v4 |

---

## Directory Structure

```
CEMS-MY/
├── app/
│   ├── Console/Commands/        # 41 Artisan commands
│   │   └── Backup/              # Backup commands
│   ├── Enums/                   # 34 PHP enums
│   ├── Events/                  # 13 events
│   ├── Exceptions/Domain/       # 43 exceptions
│   ├── Http/
│   │   ├── Controllers/        # 71 controllers
│   │   │   ├── Api/V1/          # API v1 controllers
│   │   │   └── Compliance/      # Compliance controllers
│   │   └── Middleware/          # 21 middleware
│   ├── Jobs/                    # 23 jobs
│   │   ├── Compliance/         # Compliance jobs
│   │   ├── Sanctions/          # Sanctions jobs
│   │   └── Accounting/         # Accounting jobs
│   ├── Models/                  # 62 Eloquent models
│   └── Services/                # 83 services
│       ├── Risk/               # 5 risk services
│       └── Compliance/         # 3 compliance services
│           └── Monitors/       # 8 monitors
├── config/                      # Laravel config
│   └── thresholds.php          # Centralized thresholds
├── database/
│   ├── migrations/             # Database schema
│   └── seeders/                 # Seed data
├── resources/views/             # 100+ Blade templates
│   ├── layouts/                 # Base layout
│   └── components/             # Blade components
├── routes/
│   ├── web.php                  # Web routes
│   ├── api_v1.php              # API v1 routes
│   ├── auth.php                # Auth routes
│   └── channels.php            # Broadcasting
└── tests/
    ├── Feature/                # Feature tests
    └── Unit/                   # Unit tests
```

---

## Summary Statistics

| Component | Count |
|-----------|-------|
| Models | 62 |
| Controllers | 71 |
| Services | 83 |
| Enums | 34 |
| Middleware | 21 |
| Jobs | 23 |
| Events | 13 |
| Commands | 41 |
| Exceptions | 43 |
| Routes | ~294 |
| Views | 100+ |

---

**Last Updated**: May 2, 2026