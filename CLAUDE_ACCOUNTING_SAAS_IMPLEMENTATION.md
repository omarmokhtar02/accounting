# Accounting SaaS — Claude Implementation Documentation

## 1. Project Summary

Build a production-ready, multi-tenant accounting and finance SaaS using:

- Laravel
- PHP
- Blade
- Tailwind CSS (only if already used by the project; otherwise keep presentation framework-agnostic)
- Domain-Driven Design (DDD)
- Modular Monolith architecture
- Relational database
- Queue/jobs/events where business workflows require them

The product is a complete Finance Operating System for companies, accountants, finance managers, CFOs, and auditors.

The system must cover accounting core first and grow into operational finance modules without coupling the domain to HTTP, Blade, ORM, or third-party integrations.

This document is an implementation guide for Claude Code. Treat it as the source of truth for architecture and engineering decisions unless an explicitly approved project decision supersedes it.

---

# 2. Primary Engineering Goals

1. Keep financial rules in the Domain layer.
2. Keep controllers thin.
3. Keep Blade focused on rendering and interaction.
4. Enforce tenant isolation at every data-access boundary.
5. Make accounting calculations deterministic and auditable.
6. Make modules independently extensible.
7. Prefer explicit workflows over hidden side effects.
8. Avoid unnecessary abstractions and speculative features.
9. Preserve backward compatibility when modifying an existing module.
10. Make every financial transaction traceable from report -> ledger -> source document.

---

# 3. Non-Negotiable Rules for Claude

## 3.1 Do not implement everything at once

Build in vertical slices. A slice must work end-to-end before moving to the next one.

Recommended order:

1. Tenant + Company foundation
2. Users/Roles/Permissions
3. Fiscal years + accounting periods
4. Chart of accounts
5. Journal engine + GL
6. AR + Customers
7. AP + Suppliers
8. Cash + Banks
9. Sales
10. Purchasing
11. Expenses
12. Inventory
13. Fixed Assets
14. Tax
15. Budgeting / Cost Centers / Projects
16. Financial reporting
17. Reconciliation center
18. Automation
19. AI assistant
20. Integrations

## 3.2 No business logic in Blade

Blade may:

- render data
- submit forms
- display validation/errors
- trigger routes/actions
- use simple presentation helpers

Blade must not:

- calculate accounting entries
- determine tax amounts
- calculate balances
- decide approval rules
- mutate domain state directly

## 3.3 No business logic in controllers

Controllers orchestrate input/output only.

Controller responsibilities:

- authorization
- request validation delegation
- invoke application use case
- map result to response

Controllers must not construct accounting rules inline.

## 3.4 No direct third-party integration inside Domain

Bank APIs, payment gateways, e-invoicing, OCR, AI, and messaging providers belong behind ports/interfaces.

## 3.5 No hidden financial side effects

A financial write must have an explicit application/domain flow.

Example:

`PostSalesInvoice` -> invoice posted -> accounting entry created -> AR updated -> audit event recorded.

Do not hide critical financial behavior in arbitrary model observers unless the behavior is explicitly part of the domain design and tested.

## 3.6 Never silently alter posted financial records

Posted accounting transactions are immutable from the business perspective.

Correction must use:

- reversal
- adjustment
- replacement workflow

rather than destructive edits.

## 3.7 Avoid broad refactors

When implementing a task:

- change the smallest safe surface
- preserve existing behavior
- do not rename unrelated classes
- do not introduce unrelated migrations
- do not change frontend behavior unless required
- do not add fake fallback data
- do not add database columns merely because they might be useful later

## 3.8 Design for future expansion

Where multiple business concepts are expected to grow, prefer extensible designs.

Examples:

- accounting dimensions
- tax rules
- workflow rules
- localization/compliance packs
- document sources
- integrations

Do not over-specialize the schema to one country or one industry.

---

# 4. Architecture Style

Use a **Modular Monolith + DDD** approach.

Do not split into microservices at the beginning.

The application should be deployable as one Laravel application while enforcing module boundaries internally.

High-level structure:

```text
Laravel Application
│
├── Domain
│   ├── Accounting
│   ├── Sales
│   ├── Purchasing
│   ├── Receivables
│   ├── Payables
│   ├── Banking
│   ├── Expenses
│   ├── Inventory
│   ├── FixedAssets
│   ├── Tax
│   ├── Budgeting
│   ├── CostAccounting
│   ├── Projects
│   ├── Consolidation
│   └── Shared
│
├── Application
│   ├── Commands / UseCases
│   ├── Queries
│   ├── DTOs
│   └── Services
│
├── Infrastructure
│   ├── Persistence
│   ├── Integrations
│   ├── Messaging
│   ├── Files
│   └── AI
│
└── Presentation
    ├── Http
    ├── Console
    └── Blade
```

Preferred practical Laravel layout:

```text
app/
├── Domain/
│   ├── Accounting/
│   ├── Sales/
│   ├── Purchasing/
│   ├── Receivables/
│   ├── Payables/
│   ├── Banking/
│   ├── Expenses/
│   ├── Inventory/
│   ├── FixedAssets/
│   ├── Tax/
│   ├── Budgeting/
│   ├── CostAccounting/
│   ├── Projects/
│   ├── Consolidation/
│   └── Shared/
│
├── Application/
│   ├── Accounting/
│   ├── Sales/
│   ├── Purchasing/
│   ├── Receivables/
│   ├── Payables/
│   ├── Banking/
│   ├── Expenses/
│   ├── Inventory/
│   ├── FixedAssets/
│   ├── Tax/
│   └── Reporting/
│
├── Infrastructure/
│   ├── Persistence/
│   ├── Tenancy/
│   ├── Integrations/
│   ├── AI/
│   └── Files/
│
└── Http/
    ├── Controllers/
    ├── Requests/
    └── Middleware/
```

