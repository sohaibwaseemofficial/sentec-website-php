# Task Evidence: Fix Admin Registration Buttons & Numbering

**Date:** 2026-09-22  
**Sprint:** Hotfix  
**Agents Involved:** Orchestrator, Product Manager (scope check), Lead Architect (impact check), Backend Dev (implementation)

---

## Root Cause Analysis

### Bug #1 — Broken HTML (CRITICAL)
- **File:** `admin/manage_registrations.php`, Line 431
- **Issue:** Backslash-escaped quotes in HTML (`class=\"...\"`) produced invalid DOM
- **Impact:** All jQuery click handlers (.delete-btn, .action-btn, .confirm-pay-btn, .reject-pay-btn, .send-gatepass-btn, .email-team-btn) failed to bind. Only the Edit `<a href>` link was unaffected.
- **Fix:** Replaced `class=\"badge bg-dark border border-secondary ms-2\"` with `class="badge bg-dark border border-secondary ms-2"`

### Bug #2 — Missing Participant Query (MINOR)
- **File:** `admin/bulk_update_registration_status.php`, Lines 37-42
- **Issue:** SELECT query only fetched participant1-4 but email loop iterated 1-6
- **Impact:** Participants 5 & 6 silently missed bulk approve/reject emails
- **Fix:** Extended query to include `participant5_name, participant5_email, participant6_name, participant6_email`

### Bug #3 — No Sequential Row Numbering
- **File:** `admin/manage_registrations.php`
- **Issue:** No visible serial numbers on registration cards
- **Fix:** Added `$rowNum` counter (resets on page load) displaying `#1, #2, #3...` as a badge on each card

## Files Changed

| File | Change Type | Lines Modified |
|------|------------|----------------|
| `admin/manage_registrations.php` | Fix HTML + Add numbering | Lines 391, 393, 431 |
| `admin/bulk_update_registration_status.php` | Extend query | Lines 41-43 |

## What Was NOT Changed (Preserved)
- `admin/delete_registration.php` — Backend logic verified correct
- `admin/update_registration_status.php` — Backend logic verified correct
- `admin/update_payment_status.php` — Backend logic verified correct
- `admin/reject_payment.php` — Backend logic verified correct
- `admin/edit_registration.php` — Already working, untouched
- All email templates, mailer logic, and session handling — untouched

## Verification Checklist
- [ ] Delete button triggers confirm dialog and removes record
- [ ] Approve/Reject buttons update status and send emails
- [ ] Confirm/Reject Payment buttons work on submitted payments
- [ ] Send Gate Pass button triggers email
- [ ] Email Team button opens modal
- [ ] Row numbers show #1, #2, #3... sequentially
- [ ] Bulk approve/reject emails all 6 participants
