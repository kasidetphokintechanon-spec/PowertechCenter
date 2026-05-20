# PowertechCenter – Test Report (2026-01-16)

## 1. Environment

- OS: Windows 10 (XAMPP)
- Web server: Apache (XAMPP)
- PHP: XAMPP PHP CLI used for syntax checks
- Browser: Google Chrome 143.0.0.0
- Base URL: http://localhost/PowertechCenter/
- Test time window: 2026-01-16

## 2. Scope Covered

### 2.1 Core Web Pages (Smoke)

- index.html
- login.html
- request.html (IT repair request)
- log.html (IT staff workflow / ticket list)
- directory.html (contact list)
- admin.html
- my_tickets.html
- borrow_management.html / loan.html
- job_ticket.html
- kb.html
- dashboard.html
- smart_booking.html

### 2.2 API Endpoints (Smoke + Load Baseline)

- api.php?action=check_session
- api.php?action=get_pending_counts
- api.php?action=get&file=employees&status_filter=active
- api.php?action=get&file=it_logs

## 3. Automated Test Results

### 3.1 Smoke Test (Unauthenticated + Authenticated)

Result: PASS

Executed script:
- tools/smoke_test.ps1

Output summary:
- All listed pages returned HTTP 200
- check_session returned OK
- Authenticated scoping tests passed for PTA/PT4/PTE (user + staff)
  - Employees directory data was scoped to session company
  - User created ticket had company + requester_id enforced
  - Staff updated ticket status successfully

### 3.2 Load Baseline (Unauthenticated subset)

Result: PASS (baseline, not full stress)

Executed script:
- tools/load_test.ps1

Measured on localhost (50 requests per endpoint, concurrency=10):

- check_session: Avg 98.06ms, P95 112ms, Max 116ms
- get_pending_counts: Avg 103.52ms, P95 115ms, Max 121ms
- get employees(active): Avg 100.02ms, P95 114ms, Max 118ms
- get it_logs: Avg 99.12ms, P95 112ms, Max 120ms

## 4. Findings & Fixes Applied (Prioritized)

### 4.1 CRITICAL – Unauthorized modification risk (fixed)

Risk:
- Employee create/update endpoints had no server-side access control.
- Generic CRUD endpoints (add_item/update_item/delete_item) allowed unsafe operations if called directly.

Fixes:
- Enforced role checks (admin/staff) for:
  - api/actions/employee/add_employee.php
  - api/actions/employee/update_employee.php
  - api/actions/ticket/add_item.php (generic branch)
  - api/actions/ticket/update_item.php (generic branch and it_logs branch)
  - api/actions/ticket/delete_item.php

### 4.2 HIGH – Company data scoping (partially fixed server-side)

Risk:
- Tickets (it_logs) and employees could be exposed across companies if API is called directly.

Fixes:
- api/actions/employee/get.php
  - Enforced company scoping for employees for non-admin users (filter employee_assignments to session company).
  - Enforced it_logs scoping rules:
    - Non-admin: company must match session company.
    - Role user: only tickets where requester_id or servicedById == session user_id.
  - Prevented unauthenticated access to it_logs (returns empty array if not logged in).

### 4.3 HIGH – Cross-company update risk (fixed)

Risk:
- A staff user could potentially update a ticket by ID from another company.

Fix:
- api/actions/ticket/update_item.php now verifies the ticket’s company matches the session company for non-admin before updating, and prevents company rewriting.

## 5. Pending / Not Fully Verified

The following should be validated before go-live:

### 5.1 RBAC validation matrix (manual)

- User (PTA/PT4/PTE)
  - Create ticket (allowed)
  - View only own/company tickets (must be enforced)
  - Cannot update/delete ticket (must be denied)
- Staff (PTA/PT4/PTE)
  - View company tickets
  - Update ticket status/solution
  - Cannot access other company tickets by ID
- Admin
  - View and manage all companies

### 5.2 How to run authenticated automated tests (no password in logs)

Options:
- Provide PTC_TEST_PASSWORD via environment variable, or in .env.local (ignored by .gitignore)
- If neither is provided, tools/smoke_test.ps1 auto-generates a random password and seeds test users

Step 1: Seed test users (creates TST_* accounts)

- File: tools/seed_test_users.php
- Used by tools/smoke_test.ps1 automatically

Step 2: Run smoke tests with auth enabled

- File: tools/smoke_test.ps1

## 6. Reporting / Export Readiness

Status:
- Printing from log.html was improved for A4 (header/footer, margins, page breaks).
- Export capabilities require product decision:
  - Excel export: currently depends on existing implementation (not validated end-to-end without auth)
  - PDF export: can be supported via “Print to PDF” in browser, or via server-side PDF generation (not implemented)

Recommended next step:
- Implement server-side report endpoints returning:
  - XLSX (true Excel format)
  - PDF (A4 landscape) with fixed template

## 7. Security Notes for Go-Live

- Enable HTTPS (TLS 1.2+) at Apache/XAMPP level before production.
- Keep “test-only” scripts in tools/ folder and do not expose them publicly.
- Keep secrets out of repository; use environment variables:
  - PTC_TEST_PASSWORD (testing only)
  - PRINT_LOG_AES_KEY / PRINT_LOG_SECRET (print log encryption/signature)
- Consider adding CSRF protections for POST actions if the UI is accessible cross-origin.