Laravel-specific concerns may remain in Infrastructure/Presentation even when the core is DDD.

---

# 5. DDD Boundaries

Do not create one giant `AccountingService` or one giant `FinanceService`.

Each bounded context owns its language and rules.

## 5.1 Core Bounded Contexts

### Identity & Access

Owns:

- users
- roles
- permissions
- company membership
- branch access
- tenant access

### Tenancy

Owns:

- tenants
- tenant context
- tenant lifecycle
- tenant-scoped configuration

### Company

Owns:

- companies
- branches
- fiscal configuration
- localization settings

### Accounting

Owns:

- chart of accounts
- account types
- journal entries
- journal lines
- posting
- periods
- general ledger
- trial balance

### Receivables

Owns:

- customers
- invoices
- credit notes
- debit notes
- receipts
- allocations
- aging
- collections

### Payables

Owns:

- suppliers
- bills
- supplier credits/debits
- supplier payments
- allocations
- aging

### Sales

Owns operational sales flow:

- quotations
- sales orders
- deliveries
- sales invoices as a sales document

Accounting impact is delegated to Accounting/Receivables through explicit application/domain contracts.

### Purchasing

Owns:

- purchase requests
- RFQs
- supplier quotations
- purchase orders
- goods receipts

Accounting impact is delegated to Payables/Accounting.

### Banking

Owns:

- bank accounts
- bank transactions
- bank statements
- reconciliation
- transfers

### Expenses

Owns:

- employee expenses
- expense claims
- receipts
- reimbursement workflow

### Inventory

Owns:

- products
- warehouses
- stock movements
- batches
- serials
- valuation

### Fixed Assets

Owns:

- asset register
- acquisition
- depreciation
- disposal
- transfers

### Tax

Owns:

- tax codes
- tax rules
- tax transactions
- tax reporting
- compliance integrations

### Budgeting

Owns:

- budgets
- versions
- allocations
- forecasts
- variance

### Cost Accounting

Owns:

- cost centers
- departments
- dimensions
- allocations

### Projects

Owns:

- projects
- project costs
- time
- billing
- project profitability

### Consolidation

Owns:

- group structures
- intercompany relationships
- eliminations
- consolidated reporting

### Reporting

Read-oriented context responsible for assembling financial and management views from authoritative domain data.

---

# 6. Domain Layer Rules

The Domain layer must be framework-light.

Avoid depending directly on:

- `Illuminate\\Http`
- Blade
- Controllers
- Form Requests
- HTTP request/session state
- external APIs
- UI-specific DTOs

Eloquent may be used in Infrastructure persistence. Do not allow Eloquent concerns to define the business model by default.

Prefer domain objects for business invariants.

Example:

```php
final class Money
{
    public function __construct(
        private readonly int $minorAmount,
        private readonly CurrencyCode $currency,
    ) {}

    public function add(self $other): self
    {
        if (!$this->currency->equals($other->currency)) {
            throw new DifferentCurrencies();
        }

        return new self(
            $this->minorAmount + $other->minorAmount,
            $this->currency,
        );
    }
}
```

For accounting amounts, avoid floating-point arithmetic.

Use deterministic decimal/money handling.

---

# 7. Recommended Domain Building Blocks

Use only where meaningful:

- Entities
- Value Objects
- Aggregates
- Aggregate Roots
- Domain Services
- Domain Events
- Domain Exceptions
- Specifications / Policies
- Repository interfaces

Do not create empty interfaces or one-line wrappers purely to satisfy a pattern.

---

# 8. Aggregates

Potential aggregate roots:

- Tenant
- Company
- FiscalPeriod
- Account
- JournalEntry
- Customer
- Supplier
- SalesInvoice
- PurchaseBill
- BankAccount
- BankReconciliation
- ExpenseClaim
- Product
- Warehouse
- Asset
- Budget
- Project

Keep aggregate boundaries practical.

Do not load entire company financial history into one aggregate.

---

# 9. Repository Rules

Repositories belong to the Domain/Application contract side and implementations belong in Infrastructure.

Example:

```php
interface JournalEntryRepository
{
    public function nextNumber(CompanyId $companyId): JournalNumber;

    public function save(JournalEntry $journalEntry): void;

    public function findById(JournalEntryId $id): ?JournalEntry;
}
```

Infrastructure implementation may use Eloquent.

Do not expose Eloquent models throughout the domain API.

---

# 10. Application Layer

Application services/use cases represent user/business actions.

Examples:

```text
CreateCompany
CreateAccount
OpenFiscalPeriod
CreateJournalEntry
PostJournalEntry
ReverseJournalEntry
CreateCustomer
IssueSalesInvoice
ReceiveCustomerPayment
CreateSupplier
RecordPurchaseBill
PaySupplier
ImportBankStatement
ReconcileBankTransaction
CreateExpenseClaim
ApproveExpenseClaim
CloseAccountingPeriod
GenerateTrialBalance
```

A use case should:

1. receive explicit input
2. authorize the action
3. load required aggregates
4. invoke domain behavior
5. persist changes
6. publish required domain/integration events
7. return an explicit result

---

# 11. Transaction Boundaries

Financial operations must be atomic.

Example `PostSalesInvoice` transaction:

