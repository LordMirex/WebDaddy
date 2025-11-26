# WebDaddy Empire - 5-Phase Commission & Analytics Refactor Implementation Plan

## Executive Summary
The current system has critical data inconsistencies where affiliate commissions and revenue analytics are disconnected. After recent payment verification updates, the admin dashboard and analytics pages show conflicting data because different code paths track sales differently.

**Root Issues Identified:**
- Paystack payments update `pending_orders` table but don't create `sales` records with commissions
- Manual payments create `sales` records with commission tracking
- Admin dashboard queries `payments` table (legacy) instead of `sales` table (source of truth)
- Affiliate commission updates only happen on manual payment path, not Paystack path
- Analytics pages query wrong tables leading to incorrect revenue calculations
- No unified transaction handling between payment verification and commission processing

---

## Phase 1: Unify Payment Data Structure (Week 1)
**Goal:** Create single source of truth for all payment data

### Tasks:
1. **Audit Current Tables:**
   - Review: `pending_orders`, `sales`, `payments` tables
   - Identify duplicate data and conflicting information
   - Map all payment flows (Paystack, Manual, Failed)

2. **Create Payment Tracking Layer:**
   - Add comprehensive logging to track order → payment → commission flow
   - Document exactly where data diverges between Paystack and Manual

3. **Standardize Payment States:**
   - Define clear states: `pending` → `verified` → `commission_calculated` → `commission_credited` → `completed`
   - Add `commission_status` field to track commission processing separately from payment status

### Deliverables:
- Data audit report with recommendations
- Payment flow diagram showing current vs. proposed
- Test cases for payment state transitions

---

## Phase 2: Fix Commission Calculation & Crediting (Week 2)
**Goal:** Ensure commissions are calculated and credited for ALL payment methods

### Tasks:
1. **Create Unified Commission Processor:**
   - Function: `processOrderCommission($orderId)`
   - Called after payment verification (before response sent to customer)
   - Handles both Paystack AND Manual payments identically
   - Updates: `sales` table, `affiliates` table, `commission_log` table (new)

2. **Commission Calculation Logic:**
   ```
   - Get order details and affiliate info
   - Verify payment status is 'paid' (security check)
   - Calculate commission based on: order total, affiliate's custom rate, payment method
   - Check if commission already credited (prevent duplicates)
   - Update affiliate balance atomically
   - Log commission transaction with timestamps
   - Return commission record for audit trail
   ```

3. **Add Commission Log Table:**
   - Track every commission transaction (earn, pending, paid, adjusted)
   - Essential for affiliate reconciliation

### Deliverables:
- `processOrderCommission()` function fully tested
- Commission log tracking system
- Updated `api/paystack-verify.php` to call commission processor
- Updated `api/payment-failed.php` to handle commission on manual payments
- Unit tests for commission calculations

---

## Phase 3: Fix Admin Dashboard & Analytics (Week 3)
**Goal:** Display accurate, unified revenue and commission data

### Tasks:
1. **Create Revenue Query Layer:**
   - Function: `getTotalRevenueFromAllSources($dateRange)`
   - Query: `SELECT SUM(amount_paid) FROM sales WHERE payment_confirmed_at BETWEEN ? AND ?`
   - Replace all instances of querying `payments` table
   - Add breakdowns by: payment method, order type, date

2. **Create Commission Query Layer:**
   - Function: `getAffiliateCommissionStats($affiliateId = null)`
   - Returns: earned, pending, paid with date ranges
   - Used for both dashboard and affiliate profile

3. **Fix Admin Index Dashboard:**
   - Replace hard-coded queries with new query layer functions
   - Show metrics: Total Revenue (all), Paystack Revenue, Manual Revenue
   - Show: Pending Commissions, Paid Commissions, Commission Rate
   - Add verification: compare totals across tables for data integrity

4. **Fix Analytics Pages:**
   - `admin/analytics.php` - Use correct `sales` table
   - `admin/reports.php` - Add commission breakdown reports
   - Add date range filters to all reports
   - Add export functionality (CSV/Excel)

### Deliverables:
- Unified query layer in `includes/analytics.php` (new file)
- Updated `admin/index.php` with correct dashboard
- Updated `admin/analytics.php` with correct metrics
- Data validation dashboard showing data consistency checks
- Reports: Revenue by Method, Commissions by Affiliate, Payments vs. Orders reconciliation

---

## Phase 4: Affiliate Balance Synchronization & Auditing (Week 4)
**Goal:** Ensure affiliate balances are always accurate and reconcilable

### Tasks:
1. **Create Affiliate Balance Reconciliation:**
   - Function: `reconcileAffiliateBalance($affiliateId)`
   - Recalculates affiliate balance from `commission_log` table
   - Compares against stored balance in `affiliates` table
   - Flags and logs discrepancies
   - Auto-corrects if variance < threshold, escalates if > threshold

2. **Add Affiliate Balance History:**
   - Track all balance changes with reasons and audit trail
   - Essential for support when affiliates dispute commissions

