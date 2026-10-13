const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/app.js'), 'utf8');
const context = vm.createContext({state: {slotDetails: {}}, selectedSport: 'Pickleball'});
for (const name of ['timeToMinutes', 'slotStartMinutes', 'slotEndMinutes', 'minutesToDisplay', 'mergeBookingConfirmationSlots']) {
    vm.runInContext(source.match(new RegExp(`^function ${name}\\([^]*?^}`, 'm'))[0], context);
}
const slot = {date: '2026-10-09', time: '9 AM - 12 PM', court: 7, courtName: 'Wooden Court 5', sport: 'Badminton', openPlay: {start: '09:00:00', end: '12:00:00'}};
const range = context.mergeBookingConfirmationSlots([slot])[0];
assert.equal(context.minutesToDisplay(range.start), '9:00 AM');
assert.equal(context.minutesToDisplay(range.end), '12:00 PM');
assert.equal(range.date, slot.date);
assert.equal(range.courtName, slot.courtName);
assert.equal(range.sport, slot.sport);
context.state.slotDetails.regular = {startsAt:'14:00', endsAt:'15:00'};
const regular = context.mergeBookingConfirmationSlots([{date:slot.date,time:'regular',court:1,sport:'Basketball'}])[0];
assert.equal(regular.start, 840);
assert.equal(regular.end, 900);
const midnight = context.mergeBookingConfirmationSlots([{...slot,openPlay:{start:'23:00',end:'00:00'}}])[0];
assert.equal(midnight.end, 1440);
console.log('Confirmation uses Open Play session times after state refresh; regular bookings and midnight endings remain correct.');
