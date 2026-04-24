# CEMS-MY Accounting Flow Chart

## Overview
This flow chart documents the complete accounting and ledger flow in CEMS-MY, including journal entry creation, ledger updates, reversal, and financial reporting.

## Journal Entry Creation Flow

```mermaid
flowchart TD
    Start([Create journal entry]) --> ValidateLines[Validate journal lines]
    ValidateLines -->|Invalid| ReturnError[Return validation error]
    ValidateLines -->|Valid| CheckBalanced[Check debits equal credits]
    CheckBalanced -->|Not balanced| ReturnBalanceError[Return unbalanced error]
    CheckBalanced -->|Balanced| FindPeriod[Find accounting period]
    FindPeriod --> CheckPeriod{Period exists?}
    CheckPeriod -->|Yes| CheckOpen[Check period is open]
    CheckPeriod -->|No| UseDefault[Use default period]
    CheckOpen -->|Closed| ReturnPeriodError[Return period closed error]
    CheckOpen -->|Open| CreateEntry[Create JournalEntry record]
    UseDefault --> CreateEntry
    CreateEntry --> SetEntryDate[Set entry_date]
    SetEntryDate --> SetPeriodId[Set period_id]
    SetPeriodId --> SetReference[Set reference_type and reference_id]
    SetReference --> SetDescription[Set description]
    SetDescription --> SetPostedStatus[Set status to Posted]
    SetPostedStatus --> SetPostedBy[Set posted_by user]
    SetPostedBy --> SetPostedAt[Set posted_at timestamp]
    SetPostedAt --> CreateLines[Create JournalLine records]
    CreateLines --> ForEachLine[For each journal line]
    ForEachLine --> CreateLine[Create JournalLine record]
    CreateLine --> SetAccountCode[Set account_code]
    SetAccountCode --> SetDebit[Set debit amount]
    SetDebit --> SetCredit[Set credit amount]
    SetCredit --> SetLineDescription[Set line description]
    SetLineDescription --> NextLine[Next line]
    NextLine -->|More| ForEachLine
    NextLine -->|Done| UpdateLedger[Update account ledger]
    UpdateLedger --> ForEachLedger[For each journal line]
    ForEachLedger --> GetAccountBalance[Get current account balance]
    GetAccountBalance --> CheckAccountType{Account type}
    CheckAccountType -->|Asset/Expense| CalculateDebitBalance[Calculate new balance: +debit -credit]
    CheckAccountType -->|Liability/Equity/Revenue| CalculateCreditBalance[Calculate new balance: +credit -debit]
    CalculateDebitBalance --> CreateLedgerEntry[Create AccountLedger record]
    CalculateCreditBalance --> CreateLedgerEntry
    CreateLedgerEntry --> SetLedgerAccount[Set account_code]
    SetLedgerAccount --> SetLedgerDate[Set entry_date]
    SetLedgerDate --> SetJournalId[Set journal_entry_id]
    SetJournalId --> SetLedgerDebit[Set debit]
    SetLedgerDebit --> SetLedgerCredit[Set credit]
    SetLedgerCredit --> SetRunningBalance[Set running_balance]
    SetRunningBalance --> NextLedger[Next ledger line]
    NextLedger -->|More| ForEachLedger
    NextLedger -->|Done| LogAudit[Log journal entry created]
    LogAudit --> ReturnEntry[Return journal entry]
    ReturnEntry --> End([Journal entry created])
```

## Transaction Accounting Flow

