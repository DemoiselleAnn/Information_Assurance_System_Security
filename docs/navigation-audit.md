# FROSTLINE navigation and scroll audit

Date: 25 October 2026

## Confirmed causes

1. **Incorrect installation path:** sidebar_scroll.js built /php/app.php from the origin, while the actual application is /Cakedeco/php/app.php. Dashboard links therefore bypassed the existing AJAX handler and performed ordinary document navigation. The script now resolves app.php relative to document.baseURI; no installation name is hardcoded.
2. **No content-scroll context:** The old handler replaced the entire dashboard content without saving a table/section-relative position, handling short result pages, or choosing the correct scrolling element. Management pages scroll the document; the member layout uses an overflow-y:auto .dashboard-main panel. The shared handler now supports both.
3. **Incomplete history handling:** Every AJAX update pushed an empty history state; Back/Forward could create new entries and lacked saved positions. Refresh also lacked saved client-only pagination/filter state. Entries now retain the relevant section and position. Back/Forward uses the existing entry, and refresh restores after browser form restoration. Client-side controls save their state even when they produce no scroll event or URL change.
4. **Multi-table interference:** Security Events links did not retain the Login Sessions page. The events query string now retains sessions_page, and stable section IDs distinguish the filter panel and both tables.
5. **AJAX initialization gaps:** Administrator creation confirmation and profile controls existed only in inline scripts. Those scripts would not execute when content was replaced. Their existing behavior now lives in idempotent initializers, loaded by the shell once and called after navigation. Sensitive profile recovery stages retain native navigation.
6. **Unsafe interaction edges in the old handler:** Cancelled submissions were not explicitly excluded, FormData omitted the clicked submit button's name/value, and failures could fall through to a GET without explaining an uncertain POST result. The updated handler preserves cancellation, submitter data, method, body and CSRF fields. Unconfirmed POST failures are reported without replaying the action.

## Page audit

“Live” means an interaction was actually executed in the authenticated local Super Administrator browser. “Fixture” means a synthetic page using the production navigation scripts and corresponding layout CSS, with no database/authentication handlers. No Administrator/User account was impersonated or granted privileges for testing.