```text
BEGIN TRANSACTION

Load invoice
Validate state
Validate accounting period
Validate customer
Calculate accounting impact
Create AR transaction
Create GL journal
Mark invoice posted
Write audit entry

COMMIT
```

On any failure:

```text
ROLLBACK
```

Do not allow partial financial posting.

---

# 12. Accounting Engine

The accounting engine is the most critical component.

It must support:

- balanced journals
- posting
- reversal
- recurring journals
- adjusting entries
- accruals
- deferrals
- source references
- dimensions
- multi-currency
- tax lines
- period validation

Invariant:

```text
sum(debits) == sum(credits)
```

Prefer integer minor units or a precise decimal strategy. Never use PHP floating-point values for authoritative money calculations.

---

# 13. Source-to-Ledger Design

Every operational financial document must expose a clear accounting impact.

Example:

```text
SalesInvoice
   -> ReceivableEntry
   -> RevenueEntry
   -> TaxEntry
   -> optional COGS/Inventory entry
```

The source document must retain a stable reference to the generated accounting transaction.

Example concepts:

```text
source_type
source_id
```

Use a generalized source reference strategy so future modules can participate without redesigning the ledger.

---

# 14. Accounting Event Flow

Preferred pattern:

```text
User Action
    ↓
Application Command
    ↓
Domain Aggregate
    ↓
Domain Event
    ↓
Accounting Policy / Handler
    ↓
Journal Draft
    ↓
Posting Validation
    ↓
GL Post
```

Use domain events for meaningful business events, not for every trivial setter.

---

# 15. Multi-Tenancy Architecture

Multi-tenancy is a security boundary, not only a filtering feature.

Every tenant-owned record must be isolated.

Preferred logical model:

```text
Central / Landlord
├── tenants
├── tenant domains
├── subscription data
├── platform users
└── platform configuration

Tenant Context
└── Company financial data
    ├── accounts
    ├── journals
    ├── customers
    ├── suppliers
    ├── invoices
    ├── payments
    ├── inventory
    └── reports
```

## 15.1 Tenant Context

The application must resolve the current tenant before accessing tenant-owned business data.

Tenant context may come from:

- subdomain
- custom domain
- authenticated membership
- explicit tenant switch only where authorized

Do not trust a raw `tenant_id` from a browser request without verifying membership/context.

---

# 16. Tenant Isolation Strategy

For the initial implementation, choose one clear strategy and apply it consistently.

Preferred for this product if scale/compliance requirements justify it:

### Tenant database isolation

```text
Landlord DB
   ├── tenants
   ├── domains
   └── platform data

Tenant DB A
   └── Company A data

Tenant DB B
   └── Company B data
```

Benefits:

- stronger financial data isolation
- easier tenant export/restore
- simpler tenant-level backup boundaries
- lower risk of accidental cross-tenant queries

If project constraints require a shared database, every tenant-owned table must contain a non-null tenant key and every access path must enforce it centrally. Do not mix both strategies casually.

---

# 17. Tenant Middleware

Request lifecycle should conceptually be:

```text
Request
 ↓
Resolve Tenant
 ↓
Verify Tenant Status
 ↓
Initialize Tenant Context
 ↓
Authenticate User
 ↓
Verify Tenant Membership
 ↓
Authorize Resource
 ↓
Execute Use Case
 ↓
Response
```

If tenant resolution fails:

- do not execute business logic
- do not run tenant queries
- return a controlled response

---

# 18. Tenant-Aware Jobs

Queued jobs must explicitly carry tenant identity/context.

A job must restore tenant context before accessing tenant data.

Never assume the worker's current tenant context from a previous job.

Every tenant-scoped job should be safe against accidental execution under another tenant.

---

# 19. Tenant-Aware Commands

Artisan commands that process tenant data must iterate tenants explicitly or receive an explicit tenant identifier.

Avoid commands that accidentally process whichever tenant context happened to remain initialized.

---

# 20. Landlord vs Tenant Data

## Landlord

- tenants
- tenant domains
- subscription plans
- platform billing
- platform-level feature flags
- platform administration

## Tenant

- companies
- branches
- users/memberships as applicable
- accounts
- journals
- invoices
- suppliers
- customers
- payments
- inventory
- assets
- taxes
- budgets
- projects
- audit records

Crossing landlord/tenant boundaries must use explicit application services.

---

# 21. Company vs Tenant

Do not equate `tenant` with `company`.

A tenant represents the customer/account boundary of the SaaS.

A tenant may contain multiple companies.

```text
Tenant
 ├── Company A
 ├── Company B
 └── Company C
```

Financial records should belong to a company and be accessible only under a valid tenant/company context.

---

# 22. Company-Level Authorization

A user may have access to:

- all companies
- selected companies
- selected branches
- selected modules
- read-only finance
- operational-only permissions

Authorization must account for:

```text
Tenant
Company
Branch
Module
Action
Resource
```

---

# 23. RBAC

Roles should be permission-based.

Example roles:

```text
Owner
Admin
Accountant
Senior Accountant
Finance Manager
CFO
Auditor
Sales User
Purchasing User
Expense User
Viewer
```

Example permissions:

```text
accounts.view
accounts.create
accounts.update
accounts.archive
journals.view
journals.create
journals.post
journals.reverse
invoices.create
invoices.approve
invoices.post
payments.create
payments.approve
periods.close
reports.export
```

Permissions should be granular enough to support SoD.

---

# 24. Segregation of Duties

The authorization system must support policies such as:

```text
User A creates vendor
User B approves vendor

User A creates payment
User B approves payment

User A creates journal
User B posts journal
```

