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
        courts: [2, 7, 8, 9, 10, 11, 12, 1, 3].map(id => ({
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
const column = court => ({ court, label: `Court ${court}`, sport: court <= 2 ? 'Basketball' : court >= 10 ? 'Badminton' : 'Pickleball' });
function reserve(court, status = 'Booked') {
    context.state.bookings = { fixture: {
        id: 'court:123', date, time, court, status,
        sport: court <= 2 ? 'Basketball' : court >= 10 ? 'Badminton' : 'Pickleball', customerName: 'Test Player'
    } };
}

for (const status of ['Booked', 'Held']) {
    for (const wooden of [7, 8, 9, 10, 11, 12]) {
        for (const [existing, requested] of [[wooden >= 10 ? 1 : 2, wooden], [wooden, wooden >= 10 ? 1 : 2]]) {
            reserve(existing, status);
            const cell = context.adminScheduleCell(date, time, column(requested));
            assert.equal(cell.status, status);
            assert.equal(cell.reservationId, 'court:123');
            assert.match(cell.title, /unavailable because/);
            context.selectedSport = column(requested).sport;
            assert.equal(cell.status, context.relatedConflictFor(date, time, { id: requested, name: `Court ${requested}` }).status);
            assert.equal(context.adminScheduleCell('2026-09-27', time, column(requested)).status, 'Available');
            assert.equal(context.adminScheduleCell(date, '11 AM - 12 PM', column(requested)).status, 'Available');
            assert.equal(context.adminScheduleCell(date, time, column(wooden >= 10 ? 2 : 1)).status, 'Available');
            assert.equal(context.adminScheduleCell(date, time, column(3)).status, 'Available');
        }
    }
}

reserve(7);
assert.equal(context.adminScheduleCell(date, time, column(8)).status, 'Available');
assert.equal(context.adminScheduleCell(date, time, column(9)).status, 'Available');
assert.equal(context.adminScheduleCell(date, time, column(7)).sub, 'Test Player');
for (const court of [1, 2, 7, 8, 9, 10, 11, 12]) {
    reserve(court, 'Cancelled');
    for (const requested of [1, 2, 7, 8, 9, 10, 11, 12]) {
        assert.equal(context.adminScheduleCell(date, time, column(requested)).status, 'Available');
    }
}
context.state.bookings = {};
context.state.courtBlocks = [{ id: 1, date, time, courtId: 2, status: 'Active', courtName: 'Miami', reason: 'Maintenance' }];
assert.equal(context.adminScheduleCell(date, time, column(7)).status, 'Blocked');
console.log('Admin court conflicts passed: both directions, all Wooden courts, Held/Booked, portal parity, dates/times, unrelated courts, cancellations, direct bookings and blocks.');

// Dedicated Badminton courts conflict with Lakers, but not other wooden courts.
for (const status of ['Held', 'Booked']) {
    for (const wooden of [10, 11, 12]) {
        context.state.courtBlocks = [];
        reserve(wooden, status);
        assert.equal(context.adminScheduleCell(date, time, column(1)).status, status);
        assert.equal(context.adminScheduleCell(date, time, column(wooden)).status, status);
        for (const other of [7, 8, 9, 10, 11, 12].filter(id => id !== wooden)) {
            assert.equal(context.adminScheduleCell(date, time, column(other)).status, 'Available');
        }
        reserve(1, status);
        assert.equal(context.adminScheduleCell(date, time, column(wooden)).status, status);
        assert.equal(context.courtBlockApplies(wooden, 'Badminton', 1, 'Basketball'), true);
        assert.equal(context.courtBlockApplies(1, null, wooden, 'Badminton'), true);
        assert.equal(context.courtBlockApplies(7, 'Pickleball', wooden, 'Badminton'), false);
    }
}
console.log('Dedicated Badminton courts and Lakers conflicts passed.');

for (const court of [7, 8, 9, 10, 11, 12]) {
    for (const sport of ['Pickleball', 'Badminton']) {
        context.state.courtBlocks = [];
        reserve(court);
        context.state.bookings.fixture.sport = sport;
        const otherSport = sport === 'Pickleball' ? 'Badminton' : 'Pickleball';
        assert.equal(context.adminScheduleCell(date, time, { court, sport: otherSport }).status, 'Booked');
        assert.equal(context.adminScheduleCell(date, time, column(court < 10 ? 2 : 1)).status, 'Booked');
        assert.equal(context.courtBlockApplies(court, sport, court, otherSport), true);
    }
}
console.log('Both sports share every wooden court and retain parent-court conflicts.');
