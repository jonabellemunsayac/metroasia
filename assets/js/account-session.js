// Notification only: PHP/MySQL authorizes every request independently.
(() => {
    const originalFetch = window.fetch.bind(window);
    let leaving = false;
    function leave() {
        if (leaving) return;
        leaving = true;
        window.location.replace(window.appConfig.adminLoginUrl);
    }
    window.fetch = async (...args) => {
        const response = await originalFetch(...args);
        const url = new URL(response.url, location.href);
        if (url.origin === location.origin && url.pathname === new URL(window.appConfig.apiUrl, location.href).pathname && response.status === 401) leave();
        return response;
    };
    async function check() {
        if (leaving) return;
        try {
            const response = await originalFetch(`${window.appConfig.apiUrl}?action=session-status`, {cache: 'no-store'});
            if (response.status === 401) leave();
        } catch (_) { /* Retry on the next poll after a network interruption. */ }
    }
    setInterval(check, 10000);
    window.addEventListener('focus', check);
    window.addEventListener('pageshow', check);
})();