```mermaid
flowchart TD
    Start([Transaction completed]) --> CheckType{Transaction type}
    CheckType -->|Buy| CreateBuyEntries[Create buy accounting entries]
    CheckType -->|Sell| CreateSellEntries[Create sell accounting entries]

    CreateBuyEntries --> CreateInventoryDebit[Create inventory debit]
    CreateInventoryDebit --> SetInventoryAccount[Set account to FOREIGN_CURRENCY_INVENTORY]
    SetInventoryAccount --> SetInventoryDebitAmount[Set debit to amount_local]
    SetInventoryDebitAmount --> SetInventoryCredit[Set credit to 0]
    SetInventoryCredit --> SetInventoryDesc[Set description]
    SetInventoryDesc --> CreateCashCredit[Create cash credit]
    CreateCashCredit --> SetCashAccount[Set account to CASH_MYR]
    SetCashAccount --> SetCashDebit[Set debit to 0]
    SetCashDebit --> SetCashCreditAmount[Set credit to amount_local]
    SetCashCreditAmount --> SetCashDesc[Set description]
    SetCashDesc --> CreateJournal[Create journal entry]
    CreateJournal --> ReturnBuy[Return buy journal]

    CreateSellEntries --> GetPosition[Get currency position]
    GetPosition --> CalculateAvgCost[Calculate average cost rate]
    CalculateAvgCost --> CalculateCostBasis[Calculate cost basis]
    CalculateCostBasis --> CalculateRevenue[Calculate revenue = amount_local - cost_basis]
    CalculateRevenue --> CheckGain{Gain or loss?}
    CheckGain -->|Gain| CreateGainEntry[Create gain entry]
    CheckGain -->|Loss| CreateLossEntry[Create loss entry]

    CreateGainEntry --> CreateCashDebit[Create cash debit]
    CreateCashDebit --> SetCashAccount[Set account to CASH_MYR]
    SetCashAccount --> SetCashDebitAmount[Set debit to amount_local]
    SetCashDebitAmount --> SetCashCredit[Set credit to 0]
    SetCashCredit --> SetCashDesc[Set description]
    SetCashDesc --> CreateInventoryCredit[Create inventory credit]
    CreateInventoryCredit --> SetInventoryAccount[Set account to FOREIGN_CURRENCY_INVENTORY]
    SetInventoryAccount --> SetInventoryDebit[Set debit to 0]
    SetInventoryCredit --> SetInventoryCreditAmount[Set credit to cost_basis]
    SetInventoryCreditAmount --> SetInventoryDesc[Set description]
    SetInventoryDesc --> CreateRevenueCredit[Create revenue credit]
    CreateRevenueCredit --> SetRevenueAccount[Set account to FOREX_TRADING_REVENUE]
    SetRevenueAccount --> SetRevenueDebit[Set debit to 0]
    SetRevenueDebit --> SetRevenueCreditAmount[Set credit to revenue]
    SetRevenueCreditAmount --> SetRevenueDesc[Set description]
    SetRevenueDesc --> CreateSellJournal[Create sell journal]
    CreateSellJournal --> ReturnSell[Return sell journal]

    CreateLossEntry --> CreateCashDebit2[Create cash debit]
    CreateCashDebit2 --> SetCashAccount2[Set account to CASH_MYR]
    SetCashAccount2 --> SetCashDebitAmount2[Set debit to amount_local]
    SetCashDebitAmount2 --> SetCashCredit2[Set credit to 0]
    SetCashCredit2 --> SetCashDesc2[Set description]
    SetCashDesc2 --> CreateInventoryCredit2[Create inventory credit]
    CreateInventoryCredit2 --> SetInventoryAccount2[Set account to FOREIGN_CURRENCY_INVENTORY]
    SetInventoryAccount2 --> SetInventoryDebit2[Set debit to 0]
    SetInventoryCredit2 --> SetInventoryCreditAmount2[Set credit to cost_basis]
    SetInventoryCreditAmount2 --> SetInventoryDesc2[Set description]
    SetInventoryDesc2 --> CreateLossDebit[Create loss debit]
    CreateLossDebit --> SetLossAccount[Set account to FOREX_LOSS]
    SetLossAccount --> SetLossDebitAmount[Set debit to abs(revenue)]
    SetLossDebitAmount --> SetLossCredit[Set credit to 0]
    SetLossCredit --> SetLossDesc[Set description]
    SetLossDesc --> CreateSellJournal2[Create sell journal]
    CreateSellJournal2 --> ReturnSell2[Return sell journal]

    ReturnBuy --> End([Accounting entries created])
    ReturnSell --> End
    ReturnSell2 --> End
```

