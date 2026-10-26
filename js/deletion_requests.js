(function () {
    'use strict';

    function initializeDeletionRequestSelection() {
        const search = document.getElementById('account-search');
        const table = document.getElementById('eligible-accounts-table');
        const requestForm = document.getElementById('deletion-request-form');
        const emptyState = document.getElementById('summary-empty');
        const detailsState = document.getElementById('summary-details');
        const pageSummary = document.getElementById('account-page-summary');
        const pagination = document.getElementById('account-pagination');
        const accountEmptyState = document.getElementById('account-empty-state');
        if (!search || !table || !requestForm || !emptyState || !detailsState || !pageSummary || !pagination || !accountEmptyState) return;

        if (requestForm.dataset.deletionRequestsInitialized === 'true') return;
        requestForm.dataset.deletionRequestsInitialized = 'true';
        const rows = Array.from(table.querySelectorAll('.account-option'));
        const pageSize = 8;
        let currentPage = 1;
        let filteredRows = rows.slice();

        function showEmptyAccountState() {
            emptyState.hidden = false;
            emptyState.style.display = 'flex';
            detailsState.hidden = true;
            detailsState.style.display = 'none';
        }

        function updateSelectedAccountCard(row) {
            if (!row) return false;
            const fields = {
                name: document.getElementById('summary-name'),
                username: document.getElementById('summary-username'),
                employeeId: document.getElementById('summary-employee-id'),
                role: document.getElementById('summary-role'),
                email: document.getElementById('summary-email'),
                status: document.getElementById('summary-status')
            };
            if (Object.values(fields).some(function (field) { return !field; })) return false;
            fields.name.textContent = row.dataset.accountName || '';
            fields.username.textContent = row.dataset.accountUsername || '';
            fields.employeeId.textContent = row.dataset.accountEmployeeId || '';
            fields.role.textContent = row.dataset.accountRole || '';
            fields.email.textContent = row.dataset.accountEmail || '';
            fields.status.textContent = row.dataset.accountStatus || '';
            emptyState.hidden = true;
            emptyState.style.display = 'none';
            detailsState.hidden = false;
            detailsState.style.display = 'flex';
            return true;
        }

        function selectRow(row) {
            if (!row) return;
            rows.forEach(function (item) { item.classList.toggle('is-selected', item === row); });
            const radio = row.querySelector('input[name="user_id"]');
            if (radio) radio.checked = true;
            updateSelectedAccountCard(row);
        }

        function renderPagination() {
            pagination.innerHTML = '';
            const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));
            currentPage = Math.min(currentPage, totalPages);
            const start = filteredRows.length ? (currentPage - 1) * pageSize : 0;
            const end = Math.min(start + pageSize, filteredRows.length);
            rows.forEach(function (row) { row.hidden = true; });
            filteredRows.slice(start, end).forEach(function (row) { row.hidden = false; });
            accountEmptyState.hidden = filteredRows.length !== 0;
            pageSummary.textContent = filteredRows.length ? 'Showing ' + (start + 1) + ' to ' + end + ' of ' + filteredRows.length + ' accounts' : '0 accounts';
            if (totalPages <= 1) return;
            for (let page = 1; page <= totalPages; page += 1) {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = page;
                button.classList.toggle('is-current', page === currentPage);
                button.setAttribute('aria-label', 'Account page ' + page);
                button.addEventListener('click', function () { currentPage = page; renderPagination(); });
                pagination.appendChild(button);
            }
        }

        table.querySelectorAll('input[name="user_id"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (radio.checked) selectRow(radio.closest('.account-option'));
            });
        });
        table.addEventListener('click', function (event) {
            const row = event.target.closest('.account-option');
            if (!row || event.target.closest('input')) return;
            selectRow(row);
        });
        table.addEventListener('keydown', function (event) {
            const row = event.target.closest('.account-option');
            if (!row || (event.key !== 'Enter' && event.key !== ' ')) return;
            event.preventDefault();
            selectRow(row);
        });
        search.addEventListener('input', function () {
            const query = search.value.trim().toLowerCase();
            filteredRows = rows.filter(function (row) { return row.dataset.accountSearch.includes(query); });
            currentPage = 1;
            renderPagination();
        });
        requestForm.addEventListener('submit', function (event) {
            if (requestForm.querySelector('input[name="user_id"]:checked')) return;
            event.preventDefault();
            let notice = requestForm.querySelector('.selection-validation');
            if (!notice) {
                notice = document.createElement('div');
                notice.className = 'management-alert error selection-validation';
                notice.setAttribute('role', 'alert');
                requestForm.insertBefore(notice, requestForm.firstElementChild);
            }
            notice.textContent = 'Please select an account.';
            notice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
        showEmptyAccountState();
        renderPagination();
    }

    window.initializeDeletionRequests = initializeDeletionRequestSelection;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeDeletionRequestSelection);
    else initializeDeletionRequestSelection();
})();
