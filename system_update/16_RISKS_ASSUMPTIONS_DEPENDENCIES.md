# Risks, Assumptions, and Dependencies

## Overview

This document identifies potential risks, underlying assumptions, and external dependencies for the customer account system implementation. Use this as a reference during planning, implementation, and ongoing operations.

---

## Risk Register

### High Priority Risks

#### R1: SMS OTP Delivery Failures

| Attribute | Details |
|-----------|---------|
| **Risk** | Termii SMS fails to deliver OTP, blocking customer verification |
| **Probability** | Medium (10-20%) |
| **Impact** | High - Customers cannot complete checkout |
| **Mitigation** | Email OTP fallback always enabled |
| **Contingency** | Manual verification via admin if both fail |
| **Owner** | Development Team |

**Action Items:**
- [ ] Implement dual-channel OTP (SMS + Email always)
- [ ] Add "Resend OTP" button with countdown
- [ ] Log delivery status for monitoring
- [ ] Set up Termii balance alerts

---

#### R2: Database Migration Data Loss

| Attribute | Details |
|-----------|---------|
| **Risk** | Migration script corrupts or loses order/customer data |
| **Probability** | Low (< 5%) |
| **Impact** | Critical - Historical data lost |
| **Mitigation** | Full backup before every migration |
| **Contingency** | Restore from backup, re-run corrected migration |
| **Owner** | Development Team |

**Action Items:**
- [ ] Create backup script that runs automatically before migrations
- [ ] Test migrations on copy of production data first
- [ ] Document rollback procedure
- [ ] Keep 7 days of daily backups

---

#### R3: Session Hijacking

| Attribute | Details |
|-----------|---------|
| **Risk** | Attacker steals customer session token, gains account access |
| **Probability** | Low (< 5%) |
| **Impact** | High - Customer data/credentials exposed |
| **Mitigation** | Secure cookies, HTTPS only, device fingerprinting |
| **Contingency** | Session revocation, force password reset |
| **Owner** | Development Team |

**Action Items:**
- [ ] Enforce HTTPS for all pages
- [ ] Set cookie flags: Secure, HttpOnly, SameSite=Lax
- [ ] Implement device tracking
- [ ] Add "Logout All Devices" feature

---

#### R4: Checkout Conversion Drop

| Attribute | Details |
|-----------|---------|
| **Risk** | New auth flow creates friction, reducing conversions |
| **Probability** | Medium (10-20%) |
| **Impact** | High - Revenue decrease |
| **Mitigation** | Minimal steps, auto-fill for returning users |
| **Contingency** | A/B test, revert to simpler flow if needed |
| **Owner** | Product/Business |

**Action Items:**
- [ ] Track conversion rates before/after launch
- [ ] Keep auth steps to minimum (email → OTP → done)
- [ ] Pre-fill data for logged-in users
- [ ] Monitor cart abandonment at each step

---

### Medium Priority Risks

#### R5: Termii Service Outage

| Attribute | Details |
|-----------|---------|
| **Risk** | Termii API becomes unavailable |
| **Probability** | Low (< 5%) |
| **Impact** | Medium - SMS channel unavailable |
| **Mitigation** | Email fallback, retry logic |
| **Contingency** | Switch to backup SMS provider |
| **Owner** | Development Team |