## Journal Entry Reversal Flow

```mermaid
flowchart TD
    Start([Reverse journal entry]) --> ValidateEntry[Validate entry exists]
    ValidateEntry -->|Not found| ReturnError[Return entry not found error]
    ValidateEntry -->|Found| CheckReversed[Check if already reversed]
    CheckReversed -->|Already reversed| ReturnReversedError[Return already reversed error]
    CheckReversed -->|Not reversed| CheckPosted[Check if entry is posted]
    CheckPosted -->|Not posted| ReturnPostedError[Return not posted error]
    CheckPosted -->|Posted| LoadLines[Load journal lines]
    LoadLines --> CreateReversalLines[Create reversal lines]
    CreateReversalLines --> ForEachOriginalLine[For each original line]
    ForEachOriginalLine --> SwapDebitCredit[Swap debit and credit]
    SwapDebitCredit --> SetReversalDesc[Set description to 'Reversal: ' + original]
    SetReversalDesc --> AddToLines[Add to reversal lines]
    AddToLines --> NextOriginalLine[Next original line]
    NextOriginalLine -->|More| ForEachOriginalLine
    NextOriginalLine -->|Done| CreateReversalEntry[Create reversal journal entry]
    CreateReversalEntry --> SetReversalReference[Set reference_type to 'Reversal']
    SetReversalReference --> SetReversalReferenceId[Set reference_id to original entry ID]
    SetReversalReferenceId --> SetReversalDescription[Set description]
    SetReversalDescription --> CreateReversal[Create journal entry]
    CreateReversal --> UpdateOriginal[Update original entry status]
    UpdateOriginal --> SetReversedStatus[Set status to 'Reversed']
    SetReversedStatus --> SetReversedBy[Set reversed_by user]
    SetReversedBy --> SetReversedAt[Set reversed_at timestamp]
    SetReversedAt --> LogAudit[Log reversal action]
    LogAudit --> ReturnReversal[Return reversal entry]
    ReturnReversal --> End([Journal entry reversed])
```

## Till Balance Update Flow

```mermaid
flowchart TD
    Start([Transaction completed]) --> FindTillBalance[Find till balance for today]
    FindTillBalance -->|Not found| SkipUpdate[Skip update]
    FindTillBalance -->|Found| CheckType{Transaction type}
    CheckType -->|Buy| UpdateBuy[Update for buy transaction]
    CheckType -->|Sell| UpdateSell[Update for sell transaction]

    UpdateBuy --> GetTransactionTotal[Get current transaction_total]
    GetTransactionTotal --> GetForeignTotal[Get current foreign_total]
    GetForeignTotal --> AddLocal[Add amount_local to transaction_total]
    AddLocal --> AddForeign[Add amount_foreign to foreign_total]
    AddForeign --> UpdateTill[Update till balance]
    UpdateTill --> SetTransactionTotal[Set transaction_total]
    SetTransactionTotal --> SetForeignTotal[Set foreign_total]
    SetForeignTotal --> ReturnBuy[Return success]

    UpdateSell --> GetTransactionTotal2[Get current transaction_total]
    GetTransactionTotal2 --> GetForeignTotal2[Get current foreign_total]
    GetForeignTotal2 --> AddLocal2[Add amount_local to transaction_total]
    AddLocal2 --> SubtractForeign[Subtract amount_foreign from foreign_total]
    SubtractForeign --> UpdateTill2[Update till balance]
    UpdateTill2 --> SetTransactionTotal2[Set transaction_total]
    SetTransactionTotal2 --> SetForeignTotal2[Set foreign_total]
    SetForeignTotal2 --> ReturnSell[Return success]

    SkipUpdate --> End([No update needed])
    ReturnBuy --> End
    ReturnSell --> End
```

