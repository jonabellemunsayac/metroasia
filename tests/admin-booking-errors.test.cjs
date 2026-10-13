const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { spawnSync } = require('node:child_process');

const source = fs.readFileSync(path.join(__dirname, '../assets/js/app.js'), 'utf8');
const alerts = [];
const context = vm.createContext({
    els: { admin: { tagName: 'TBODY', innerHTML: '' } },
    escapeHtml: text => text.replaceAll('<', '&lt;').replaceAll('>', '&gt;'),
    FormData, api: 'api.php', adminLoginUrl: 'login.php', state: null,
    window: { location: { href: '' } },
    alert: message => alerts.push(message),
    console: { error() {} },
    renderAll() {},
    showAdminToast() { throw new Error('Must not claim success for a failed response'); },
});
for (const name of ['readApiResponse', 'adminStateUrl', 'loadState', 'showLoadError', 'updateStatus']) {
    const match = source.match(new RegExp(`^(?:async )?function ${name}\\([^]*?^}`, 'm'));
    assert.ok(match, `Missing ${name}`);
    vm.runInContext(match[0], context);
}

(async () => {
    assert.equal(context.adminStateUrl('admin-status'), 'api.php?action=admin-status&stateContext=bookings');
    const adminTable = context.els.admin;
    context.els.admin = null;
    assert.equal(context.adminStateUrl('state'), 'api.php?action=state');
    context.els.admin = adminTable;
    for (const body of ['', '  ', '{"ok":', '<html>Error</html>']) {
        await assert.rejects(context.readApiResponse({ status: 502, text: async () => body }), /HTTP 502/);
    }
    context.fetch = async (url, options) => {
        assert.equal(url, 'api.php?action=state&stateContext=bookings');
        assert.equal(options.cache, 'no-store');
        return { status: 200, text: async () => '{"ok":true,"state":{"loaded":true}}' };
    };
    await context.loadState();
    assert.equal(context.state.loaded, true);
    context.fetch = async () => ({ status: 503, text: async () => '' });
    await context.loadState();
    assert.match(context.els.admin.innerHTML, /empty response \(HTTP 503\)/);
    context.showLoadError('<server error>');
    assert.match(context.els.admin.innerHTML, /^<tr><td colspan="8">/);
    assert.match(context.els.admin.innerHTML, /&lt;server error&gt;/);
    for (const fetch of [
        async () => { throw new Error('Network failure'); },
        async () => ({ status: 502, text: async () => '<html>Error</html>' }),
        async () => ({ status: 200, text: async () => '' }),
    ]) {
        context.fetch = fetch;
        await context.updateStatus('court:1', 'Booked');
        assert.match(alerts.pop(), /check its status before trying again/);
    }
    context.fetch = async () => ({ status: 500, text: async () => JSON.stringify({ ok: false, message: 'Server failure' }) });
    await context.updateStatus('court:1', 'Booked');
    assert.equal(alerts.pop(), 'Server failure');
    context.fetch = async () => ({ status: 401, text: async () => JSON.stringify({ ok: false }) });
    await context.updateStatus('court:1', 'Booked');
    assert.equal(context.window.location.href, 'login.php');

    const api = fs.readFileSync(path.join(__dirname, '../api.php'), 'utf8');
    const jsonResponse = api.match(/^function json_response\([^]*?^}/m)[0];
    const result = spawnSync(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', [], {
        input: `<?php\n${jsonResponse}\njson_response(['ok'=>true,'name'=>"bad\\xB1name"]);`,
        encoding: 'utf8',
    });
    assert.equal(result.status, 0, result.stderr);
    assert.deepEqual(JSON.parse(result.stdout), { ok: true, name: 'bad\uFFFDname' });
    console.log('Booking errors: table layout, escaped text, network/JSON failures, server errors, expired login and invalid UTF-8 passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
