# God Mode Lead Architect - Discovery Report

## Objective
Trace the root cause of the "network communication error" and missing module registration on the user dashboard.

## Technical Tracing
- **Frontend Submission:** `event_registration.php` lines 880-920 use JS `fetch()`. If the promise rejection occurs, it throws the specific "A network communication error occurred" alert.
- **Backend Handler:** `event_registration.php` lines 1-207. Parses `$_POST` and `$_FILES`. If the combined payload of up to 6 participant images (Face + ID) exceeds the PHP server's `post_max_size` (default 8MB), PHP dumps the `$_POST` array entirely. 
- **The Cascade Failure:**
  1. `$_POST` is empty.
  2. `getVal('user_id')` returns empty string -> cast to `(int) 0`.
  3. Form validation triggers a PHP Warning/Fatal Error before `ini_set('display_errors', 0)` takes effect, OR it outputs a non-JSON string.
  4. JS `res.json()` crashes. Network Error UI is shown.
  5. The database either fails to insert due to missing fields, or inserts with a `user_id` of `null` (found via DB introspection: rows exist with `user_id = null`).
- **Dashboard Visibility (`dashboard.php`):** The Arena Competitions table is populated by `SELECT * FROM event_registrations WHERE user_id = ?`. Since the inserted record has `user_id = null`, the query returns empty, causing the dashboard to hide the registration.

## Proposed Resolution Architecture
1. **Frontend Safety:** Implement Javascript client-side file size validation before `fetch()` is allowed to fire. Ensure total payload does not exceed 8MB.
2. **Backend Hardening:** Add a strict check in `event_registration.php` to verify `if (empty($_POST) && $_SERVER['CONTENT_LENGTH'] > 0)` which specifically catches `post_max_size` violations and returns a clean JSON error.
3. **Database Integrity Check:** Modify the SQL query or submission logic to guarantee `user_id` is passed correctly and rejects the payload natively if `user_id` is 0.

---
*Signed off by: godmode_lead_architect*
