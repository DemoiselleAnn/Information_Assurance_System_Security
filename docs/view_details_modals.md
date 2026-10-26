# Shared View Details modals

## Scout results

Inspected front-controller routes, the shared dashboard shell, PHP/HTML pages, JavaScript View triggers, and three-dot action menus before implementation. Both dashboard roles use the same page components.

| Page | View feature and result | Administrator access |
| --- | --- | --- |
| Account Management | Bottom Account Details panel converted. | Existing MANAGE_USERS permission. |
| Registration Approval | Existing View link had no details renderer. Added a read-only view using its table fields and USER-only query scope. | Existing APPROVE_REGISTRATIONS permission. |
| Blocked Accounts | Only Unblock exists; no View action. Left unchanged. | Existing account/block permissions. |
| Roles & Permissions | Read-only View Permissions converted. Edit Permissions remains unchanged. | Denied; Super Administrator-only page. |
| Security Logs | Already had an acceptable compact modal. Reused its design and moved behavior into the shared components. | Existing VIEW_SECURITY_LOGS permission. |
| Administrators | Bottom Administrator Details panel converted. | Denied; Super Administrator-only page. |
| Deletion Requests | Details and Complete Profile Information combined in one modal. Existing review forms and confirmations preserved. | Existing submit/review permissions and record scope. |
| My Profile, Password, System Settings | No applicable record-details View actions. Settings uses the existing access-restricted page. | Existing routing unchanged. |

Password visibility icons and profile navigation are not record-details actions. No applicable View action was left unconverted. Create/Edit, approval, blocking, deletion, and demotion business logic remain unchanged.

## Shared implementation

- php/record_details_modal.php: one native dialog per applicable page, title/record ID header, close control, and focusable scrolling body.
- css/record_details.css: the existing Security Logs blue-and-white design; width min(92vw, 720px), maximum height 85vh/85dvh, responsive compact cards, backdrop, and body-only scrolling.
- js/record_details.js: authorized same-origin requests, per-record template matching, loading/error states, stale-request cancellation, X/backdrop/Escape dismissal, focus trapping/restoration, three-dot menu integration, and background scroll locking.
- Each page retains its existing escaped fields in an inert template. Requests use existing authorized page routes without replacing the current dashboard, URL, filters, or pagination.
- Existing bookmarked View URLs open the same dialog. Former inline panels are no longer visible.
- Security Logs retains its authorized details-only response and field partial. Its dedicated dialog controller was removed to avoid duplicate handlers.
- Deletion review forms retain their existing actions, confirmations, tokens, and access checks. Canceled confirmations stay canceled before dashboard form navigation.

## Files added

- php/record_details_modal.php
- css/record_details.css
- js/record_details.js
- docs/view_details_modals.md

## Files modified

- php/account_management.php
- php/admin_management.php
- php/registration_approval.php
- php/deletion_requests.php
- php/roles_permissions.php
- php/security_logs.php
- php/superadmin_shell.php
- js/sidebar_scroll.js
- js/security_logs.js
- css/security_logs.css

## Verification

- PHP and JavaScript syntax checks passed for all changed source files.
- Headless Edge checks passed for all six Super Administrator View features and all four applicable Administrator features, plus denied responses for restricted pages.
- Browser fixtures compared rendered PHP templates against original-panel baselines: labels, values, badges, permission states, review forms, tokens, and confirmations matched.
- Checked desktop/mobile dimensions, long content, X/backdrop/Escape, keyboard focus, action menus, unchanged URL/search/scroll, single-dialog/request behavior, and bookmarked View compatibility.
- Checked closing while loading, stale-request cancellation, and denied-record responses without stale details.
- Existing Month/Date synchronization checks passed, including leap years, year navigation, Apply/Clear, filter submission, and pagination reset.
- Isolated PHP checks passed for dashboard role gates, denied permissions, deletion submission restrictions, and USER-only registration retrieval with validated parameters.
- Backend source comparisons confirmed existing filtering, pagination, retrieval, actions, and authorization unchanged. Registration Approval adds only its missing read-only lookup.

Verification used isolated browser fixtures and mocked PHP data access. Authenticated live-database end-to-end checks were not run. No database schema or stored records were changed.
