(function () {
    'use strict';

    const menus = new Set();

    function initializeActionCells() {
        document.querySelectorAll('.action-cell').forEach(function (cell) {
            if (cell.querySelector('.row-action-menu')) return;

            // Keep the modal outside the popover's pointer and positioning handlers.
            cell.querySelectorAll('.superadmin-demotion-dialog').forEach(function (dialog) {
                (cell.closest('.management-page') || document.body).appendChild(dialog);
            });

            const menu = document.createElement('div');
            menu.className = 'row-action-menu';
            const trigger = document.createElement('button');
            trigger.className = 'row-action-trigger';
            trigger.type = 'button';
            trigger.setAttribute('aria-label', 'Actions');
            trigger.setAttribute('aria-expanded', 'false');
            const icon = document.createElement('i');
            icon.className = 'fas fa-ellipsis-v';
            icon.setAttribute('aria-hidden', 'true');
            trigger.appendChild(icon);

            const panel = document.createElement('div');
            panel.className = 'row-action-popover';
            panel.setAttribute('role', 'menu');
            panel.setAttribute('aria-hidden', 'true');
            const entry = document.createElement('div');
            entry.className = 'row-action-entry';
            entry.setAttribute('role', 'none');
            while (cell.firstChild) entry.appendChild(cell.firstChild);
            panel.appendChild(entry);
            menu.appendChild(trigger);
            menu.appendChild(panel);
            cell.appendChild(menu);
        });
    }

    window.initializeActionMenus = initializeActionCells;

    function getPanel(menu) {
        return menu.querySelector('.row-action-popover');
    }

    function positionPanel(menu) {
        const trigger = menu.querySelector('.row-action-trigger');
        const panel = getPanel(menu);
        if (!trigger || !panel) return;

        panel.style.display = 'block';
        panel.style.visibility = 'hidden';
        panel.style.left = '0px';
        panel.style.top = '0px';

        const triggerRect = trigger.getBoundingClientRect();
        const panelRect = panel.getBoundingClientRect();
        const edge = 8;
        const gap = 6;
        let left = triggerRect.right - panelRect.width;
        let top = triggerRect.bottom + gap;

        if (top + panelRect.height > window.innerHeight - edge && triggerRect.top - gap - panelRect.height >= edge) {
            top = triggerRect.top - gap - panelRect.height;
        }
        left = Math.max(edge, Math.min(left, window.innerWidth - panelRect.width - edge));
        top = Math.max(edge, Math.min(top, window.innerHeight - panelRect.height - edge));

        panel.style.left = left + 'px';
        panel.style.top = top + 'px';
        panel.style.visibility = 'visible';
    }

    function closeMenu(menu) {
        const panel = getPanel(menu);
        const trigger = menu.querySelector('.row-action-trigger');
        if (!panel || !trigger) return;
        menu.classList.remove('is-click-open', 'is-hover-open');
        panel.style.display = '';
        panel.style.visibility = '';
        panel.style.left = '';
        panel.style.top = '';
        panel.setAttribute('aria-hidden', 'true');
        trigger.setAttribute('aria-expanded', 'false');
        menus.delete(menu);
    }

    function openMenu(menu, mode) {
        document.querySelectorAll('.row-action-menu').forEach(function (otherMenu) {
            if (otherMenu !== menu) closeMenu(otherMenu);
        });
        const panel = getPanel(menu);
        const trigger = menu.querySelector('.row-action-trigger');
        if (!panel || !trigger) return;
        menu.classList.toggle('is-click-open', mode === 'click');
        menu.classList.toggle('is-hover-open', mode === 'hover');
        panel.setAttribute('aria-hidden', 'false');
        trigger.setAttribute('aria-expanded', 'true');
        menus.add(menu);
        positionPanel(menu);
    }

    function refreshOpenMenus() {
        menus.forEach(function (menu) {
            if (document.body.contains(menu)) positionPanel(menu);
        });
    }

    document.addEventListener('pointerenter', function (event) {
        if (!(event.target instanceof Element)) return;
        const menu = event.target.closest('.row-action-menu');
        if (menu && !menu.classList.contains('is-click-open')) openMenu(menu, 'hover');
    }, true);

    document.addEventListener('pointerleave', function (event) {
        if (!(event.target instanceof Element)) return;
        const menu = event.target.closest('.row-action-menu');
        if (menu && !menu.classList.contains('is-click-open') && !menu.matches(':hover')) closeMenu(menu);
    }, true);

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('.row-action-trigger');
        if (trigger) {
            const menu = trigger.closest('.row-action-menu');
            if (menu.classList.contains('is-click-open')) closeMenu(menu);
            else openMenu(menu, 'click');
            return;
        }

        const menu = event.target.closest('.row-action-menu');
        if (!menu) {
            document.querySelectorAll('.row-action-menu').forEach(closeMenu);
            return;
        }

        if (event.target.closest('.row-action-entry')) closeMenu(menu);
    });

    document.addEventListener('keydown', function (event) {
        const openMenuElement = document.querySelector('.row-action-menu.is-click-open, .row-action-menu.is-hover-open');
        if (!openMenuElement) return;

        if (event.key === 'Escape') {
            closeMenu(openMenuElement);
            openMenuElement.querySelector('.row-action-trigger').focus();
            return;
        }

        const panel = getPanel(openMenuElement);
        const items = Array.from(panel.querySelectorAll('a, button')).filter(function (item) {
            return !item.disabled && item.offsetParent !== null;
        });
        const currentIndex = items.indexOf(document.activeElement);
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (!items.length) return;
            const direction = event.key === 'ArrowDown' ? 1 : -1;
            items[(currentIndex + direction + items.length) % items.length].focus();
        } else if (event.key === 'Home' || event.key === 'End') {
            event.preventDefault();
            if (items.length) items[event.key === 'Home' ? 0 : items.length - 1].focus();
        }
    });

    window.addEventListener('resize', refreshOpenMenus);
    window.addEventListener('scroll', refreshOpenMenus, true);
    initializeActionCells();
})();