The product must support explicit policy exceptions for authorized administrators.

---

# 25. Blade Architecture

Preferred:

```text
resources/views/
├── layouts/
├── components/
├── shared/
├── auth/
├── dashboard/
├── accounting/
├── receivables/
├── payables/
├── sales/
├── purchasing/
├── banking/
├── expenses/
├── inventory/
├── assets/
├── tax/
├── budgeting/
└── reports/
```

Use Blade components for reusable presentation elements.

Examples:

```text
<x-page-header />
<x-data-table />
<x-form.input />
<x-form.select />
<x-money />
<x-status-badge />
<x-alert />
<x-pagination />
```

---

# 26. Blade Rules

Do:

- use view models/read DTOs for complex screens
- keep conditionals presentation-oriented
- keep formatting in presenters/components
- use route names
- use authorization directives where appropriate

Do not:

- call repositories directly from Blade
- execute queries in Blade
- calculate totals in Blade
- post accounting transactions from Blade
- embed domain decisions in templates

---

# 27. Controllers

Prefer one controller per resource/use-case area.

Example:

```text
Accounting/
  JournalEntryController
  AccountController
  FiscalPeriodController
```

For complex workflows, explicit action controllers are acceptable:

```text
PostJournalEntryController
ReverseJournalEntryController
ClosePeriodController
ReconcileBankTransactionController
```

Do not create a 2,000-line `AccountingController`.

---

# 28. Form Requests

Use Form Requests only for HTTP validation/normalization.

They may validate:

- required fields
- formatting
- primitive constraints
- authorization hooks

They should not own accounting business rules.

---

# 29. DTOs

Use explicit DTOs at Application boundaries when input/output is complex.

Example:

```php
final readonly class CreateJournalEntryData
{
    public function __construct(
        public CompanyId $companyId,
        public CarbonImmutable $date,
        public string $description,
        public array $lines,
    ) {}
}
```

Avoid passing entire HTTP Request objects into application/domain services.

---

# 30. Query Side

Separate write/use-case behavior from read-heavy reporting behavior where useful.

Reporting queries may use optimized read models/query builders and do not need to recreate domain aggregates for every row.

Example:

```text
Domain/Application Write Side
        |
        +---- authoritative financial data

Reporting Read Side
        |
        +---- trial balance
        +---- aging
        +---- dashboards
        +---- analytics
```

Do not introduce full CQRS/event sourcing unless actual complexity justifies it.

---

# 31. Reporting Rules

Reports must derive from authoritative posted data.

Do not keep independently editable balances that can diverge from the ledger.

Caching/materialized views are allowed as performance optimizations, but the ledger remains the source of truth.

---

# 32. Period Closing Rules

A period cannot close while blocking conditions exist.

Examples:

- unposted journals
- unresolved reconciliation differences
- missing depreciation
- failed tax processing
- configured mandatory checks

Closing must be an explicit use case and produce an auditable event.

---

# 33. Reversal Rules

A posted journal may be reversed by creating a new equal/opposite journal.

Maintain links:

```text
Original Journal
     ↕
Reversal Journal
```

Never destroy the historical posted record.

---

# 34. Document Numbering

Document sequences must be tenant/company aware and concurrency safe.

Examples:

```text
INV-2026-000001
BILL-2026-000001
JV-2026-000001
PAY-2026-000001
```

Generation must not rely on `count()` or race-prone application-side increments.

Use transactional/atomic sequencing appropriate for the database strategy.

---

# 35. Money & Currency

All financial values must preserve:

- transaction currency
- base/reporting currency where required
- exchange rate
- precision/scale

Never use floats for accounting calculations.

Create reusable value objects or a consistent money service.

---

# 36. Tax Engine

Tax must be configurable, not hard-coded.

Potential concepts:

```text
TaxAuthority
TaxCode
TaxRate
TaxRule
TaxCategory
TaxTransaction
TaxPeriod
```

Country-specific rules must be encapsulated behind localization/compliance strategies.

---

# 37. Localization

Use translation files and locale-aware formatting.

Support:

```text
ar
ar-EG
en
```

where required by the product.

Never hard-code Arabic/English labels in domain or controller code.

---

# 38. Date & Period Handling

Accounting dates and timestamps are different concepts.

Store domain dates intentionally.

Examples:

- document date
- posting date
- due date
- settlement date
- created_at
- approved_at

Do not derive accounting dates from UI-local timestamps without explicit rules.

---

# 39. Attachments

Attachments must be associated with domain documents through a generic document attachment abstraction.

Recommended metadata:

```text
id
owner_type
owner_id
file_name
mime_type
size
storage_key
uploaded_by
uploaded_at
```

Storage may be local in development and object storage in production.

Domain code should depend on an abstraction, not a specific filesystem driver.

---

# 40. Audit Trail

Audit must capture business-significant changes.

At minimum:

```text
actor
tenant
company
entity
entity_id
action
old_values
new_values
reason
occurred_at
```

Financial audit entries must not be editable by normal users.

---

# 41. Idempotency

Critical write operations must be idempotent where retries are possible.

Especially:

- payments
- webhook handling
- invoice posting
- bank imports
- e-invoice submission
- recurring jobs

Use a stable idempotency/reference key and unique constraints where appropriate.

Never solve duplicates using application-only `if exists` checks when a database constraint can provide the final guarantee.

---

# 42. Concurrency

Financial posting, numbering, reconciliation, and balance-affecting operations must account for concurrent requests.

Use:

- database transactions
- row-level locking where necessary
- unique indexes
- optimistic/pessimistic strategies when appropriate

