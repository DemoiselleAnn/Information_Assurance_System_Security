(function () {
    'use strict';
    // Mirrors the pure PHP registration validators; parity cases are tested in both runtimes.
    function profileFieldError(name, raw, original) {
        const value = raw.trim();
        if (['first_name', 'last_name'].includes(name) && !value) return 'This name is required.';
        if (original !== undefined && value === original.trim()) return '';
        const limit = name === 'email' ? 100 : ['extension_name', 'zip_code', 'birthdate', 'sex'].includes(name) ? 10 : 50;
        if (Array.from(value).length > limit || /[\x00-\x1F\x7F]/.test(value)) return `Use at most ${limit} characters without control characters.`;
        if (!value) return '';
        if (['first_name', 'middle_name', 'last_name'].includes(name)) {
            if (/\d/.test(value)) return 'Numbers are not allowed in names.';
            if (!/^[A-Za-z\s-]+$/.test(value)) return 'Use letters, spaces or hyphens only.';
            if (/\s{2,}/.test(value)) return 'Double spaces are not allowed.';
            if (/([A-Za-z])\1{2,}/.test(value)) return 'Three identical consecutive letters are not allowed.';
            const first = value.match(/[A-Za-z]/)?.[0];
            if (!first) return 'Enter a valid name.';
            const valid = /[A-Z]/.test(first)
                ? value.split(/\s+/).every(word => word.split('-').every(part => !part || /^[A-Z][a-z]*$/.test(part)))
                : !/[A-Z]/.test(value);
            return valid ? '' : 'Use all lowercase words or capitalize every word and hyphenated part; mixed case is not allowed.';
        }
        if (name === 'extension_name') return /^(Jr\.?|Sr\.?|JR\.?|SR\.?|I|II|III|IV|V|VI|VII|VIII|IX|X|i|ii|iii|iv|v|vi|vii|viii|ix|x)$/.test(value) || /^(i|ii|iii|iv|v|vi|vii|viii|ix|x)$/i.test(value) ? '' : 'Name Extension must be Jr., Sr. or a Roman numeral I–X.';
        if (name === 'zip_code') return /^[0-9]{4,5}$/.test(value) ? '' : 'ZIP Code must be 4–5 digits.';
        if (name === 'purok') {
            const valid = value.length >= 2 && value.length <= 50 && /^[A-Za-z0-9\s.,-]+$/.test(value)
                && (/^[Pp]/.test(value) ? /^Purok\s+\d+$/i.test(value) : /^[0-9]/.test(value) ? /^[0-9]+\s+[A-Za-z]+/i.test(value) : /^[A-Z]/.test(value));
            return valid ? '' : 'Use Purok <Number>, a numbered street such as 123 Main Street, or an address starting with a capital letter (2–50 characters).';
        }
        if (['barangay', 'city_municipality', 'province', 'country'].includes(name)) {
            let valid = value.length >= 2 && value.length <= 50 && /^[A-Za-z]+(?:[\s-][A-Za-z]+)*$/.test(value) && /^[A-Z]/.test(value);
            if (name === 'barangay') valid = valid && /^Barangay/i.test(value) && (value.length <= 8 || /^Barangay\s/.test(value));
            return valid ? '' : name === 'barangay' ? 'Start with Barangay followed by a space and letters (2–50 characters).' : 'Use letters, single spaces or hyphens, starting with a capital letter (2–50 characters).';
        }
        return '';
    }
    window.profileFieldError = profileFieldError;
    function validateProfileField(field) {
        field.setCustomValidity('');
        let message = profileFieldError(field.name, field.value, field.dataset.original);
        if (!message && field.required && !field.value.trim()) message = 'This field is required.';
        // Unchanged legacy text is preserved. Native email/date/select constraints still apply.
        const legacy = field.dataset.original !== undefined && field.value.trim() === field.dataset.original.trim();
        if (!message && (!legacy || ['email', 'birthdate', 'sex'].includes(field.name)) && !field.checkValidity()) message = field.validationMessage;
        field.setCustomValidity(message);
        field.setAttribute('aria-invalid', String(!!message));
        const target = document.getElementById(field.getAttribute('aria-describedby'));
        if (target) target.textContent = message;
        return !message;
    }
    function attachProfileValidation(form) {
        const fields = Array.from(form.querySelectorAll('input:not([type=hidden]), select'));
        fields.forEach(field => ['input', 'blur', 'change'].forEach(event => field.addEventListener(event, () => validateProfileField(field))));
        return fields;
    }
    function initializeMemberModal(panel) {
        const form = panel.querySelector('form');
        const steps = Array.from(panel.querySelectorAll('[data-member-step]'));
        const fields = Array.from(form.querySelectorAll('input:not([type=hidden]), select'));
        attachProfileValidation(form);
        const next = panel.querySelector('[data-member-next]');
        const back = panel.querySelector('[data-member-back]');
        const save = panel.querySelector('[data-member-save]');
        const error = panel.querySelector('[data-member-error]');
        let step = 1, busy = false, opener, baseline, locks = [];
        const snapshot = () => JSON.stringify(fields.map(field => field.value));
        function showStep(value, focus = true) {
            step = value;
            steps.forEach((section, index) => { section.hidden = index + 1 !== step; });
            back.hidden = step === 1;
            next.hidden = step === 3;
            save.hidden = step !== 3;
            panel.querySelector('.member-step-status').textContent = `Step ${step} of 3 — ${['Personal Information', 'Address Information', 'Review & Save'][step - 1]}`;
            panel.querySelectorAll('.member-progress li').forEach((item, index) => {
                item.classList.toggle('is-complete', index + 1 < step);
                if (index + 1 === step) item.setAttribute('aria-current', 'step');
                else item.removeAttribute('aria-current');
            });
            if (step === 3) {
                const review = panel.querySelector('[data-member-review]');
                review.replaceChildren();
                steps.slice(0, 2).forEach(section => {
                    const heading = document.createElement('h3');
                    heading.className = 'member-review-heading';
                    heading.textContent = section.querySelector('h3').textContent;
                    const list = document.createElement('dl');
                    list.className = 'member-review';
                    section.querySelectorAll('input, select').forEach(field => {
                        const row = document.createElement('div');
                        const label = document.createElement('dt');
                        const value = document.createElement('dd');
                        label.textContent = field.closest('label').querySelector('span').textContent.replace(/ \*$/, '');
                        value.textContent = field.tagName === 'SELECT' ? field.selectedOptions[0].textContent : field.value.trim() || 'Not provided';
                        row.append(label, value); list.append(row);
                    });
                    review.append(heading, list);
                });
            }
            if (focus) steps[step - 1].querySelector('h3').focus({ preventScroll: true });
            panel.querySelector('.member-modal-body').scrollTop = 0;
        }
        function validate(section) {
            let first;
            section.querySelectorAll('input, select').forEach(field => {
                const valid = validateProfileField(field);
                if (!valid && !first) first = field;
            });
            if (first) first.focus();
            return !first;
        }
        function unlock() {
            locks.forEach(([element, overflow, top, left]) => {
                element.style.overflow = overflow;
                element.scrollTop = top; element.scrollLeft = left;
            });
            locks = [];
        }
        function close() {
            if (busy) return;
            if (snapshot() !== baseline && !window.confirm('Discard your unsaved profile changes?')) return;
            form.reset();
            panel.close();
        }
        function open(button) {
            if (panel.open) return;
            opener = button || document.querySelector('[data-mp-toggle="editProfile"]');
            baseline = snapshot();
            locks = Array.from(new Set([document.documentElement, document.body, document.querySelector('.dashboard-main')].filter(Boolean)))
                .map(element => [element, element.style.overflow, element.scrollTop, element.scrollLeft]);
            locks.forEach(([element]) => { element.style.overflow = 'hidden'; });
            panel.showModal();
            showStep(1, false);
            if (!error.hidden) error.setAttribute('tabindex', '-1');
            (error.hidden ? fields[0] : error).focus({ preventScroll: true });
        }
        panel.addEventListener('cancel', event => { event.preventDefault(); close(); });
        panel.addEventListener('close', () => { unlock(); opener?.focus({ preventScroll: true }); });
        panel.querySelectorAll('[data-member-close]').forEach(button => button.addEventListener('click', close));
        document.querySelectorAll('[data-mp-toggle="editProfile"]').forEach(button => button.addEventListener('click', () => open(button)));
        next.addEventListener('click', () => { if (validate(steps[step - 1])) showStep(step + 1); });
        back.addEventListener('click', () => showStep(step - 1));
        form.addEventListener('submit', async event => {
            event.preventDefault();
            event.stopPropagation(); // This dialog handles its response without replacing dashboard navigation state.
            if (busy) return;
            if (step !== 3) { next.click(); return; }
            for (let index = 0; index < 2; index++) {
                showStep(index + 1, false);
                if (!validate(steps[index])) return;
            }
            showStep(3, false);
            busy = true;
            form.setAttribute('aria-busy', 'true');
            const buttons = Array.from(form.querySelectorAll('button'));
            buttons.forEach(button => { button.disabled = true; });
            save.textContent = 'Saving…';
            error.hidden = true;
            try {
                const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin' });
                const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
                const serverError = parsed.querySelector('[data-member-error]');
                parsed.querySelectorAll('[data-member-modal] [aria-invalid="true"]').forEach(incomingField => {
                    const local = fields.find(field => field.name === incomingField.name);
                    const message = parsed.getElementById(incomingField.getAttribute('aria-describedby'))?.textContent;
                    if (local && message) {
                        local.setCustomValidity(message); local.setAttribute('aria-invalid', 'true');
                        document.getElementById(local.getAttribute('aria-describedby')).textContent = message;
                    }
                });
                const incoming = parsed.querySelector('.dashboard-main .content-wrap');
                if (!response.ok || !incoming || !parsed.querySelector('.profile-alert.success') || serverError && !serverError.hidden) {
                    throw new Error(serverError && !serverError.hidden ? serverError.textContent : 'Your changes could not be confirmed. Check your connection and session, then try again.');
                }
                const current = document.querySelector('.dashboard-main .content-wrap');
                panel.close(); unlock();
                current.replaceChildren(...Array.from(incoming.childNodes));
                initializeMyProfile();
                const success = current.querySelector('.profile-alert.success');
                success.setAttribute('tabindex', '-1'); success.focus({ preventScroll: true });
            } catch (failure) {
                error.textContent = failure.message;
                error.hidden = false;
                error.setAttribute('tabindex', '-1'); error.focus({ preventScroll: false });
            } finally {
                busy = false;
                buttons.forEach(button => { button.disabled = false; });
                save.textContent = 'Save Changes'; form.removeAttribute('aria-busy');
            }
        });
        // Unlock if dashboard Back/Forward removes an open dialog.
        const observer = new MutationObserver(() => { if (!panel.isConnected) { unlock(); observer.disconnect(); } });
        observer.observe(panel.parentElement, { childList: true });
        if (panel.dataset.open === 'true') open();
    }
    function initializeMyProfile() {
        const panel = document.getElementById('mp-edit-profile-panel');
        if (!panel || panel.dataset.profileInitialized) return;
        panel.dataset.profileInitialized = 'true';
        if (panel.matches('[data-member-modal]')) { initializeMemberModal(panel); return; }
        const editForm = panel.querySelector('form');
        if (editForm?.id === 'mpEditForm') {
            const fields = attachProfileValidation(editForm);
            editForm.addEventListener('submit', event => {
                const invalid = fields.filter(field => !validateProfileField(field));
                if (invalid.length) { event.preventDefault(); invalid[0].focus(); }
            });
        }
        function togglePanel(show) {
            const visible = typeof show === 'boolean' ? show : panel.style.display === 'none';
            panel.style.display = visible ? 'block' : 'none';
            if (!visible) panel.querySelector('form')?.reset();
            if (visible) {
                const header = document.querySelector('.top-header');
                panel.style.scrollMarginTop = ((header ? header.getBoundingClientRect().height : 0) + 16) + 'px';
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                const input = panel.querySelector('input:not([type=hidden])');
                if (input) input.focus({ preventScroll: true });
            }
        }
        document.querySelectorAll('[data-mp-toggle="editProfile"]').forEach(button => button.addEventListener('click', event => { event.preventDefault(); togglePanel(); }));
        document.querySelectorAll('[data-mp-form="changePassword"]').forEach(form => form.addEventListener('click', event => {
            if (!event.target.closest('button, input, select, textarea, a')) form.requestSubmit();
        }));
        if (panel.style.display !== 'none') togglePanel(true);
    }
    window.initializeMyProfile = initializeMyProfile;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeMyProfile);
    else initializeMyProfile();
})();
