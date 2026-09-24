const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../assets/js/app.js'), 'utf8');
const names = [
    'isActiveReservation', 'compactStatusLabel', 'isMiamiCourt', 'isWoodenCourt',
    'bookingResourcesConflict', 'bookingsAt', 'directBookingAt', 'courtById',
    'courtBlockApplies', 'blockCell', 'scheduleCell', 'adminBookingCustomerDisplay',
    'adminScheduleCell', 'bookingFor', 'courtDisplayName', 'blockConflictFor', 'relatedConflictFor'
];
const context = vm.createContext({
    selectedSport: 'Pickleball',
    state: {
        bookings: {}, courtBlocks: [],
        courts: [2, 7, 8, 9, 1, 3].map(id => ({
            id, name: id === 2 ? 'Miami' : `Court ${id}`
        }))
    }
});
for (const name of names) {
    const match = source.match(new RegExp(`^function ${name}\\([^]*?^}`, 'm'));
    assert.ok(match, `Missing function ${name}`);
    vm.runInContext(match[0], context);
}

const date = '2026-09-26';
const time = '10 AM - 11 AM';
const column = court => ({ court, label: `Court ${court}`, sport: court === 2 ? 'Basketball' : 'Pickleball' });
function reserve(court, status = 'Booked') {
    context.state.bookings = { fixture: {
        id: 'court:123', date, time, court, status,
        sport: court === 2 ? 'Basketball' : 'Pickleball', customerName: 'Test Player'
    } };
}

for (const status of ['Booked', 'Held']) {
    for (const wooden of [7, 8, 9]) {
        for (const [existing, requested] of [[2, wooden], [wooden, 2]]) {
            reserve(existing, status);
            const cell = context.adminScheduleCell(date, time, column(requested));
            assert.equal(cell.status, status);
            assert.equal(cell.reservationId, 'court:123');
            assert.match(cell.title, /unavailable because/);
            context.selectedSport = column(requested).sport;
            assert.equal(cell.status, context.relatedConflictFor(date, time, { id: requested, name: `Court ${requested}` }).status);
            assert.equal(context.adminScheduleCell('2026-09-27', time, column(requested)).status, 'Available');
            assert.equal(context.adminScheduleCell(date, '11 AM - 12 PM', column(requested)).status, 'Available');
            assert.equal(context.adminScheduleCell(date, time, column(1)).status, 'Available');
            assert.equal(context.adminScheduleCell(date, time, column(3)).status, 'Available');
        }
    }
}

reserve(7);
assert.equal(context.adminScheduleCell(date, time, column(8)).status, 'Available');
assert.equal(context.adminScheduleCell(date, time, column(9)).status, 'Available');
assert.equal(context.adminScheduleCell(date, time, column(7)).sub, 'Test Player');
for (const court of [2, 7, 8, 9]) {
    reserve(court, 'Cancelled');
    for (const requested of [2, 7, 8, 9]) {
        assert.equal(context.adminScheduleCell(date, time, column(requested)).status, 'Available');
    }
}
context.state.bookings = {};
context.state.courtBlocks = [{ id: 1, date, time, courtId: 2, status: 'Active', courtName: 'Miami', reason: 'Maintenance' }];
assert.equal(context.adminScheduleCell(date, time, column(7)).status, 'Blocked');
console.log('Admin court conflicts passed: both directions, all Wooden courts, Held/Booked, portal parity, dates/times, unrelated courts, cancellations, direct bookings and blocks.');