Do not assume single-user execution.

---

# 43. Database Design Principles

1. Foreign keys where appropriate.
2. Unique constraints for business invariants.
3. Index tenant/company/date fields according to access patterns.
4. Avoid nullable foreign keys when a relationship is mandatory.
5. Use explicit status fields/enums or controlled value objects.
6. Never store financial balances only as manual fields without authoritative transaction history.
7. Use soft deletion only where business semantics permit it.
8. Never soft-delete authoritative posted ledger records as a mechanism for correction.

---

# 44. Example Core Tables

Actual schema can evolve, but the conceptual model should include:

```text
landlord/central
- tenants
- tenant_domains
- subscriptions
- platform_users

company/core
- companies
- branches
- fiscal_years
- accounting_periods
- currencies
- exchange_rates

accounting
- accounts
- journal_entries
- journal_lines
- journal_sources
- accounting_dimensions
- dimension_values

receivables
- customers
- customer_invoices
- customer_invoice_lines
- customer_payments
- customer_payment_allocations

payables
- suppliers
- supplier_bills
- supplier_bill_lines
- supplier_payments
- supplier_payment_allocations

banking
- bank_accounts
- bank_transactions
- bank_statements
- bank_reconciliations

sales
- quotations
- sales_orders
- deliveries

purchasing
- purchase_requests
- rfqs
- purchase_orders
- goods_receipts

expenses
- expense_claims
- expense_lines

inventory
- products
- warehouses
- stock_movements
- batches
- serial_numbers
- inventory_valuations

assets
- fixed_assets
- depreciation_entries
- asset_disposals

budgeting
- budgets
- budget_versions
- budget_lines

projects
- projects
- project_costs
- time_entries

compliance
- tax_codes
- tax_rates
- tax_transactions
- compliance_submissions

audit
- audit_logs
```

Do not create all tables before their module is implemented unless there is a clear dependency.

---

# 45. Accounting Dimensions

Use an extensible dimension approach.

Possible standard dimensions:

```text
Branch
Department
Cost Center
Project
Product
Customer
Supplier
Employee
```

Do not hard-code every possible future reporting dimension into every table if a generic dimension model can meet the business requirement cleanly.

However, do not make every relationship polymorphic by default. Use a practical schema with explicit typed relationships where they improve integrity/performance.

---

# 46. Status Machines

Statuses must represent valid state transitions.

Example Invoice:

```text
Draft
  ↓
Submitted
  ↓
Approved
  ↓
Posted
  ↓
Partially Paid
  ↓
Paid
```

Invalid transitions must be rejected by domain rules.

Do not implement status changes as arbitrary `$model->status = ...` throughout the codebase.

---

# 47. Policies vs Domain Rules

Use Laravel Policies for user authorization.

Use Domain rules/policies for business invariants.

Example:

```text
Laravel Policy:
"Can this user approve the invoice?"

Domain Rule:
"An invoice cannot be posted unless the accounting period is open."
```

Keep these responsibilities separate.

---

# 48. Events & Listeners

Good domain events:

```text
InvoiceIssued
InvoicePosted
PaymentReceived
JournalPosted
PeriodClosed
BankTransactionReconciled
ExpenseApproved
```

Use listeners for secondary concerns such as:

- notifications
- analytics refresh
- audit integration where appropriate
- webhook dispatch
- search indexing

Do not use events to hide critical synchronous financial posting steps.

---

# 49. Queues

Use queues for work that is safe to retry and not required to complete the current transaction synchronously.

Good examples:

- emails
- notifications
- report exports
- OCR processing
- AI analysis
- non-critical integrations
- large imports

Financial posting itself should complete transactionally unless an explicit eventual-consistency design has been approved.

---

# 50. Scheduled Jobs

Potential scheduled jobs:

```text
Generate recurring invoices
Generate depreciation
Generate accruals
Send payment reminders
Update forecasts
Detect anomalies
Process integration retries
Generate scheduled reports
```

Every scheduled action must be tenant-safe and idempotent.

---

# 51. AI Integration Boundary

AI belongs in Infrastructure/Application integration boundaries.

Example:

```text
Application
   ↓
AccountingAnalysisService interface
   ↓
AI Adapter
```

Never call an external AI SDK directly from:

- Blade
- Controller
- Domain Entity

AI suggestions must be reviewable for sensitive accounting actions.

---

# 52. Testing Strategy

Testing is mandatory for the accounting core.

## Unit Tests

Test:

- money arithmetic
- tax calculation
- journal balance
- state transitions
- period restrictions
- depreciation
- allocations
- currency rules

## Feature/Application Tests

Test complete workflows:

- create invoice
- post invoice
- receive payment
- create purchase bill
- pay supplier
- reconcile bank transaction
- close period

## Integration Tests

Test:

- database transactions
- tenant isolation
- external adapters
- file storage

## Security Tests

Explicitly test cross-tenant access attempts.

---

# 53. Required High-Value Tests

These cases must exist early:

### Tenant Isolation

User from Tenant A cannot read/write Tenant B records even when IDs are known.

### Journal Balance

Unbalanced journal cannot post.

### Closed Period

No posting into closed period.

### Double Payment

Same payment idempotency key cannot create two financial effects.

### Invoice Reversal

Reversal creates compensating entries and preserves original history.

### Concurrent Numbering

Parallel invoice creation does not duplicate sequence numbers.

### Cross-Company Access

User without Company B access cannot query Company B financial data.

---

# 54. Security Requirements

Minimum baseline:

- CSRF protection
- session security
- secure password hashing
- authorization on every protected action
- tenant isolation
- company/branch authorization
- rate limiting for sensitive endpoints
- validation of uploaded files
- signed URLs for protected downloads where applicable
- encrypted secrets
- secure webhook verification
- audit trail

Never expose internal IDs or tenant identifiers as proof of authorization.

---

# 55. File Upload Security

Validate:

- MIME type
- extension
- file size
- storage destination

Do not trust client-supplied filenames or content types.

Store files outside executable/public paths where possible.

---

# 56. API / Integration Boundary

Even if the first UI is Blade-only, define Application use cases independently from HTTP so APIs can be added later.

Example:

```text
Blade
  └──> Application Use Case

Future API
  └──> Application Use Case
```

Avoid duplicating business logic between web controllers and future API controllers.

---

# 57. UX Priorities

The product is for accountants, so common tasks must be fast.

Prioritize:

- keyboard-friendly data entry
- bulk operations
- advanced search/filter
- saved filters
- sticky table headers
- clear monetary formatting
- drill-down from reports
- fast journal entry creation
- fast bank matching
- clear validation messages

Use Arabic/English consistently.

---

# 58. Report Drill-Down Requirement

A user must be able to navigate:

```text
P&L
  -> Account
    -> Period
      -> Transactions
        -> Source Document
          -> Journal
            -> Journal Lines
```

This is a core product requirement, not an optional UX enhancement.

---

# 59. Error Handling

Domain exceptions should express business failures.

Examples:

```text
AccountingPeriodClosed
JournalNotBalanced
InvalidJournalState
InsufficientPermission
InvalidTenantContext
InvoiceAlreadyPosted
PaymentAlreadyProcessed
DuplicateExternalReference
```

Presentation should map these into understandable user messages.

Do not expose stack traces to users.

---

# 60. Logging

Application logs must help diagnose failures without leaking secrets or sensitive financial data unnecessarily.

Never log:

- passwords
- tokens
- secret API keys
- full payment credentials

For financial failures, include safe identifiers such as:

- tenant reference
- company reference
- document number
- job ID
- correlation ID

---

# 61. Observability

Every important request/workflow should be traceable with a correlation identifier.

For background jobs, retain:

- job ID
- tenant
- company
- operation
- status
- retry count
- failure reason

---

# 62. Deployment Considerations

The application should support separate environments:

```text
local
staging
production
```

Environment-specific values must remain outside committed source code.

Do not put tenant/customer secrets into Git.

---

# 63. Migration Rules

Migrations must be:

- backward-aware where feasible
- safe to run in CI
- reversible when practical
- scoped to the module being changed

Do not rewrite historical financial data through migrations unless a reviewed data migration explicitly requires it.

---

# 64. Seeders

Seed only safe reference/configuration data:

- system roles
- base permissions
- account categories
- common currencies if appropriate
- demo tenant data in development only

Do not seed fake production transactions.

---

# 65. Code Style

Follow existing project conventions first.

Use:

- strict typing where practical
- descriptive names
- small cohesive classes
- explicit return types
- immutable DTOs where useful
- domain methods instead of public mutable state

Avoid:

- static service abuse
- giant helper classes
- generic `Utils` classes
- `BaseService` as a dumping ground
- `if/else` trees for country logic
- controllers containing SQL/business workflows

---

# 66. Dependency Direction

Preferred direction:

```text
Presentation
    ↓
Application
    ↓
Domain

Infrastructure
    ↓ implements contracts used by Application/Domain
```

The Domain must not depend upward on Presentation.

Infrastructure adapters may depend on frameworks/vendors.

---

# 67. Module Communication

A module may communicate through:

1. application service/use case
2. domain event
3. explicit repository/query contract
4. shared value objects

Avoid reaching into another module's internal Eloquent models.

Bad:

```php
$invoice->supplier->account->journalEntries()->...
```

Preferred:

```php
$postSupplierBill->handle(...);
```

or an explicit query/read service when appropriate.

---

# 68. Shared Kernel

Keep Shared small.

Possible shared concepts:

- IDs
- Money
- Currency
- Date ranges
- Result/Error primitives where justified
- audit abstractions
- tenant context contract

Do not put business-specific logic into Shared simply because multiple modules use it.

---

# 69. Implementation Workflow for Claude Code

Before modifying code:

1. Inspect repository structure.
2. Identify existing conventions.
3. Locate the affected bounded context.
4. Trace current data flow.
5. Identify existing migrations/models/services/tests.
6. Determine whether the requested behavior already exists partially.
7. Write a concise implementation plan.
8. Identify exact files likely to change.
9. Implement the smallest coherent slice.
10. Add/update tests.
11. Run focused tests/static analysis.
12. Review diff for unrelated changes.

Do not start coding immediately after seeing a feature request.

---

# 70. Claude Planning Output Format

Before implementation, produce:

```text
## Understanding
<what the task actually requires>

## Existing Flow
<how the current code behaves>

## Root Cause / Gap
<what is missing or wrong>

## Proposed Design
<minimal design>

## Files to Change
<exact files>

## Data Changes
<only if required>

## Test Plan
<tests to add/update>

## Risks
<compatibility / migration / security concerns>
```

If the task is simple and the repository already has an established pattern, keep this short.

---

# 71. Claude Implementation Constraints

When implementing an approved task:

- follow existing naming conventions
- do not create parallel architecture unnecessarily
- reuse existing abstractions when they are sound
- do not refactor unrelated files
- do not introduce microservices
- do not add APIs when the task is Blade-only unless required
- do not add frontend frameworks when Blade is sufficient
- do not add new DB columns unless the requirement needs persistent state
- do not fabricate fallback records
- do not suppress failing tests to make the build green