## Financial Reporting Flow

```mermaid
flowchart TD
    Start([Generate financial report]) --> SelectReport{Report type}
    SelectReport -->|Trial Balance| GenerateTrialBalance[Generate trial balance]
    SelectReport -->|P&L| GeneratePL[Generate profit & loss]
    SelectReport -->|Balance Sheet| GenerateBalanceSheet[Generate balance sheet]
    SelectReport -->|Cash Flow| GenerateCashFlow[Generate cash flow]

    GenerateTrialBalance --> GetPeriod[Get accounting period]
    GetPeriod --> FetchAccounts[Fetch all accounts]
    FetchAccounts --> ForEachAccount[For each account]
    ForEachAccount --> GetBalance[Get account balance]
    GetBalance --> CheckBalanceType{Balance type}
    CheckBalanceType -->|Debit| AddToDebit[Add to debit column]
    CheckBalanceType -->|Credit| AddToCredit[Add to credit column]
    AddToDebit --> NextAccount[Next account]
    AddToCredit --> NextAccount
    NextAccount -->|More| ForEachAccount
    NextAccount -->|Done| CalculateTotals[Calculate totals]
    CalculateTotals --> SumDebits[Sum debit column]
    SumDebits --> SumCredits[Sum credit column]
    SumCredits --> CheckBalanced{Balanced?}
    CheckBalanced -->|No| FlagImbalance[Flag imbalance]
    CheckBalanced -->|Yes| ReturnTrialBalance[Return trial balance]
    FlagImbalance --> ReturnTrialBalance
    ReturnTrialBalance --> End([Report generated])

    GeneratePL --> GetRevenueAccounts[Get revenue accounts]
    GetRevenueAccounts --> GetExpenseAccounts[Get expense accounts]
    GetExpenseAccounts --> CalculateRevenue[Calculate total revenue]
    CalculateRevenue --> CalculateExpenses[Calculate total expenses]
    CalculateExpenses --> CalculateGrossProfit[Calculate gross profit]
    CalculateGrossProfit --> CalculateNetProfit[Calculate net profit]
    CalculateNetProfit --> ReturnPL[Return P&L statement]
    ReturnPL --> End

    GenerateBalanceSheet --> GetAssetAccounts[Get asset accounts]
    GetAssetAccounts --> GetLiabilityAccounts[Get liability accounts]
    GetLiabilityAccounts --> GetEquityAccounts[Get equity accounts]
    GetEquityAccounts --> CalculateAssets[Calculate total assets]
    CalculateAssets --> CalculateLiabilities[Calculate total liabilities]
    CalculateLiabilities --> CalculateEquity[Calculate total equity]
    CalculateEquity --> CheckBalance{Assets = Liabilities + Equity?}
    CheckBalance -->|No| FlagBalanceError[Flag balance error]
    CheckBalance -->|Yes| ReturnBalanceSheet[Return balance sheet]
    FlagBalanceError --> ReturnBalanceSheet
    ReturnBalanceSheet --> End

    GenerateCashFlow --> GetOperatingActivities[Get operating activities]
    GetOperatingActivities --> GetInvestingActivities[Get investing activities]
    GetInvestingActivities --> GetFinancingActivities[Get financing activities]
    GetFinancingActivities --> CalculateNetCash[Calculate net cash flow]
    CalculateNetCash --> ReturnCashFlow[Return cash flow statement]
    ReturnCashFlow --> End
```

## Account Codes