3. **Create Affiliate Portal Pages:**
   - Dashboard showing: total earned, pending, paid, last payment date
   - Detailed commission ledger: date, order, amount, payment status
   - Payment method & bank details validation
   - Withdrawal request history

4. **Automated Audit Reports:**
   - Daily: Check all affiliate balances for consistency
   - Flag mismatches and send admin alert
   - Weekly: Send affiliate email showing their commission summary

### Deliverables:
- `reconcileAffiliateBalance()` function with audit logging
- `affiliate-dashboard.php` showing accurate balance
- Daily automated balance audit script
- Affiliate commission ledger page
- Email notifications for balance alerts

---

## Phase 5: Testing, Monitoring & Documentation (Week 5)
**Goal:** Ensure system reliability and maintainability

### Tasks:
1. **Comprehensive Testing:**
   - Unit tests: commission calculation edge cases
   - Integration tests: Paystack payment → commission flow
   - Integration tests: Manual payment → commission flow
   - Data consistency tests: reconcile all tables
   - Edge cases: multiple orders by same affiliate, custom commission rates, suspended affiliates

2. **Add Monitoring & Alerts:**
   - Alert if commission not credited within 1 hour of payment
   - Alert if affiliate balance discrepancy detected
   - Alert if payment processing fails
   - Dashboard showing system health: pending commissions, failed processes, audit issues

3. **Documentation:**
   - API documentation for payment/commission flow
   - Admin guide: understanding revenue and commission reports
   - Affiliate FAQ: how commissions are calculated
   - Troubleshooting guide for common commission issues
   - Database schema documentation with relationships

4. **Rollout Plan:**
   - Backup production database
   - Run balance reconciliation on all affiliates
   - Deploy with feature flag for new commission processor
   - Monitor for 48 hours before full rollout
   - Document any issues found during rollout

### Deliverables:
- Test suite (phpunit tests)
- Monitoring dashboard
- Admin documentation
- Affiliate documentation
- Release notes and rollout checklist

---

## Additional Issues Found (Not in Original Scope but Discovered):

### Critical:
1. **No Idempotency Check:** If payment verification is called twice, commission might be credited twice
   - Solution: Add unique constraint on (order_id, commission_type) in commission_log

2. **No Affiliate Verification:** Commissions credited to affiliate without validating account is active
   - Solution: Check affiliate.status = 'active' before crediting

3. **Order-Commission Linking:** No clear link between order_items and commission calculation
   - Solution: Store commission details (calculation formula, rate used) in commission_log

### High Priority:
4. **Pending Commission Aging:** No tracking of how long commissions stay pending
   - Solution: Add `pending_since` timestamp, create aging report

5. **Multi-Currency Support:** System assumes single currency
   - Solution: Add currency field to transactions if expanding internationally

### Medium Priority:
6. **Commission Rate Audit Trail:** Custom commission rates can be changed without recording old rate
   - Solution: Add rate history table

7. **Bulk Commission Actions:** No ability to bulk approve/reject/modify commissions
   - Solution: Add admin bulk actions UI

---

## Implementation Dependencies & Order:

```
Phase 1 ────→ Phase 2 ────→ Phase 3 ────→ Phase 4 ────→ Phase 5
(Data Audit) (Commission) (Analytics)  (Balance)    (Testing)
     ↓            ↓            ↓           ↓            ↓
   Schema      Processor    Dashboard   Audit Tool   Monitoring
   Review      Creation     Queries     Creation     & Docs
```

---

## Success Metrics:

- [ ] Revenue queries show 100% match between sales table and admin dashboard
- [ ] Affiliate balances match commission_log sum for all active affiliates
- [ ] No commission credited more than once per order
- [ ] All Paystack AND Manual payments credit commissions within 1 minute
- [ ] Affiliate portal shows accurate balance within 5 minutes of order payment
- [ ] Zero discrepancies in daily automated reconciliation
- [ ] Admin alerts trigger within 1 minute of commission issues
- [ ] Payment verification logs show commission processing status for every order

---

## Estimated Effort:
- **Phase 1:** 8 hours (audit, documentation)
- **Phase 2:** 16 hours (commission processor, testing)
- **Phase 3:** 12 hours (analytics rewrite, dashboard)
- **Phase 4:** 12 hours (reconciliation, portal)
- **Phase 5:** 12 hours (testing, monitoring, docs)

**Total: 60 hours (~1.5 weeks for one developer)**

---

## Risk Assessment:

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|-----------|
| Data loss during reconciliation | Low | Critical | Full DB backup before Phase 1 |
| Commission calculation error | Medium | High | Unit tests, then trial run on test data |
| Backward compatibility | Medium | Medium | Feature flags, gradual rollout |
| Performance impact | Low | Medium | Index optimization, query profiling |

---

## Questions for Product Team:

1. Should affiliate commissions be calculated immediately or batch processed nightly?
2. Should suspended affiliates have commissions on-hold or forfeited?
3. What's the SLA for commission payment (daily/weekly/monthly)?
4. Should affiliates be able to adjust commission rates in their dashboard?
5. Do you need commission tax reporting (1099 equivalents)?
