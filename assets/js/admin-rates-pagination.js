(() => {
    const tbody = document.getElementById('adminRateSummary');
    const pager = document.getElementById('adminRatePagination');
    const pageInfo = document.getElementById('adminRatePageInfo');
    const prevButton = document.getElementById('adminRatePrev');
    const nextButton = document.getElementById('adminRateNext');
    const pageSizeSelect = document.getElementById('adminRatePageSize');

    if (!tbody || !pager || !pageInfo || !prevButton || !nextButton || !pageSizeSelect) {
        return;
    }

    let currentPage = 1;
    let pageSize = Number(pageSizeSelect.value) || 10;
    let refreshTimer = null;

    function rateRows() {
        return [...tbody.children].filter(row => row.tagName === 'TR');
    }

    function isPlaceholderRow(row) {
        const cells = row.querySelectorAll('td');
        return cells.length === 1 && Number(cells[0].getAttribute('colspan') || 0) >= 5;
    }

    function renderPagination() {
        const rows = rateRows();

        // Keep loading / empty-state rows visible and hide pagination.
        if (rows.length === 0 || (rows.length === 1 && isPlaceholderRow(rows[0]))) {
            rows.forEach(row => {
                row.hidden = false;
            });
            pager.hidden = true;
            return;
        }

        pager.hidden = false;

        const totalRows = rows.length;
        const totalPages = Math.max(1, Math.ceil(totalRows / pageSize));

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        if (currentPage < 1) {
            currentPage = 1;
        }

        const startIndex = (currentPage - 1) * pageSize;
        const endIndex = Math.min(startIndex + pageSize, totalRows);

        rows.forEach((row, index) => {
            row.hidden = index < startIndex || index >= endIndex;
        });

        pageInfo.textContent =
            `Showing ${startIndex + 1}-${endIndex} of ${totalRows} rates · Page ${currentPage} of ${totalPages}`;

        prevButton.disabled = currentPage <= 1;
        nextButton.disabled = currentPage >= totalPages;
    }

    prevButton.addEventListener('click', () => {
        if (currentPage <= 1) return;
        currentPage -= 1;
        renderPagination();
    });

    nextButton.addEventListener('click', () => {
        const totalPages = Math.max(1, Math.ceil(rateRows().length / pageSize));
        if (currentPage >= totalPages) return;
        currentPage += 1;
        renderPagination();
    });

    pageSizeSelect.addEventListener('change', () => {
        pageSize = Number(pageSizeSelect.value) || 10;
        currentPage = 1;
        renderPagination();
    });

    document.addEventListener('admin-rates-filtered', () => {
        currentPage = 1;
        renderPagination();
    });

    // app.js rebuilds adminRateSummary after load/save/update.
    // Observe the tbody so pagination is reapplied automatically.
    const observer = new MutationObserver(() => {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(() => {
            const totalPages = Math.max(1, Math.ceil(rateRows().length / pageSize));
            if (currentPage > totalPages) currentPage = totalPages;
            renderPagination();
        }, 0);
    });

    observer.observe(tbody, {
        childList: true
    });

    renderPagination();
})();
