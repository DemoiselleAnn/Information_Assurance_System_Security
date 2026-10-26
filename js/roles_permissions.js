function initializeRolesPermissions() {
    const section = document.getElementById('individual-accounts');
    if (!section) return;
    const search = section.querySelector('#account-search');
    const role = section.querySelector('#account-role');
    const status = section.querySelector('#account-status');
    const rows = Array.from(section.querySelectorAll('[data-account-row]'));
    if (section.dataset.rolesPermissionsInitialized === 'true') return;
    section.dataset.rolesPermissionsInitialized = 'true';
    const ITEMS_PER_PAGE = 3;
    let currentPage = 1;
    let filteredRows = [];
    const pagination = document.createElement('div');
    pagination.className = 'account-pagination';
    const range = document.createElement('span');
    range.setAttribute('aria-live', 'polite');
    const controls = document.createElement('nav');
    controls.setAttribute('aria-label', 'Account result pages');
    pagination.append(range, controls);
    section.querySelector('.table-container').after(pagination);

    function renderPage() {
        section.querySelectorAll('.row-action-menu.is-click-open .row-action-trigger').forEach(button => button.click());
        section.querySelectorAll('.row-action-menu.is-hover-open').forEach(menu => {
            // Use the existing menu handler so its open-menu tracking is cleared.
            const trigger = menu.querySelector('.row-action-trigger');
            trigger.click();
            trigger.click();
        });
        const total = filteredRows.length;
        const pages = Math.ceil(total / ITEMS_PER_PAGE);
        currentPage = Math.max(1, Math.min(currentPage, pages || 1));
        const start = (currentPage - 1) * ITEMS_PER_PAGE;
        rows.forEach(row => { row.hidden = true; });
        filteredRows.slice(start, start + ITEMS_PER_PAGE).forEach(row => { row.hidden = false; });
        section.querySelector('#account-empty').hidden = total !== 0;
        section.querySelector('#account-result-count').textContent = total + (total === 1 ? ' account' : ' accounts');
        pagination.hidden = total === 0;
        range.textContent = total ? `Showing ${start + 1}–${Math.min(start + ITEMS_PER_PAGE, total)} of ${total} accounts` : '';
        controls.replaceChildren();
        function addButton(label, page, disabled, active) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = label;
            button.disabled = disabled;
            if (active) button.setAttribute('aria-current', 'page');
            button.addEventListener('click', () => {
                currentPage = page;
                renderPage();
                const activeButton = controls.querySelector('[aria-current="page"]');
                if (activeButton) activeButton.focus();
            });
            controls.appendChild(button);
        }
        addButton('‹ Previous', currentPage - 1, currentPage === 1, false);
        for (let page = 1; page <= pages; page++) addButton(String(page), page, false, page === currentPage);
        addButton('Next ›', currentPage + 1, currentPage >= pages, false);
    }

    function filterAccounts() {
        const query = search.value.trim().toLocaleLowerCase();
        filteredRows = rows.filter(function (row) {
            return row.dataset.search.toLocaleLowerCase().includes(query)
                && (!role.value || row.dataset.role === role.value)
                && (!status.value || row.dataset.status === status.value);
        });
        currentPage = 1;
        renderPage();
    }
    search.addEventListener('input', filterAccounts);
    role.addEventListener('change', filterAccounts);
    status.addEventListener('change', filterAccounts);
    filterAccounts();
}

window.initializeRolesPermissions = initializeRolesPermissions;
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeRolesPermissions);
else initializeRolesPermissions();