| Tab/page and roles | Problem found | Fix applied | Testing status |
| --- | --- | --- | --- |
| Account Management — Super Administrator/Administrator | Subdirectory links fell through to full navigation; edits/cancel could lose list context. | Shared routing, list-filter inheritance for Edit/Cancel, editor targeting, section restoration. | Live search, edit opening, cancel, filter retention, sidebar highlight and tab reset passed. Only 9 accounts existed, so real server pagination was unavailable; generic pagination covered by fixture/unit checks. No account was saved, blocked, promoted or deleted. |
| Registration Approval — Super Administrator/Administrator | Same shared navigation defect. Existing secure review workflow remains server-controlled. | Shared handler; modal View remains with the existing modal controller. | Live page opening/highlight/top reset passed; empty results meant no real pagination/review test. Search/approval/rejection remain source-audited, not live-tested. |
| Blocked Accounts — Super Administrator/Administrator | Same shared path/context defect. | Shared handler. | Live opening/highlight/top reset passed. Dataset had no multi-page results during testing. No unblock was performed; existing BLOCKED-to-INACTIVE workflow unchanged. |
| Roles & Permissions — Super Administrator | Edit links used server navigation; client filters/pages were not recorded when no scroll occurred. | Shared AJAX + client-view state capture, edit/hash targeting, restoration after pageshow. | Live client Next, search, edit opening, Back, and filtered page-2 refresh passed. CSS loaded and filters remained. No permission changes submitted. |
| Security Logs — all permitted roles | Shared path bug, no section-aware scroll, User inner scroll panel unsupported, events links reset the other table. | Shared handler plus stable filter/session/event IDs and independent page parameters; added accessible Previous/Next labels to events pager. | Live Super Administrator session Next (keyboard and pointer), numbered last page, Events Next, independent page preservation, Month/Date/Clear Filter, Back/Forward, and highlight passed. Short last session page kept its heading below the sticky header. User layout behavior verified in fixture; actual User authentication/filter scope not re-tested in-browser. |
| Administrators — Super Administrator | Shared path/context defect; inline creation confirmation would be inert after AJAX. | Idempotent external initializer preserves the original Super Administrator creation confirmation. | Live opening/highlight/styles/top reset passed. Confirmation cancellation and duplicate initialization passed offline. Actual creation/update/status changes not submitted. |
| Deletion Requests — Super Administrator/Administrator | Shared path/context defect; existing client account selector must reinitialize. | Existing exported initializer retained in module list; shared handling for server filters/pagers. | Live opening/highlight and existing three-page controls observed. Real pagination, request submission and decisions were not exercised before the test session ended. Source audit and generic pager/form fixtures cover the navigation plumbing only. |
| My Profile — Super Administrator/Administrator | Inline controls would stop working after AJAX; deep editor requires intentional scrolling. | External idempotent initializer; editor uses header-relative scroll margin and focus without a delayed focus jump. | Live opening/Edit/Cancel passed before final scroll-margin refinement; final 106px header-relative spacing and duplicate initialization covered offline. No profile was saved. |
| Notifications — Super Administrator/Administrator | Header link/history filters use the shared route; background history refresh must not add history entries or reset context. | Shared handler preserves section state; existing history-refresh event uses replace. Existing notification module is reinitialized. | Live bell menu/View All/notifications page passed; read/unread mutations and notification pagination not exercised. No sidebar item exists for this header-only destination. |
| Profile Password / recovery stages — applicable authenticated roles | Inline OTP/security-stage scripts need their normal page lifecycle. | Kept native navigation/POST for dedicated recovery handlers and profile_password; no generic script evaluation. | Source audit and unit verification that native authentication handlers are not intercepted. No password, OTP, or security answer was entered or changed. |
| User Home and Profile | Separate member shell had no shared navigation controller and uses an inner scrolling panel. | Attach the shared navigator plus existing details/date-picker modules; replace member content and update its view classes/sidebar. | Source audit; synthetic member fixture verified AJAX, correct inner scroll root, tab-top reset and active state. No live User session available. Train cards and placeholder controls were not turned into new features. |
| User Security Logs and password page | Same member-shell gap; permitted log/detail controls must initialize. | Member scripts initialized; server authorization/own-log scope unchanged. | Member fixture tested two independent pagers, short-page recovery, synthetic POST/submitter and error handling. Real account authorization and password mutation not re-tested live. |
| System Settings | Existing placeholder/unavailable route. | No feature added or authorization changed. | Source audit only; remains unavailable. |
| Public Home, Login, Register and registration preview | Normal entry/authentication transitions; registration uses its existing client step controls rather than dashboard pagination. | Unchanged. | Source audit. Existing native authentication/session redirects preserved; no new public-page AJAX interception. |
| Forgot Password, OTP, security questions, first-login setup, waiting/ended session pages | Security workflows intentionally use their own submission/redirect lifecycle. | Unchanged; explicitly excluded where relevant. | Source audit. Actual test session eventually ended and returned to Login through the existing guard. These flows were not bypassed or completed for testing. |
| Legacy profile/edit_profile | Older single-record pages remain in the router; no table pagination or matching dashboard shell. | Unchanged; intentional full submission/validation flows retained. | Source audit only; not presented as live verified. |
| Legacy pastry shop/cart files | Unrouted legacy files; cart includes an obsolete includes/header.php and has its own quantity reload logic. Direct PHP access is blocked by current Apache rules. | Not refactored or exposed as part of the active FROSTLINE dashboard. | Source audit only. Legacy functionality remains outside the active dashboard and needs a separately scoped decision if it is to be restored. |

## Files changed

| File | Purpose |
| --- | --- |
| js/sidebar_scroll.js | Correct installation-relative routing; shared document/member content navigation, section-relative scroll, shorter-result handling, history/refresh, assets/modules, filters, secure forms, request race/failure handling and native exclusions. |
| js/admin_management.js (new) | Reusable existing creation confirmation, protected against duplicate binding. |
| js/my_profile.js (new) | Reusable existing profile controls with visible editor/focus handling. |
| php/superadmin_shell.php | Load the two reusable modules for dynamic navigation. |
| php/customer_dashboard.php | Load the shared navigation and existing log/detail modules in the member shell. |
| php/security_logs.php | Stable section IDs, independent pager URL state and accessible event-pager labels. No query access/status logic changed. |
| php/admin_management.php | Replace inline-only initialization with the reusable module. Standalone include remains guarded; shell renders one module load. Backend account logic unchanged. |
| php/my_profile.php | Replace inline-only initialization with the reusable module. Standalone include remains guarded; backend profile/security logic unchanged. |
| tests/navigation_test.cjs (new) | Offline shared-navigation, scroll math, form-security, link and initializer regression checks. |
| tests/navigation_fixture/app.php (new) | Synthetic management/member pages and POST receipt; no database or authentication dependency. Apache continues to block direct test-PHP access. |
| tests/navigation_fixture/router.php (new) | Temporary localhost fixture server exposes only synthetic routes and public assets; application PHP handlers are not exposed. |
| docs/navigation-user-layout-proof.jpg (new) | Screenshot of a short final page remaining at the correct table in the synthetic User layout. |
| docs/navigation-audit.md (new) | This audit/report. |

