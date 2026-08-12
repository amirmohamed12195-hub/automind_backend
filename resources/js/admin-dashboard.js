const dashboard = document.querySelector('.admin-v2');

if (dashboard) {
    const sidebar = document.querySelector('[data-admin-sidebar]');
    const overlay = document.querySelector('[data-admin-overlay]');
    const globalSearch = document.querySelector('[data-global-search]');

    const closeSidebar = () => {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('show');
    };

    const filterTable = (table, query) => {
        if (!table) return;
        const normalized = query.trim().toLocaleLowerCase();
        table.querySelectorAll('tbody tr').forEach((row) => {
            row.dataset.searchHidden = String(normalized !== '' && !row.textContent.toLocaleLowerCase().includes(normalized));
        });
    };

    const activeView = () => document.querySelector('.admin-view.active');

    const switchView = (view, updateHistory = true) => {
        const target = document.querySelector(`.admin-view[data-view="${CSS.escape(view)}"]`);
        if (!target) return;
        document.querySelectorAll('.admin-view').forEach((section) => section.classList.toggle('active', section === target));
        document.querySelectorAll('.admin-nav-item').forEach((item) => item.classList.toggle('active', item.dataset.adminView === view));
        const label = document.querySelector('[data-current-view]');
        const navLabel = document.querySelector(`.admin-nav-item[data-admin-view="${CSS.escape(view)}"] span`);
        if (label) label.textContent = navLabel?.textContent || view;
        if (updateHistory) history.replaceState(null, '', `#${view}`);
        if (globalSearch) globalSearch.value = '';
        target.querySelectorAll('tbody tr').forEach((row) => delete row.dataset.searchHidden);
        closeSidebar();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const openDialog = (id) => {
        const dialog = document.getElementById(id);
        if (!(dialog instanceof HTMLDialogElement)) return;
        if (!dialog.open) dialog.showModal();
    };

    const csvValue = (value) => `"${String(value).replaceAll('"', '""').replace(/\s+/g, ' ').trim()}"`;

    const exportCurrentTable = () => {
        const table = activeView()?.querySelector('table');
        if (!table) return;
        const rows = Array.from(table.querySelectorAll('tr')).filter((row) => row.dataset.searchHidden !== 'true');
        const csv = rows.map((row) => Array.from(row.querySelectorAll('th,td')).map((cell) => csvValue(cell.innerText)).join(',')).join('\n');
        const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${table.dataset.exportName || activeView()?.dataset.view || 'automind'}-${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    };

    document.querySelector('[data-sidebar-open]')?.addEventListener('click', () => {
        sidebar?.classList.add('open');
        overlay?.classList.add('show');
    });
    document.querySelector('[data-sidebar-close]')?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);
    document.querySelector('[data-export-current]')?.addEventListener('click', exportCurrentTable);

    document.addEventListener('click', (event) => {
        const viewLink = event.target.closest('[data-admin-view]');
        if (viewLink) {
            event.preventDefault();
            switchView(viewLink.dataset.adminView);
            return;
        }

        const dialogButton = event.target.closest('[data-open-dialog]');
        if (dialogButton) {
            event.preventDefault();
            openDialog(dialogButton.dataset.openDialog);
            return;
        }

        const closeButton = event.target.closest('[data-close-dialog]');
        if (closeButton) {
            closeButton.closest('dialog')?.close();
            return;
        }

        const confirmButton = event.target.closest('[data-confirm]');
        if (confirmButton && !window.confirm(confirmButton.dataset.confirm)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }

        const dismiss = event.target.closest('[data-flash-alert] button');
        if (dismiss) dismiss.closest('[data-flash-alert]')?.remove();
    });

    document.querySelectorAll('.admin-dialog').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    });

    document.querySelectorAll('[data-table-search]').forEach((input) => {
        input.addEventListener('input', () => filterTable(document.getElementById(input.dataset.tableSearch), input.value));
    });

    globalSearch?.addEventListener('input', () => {
        const table = activeView()?.querySelector('table');
        filterTable(table, globalSearch.value);
        const localSearch = activeView()?.querySelector('[data-table-search]');
        if (localSearch) localSearch.value = globalSearch.value;
    });

    const audienceSelect = document.querySelector('[data-audience-select]');
    const audienceUser = document.querySelector('[data-audience-user]');
    const syncAudience = () => {
        if (audienceUser) audienceUser.hidden = audienceSelect?.value !== 'user';
    };
    audienceSelect?.addEventListener('change', syncAudience);
    syncAudience();

    document.querySelectorAll('form').forEach((form) => form.addEventListener('submit', (event) => {
        if (event.defaultPrevented) return;
        const button = event.submitter;
        if (button) {
            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.textContent = 'Working…';
        }
    }));

    window.addEventListener('keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLocaleLowerCase() === 'k') {
            event.preventDefault();
            globalSearch?.focus();
        }
        if (event.key === 'Escape') {
            closeSidebar();
            document.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());
        }
    });

    const requestedView = location.hash.slice(1) || dashboard.dataset.initialView || 'overview';
    switchView(requestedView, false);
}
