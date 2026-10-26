# Phase 1 — Sidebar pending-action badges

## Scout results

Both dashboard roles render their sidebar through php/superadmin_shell.php. Reviewed the sidebar routes, authorization helper, workflow handlers, SQL schema/migrations, asynchronous navigation, and the separate header notification implementation before editing.

| Sidebar tab inspected | Result |
| --- | --- |
| Registration Approval | Eligible: users with role USER and status PENDING require approval/rejection. |
| Deletion Requests | Eligible for reviewers: deletion_requests with status PENDING. |
| Blocked Accounts | No unblock-request or appeal workflow exists; blocked records alone do not receive a badge. |
| Account Management | No separate actionable request queue; no badge. |
| Roles & Permissions | Permission editing is immediate; no approval queue or badge. |
| Security Logs | Informational records; no badge. |
| Administrators | Account creation/setup and management do not form an approval queue; no badge. |
| System Settings | Existing restricted placeholder; no badge. |
| My Profile, Change Password, Logout | No pending administrative review queue; unchanged. |

Only these two pending workflows are implemented in the current dashboard. Legacy schema request tables have no active dashboard processing routes, so no badges were added for them. No eligible implemented workflow was left unimplemented.

## Tables, statuses, and permissions

- Registration count: SELECT COUNT(*) FROM users WHERE role = USER AND status = PENDING, with both values bound as parameters. Matches the registration handler and default pending list. Approved registrations become INACTIVE; rejected registrations become REJECTED and are excluded.
- Deletion count: SELECT COUNT(*) FROM deletion_requests WHERE status = PENDING, with status bound as a parameter. Review processing writes APPROVED or REJECTED. Direct account deletions write APPROVED rows; these are excluded.
- Registration badge requires an ADMIN or SUPER_ADMIN dashboard role and existing APPROVE_REGISTRATIONS permission.
- Deletion badge requires an ADMIN or SUPER_ADMIN dashboard role and existing APPROVE_DELETIONS permission. Submit-only administrators see no review badge, even on their own pending submissions.
- Super Administrators retain their existing full-permission behavior. Default Administrators see registrations; Administrators explicitly granted deletion review also see deletion badges. Existing user-specific permission revocations are honored.
- Unauthorized workflows are not counted or embedded in badge markup. USER/member roles receive no counts.

## Update mechanism and styling

Counts are calculated server-side for each completed dashboard response, after the current page handler has run. Full loads/reloads show current counts. Existing asynchronous navigation and administrative form submissions already consume the resulting dashboard response; sidebar_scroll.js now copies the corresponding badge slots from that response into the current sidebar. There is no new endpoint, polling timer, real-time service, or badge-specific background request.

Navigation items are matched by route rather than position, preventing a changed permission list from copying another section’s count. Missing/unauthorized badge slots are removed. Existing sidebar nodes, icons, labels, grouping, routes, active styling, and scroll state remain intact.

Badges are compact red pills with white text, capped at 99+. The exact number is retained in the accessible label. Zero counts render an empty hidden badge in a reserved slot, keeping row dimensions stable without a visible zero or red circle. Count-query errors suppress only the affected badge and log a generic diagnostic.

The header bell markup, notifications.js, and notification_status.php were not changed. Phase 2 dropdown/page functionality was not added.

## Files

Added:
- php/sidebar_pending_counts.php — permission-aware COUNT queries and escaped badge rendering.
- tests/sidebar_pending_counts_test.php — isolated count, permission, formatting, and failure checks.
- docs/sidebar_notification_badges.md — this report.

Modified:
- php/superadmin_shell.php — calculate and render authorized sidebar badge counts.
- js/sidebar_scroll.js — synchronize badge slots from existing navigation/action responses.
- css/superadmin.css — scoped badge design, alignment, and reserved slot dimensions.

## Verification

- The real PHP helper passed isolated PDO tests for pending-only counts, exclusion of resolved/non-USER registrations, Super Administrator/default Administrator/reviewer/revoked/member cases, zero/99+ formatting, label escaping, efficient count-only queries, processed-count refresh, and isolated database failures.
- PHP syntax passed for the new helper, test, and modified shell; JavaScript syntax passed for sidebar_scroll.js.
- Headless Edge tests used real PHP-rendered sidebar markup with mocked dashboard responses. Passed 3 → 2 → hidden updates after successful asynchronous processing, large-count display, route-based navigation, added/revoked review permission visibility, no duplicate badges, no added polling, active/mobile alignment, and stable row size when badges disappear.
- Compared the old/new header bell markup and verified it remained unchanged during browser navigation.
- No schema, workflow/status handling, permission definitions, stored records, or header-notification source files were modified.

Testing used isolated fixtures and mocked database access; authenticated live-database approval/rejection testing was not performed.