```mermaid
flowchart TD
    Start([Account codes]) --> AssetAccounts[Asset accounts]
    AssetAccounts --> CashMYR[CASH_MYR - Cash in MYR<br/>1000 - Main house account]
    AssetAccounts --> BranchCashMYR[Branch MYR Cash<br/>1021 - BR001 | 1022 - BR002 | 1023 - BR003]
    AssetAccounts --> ForeignInventory[FOREIGN_CURRENCY_INVENTORY - Foreign currency inventory]
    AssetAccounts --> AccountsReceivable[ACCOUNTS_RECEIVABLE - Accounts receivable]

    Start --> LiabilityAccounts[Liability accounts]
    LiabilityAccounts --> AccountsPayable[ACCOUNTS_PAYABLE - Accounts payable]
    LiabilityAccounts --> AccruedExpenses[ACCRUED_EXPENSES - Accrued expenses]

    Start --> EquityAccounts[Equity accounts]
    EquityAccounts --> OwnerEquity[OWNER_EQUITY - Owner's equity]
    EquityAccounts --> PaidInCapital[PAID-IN CAPITAL - Paid-in capital<br/>3000]
    EquityAccounts --> RetainedEarnings[RETAINED_EARNINGS - Retained earnings]

    Start --> RevenueAccounts[Revenue accounts]
    RevenueAccounts --> ForexRevenue[FOREX_TRADING_REVENUE - Forex trading revenue]
    RevenueAccounts --> OtherRevenue[OTHER_REVENUE - Other revenue]

    Start --> ExpenseAccounts[Expense accounts]
    ExpenseAccounts --> ForexLoss[FOREX_LOSS - Forex loss]
    ExpenseAccounts --> OperatingExpenses[OPERATING_EXPENSES - Operating expenses]
    ExpenseAccounts --> OtherExpenses[OTHER_EXPENSES - Other expenses]
```

## Double-Entry Bookkeeping

```
Every transaction must balance:
Total Debits = Total Credits

Account Types:
- Asset accounts: Normal debit balance
- Liability accounts: Normal credit balance
- Equity accounts: Normal credit balance
- Revenue accounts: Normal credit balance
- Expense accounts: Normal debit balance

Balance Calculation:
- Debit accounts: New Balance = Old Balance + Debit - Credit
- Credit accounts: New Balance = Old Balance + Credit - Debit
```

## Capital & Branch Fund Allocation Flow

```mermaid
flowchart TD
    Start([Capital injection & branch allocation]) --> ValidatePeriod[Validate accounting period is open]
    ValidatePeriod --> CreateMainJE[Create main capital injection JE]
    CreateMainJE --> SetMainDebit[Debit: Cash-MYR 1000<br/>RM 1,000,000]
    SetMainDebit --> SetMainCredit[Credit: Paid-in Capital 3000<br/>RM 1,000,000]
    SetMainCredit --> MainJEPosted{Journal entry posted?}
    MainJEPosted -->|Yes| DistributeBranch[Distribute to each branch]
    MainJEPosted -->|No| ReturnError[Return error]

    DistributeBranch --> ForEachBranch[For each branch: BR001, BR002, BR003]
    ForEachBranch --> CreateBranchJE[Create branch allocation JE]
    CreateBranchJE --> SetBranchDebit[Debit: Branch Cash MYR<br/>1021/1022/1023<br/>RM 300,000 each]
    SetBranchDebit --> SetBranchCredit[Credit: Cash-MYR 1000<br/>RM 300,000]
    SetBranchCredit --> BranchJEPosted{Journal entry posted?}
    BranchJEPosted -->|Yes| UpdateLedger[Update account ledgers]
    BranchJEPosted -->|No| ReturnBranchError[Return error]
    UpdateLedger --> NextBranch[Next branch]
    NextBranch -->|More| CreateBranchJE
    NextBranch -->|Done| ReturnResult[Return allocation results]

    ReturnResult --> End([Capital injected:<br/>Main: Dr 1M to 1000<br/>Branches: Dr 300K each to 1021/1022/1023])

    ReturnError --> End
    ReturnBranchError --> End
```

## API Endpoints