**Action Items:**
- [ ] Implement circuit breaker for Termii calls
- [ ] Research backup SMS providers (Twilio, Africa's Talking)
- [ ] Email always as primary fallback

---

#### R6: Customer Support Overload

| Attribute | Details |
|-----------|---------|
| **Risk** | New ticket system overwhelms support capacity |
| **Probability** | Medium (15-25%) |
| **Impact** | Medium - Slow response times, unhappy customers |
| **Mitigation** | FAQ, self-service features, canned responses |
| **Contingency** | Prioritization system, hire temp support |
| **Owner** | Operations |

**Action Items:**
- [ ] Create comprehensive FAQ section
- [ ] Enable self-service credential recovery
- [ ] Set up auto-responses for common issues
- [ ] Define SLAs for ticket response

---

#### R7: Email Deliverability Issues

| Attribute | Details |
|-----------|---------|
| **Risk** | OTP/notification emails land in spam |
| **Probability** | Medium (10-20%) |
| **Impact** | Medium - Customers miss important emails |
| **Mitigation** | Proper SPF/DKIM/DMARC, reputable sender |
| **Contingency** | SMS as backup, whitelist instructions |
| **Owner** | Development Team |

**Action Items:**
- [ ] Configure SPF, DKIM, DMARC records
- [ ] Use reputable SMTP service
- [ ] Add "check spam folder" message in UI
- [ ] Monitor bounce/spam rates

---

#### R8: Brute Force Attacks

| Attribute | Details |
|-----------|---------|
| **Risk** | Attackers attempt to guess passwords/OTPs |
| **Probability** | High (likely attempts) |
| **Impact** | Medium - Account compromise if successful |
| **Mitigation** | Rate limiting, lockouts, CAPTCHA |
| **Contingency** | Block IP ranges, force password reset |
| **Owner** | Development Team |

**Action Items:**
- [ ] 5 failed logins = 15 minute lockout
- [ ] 3 OTP requests per hour per email
- [ ] 5 OTP verification attempts per code
- [ ] Log and alert on suspicious patterns

---

### Low Priority Risks

#### R9: Browser Compatibility Issues

| Attribute | Details |
|-----------|---------|
| **Risk** | Auth flow breaks on older browsers |
| **Probability** | Low (< 10%) |
| **Impact** | Low - Small user segment affected |
| **Mitigation** | Progressive enhancement, polyfills |
| **Contingency** | Provide fallback non-JS flow |
| **Owner** | Development Team |

---

#### R10: Mobile Responsiveness Problems

| Attribute | Details |
|-----------|---------|
| **Risk** | Dashboard/auth unusable on mobile |
| **Probability** | Low (< 10%) |
| **Impact** | Medium - Poor mobile experience |
| **Mitigation** | Mobile-first design, thorough testing |
| **Contingency** | Fix issues post-launch |
| **Owner** | Development Team |

---

## Risk Matrix

```
                    IMPACT
                Low    Medium    High    Critical
           ┌────────┬─────────┬────────┬──────────┐
    High   │        │   R8    │        │          │
           ├────────┼─────────┼────────┼──────────┤
P  Medium  │        │ R5,R6,R7│ R1,R4  │          │
R          ├────────┼─────────┼────────┼──────────┤
O  Low     │   R9   │   R10   │   R3   │    R2    │
B          ├────────┼─────────┼────────┼──────────┤
           │        │         │        │          │
           └────────┴─────────┴────────┴──────────┘
```

---

## Assumptions

### Technical Assumptions

| ID | Assumption | Validation Method | Risk if Invalid |
|----|------------|-------------------|-----------------|
| A1 | PHP 7.4+ available on production | Check `php -v` | Update PHP or refactor code |
| A2 | SQLite suitable for customer scale | Load testing | Migrate to PostgreSQL |
| A3 | Termii API stable and reliable | Monitor uptime | Add backup SMS provider |
| A4 | SMTP service has good deliverability | Test with major providers | Switch SMTP provider |
| A5 | HTTPS/SSL properly configured | Check certificate | Fix SSL configuration |
| A6 | Session storage sufficient | Monitor disk space | Configure external session storage |

### Business Assumptions

| ID | Assumption | Validation Method | Risk if Invalid |
|----|------------|-------------------|-----------------|
| A7 | Customers want accounts | Post-launch surveys | Simplify or make optional |
| A8 | OTP acceptable vs password-only | Conversion tracking | Offer password-first option |
| A9 | 12-month sessions acceptable | Security review | Reduce session length |
| A10 | Email is primary contact method | User research | Add WhatsApp notifications |
| A11 | Support tickets reduce WhatsApp load | Track support channels | Maintain WhatsApp support |

### Operational Assumptions

| ID | Assumption | Validation Method | Risk if Invalid |
|----|------------|-------------------|-----------------|
| A12 | Admin can handle customer management | Training feedback | Improve admin UI/tools |
| A13 | Current server handles increased load | Load testing | Scale infrastructure |
| A14 | Backup procedures adequate | Restore testing | Improve backup system |

---

## Dependencies

### External Service Dependencies

| Service | Purpose | Criticality | Fallback | SLA |
|---------|---------|-------------|----------|-----|
| **Termii** | SMS OTP delivery | High | Email OTP | 99.9% |
| **SMTP (PHPMailer)** | Email delivery | High | Queue and retry | Varies |
| **Paystack** | Payment processing | Critical | Manual bank transfer | 99.95% |
| **SSL Certificate** | HTTPS encryption | Critical | None - required | Auto-renew |

### Internal Dependencies

| Component | Depends On | Impact if Unavailable |
|-----------|------------|----------------------|
| Customer Auth | Database, Session | Complete auth failure |
| OTP System | Termii, SMTP, Database | Verification blocked |
| User Dashboard | Customer Auth, Database | Dashboard inaccessible |
| Checkout | Customer Auth, Paystack | Cannot complete orders |
| Admin Panel | Database, Session | Cannot manage customers |
| Delivery System | Database, Email | Delivery status unknown |

### Implementation Dependencies

| Phase | Depends On | Must Complete Before |
|-------|------------|---------------------|
| Phase 2 (Auth) | Phase 1 (Database) | Phase 3 |
| Phase 3 (API) | Phase 2 (Auth) | Phase 4 |
| Phase 4 (Dashboard) | Phase 3 (API) | Phase 5 |
| Phase 5 (Checkout) | Phase 4 (Dashboard) | Phase 6 |
| Phase 6 (Admin) | Phase 2 (Auth) | Independent |

### Dependency Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                     EXTERNAL SERVICES                        │
├──────────────┬──────────────┬──────────────┬────────────────┤
│   Termii     │    SMTP      │   Paystack   │  SSL/Domain    │
│   (SMS)      │   (Email)    │  (Payment)   │  (Security)    │
└──────┬───────┴──────┬───────┴──────┬───────┴────────────────┘
       │              │              │
       ▼              ▼              ▼
┌─────────────────────────────────────────────────────────────┐
│                     CORE SERVICES                            │
├──────────────┬──────────────┬──────────────┬────────────────┤
│  OTP System  │ Email Service│   Payment    │   Session      │
│              │              │   Handler    │   Manager      │
└──────┬───────┴──────┬───────┴──────┬───────┴───────┬────────┘
       │              │              │               │
       └──────────────┼──────────────┼───────────────┘
                      │              │
                      ▼              ▼
              ┌───────────────────────────┐
              │     CUSTOMER AUTH         │
              │   (Central Component)     │
              └─────────────┬─────────────┘
                            │
       ┌────────────────────┼────────────────────┐
       │                    │                    │
       ▼                    ▼                    ▼
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   Checkout  │    │  Dashboard  │    │ Admin Panel │
│    Flow     │    │    Pages    │    │   Updates   │
└─────────────┘    └─────────────┘    └─────────────┘
```

---

## Mitigation Strategies Summary

### Technical Mitigations

1. **Redundancy:** Email fallback for SMS, multiple retry attempts
2. **Graceful Degradation:** System continues with reduced features if service fails
3. **Monitoring:** Real-time alerts for critical failures
4. **Backups:** Daily automated backups with tested restore procedures
5. **Rate Limiting:** Prevent abuse of authentication endpoints

### Operational Mitigations

1. **Documentation:** All procedures documented for any team member
2. **Training:** Admin team trained on new features before launch
3. **Support:** Clear escalation paths for issues
4. **Communication:** Customer notifications for known issues

### Business Mitigations

1. **Phased Rollout:** Launch to subset of users first
2. **Metrics Tracking:** Monitor conversion rates closely
3. **Quick Revert:** Ability to disable new features if issues arise
4. **Customer Communication:** Proactive communication about changes

---

## Contingency Plans

### Plan A: Termii Complete Failure

**Trigger:** Termii API unreachable for > 30 minutes

**Actions:**
1. Disable SMS OTP sending (email only)
2. Update UI to show "Email verification only"
3. Monitor for restoration
4. Consider backup provider activation

### Plan B: Major Conversion Drop

**Trigger:** Checkout conversion drops > 20% after launch

**Actions:**
1. Analyze where users are dropping off
2. Simplify problematic steps
3. Consider optional account creation
4. A/B test alternatives

### Plan C: Security Breach Detected

**Trigger:** Evidence of unauthorized access

**Actions:**
1. Revoke all active sessions immediately
2. Force password reset for affected users
3. Notify affected customers
4. Investigate and patch vulnerability
5. Report as required by regulations

### Plan D: Database Corruption

**Trigger:** Database errors, missing data

**Actions:**
1. Enable maintenance mode
2. Restore from most recent backup
3. Replay any lost transactions manually
4. Investigate root cause
5. Implement additional safeguards

---

## Review Schedule

| Review Type | Frequency | Participants | Focus |
|-------------|-----------|--------------|-------|
| Risk Review | Monthly | Dev Lead, PM | Update risk register |
| Dependency Check | Quarterly | Dev Team | Verify external services |
| Assumption Validation | Post-Launch, then quarterly | All stakeholders | Validate assumptions |
| Incident Review | After each P1/P2 | All involved | Learn and prevent |

---

## Sign-Off

| Role | Name | Date | Signature |
|------|------|------|-----------|
| Project Lead | _____________ | _______ | _________ |
| Tech Lead | _____________ | _______ | _________ |
| Business Owner | _____________ | _______ | _________ |

---

## Related Documents

- [10_SECURITY.md](./10_SECURITY.md) - Security implementation details
- [12_IMPLEMENTATION_GUIDE.md](./12_IMPLEMENTATION_GUIDE.md) - Implementation phases
- [14_DEPLOYMENT_GUIDE.md](./14_DEPLOYMENT_GUIDE.md) - Deployment procedures
- [15_OPERATIONS_AND_MAINTENANCE.md](./15_OPERATIONS_AND_MAINTENANCE.md) - Ongoing operations