Inspected but unchanged: app.php, superadmin.css, customer-dashboard.css, authorization/session/status helpers, JS date-picker/details/notification/client-pager modules, .htaccess, and database files. Previous Account Management, Roles & Permissions and Security Logs backend fixes remain.

## Behavior after the fix

- Same-tab GET actions update the existing shell, loading missing local styles before restoration and reinitializing known modules. Arbitrary inline scripts are not executed.
- Scroll is restored relative to the affected section in its actual scroll container. If fewer rows make the old position invalid, the heading is revealed instead. Edit/hash/error destinations intentionally receive focus/scroll context.
- Sidebar switches to another tab start at the top and keep sidebar scroll separately.
- Explicit anchors, modifiers/new tabs, downloads, modal View, disabled links, dialog forms, cancelled confirmations and dedicated security handlers retain their intended behavior.
- POST actions keep their method, FormData, CSRF fields and submitter. POST requests are never retried automatically. A server POST redirect that dropped list filters can be followed by a safe filtered GET. Authorization and CSRF remain entirely server-enforced.
- GET failure or incompatible shell can use normal document navigation with a short-lived, URL-specific position hint. Authentication redirects retain native navigation. HTTP errors/uncertain POST outcomes produce a visible message rather than replaying the mutation.
- Browser history records positions without pushing entries during Back/Forward; refreshing bookmarkable server pagination/filter URLs keeps server state and restores scroll where available.

## Executed checks

1. Node navigation regression suite passed: installation paths, both scroll roots, relative position, shortened sections, new-tab reset, filter inheritance, POST CSRF/submitter payloads, cancelled/native/dialog forms, duplicate submission protection, disabled/modal/native links, keyboard modifiers and idempotent Administrator/profile initialization.
2. JavaScript syntax passed for all three changed/new production scripts.
3. PHP syntax passed for all five changed production PHP files and both fixture PHP files.
4. Previous offline suites passed: account_management_status_test.php, roles_permissions_authorization_test.php and security_logs_regression_test.php (including 15 audit payloads).
5. Authenticated local Super Administrator browser checks are listed individually in the audit table. They used existing data and did not create, delete, block, unblock, promote, approve, grant permissions, save profiles or change credentials.
6. Real browser fixtures passed under /nested-install/php/app.php, proving no /Cakedeco hardcoding. The same document marker survived AJAX navigation. Member pagination changed .dashboard-main.scrollTop while document scroll remained zero. Short-page heading was at approximately 92px below the User header. Management fixture document scroll, Back/Forward and refresh passed.
7. Synthetic POST received its named submitter as save_fixture. The browser tool timed out around the confirmation interaction and did not allow reliable live cancellation observation, so cancellation is claimed only from the passing automated event/initializer tests. No real mutation was involved.
8. A synthetic HTTP 403 rendered an error without a document reload or POST replay.
9. With the navigation script omitted from the fixture, ordinary server pagination still reached page 2 through native document navigation. This was not a browser-wide JavaScript-disabled run.
10. No warning/error console entries were returned for the checked application/fixture tabs.
11. The temporary fixture server was stopped after testing. .htaccess was not weakened, and no database/schema tests or restructuring were performed in this task.

## Limits and remaining verification

- The live test session ended during later checks. Administrator and regular User authenticated end-to-end tests remain, as do production mutation workflows and pagers unavailable in the current small/empty datasets. Fixture coverage is not a claim that those role-specific backends were live tested.
- Public authentication/recovery flows, responsive device widths, network-offline fallback and full JavaScript-disabled browser behavior still need dedicated manual coverage. Existing security handlers were kept native and are not redesigned here.
- No migration, account-status change, CSS redesign or permission change was made. Unblocking remains BLOCKED to INACTIVE.
- The referenced IT 113 PDF was not available; this navigation change followed the requirements quoted in the task and preserved existing access rules.

## Re-run offline checks

Run node tests/navigation_test.cjs. Run the three existing PHP regression scripts listed above. For isolated browser checks, start the supplied fixture router bound to 127.0.0.1 only; it does not load application authentication or connect to MySQL. Do not remove Apache restrictions to expose test PHP files.
