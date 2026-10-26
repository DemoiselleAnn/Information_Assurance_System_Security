(function () {
    'use strict';

    function initializeSecurityLogFilters() {
        const picker = document.querySelector('[data-security-date-picker]');
        if (!picker || picker.dataset.initialized) return;
        picker.dataset.initialized = 'true';
        const input = picker.querySelector('input[name="date"]');
        const monthFilter = picker.closest('form').querySelector('[name="month"]');
        const months = Array.from(monthFilter.options).slice(1).map(option => option.textContent.trim());
        const today = new Date();
        let selected = input.value;
        let year = selected ? Number(selected.slice(0, 4)) : today.getFullYear();
        let month = selected ? Number(selected.slice(5, 7)) - 1 : (Number(monthFilter.value) || today.getMonth() + 1) - 1;
        const dateAt = (y, m, d) => {
            const date = new Date(0);
            date.setHours(12, 0, 0, 0);
            date.setFullYear(y, m, d);
            return date;
        };
        const iso = date => String(date.getFullYear()).padStart(4, '0') + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
        const display = value => value ? value.slice(8, 10) + '/' + value.slice(5, 7) + '/' + value.slice(0, 4) : '';
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'date';
        hidden.value = selected;
        input.removeAttribute('name');
        input.type = 'text';
        input.readOnly = true;
        input.placeholder = 'dd/mm/yyyy';
        input.value = display(selected);
        input.setAttribute('aria-haspopup', 'dialog');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', 'sl-date-calendar');
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'sl-calendar-toggle';
        toggle.setAttribute('aria-label', 'Open date calendar');
        toggle.setAttribute('aria-haspopup', 'dialog');
        toggle.setAttribute('aria-controls', 'sl-date-calendar');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.innerHTML = '<i class="fas fa-calendar-days" aria-hidden="true"></i>';
        const panel = document.createElement('div');
        panel.id = 'sl-date-calendar';
        panel.className = 'sl-calendar';
        panel.hidden = true;
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Choose a date');
        panel.innerHTML = '<div class="sl-calendar-heading"><button type="button" data-move="-1" aria-label="Previous month">‹</button><span class="sl-calendar-month" aria-live="polite"></span><button type="button" data-move="1" aria-label="Next month">›</button></div><label class="sl-calendar-year">Year <input type="number" min="1" max="9999" step="1" aria-label="Calendar year"></label><table class="sl-calendar-days" aria-label="Days"><thead><tr><th scope="col">Su</th><th scope="col">Mo</th><th scope="col">Tu</th><th scope="col">We</th><th scope="col">Th</th><th scope="col">Fr</th><th scope="col">Sa</th></tr></thead><tbody></tbody></table><div class="sl-calendar-actions"><button type="button" data-clear>Clear date</button><button type="button" data-close>Close</button></div>';
        picker.append(hidden, toggle, panel);
        const yearInput = panel.querySelector('input');
        const body = panel.querySelector('tbody');
        function render(focusDay) {
            panel.querySelector('.sl-calendar-month').textContent = months[month] + ' ' + year;
            yearInput.value = year;
            const restoreDayFocus = body.contains(document.activeElement);
            body.replaceChildren();
            const start = dateAt(year, month, 1).getDay();
            const days = dateAt(year, month + 1, 0).getDate();
            const selectedDay = selected && Number(selected.slice(0, 4)) === year && Number(selected.slice(5, 7)) === month + 1 ? Number(selected.slice(8, 10)) : 0;
            const activeDay = Math.min(days, focusDay || selectedDay || 1);
            let row;
            for (let cell = 0; cell < Math.ceil((start + days) / 7) * 7; cell++) {
                if (cell % 7 === 0) { row = document.createElement('tr'); body.append(row); }
                const td = document.createElement('td');
                row.append(td);
                const day = cell - start + 1;
                if (day < 1 || day > days) continue;
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.day = day;
                button.textContent = day;
                button.tabIndex = day === activeDay ? 0 : -1;
                button.setAttribute('aria-label', day + ' ' + months[month] + ' ' + year);
                button.setAttribute('aria-pressed', day === selectedDay ? 'true' : 'false');
                if (day === selectedDay) button.className = 'selected';
                if (iso(dateAt(year, month, day)) === iso(today)) button.setAttribute('aria-current', 'date');
                td.append(button);
            }
            if (restoreDayFocus) body.querySelector('[tabindex="0"]').focus();
        }
        function close(restoreFocus) {
            panel.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-expanded', 'false');
            if (restoreFocus) input.focus();
        }
        function open() {
            render();
            panel.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            toggle.setAttribute('aria-expanded', 'true');
            body.querySelector('[tabindex="0"]').focus();
        }
        function clearDate() {
            selected = '';
            hidden.value = '';
            input.value = '';
        }
        input.addEventListener('click', open);
        toggle.addEventListener('click', () => panel.hidden ? open() : close(true));
        input.addEventListener('keydown', event => {
            if (['Enter', ' ', 'ArrowDown'].includes(event.key)) { event.preventDefault(); open(); }
            if (['Backspace', 'Delete'].includes(event.key)) { event.preventDefault(); clearDate(); }
        });
        monthFilter.addEventListener('change', () => {
            clearDate();
            if (Number(monthFilter.value)) month = Number(monthFilter.value) - 1;
            render();
        });
        yearInput.addEventListener('change', () => {
            if (!/^\d{1,4}$/.test(yearInput.value) || Number(yearInput.value) < 1) { yearInput.value = year; return; }
            if (year === Number(yearInput.value)) return;
            year = Number(yearInput.value);
            render();
        });
        yearInput.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                yearInput.dispatchEvent(new Event('change'));
                body.querySelector('[tabindex="0"]').focus();
            }
        });
        panel.addEventListener('click', event => {
            const button = event.target.closest('button');
            if (!button) return;
            if (button.dataset.day) {
                selected = iso(dateAt(year, month, Number(button.dataset.day)));
                hidden.value = selected;
                input.value = display(selected);
                monthFilter.value = String(month + 1);
                close(true);
            } else if (button.dataset.move) {
                const next = dateAt(year, month + Number(button.dataset.move), 1);
                if (next.getFullYear() < 1 || next.getFullYear() > 9999) return;
                year = next.getFullYear(); month = next.getMonth(); render();
            } else if (button.hasAttribute('data-clear')) { clearDate(); close(true); }
            else if (button.hasAttribute('data-close')) close(true);
        });
        picker.addEventListener('keydown', event => {
            if (event.key === 'Escape') { event.preventDefault(); close(true); return; }
            const button = event.target.closest('[data-day]');
            if (!button) return;
            const day = Number(button.dataset.day);
            let target;
            const delta = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[event.key];
            if (delta) target = dateAt(year, month, day + delta);
            if (event.key === 'Home') target = dateAt(year, month, day - dateAt(year, month, day).getDay());
            if (event.key === 'End') target = dateAt(year, month, day + 6 - dateAt(year, month, day).getDay());
            if (event.key === 'PageUp' || event.key === 'PageDown') {
                const nextMonth = dateAt(year, month + (event.key === 'PageUp' ? -1 : 1), 1);
                target = dateAt(nextMonth.getFullYear(), nextMonth.getMonth(), Math.min(day, dateAt(nextMonth.getFullYear(), nextMonth.getMonth() + 1, 0).getDate()));
            }
            if (!target || target.getFullYear() < 1 || target.getFullYear() > 9999) return;
            event.preventDefault();
            year = target.getFullYear(); month = target.getMonth(); render(target.getDate());
            body.querySelector('[tabindex="0"]').focus();
        });
        // Scoped listeners are discarded together with the page during dashboard navigation.
        picker.closest('form').addEventListener('focusout', () => {
            setTimeout(() => { if (!picker.contains(document.activeElement)) close(false); }, 0);
        });
        picker.closest('.sl-page').addEventListener('click', event => {
            if (!picker.contains(event.target)) close(false);
        });
        render();
    }
    window.initializeSecurityLogFilters = initializeSecurityLogFilters;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeSecurityLogFilters);
    else initializeSecurityLogFilters();
})();
