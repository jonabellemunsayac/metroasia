const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const path = require('node:path');
if (process.env.RUN_ADMIN_NAVIGATION_TEST !== '1') {
    console.log('Set RUN_ADMIN_NAVIGATION_TEST=1 to test against local Apache/MySQL.');
} else run().catch(error => { console.error(error); process.exitCode = 1; });

async function run() {
    const base = process.env.TEST_APP_URL || 'http://localhost/multisportspass';
    function fixture(mode, data = {}) {
        const result = spawnSync(process.env.PHP_BINARY || 'C:/xampp/php/php.exe',
            [path.join(__dirname, 'admin-navigation-fixtures.php'), mode],
            { input: JSON.stringify(data), encoding: 'utf8' });
        assert.equal(result.status, 0, result.stderr);
        return result.stdout ? JSON.parse(result.stdout) : null;
    }
    const account = fixture('init');
    let cookie = '';
    async function request(url, data) {
        const started = Date.now();
        const response = await fetch(base + url, {
            method: data ? 'POST' : 'GET', redirect: 'manual',
            headers: { Cookie: cookie, ...(data ? { 'Content-Type': 'application/x-www-form-urlencoded' } : {}) },
            body: data ? new URLSearchParams(data) : undefined,
        });
        for (const item of response.headers.getSetCookie()) if (item.startsWith('PHPSESSID=')) cookie = item.split(';')[0];
        const body = await response.text();
        assert.equal(response.status, data ? 302 : 200, `${url}: ${response.status}: ${body.slice(0, 500)}`);
        if (url.startsWith('/api.php')) {
            const payload = JSON.parse(body);
            assert.equal(payload.ok, true);
            if (payload.state) {
                assert.equal(payload.state.currentAdmin.role, 'admin');
                assert.equal(payload.state.currentAdmin.canManageOperations, true);
                assert.ok(Object.keys(payload.state.slotDetails).length);
                if (!url.includes('stateContext')) assert.ok(payload.state.adminDashboardCourtCounts);
            }
        }
        console.log(`${url}: OK (${body.length} characters, ${Date.now() - started} ms)`);
    }
    try {
        await request('/ui/login.php', { email: account.email, password: account.password });
        for (let visit = 0; visit < 3; visit++) {
            await request('/admin/dashboard.php');
            await request('/api.php?action=state');
            await request('/admin/bookings.php');
            await request('/api.php?action=state&stateContext=bookings');
            await request('/api.php?action=admin-bookings&status=Held&from=&to=');
        }
        await request('/admin/dashboard.php');
        await request('/api.php?action=state');
        // Fast navigation can overlap the old page's API request with the next page.
        await Promise.all([
            request('/api.php?action=state&stateContext=bookings'),
            request('/admin/dashboard.php'),
            request('/api.php?action=state'),
        ]);
    } finally {
        fixture('cleanup', account);
    }
}