| Endpoint | Method | Description | Auth Required |
|----------|--------|-------------|---------------|
| `/api/v1/accounting/journal-entries` | POST | Create journal entry | Manager, Admin |
| `/api/v1/accounting/journal-entries/{id}/reverse` | POST | Reverse journal entry | Manager, Admin |
| `/api/v1/accounting/journal-entries/{id}` | GET | Get journal entry | All roles |
| `/api/v1/accounting/journal-entries` | GET | List journal entries | All roles |
| `/api/v1/accounting/ledger/{account_code}` | GET | Get account ledger | All roles |
| `/api/v1/accounting/trial-balance` | GET | Get trial balance | Manager, Admin |
| `/api/v1/accounting/profit-loss` | GET | Get P&L statement | Manager, Admin |
| `/api/v1/accounting/balance-sheet` | GET | Get balance sheet | Manager, Admin |
| `/api/v1/accounting/cash-flow` | GET | Get cash flow statement | Manager, Admin |

## Key Services

| Service | Purpose |
|---------|---------|
| `AccountingService` | Journal entry creation and reversal |
| `LedgerService` | Ledger queries and financial reporting |
| `RevaluationService` | Monthly currency revaluation |
| `MathService` | BCMath precision calculations |
| `AuditService` | Audit logging |

## Account Code Enum

```php
enum AccountCode {
    // Asset accounts
    case CASH_MYR = '1000';                    // Main house MYR cash
    case BR001_CASH_MYR = '1021';              // BR001 branch MYR cash
    case BR002_CASH_MYR = '1022';              // BR002 branch MYR cash
    case BR003_CASH_MYR = '1023';              // BR003 branch MYR cash
    case FOREIGN_CURRENCY_INVENTORY = '1100';
    case ACCOUNTS_RECEIVABLE = '1200';
    // Liability accounts
    case ACCOUNTS_PAYABLE = '2000';
    case ACCRUED_EXPENSES = '2100';
    // Equity accounts
    case OWNER_EQUITY = '3000';
    case PAID_IN_CAPITAL = '3001';             // Paid-in capital
    case RETAINED_EARNINGS = '3100';
    // Revenue accounts
    case FOREX_TRADING_REVENUE = '4000';
    case OTHER_REVENUE = '4100';
    // Expense accounts
    case FOREX_LOSS = '5000';
    case OPERATING_EXPENSES = '5100';
    case OTHER_EXPENSES = '5200';
}
```

## Transaction Accounting Examples

### Buy Transaction
```
Debit:  FOREIGN_CURRENCY_INVENTORY  RM 10,000
Credit: CASH_MYR                     RM 10,000
```

### Sell Transaction (Gain)
```
Debit:  CASH_MYR                     RM 12,000
Credit: FOREIGN_CURRENCY_INVENTORY  RM 10,000 (cost basis)
Credit: FOREX_TRADING_REVENUE       RM 2,000 (gain)
```

### Sell Transaction (Loss)
```
Debit:  CASH_MYR                     RM 9,000
Debit:  FOREX_LOSS                   RM 1,000
Credit: FOREIGN_CURRENCY_INVENTORY  RM 10,000 (cost basis)
```

### Capital Injection (Paid-in Capital)
```
Debit:  CASH_MYR            RM 1,000,000
Credit: PAID-IN CAPITAL      RM 1,000,000
```

### Branch Fund Allocation
```
Debit:  BR001 Cash MYR (1021)  RM 300,000
Debit:  BR002 Cash MYR (1022)  RM 300,000
Debit:  BR003 Cash MYR (1023)  RM 300,000
Credit: CASH_MYR               RM 900,000
```

## Period Management

- **Open Period**: Journal entries can be created
- **Closed Period**: No journal entries allowed
- **Period Closing**: Finalizes period for reporting
- **Period Reopening**: Requires admin approval

## Precision

All monetary calculations use BCMath for precision:
- No floating-point arithmetic
- String-based calculations
- Configurable decimal precision (default: 4)
