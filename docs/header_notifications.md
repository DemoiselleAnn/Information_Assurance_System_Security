# Phase 2 — Header notifications and profile navigation

## Inspection and existing infrastructure

Inspected app.php, the shared superadmin_shell.php header/sidebar, authorization.php, session_bootstrap.php, superadmin_session.php, notification_status.php, notifications.js, sidebar_scroll.js, sidebar_pending_counts.php, My Profile routing, registration/deletion/administrator handlers, database schemas, and migration conventions. Both administrative dashboards use the same shell. The old bell endpoint only exposed the real Super Administrator takeover warning; there was no persistent inbox or read history.

## Persistent backend and migration

Added archive/sql/2026-10-26/add_notifications.sql and CLI-only cli/migrate_notifications.php. The additive CREATE TABLE IF NOT EXISTS migration has been applied to the local database. It adds only notifications; existing user, request, security-log, and permission tables/records are untouched. Deploy with: php cli/migrate_notifications.php --apply. Do not run the unrelated legacy migration for this feature.

The inbox stores a unique ID, recipient_user_id, duplicate-safe event_key, category, title, description, required_permission, allowlisted destination page, optional related record ID, creation time, and nullable read_at. Unique (recipient_user_id,event_key) prevents delivery retries or repeated pending synchronization from duplicating the same message. The recipient foreign key follows the existing users schema and removes inbox entries only if that recipient is deleted.

Current pending registrations and deletion requests seed genuine historical events for authorized recipients, using their actual record creation times. The same event keys are used by workflow hooks. Synchronization never resets an existing read_at value and never alters request statuses.

## Connected real events

- New USER registration: after the registration transaction commits, notify existing APPROVE_REGISTRATIONS recipients.
- Registration approval/rejection: notify authorized registration reviewers after the existing decision commits.
- New deletion request: notify APPROVE_DELETIONS reviewers.
- Deletion approval/rejection: notify authorized reviewers and the administrator who submitted the request, within their existing deletion-page access.
- Administrator creation/update in the Administrators workflow: use the existing successful security-log event ID as the duplicate-safe key; notify the affected administrator privately and authorized Super Administrator management recipients.
- Existing Super Administrator takeover warning: preserved as a private persistent security message, once per active session. No credential, raw session token, password, answer, or recovery data enters notification content.

Ordinary security-log entries do not generate notifications. No unblock-request notifications were invented because that workflow does not exist.

## Authorization and read state

Every API request rechecks the current database account, ADMIN/SUPER_ADMIN role, account status, completed setup, and existing Super Administrator session rules. Reads and updates are scoped to both the authenticated recipient and their current permission scopes. Role/permission revocations hide restricted messages; a recipient cannot access another inbox by changing an ID.

POST actions read and read_all require a session CSRF token. A single read affects one authorized notification; Mark All affects only currently authorized messages for that recipient. Read entries remain in history. Destinations are constructed server-side from a fixed page allowlist and current permissions; no arbitrary external URL is accepted. Related-record access continues through the destination page’s existing checks.

## Header and history UI

The bell opens a floating approximately 370px white panel with navy header accents, five newest notifications, title, brief description, timestamp, distinct read/unread styling, unread count, Mark All as Read, and View All Notifications. It closes on toggle, outside click/focus, Escape, or navigation. Escape restores bell focus. Empty and failure states are distinct. Untrusted text uses DOM textContent or PHP output escaping.

The bell badge is red/white, hidden at zero, capped at 99+, and retains the exact accessible unread count. Refreshes occur on initial load, existing dashboard navigation, opening the panel, read actions, and returning focus after a 30-second freshness threshold. The former seven-second bell timer was removed; no polling or new real-time service was added.

The authorized app.php?page=notifications route retains the dashboard shell and is not added to the sidebar. It has All/Unread/Read filtering, newest-first ordering with unique ID tie-breaking, ten messages per page, Previous/numbered/Next controls, current-page state, clamped page numbers, accurate record counts, and Mark All as Read.

The whole existing header profile block is now an anchor to app.php?page=my_profile, using existing dashboard navigation and the authenticated user’s existing profile implementation.

## Phase 1 separation

The sidebar count helper, sidebar badge styles, and approval logic were not modified. Sidebar badges still count pending workflow records. Bell counts only unread inbox messages. Reading or marking all notifications does not approve/reject registrations or deletion requests and does not change sidebar task counts. Existing navigation-response badge synchronization remains intact.

## Files added

- archive/sql/2026-10-26/add_notifications.sql
- cli/migrate_notifications.php
- php/notifications_store.php
- php/notifications_auth.php
- php/notifications.php
- css/notifications.css
- tests/notifications_integration_test.php
- tests/notifications_browser_fixtures.php
- docs/header_notifications.md

## Files modified

- php/notification_status.php
- php/app.php
- php/superadmin_shell.php
- php/process_register.php
- php/registration_approval.php
- php/deletion_requests.php
- php/admin_management.php
- js/notifications.js
- js/sidebar_scroll.js

## Verification

- PHP/JavaScript syntax checks passed for the changed sources and test utilities.
- Real MySQL integration tests ran against connection-local temporary copies of the relevant tables. Checked event delivery, recipient isolation, scope revocation, duplicate prevention, persistent read state, safe destinations, stable ten-row history pagination, filters, Mark All ownership, and unchanged Phase 1 counts. No fixture notifications or account/request/log mutations reached production tables.
- Direct API tests passed: invalid CSRF 419, another recipient’s ID 404, member role 403, unauthenticated 401, authorized read 200, authorized Mark All 200.
- Headless Edge checks passed for Administrator and Super Administrator headers: five newest messages, escaped text, read/unread styles, toggle/outside/Escape/navigation dismissal, View All, history filtering/pagination, read-count updates, 99+ count, profile navigation, empty states, mobile bounds, and unchanged sidebar task counts.
- Production migration application succeeded. Test data remained in temporary tables and disposable CLI sessions were destroyed.

## Operational limits

No background delivery worker or real-time push was introduced. Closed pages refresh inbox counts at the documented navigation/open/focus points. Existing pending requests are synchronized; already resolved pre-migration events are not fabricated or broadly backfilled. Workflow and API integration used isolated temporary data, while authenticated browser interactions used rendered fixtures rather than live production accounts.
