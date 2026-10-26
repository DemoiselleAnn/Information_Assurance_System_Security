(function () {
    'use strict';
    if (window.__frostlineNavigation) return;
    window.__frostlineNavigation = true;
    var dashboardPath = new URL('app.php', document.baseURI).pathname;
    var sectionSelector = '[data-scroll-context], section.management-panel, section.sl-card, section.mp-card, .mp-edit-panel, .individual-permissions';
    var sidebarStorageKey = 'superadmin-sidebar-scroll-top';
    var renderedUrl = new URL(window.location.href);
    var version = 0;
    var pendingGet = null;
    var restoring = false;
    var scrollFrame = 0;
    var filterKeys = ['q', 'role', 'status', 'month', 'date', 'log_type', 'page_no', 'sessions_page', 'p', 'notification_filter', 'notification_page'];

    function layout(doc) {
        var content = doc.querySelector('.dashboard-content');
        if (content) return { kind: 'management', content: content, sidebar: doc.querySelector('.sidebar') };
        content = doc.querySelector('.dashboard-main .content-wrap');
        return content ? { kind: 'member', content: content, sidebar: doc.querySelector('.sidebar') } : null;
    }
    function tabKey(url) {
        var page = url.searchParams.get('page');
        if (page === 'customer_dashboard') return url.searchParams.get('dashboard_view') || url.searchParams.get('view') || 'home';
        return page;
    }
    function isDashboardUrl(url) {
        return url.origin === window.location.origin && url.pathname === dashboardPath && url.searchParams.has('page')
            && !['home', 'login', 'register', 'logout', 'forgot_password', 'otp_verification', 'security_questions', 'change_password', 'first_login_otp', 'first_login_setup'].includes(url.searchParams.get('page'));
    }
    function storageRead(key) { try { return sessionStorage.getItem(key); } catch (error) { return null; } }
    function storageWrite(key, value) { try { sessionStorage.setItem(key, value); } catch (error) {} }
    function scrollRoot(content) {
        for (var el = content.parentElement; el && el !== document.body; el = el.parentElement) {
            if (/(auto|scroll)/.test(getComputedStyle(el).overflowY)) return el;
        }
        return document.scrollingElement || document.documentElement;
    }
    function viewportTop(root) { return root === document.scrollingElement ? 0 : root.getBoundingClientRect().top; }
    function inset(root) {
        var header = root === document.scrollingElement && document.querySelector('.top-header');
        return (header ? header.getBoundingClientRect().height : 0) + 16;
    }
    function sectionKey(section, content) {
        if (!section) return null;
        return section.id ? { id: section.id } : { index: Array.from(content.querySelectorAll(sectionSelector)).indexOf(section) };
    }
    function findSection(key, content) {
        if (!key) return null;
        var section = key.id ? document.getElementById(key.id) : content.querySelectorAll(sectionSelector)[key.index];
        return section && content.contains(section) ? section : null;
    }
    function capture(element) {
        var current = layout(document);
        if (!current) return null;
        var root = scrollRoot(current.content);
        var section = element && element.closest(sectionSelector);
        if (!section || !current.content.contains(section)) {
            section = Array.from(current.content.querySelectorAll(sectionSelector)).find(function (candidate) {
                var rect = candidate.getBoundingClientRect();
                return rect.bottom > viewportTop(root) + inset(root) && rect.top < viewportTop(root) + root.clientHeight;
            });
        }
        var client = {};
        ['account-search', 'account-role', 'account-status'].forEach(function (id) {
            var field = current.content.querySelector('#' + id);
            if (field && field.closest('#individual-accounts')) client[id] = field.value;
        });
        var page = current.content.querySelector('.account-pagination [aria-current="page"]');
        return { top: root.scrollTop, left: root.scrollLeft, section: sectionKey(section, current.content),
            offset: section ? viewportTop(root) - section.getBoundingClientRect().top : 0,
            client: client, clientPage: page ? Number(page.textContent) : 1 };
    }
    function remember(context) {
        if (restoring || window.location.pathname !== renderedUrl.pathname || window.location.search !== renderedUrl.search) return;
        context = context || capture();
        if (!context) return;
        var state = Object.assign({}, window.history.state, { frostlineNavigation: { url: window.location.href, context: context } });
        window.history.replaceState(state, '', window.location.href);
    }
    function restoreClient(context, content) {
        if (!context || !context.client) return;
        Object.keys(context.client).forEach(function (id) {
            var field = content.querySelector('#' + id);
            if (field && field.closest('#individual-accounts')) field.value = context.client[id];
        });
    }
    function restoreClientPage(context, content, refilter) {
        if (!context) return;
        var search = content.querySelector('#individual-accounts #account-search');
        if (refilter && search) search.dispatchEvent(new Event('input', { bubbles: true }));
        if (context.clientPage > 1) {
            var button = Array.from(content.querySelectorAll('.account-pagination button')).find(function (item) { return Number(item.textContent) === context.clientPage; });
            if (button) button.click();
        }
    }
    function initializeDashboardModules() {
        ['initializeActionMenus', 'initializeRecordDetails', 'initializeSecurityLogFilters', 'initializeSuperAdminDemotion',
            'initializeAccountManagement', 'initializeAdministrators', 'initializeMyProfile', 'initializeDeletionRequests',
            'initializeRolesPermissions', 'initializeNotifications', 'initializeAccountStatusPolling'].forEach(function (name) {
            if (typeof window[name] === 'function') window[name]();
        });
    }
    function updateActiveItems(incomingSidebar) {
        var incomingItems = Array.from(incomingSidebar.querySelectorAll('.nav-item, .sidebar-link'));
        document.querySelectorAll('.sidebar .nav-item, .sidebar .sidebar-link').forEach(function (item) {
            var incoming = incomingItems.find(function (candidate) { return candidate.getAttribute('href') === item.getAttribute('href'); });
            var active = !!incoming && incoming.classList.contains('active');
            item.classList.toggle('active', active);
            if (active) item.setAttribute('aria-current', 'page'); else item.removeAttribute('aria-current');
            var badge = item.querySelector('[data-sidebar-pending-slot]');
            var incomingBadge = incoming && incoming.querySelector('[data-sidebar-pending-slot]');
            if (incomingBadge) {
                if (badge) badge.replaceWith(incomingBadge.cloneNode(true)); else item.appendChild(incomingBadge.cloneNode(true));
            } else if (badge) badge.remove();
        });
    }
    function loadStyles(parsed, url) {
        var existing = new Set(Array.from(document.head.querySelectorAll('link[rel="stylesheet"]')).map(function (link) { return link.href; }));
        return Promise.all(Array.from(parsed.querySelectorAll('link[rel="stylesheet"][href]')).map(function (link) {
            var href = new URL(link.getAttribute('href'), url.href);
            if (href.origin !== url.origin || existing.has(href.href)) return Promise.resolve();
            existing.add(href.href);
            return new Promise(function (resolve, reject) {
                var added = document.createElement('link'); added.rel = 'stylesheet'; added.href = href.href;
                added.onload = resolve; added.onerror = reject; document.head.appendChild(added);
            });
        }));
    }
    function focusSection(section) {
        if (!section || document.querySelector('dialog[open], .delete-modal-backdrop.open')) return;
        var target = section.querySelector('[aria-current="page"], .page-number.active, .sl-page-link.active, h1, h2, h3') || section;
        if (!target.matches('a, button, input, select, textarea, [tabindex]')) target.setAttribute('tabindex', '-1');
        target.focus({ preventScroll: true });
    }
    function restorePosition(context, url, options) {
        var current = layout(document);
        var root = scrollRoot(current.content);
        var section;
        if (options.historyContext) context = options.historyContext;
        else {
            var error = current.content.querySelector('.management-alert.error, .mp-alert.error, .mp-alert-error, .error-message, [data-navigation-error]');
            if (error) section = error;
            if (!section && url.hash) { try { section = document.getElementById(decodeURIComponent(url.hash.slice(1))); } catch (error) {} }
            if (!section && url.searchParams.has('edit')) section = current.content.querySelector('#account-form, #administrator-form');
            if (!section && url.searchParams.get('mode') === 'edit') section = current.content.querySelector('#account-permissions');
            if (!section && options.cancel) section = current.content.querySelector('section.management-panel, section.sl-card');
        }
        var top = context ? context.top : 0;
        var left = context ? context.left : 0;
        if (section) top = root.scrollTop + section.getBoundingClientRect().top - viewportTop(root) - inset(root);
        else if (context) {
            section = findSection(context.section, current.content);
            if (section) {
                var offset = context.offset;
                if (offset > section.getBoundingClientRect().height - 80) offset = -inset(root);
                top = root.scrollTop + section.getBoundingClientRect().top - viewportTop(root) + offset;
            }
        }
        root.scrollTo({ top: Math.max(0, top), left: left, behavior: 'instant' });
        if (!options.historyContext && (options.focus || !context)) focusSection(section || current.content);
    }
    function frames() { return new Promise(function (resolve) { requestAnimationFrame(function () { requestAnimationFrame(resolve); }); }); }
    function carryFilters(url, source) {
        var result = new URL(url.href);
        if (tabKey(result) !== tabKey(source)) return result;
        filterKeys.forEach(function (key) { if (!result.searchParams.has(key) && source.searchParams.has(key)) result.searchParams.set(key, source.searchParams.get(key)); });
        return result;
    }
    function fallback(url, context) {
        if (context) storageWrite('frostline-navigation-return', JSON.stringify({ url: url.href, context: context, expires: Date.now() + 30000 }));
        window.location.assign(url.href);
    }
    function failure(form, status) {
        var current = layout(document);
        if (!current) return;
        var notice = current.content.querySelector('[data-navigation-error]');
        if (!notice) { notice = document.createElement('p'); notice.dataset.navigationError = ''; notice.className = 'management-alert error'; notice.setAttribute('role', 'alert'); current.content.prepend(notice); }
        notice.textContent = status ? 'The request was rejected (' + status + '). Refresh the page before trying again.'
            : 'The request could not be confirmed. Refresh to check the result before submitting again.';
        notice.setAttribute('tabindex', '-1'); notice.focus();
    }
    async function navigate(url, options) {
        options = options || {};
        var source = renderedUrl;
        var sameTab = tabKey(url) === tabKey(source);
        var context = options.historyContext || (sameTab ? capture(options.element) : null);
        var saved = capture(options.element);
        remember(saved);
        var request = ++version;
        if (pendingGet) pendingGet.abort();
        var controller = new AbortController();
        var method = options.method || 'GET';
        pendingGet = method === 'GET' ? controller : null;
        var current = layout(document);
        current.content.setAttribute('aria-busy', 'true');
        try {
            var response = await fetch(url.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' },
                method: method, body: options.body, signal: method === 'GET' ? controller.signal : undefined });
            if (request !== version) return;
            var destination = new URL(response.url);
            if (!isDashboardUrl(destination) || destination.searchParams.get('page') === 'profile_password') { fallback(destination); return; }
            // Follow the server's POST redirect with a safe GET only when it dropped list filters.
            if (method !== 'GET' && response.redirected && tabKey(destination) === tabKey(source)) {
                var filtered = carryFilters(destination, source);
                if (filtered.href !== destination.href) {
                    response = await fetch(filtered.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    destination = new URL(response.url);
                }
            }
            if (request !== version) return;
            if (!isDashboardUrl(destination)) { fallback(destination); return; }
            var text = await response.text();
            var parsed = new DOMParser().parseFromString(text, 'text/html');
            var incoming = layout(parsed);
            if ((!response.ok && response.status !== 422) || !incoming || incoming.kind !== current.kind || !incoming.sidebar) {
                if (!response.ok) failure(options.form, response.status); else fallback(destination, sameTab ? context : null);
                return;
            }
            await loadStyles(parsed, destination);
            if (request !== version) return;
            restoring = true;
            document.documentElement.classList.remove('sl-modal-open');
            current.content.replaceChildren.apply(current.content, Array.from(incoming.content.childNodes));
            if (current.kind === 'member') {
                document.querySelector('.dashboard-main').className = parsed.querySelector('.dashboard-main').className;
                document.querySelector('.dashboard-shell').className = parsed.querySelector('.dashboard-shell').className;
            }
            updateActiveItems(incoming.sidebar);
            document.title = parsed.title;
            var destinationContext = tabKey(destination) === tabKey(source) ? context : null;
            restoreClient(destinationContext, current.content);
            initializeDashboardModules();
            restoreClientPage(destinationContext, current.content, false);
            if (!options.popstate) {
                var state = Object.assign({}, window.history.state, { frostlineNavigation: null });
                window.history[options.replaceHistory || destination.href === window.location.href ? 'replaceState' : 'pushState'](state, '', destination.href);
            }
            renderedUrl = destination;
            if (document.fonts) await document.fonts.ready;
            await frames();
            if (request !== version) return;
            restorePosition(destinationContext, destination, options);
            restoring = false;
            remember();
        } catch (error) {
            if (error.name === 'AbortError' || request !== version) return;
            restoring = false;
            if (method === 'GET') fallback(url, context); else failure(options.form);
        } finally {
            if (request === version) { restoring = false; var active = layout(document); if (active) active.content.removeAttribute('aria-busy'); }
            if (options.form && options.form.isConnected) {
                delete options.form.dataset.ajaxSubmitting;
                (options.disabled || []).forEach(function (button) { button.disabled = false; button.removeAttribute('aria-busy'); });
            }
        }
    }
    function handleLinkClick(event) {
        var link = event.target.closest('a');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
            || link.target && link.target !== '_self' || link.hasAttribute('download') || link.closest('[data-record-view], [data-native-navigation]')) return;
        if (link.matches('[aria-disabled="true"], .disabled')) { event.preventDefault(); return; }
        var href = link.getAttribute('href');
        if (!href || href === '#') { if (href === '#') event.preventDefault(); return; }
        var url = new URL(href, document.baseURI);
        if (!isDashboardUrl(url) || url.searchParams.get('page') === 'profile_password' || link.closest('.logout')) return;
        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;
        var cancel = /cancel/i.test(link.textContent);
        if (tabKey(url) === tabKey(renderedUrl) && (cancel || url.searchParams.has('edit') || url.searchParams.get('mode') === 'edit')) url = carryFilters(url, renderedUrl);
        event.preventDefault();
        navigate(url, { element: link, cancel: cancel, focus: true });
    }
    function handleFormSubmit(event) {
        var form = event.target;
        var current = layout(document);
        if (event.defaultPrevented || !(form instanceof HTMLFormElement) || !current || !current.content.contains(form)
            || form.matches('[data-superadmin-demotion-form], [data-native-navigation]')) return;
        var submitter = event.submitter;
        var method = ((submitter && submitter.getAttribute('formmethod')) || form.getAttribute('method') || 'GET').toUpperCase();
        var target = (submitter && submitter.getAttribute('formtarget')) || form.target;
        if (!['GET', 'POST'].includes(method) || target && target !== '_self') return;
        var action = new URL((submitter && submitter.getAttribute('formaction')) || form.getAttribute('action') || window.location.href, document.baseURI);
        var data = new FormData(form);
        if (submitter && submitter.name) data.append(submitter.name, submitter.value);
        if (method === 'GET') action.search = new URLSearchParams(data).toString();
        if (!isDashboardUrl(action) || action.searchParams.get('page') === 'profile_password') return;
        event.preventDefault();
        if (form.dataset.ajaxSubmitting === 'true') return;
        form.dataset.ajaxSubmitting = 'true';
        var disabled = Array.from(form.elements).filter(function (button) { return button.type === 'submit' && !button.disabled; });
        disabled.forEach(function (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); });
        navigate(action, { method: method, body: method === 'GET' ? undefined : data, replaceHistory: method !== 'GET', element: form, form: form, disabled: disabled, focus: true });
    }
    function initializeScrollCoordinator() {
        var current = layout(document);
        if (!current || !current.sidebar) return;
        var sidebarNav = document.querySelector('.sidebar-nav');
        var savedSidebar = Number(storageRead(sidebarStorageKey));
        if (sidebarNav && Number.isFinite(savedSidebar)) sidebarNav.scrollTop = savedSidebar;
        if (sidebarNav) sidebarNav.addEventListener('scroll', function () { storageWrite(sidebarStorageKey, String(sidebarNav.scrollTop)); }, { passive: true });
        window.history.scrollRestoration = 'manual';
        var initial = window.history.state && window.history.state.frostlineNavigation;
        var returned;
        try { returned = JSON.parse(storageRead('frostline-navigation-return')); sessionStorage.removeItem('frostline-navigation-return'); } catch (error) {}
        var initialContext = initial && initial.url === window.location.href ? initial.context
            : returned && returned.url === window.location.href && returned.expires > Date.now() ? returned.context : null;
        document.addEventListener('click', handleLinkClick, false);
        document.addEventListener('submit', handleFormSubmit);
        document.addEventListener('scroll', function () {
            if (!restoring && !scrollFrame) scrollFrame = requestAnimationFrame(function () { scrollFrame = 0; remember(); });
        }, true);
        // Client-side filters/pagers may change without producing a scroll event or URL.
        function rememberClientChange(event) {
            var path = event.composedPath();
            if (path.some(function (node) { return node instanceof Element && node.id === 'individual-accounts'; })) remember();
        }
        document.addEventListener('input', rememberClientChange);
        document.addEventListener('change', rememberClientChange);
        document.addEventListener('click', rememberClientChange);
        window.addEventListener('beforeunload', function () { remember(); });
        window.addEventListener('pagehide', function () { remember(); });
        window.addEventListener('notifications:history-refresh', function () { navigate(new URL(window.location.href), { replaceHistory: true }); });
        window.addEventListener('popstate', function (event) {
            var state = event.state && event.state.frostlineNavigation;
            navigate(new URL(window.location.href), { popstate: true, historyContext: state && state.url === window.location.href ? state.context : null });
        });
        document.addEventListener('click', function (event) {
            var close = event.target.closest('[data-management-dialog-close]');
            if (!close) return;
            var dialog = close.closest('.management-dialog'); if (!dialog) return;
            event.preventDefault(); if (typeof dialog.close === 'function') dialog.close(); else dialog.removeAttribute('open');
        });
        // pageshow follows the browser's own form-value restoration on refresh.
        function restoreInitial() {
            Promise.resolve(document.fonts && document.fonts.ready).then(frames).then(function () {
                if (version !== 0) return;
                if (initialContext) {
                    restoring = true;
                    restoreClient(initialContext, current.content);
                    restoreClientPage(initialContext, current.content, true);
                    restorePosition(initialContext, new URL(window.location.href), { historyContext: initialContext });
                    restoring = false;
                }
                remember();
            });
        }
        if (document.readyState === 'complete') restoreInitial();
        else window.addEventListener('pageshow', restoreInitial, { once: true });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeScrollCoordinator);
    else initializeScrollCoordinator();
})();