---

# 72. Feature Definition of Done

A feature is not complete until:

- domain rules are implemented
- authorization exists
- tenant isolation is verified
- validation exists
- database constraints exist where appropriate
- happy path works
- failure paths are handled
- audit behavior is covered where relevant
- tests cover critical rules
- Blade UI works
- translations exist for user-facing text
- relevant reports/drill-downs remain consistent
- no unrelated regressions were introduced

---

# 73. Recommended First Epic

## Epic: Accounting Foundation

Implement this first:

```text
Tenant
 ↓
Company
 ↓
Fiscal Year
 ↓
Accounting Period
 ↓
Chart of Accounts
 ↓
Journal Entry
 ↓
Posting Engine
 ↓
General Ledger
 ↓
Trial Balance
 ↓
P&L
 ↓
Balance Sheet
```

Acceptance criteria:

1. Tenant is isolated.
2. User can create/select an authorized company.
3. User can configure fiscal year.
4. User can open/close periods.
5. User can create accounts.
6. User can create a balanced journal.
7. User can post it.
8. Posted journal updates ledger views.
9. Trial Balance balances.
10. P&L and Balance Sheet reflect the posted entries.
11. Reversal preserves original history.
12. Audit history is available.

---

# 74. Recommended Second Epic

## Epic: Receivables

```text
Customer
 ↓
Invoice
 ↓
Posting
 ↓
AR
 ↓
Payment
 ↓
Allocation
 ↓
Customer Statement
 ↓
Aging
```

Acceptance criteria:

- invoice posts correctly
- tax is correct
- AR balance changes correctly
- payment can be allocated
- partial payment supported
- overdue balances appear in aging
- journal and invoice remain traceable

---

# 75. Recommended Third Epic

## Epic: Payables

```text
Supplier
 ↓
Bill
 ↓
Posting
 ↓
AP
 ↓
Payment
 ↓
Allocation
 ↓
Supplier Statement
 ↓
Aging
```

---

# 76. Reporting Architecture

Reporting should be treated as a product surface, not random controller queries.

Recommended organization:

```text
Application/Reporting/
├── TrialBalance/
├── GeneralLedger/
├── ProfitAndLoss/
├── BalanceSheet/
├── CashFlow/
├── ReceivablesAging/
├── PayablesAging/
├── TaxReports/
└── Profitability/
```

Each report should define:

- filters
- dimensions
- columns
- calculations
- permissions
- export behavior

---

# 77. Financial Source of Truth

The source of truth hierarchy should be explicit:

```text
Operational Document
        ↓
Approved/Postable Business State
        ↓
Accounting Journal
        ↓
Ledger / Reporting Views
```

Do not allow dashboard totals to become independent financial truth.

---

# 78. Anti-Corruption Layer

Use anti-corruption adapters when integrating external systems.

Example:

```text
External E-Commerce API
        ↓
Adapter / Mapper
        ↓
Application DTO
        ↓
Domain Model
```

Never allow external payload shapes to leak into core domain objects.

---

# 79. Compliance Strategy

Country-specific accounting/tax/e-invoice behavior must be pluggable.

Example conceptual interface:

```php
interface ComplianceProvider
{
    public function validateInvoice(...): ComplianceResult;
    public function submitInvoice(...): SubmissionResult;
}
```

Implement providers per jurisdiction/integration instead of placing country conditions across the codebase.

---

# 80. Performance Priorities

Optimize only after measuring, but design for:

- paginated transaction lists
- indexed date/company/account searches
- efficient ledger queries
- aggregated report queries
- queued large exports
- queued large imports
- batched reconciliation

Never load all journal lines into PHP just to calculate a report that the database can aggregate safely.

---

# 81. Import/Export Architecture

Use import jobs/services with stages:

```text
Upload
 ↓
Validate Structure
 ↓
Map Columns
 ↓
Validate Business Rules
 ↓
Preview
 ↓
Commit
 ↓
Summary
```

Imports affecting financial state should support idempotency and clear failure reporting.

---

# 82. Search Architecture

Global search may index references/read models, but opening a result must still re-check tenant/company authorization.

Never treat search index visibility as an authorization mechanism.

---

# 83. Notifications

Notification generation belongs outside core domain entities.

Domain event:

```text
InvoiceBecameOverdue
```

Application/infrastructure handles:

```text
Email
Push
WhatsApp
In-App
```

---

# 84. Business Metrics vs Accounting Truth

The system may maintain derived business metrics, but each metric must document its calculation source.

Example:

```text
Gross Margin
= (Revenue - COGS) / Revenue
```

Metrics must not silently use unposted transactions unless explicitly labeled as operational/forecast figures.

---

# 85. Accounting Reconciliation Principle

Where two authoritative sources exist, provide a reconciliation process rather than silently selecting one.

Examples:

```text
Bank ↔ System
Inventory ↔ GL
AR subledger ↔ Control Account
AP subledger ↔ Control Account
Intercompany A ↔ Intercompany B
```

---

# 86. Subledger Principle

Operational modules can act as subledgers.

Examples:

```text
AR Subledger → Accounts Receivable control account
AP Subledger → Accounts Payable control account
Inventory Subledger → Inventory control account
Fixed Asset Subledger → Fixed Asset control accounts
```

The GL should remain the financial control layer.

---

# 87. Audit-Friendly Design

An auditor should be able to answer:

```text
Who created this?
Who approved it?
What document caused it?
What journal did it create?
What accounts changed?
What payment settled it?
Was it later reversed?
Who changed the related master data?
```

