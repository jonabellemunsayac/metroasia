const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/open-play.js'), 'utf8');
const buttons = Array.from({length: 5}, (_, index) => ({
    disabled: false,
    dataset: {cellStatus: 'Available', time: `t${index}`, timeSlotId: String(index + 1),
        date: '2098-10-20', courtId: '7', sport: 'Pickleball'}
}));
const slots = Object.fromEntries(buttons.map((button, index) => [button.dataset.time,
    {id: index + 1, startsAt: `${9 + index}:00`, endsAt: `${10 + index}:00`}]));
const context = vm.createContext({
    state: {slotDetails: slots}, openPlaySelectedCells: [],
    els: {adminScheduleGrid: {querySelectorAll: () => buttons}},
    adminCanManageOperations: () => true,
    slotAvailableForSport: slot => slot.enabled !== false,
    courtById: () => ({name: 'Wooden Court 5'}),
    timeToMinutes: time => Number(time.split(':')[0]) * 60,
    syncOpenPlayCalendarSelection: () => {}
});
for (const name of ['openPlayCalendarCell', 'highlightOpenPlayRange']) {
    vm.runInContext(source.match(new RegExp(`^function ${name}\\([^]*?^}`, 'm'))[0], context);
}
const cell = index => context.openPlayCalendarCell(buttons[index]);
const ids = () => Array.from(context.openPlaySelectedCells, item => item.id);
context.highlightOpenPlayRange(cell(0), cell(3));
assert.deepEqual(ids(), [1, 2, 3, 4]);
context.highlightOpenPlayRange(cell(3), cell(0));
assert.deepEqual(ids(), [1, 2, 3, 4]);
for (const status of ['Booked', 'Held', 'Blocked']) {
    buttons[1].dataset.cellStatus = status;
    context.highlightOpenPlayRange(cell(0), cell(3));
    assert.deepEqual(ids(), [1]);
    context.highlightOpenPlayRange(cell(3), cell(0));
    assert.deepEqual(ids(), [3, 4]);
}
buttons[1].dataset.cellStatus = 'Available';
slots.t1.enabled = false;
context.highlightOpenPlayRange(cell(0), cell(3));
assert.deepEqual(ids(), [1]);
slots.t1.enabled = true;
buttons[1].disabled = true;
context.highlightOpenPlayRange(cell(0), cell(3));
assert.deepEqual(ids(), [1]);
buttons[1].disabled = false;
for (const changed of [{date: '2098-10-21'}, {court: 8}, {sport: 'Badminton'}]) {
    context.highlightOpenPlayRange(cell(0), {...cell(3), ...changed});
    assert.deepEqual(ids(), [1]);
}
// A filtered-out hour cannot silently turn a disjoint range into a session.
const removed = buttons.splice(1, 1)[0];
context.highlightOpenPlayRange(context.openPlayCalendarCell(buttons[0]), context.openPlayCalendarCell(buttons[2]));
assert.deepEqual(ids(), [1]);
buttons.splice(1, 0, removed);
console.log('Open Play selection: consecutive ranges, reverse dragging, conflicts, disabled/missing slots and court/date/sport boundaries passed.');