The architecture must preserve these relationships.

---

# 88. Master Data Governance

Master data such as:

- customers
- suppliers
- products
- accounts
- tax codes
- bank accounts

must have controlled lifecycle management.

Do not let users silently change critical master data after transactions depend on it.

Prefer:

```text
Draft / Active / Inactive / Archived
```

with history for sensitive changes.

---

# 89. Critical Database Constraints

At minimum, consider constraints for:

- unique document number within the correct scope
- unique external reference within tenant/company where relevant
- duplicate idempotency key prevention
- nonnegative sequence semantics
- valid foreign keys
- unique active mappings
- journal line integrity

Business-layer validation is not a substitute for database integrity.

---

# 90. What Claude Should Not Do

Do not:

- generate a giant generic `AccountingService`
- place every table in one module
- use polymorphic relations for everything
- use events for essential synchronous accounting writes without a deliberate consistency model
- store money in floating-point columns
- use UI values as accounting truth
- bypass tenant context
- trust `tenant_id` from request input
- hard-code one country's tax logic into every invoice class
- let Blade query the database directly
- create speculative tables for future modules
- introduce microservices just for architectural aesthetics
- replace existing stable patterns without need

---

# 91. Suggested Coding Convention

Naming examples:

```text
Domain:
  Invoice
  InvoiceLine
  JournalEntry
  Money
  CurrencyCode
  AccountingPeriod

Application:
  IssueSalesInvoice
  PostJournalEntry
  ReceiveCustomerPayment

Infrastructure:
  EloquentInvoiceRepository
  EloquentJournalEntryRepository
  BankApiClient
  OpenAiAccountingAnalyzer

Presentation:
  SalesInvoiceController
  PostSalesInvoiceController
```

Use verbs for actions/use cases and nouns for domain entities.

---

# 92. Suggested Folder Example

```text
app/Domain/Accounting/
├── Entities/
│   ├── Account.php
│   ├── JournalEntry.php
│   └── AccountingPeriod.php
├── ValueObjects/
│   ├── AccountCode.php
│   ├── CurrencyCode.php
│   └── JournalNumber.php
├── Events/
│   ├── JournalPosted.php
│   └── JournalReversed.php
├── Exceptions/
│   ├── JournalNotBalanced.php
│   └── AccountingPeriodClosed.php
├── Repositories/
│   └── JournalEntryRepository.php
└── Services/
    └── JournalPostingService.php

app/Application/Accounting/
├── Commands/
│   ├── CreateJournalEntry.php
│   ├── PostJournalEntry.php
│   └── ReverseJournalEntry.php
├── DTOs/
└── Queries/

app/Infrastructure/Persistence/Accounting/
├── Models/
├── Repositories/
└── Mappers/
```

Exact folders may be simplified if the existing repository has strong conventions. Consistency is more important than forcing this exact tree.

---

# 93. Implementation Milestones

## Milestone 1 — Foundation

- tenant context
- authentication
- company/branch
- RBAC
- localization
- audit foundation

## Milestone 2 — Accounting Core

- periods
- chart of accounts
- journal engine
- posting
- GL
- trial balance

## Milestone 3 — AR/AP

- customers
- suppliers
- invoices
- bills
- payments
- aging

## Milestone 4 — Banking & Cash

- bank accounts
- statements
- imports
- reconciliation
- transfers

## Milestone 5 — Operations

- sales
- purchasing
- expenses
- inventory
- fixed assets

## Milestone 6 — Finance Management

- taxes
- budgets
- cost centers
- projects
- cash flow
- forecasts

## Milestone 7 — Advanced

- consolidation
- compliance packs
- integrations
- AI

---

# 94. First Implementation Prompt for Claude Code

Use the following prompt when starting the project:

```text
You are implementing an accounting SaaS in Laravel using Blade, DDD, and a modular monolith architecture.

Read CLAUDE_ACCOUNTING_SAAS_IMPLEMENTATION.md before making changes.

Rules:
- Do not implement the whole product at once.
- Start with the approved milestone only.
- Inspect the existing repository before changing code.
- Follow existing project conventions where they do not conflict with the architecture.
- Keep controllers thin.
- Keep Blade free of business logic.
- Keep accounting rules in the Domain/Application layers.
- Enforce tenant isolation as a security boundary.
- Do not trust tenant/company IDs coming from the client without authorization.
- Do not use floating point for authoritative money calculations.
- Posted financial transactions must be immutable and corrected by reversal/adjustment workflows.
- Use DB constraints, transactions, and idempotency for financial integrity.
- Do not create speculative tables or abstractions.
- Do not make unrelated refactors.

Before coding, output:
1. Understanding
2. Existing flow
3. Gap/root cause
4. Proposed design
5. Files to change
6. Data changes
7. Test plan
8. Risks

Then implement only the approved scope.

After implementation:
- run focused tests
- run relevant static analysis if available
- inspect the final diff
- report changed files
- report tests executed and their results
- explicitly mention anything not verified
```

---

# 95. First Development Task

The first actual coding task should be the **Accounting Foundation vertical slice**:

```text
Tenant
 ↓
Company
 ↓
Fiscal Year
 ↓
Accounting Period
 ↓
Chart of Accounts
 ↓
Journal Entry
 ↓
Posting Engine
 ↓
General Ledger
 ↓
Trial Balance
 ↓
P&L
 ↓
Balance Sheet
```

Do not begin with inventory, payroll, AI, or integrations.

The ledger must be correct before building higher-level financial automation.
