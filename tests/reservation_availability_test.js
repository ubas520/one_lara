const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
class Element {
    constructor(value = '') { this.value = value; this.events = {}; }
    addEventListener(name, fn) { this.events[name] = fn; }
    setCustomValidity(message) { this.error = message; }
    setAttribute() {}
}
const date = new Element('2026-10-23'), start = new Element('10:30'), end = new Element('11:30');
const facility = new Element('1'), submit = new Element(), note = new Element(), busy = new Element();
const form = new Element();
form.dataset = {serverNow: Date.parse('2026-10-22T12:00:00+08:00'), bookings: JSON.stringify([
    {facility_id: 1, reserve_date: '2026-10-23', start_time: '10:00:00', end_time: '12:00:00'}
])};
form.querySelector = selector => selector.includes('facility_id') ? facility : submit;
form.checkValidity = () => !date.error && !start.error && !end.error;
form.reportValidity = () => {};
const ids = {'reservation-form': form, 'reservation-date': date, 'reservation-start': start, 'reservation-end': end, 'reservation-time-note': note, 'reservation-busy-times': busy};
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname, '../assets/js/reservation-time.js'), 'utf8'), {
    document: {getElementById: id => ids[id], addEventListener() {}}, window: {addEventListener() {}}, setInterval() {}, Date
});
assert.equal(submit.disabled, true);
assert.match(note.textContent, /Not available/);
assert.match(busy.textContent, /10:00–12:00/);
let prevented = false;
form.events.submit({preventDefault() { prevented = true; }});
assert(prevented);
for (const [from, to, conflict] of [['09:00','10:00',false],['12:00','13:00',false],['09:00','13:00',true],['10:00','12:00',true]]) {
    start.value = from; end.value = to; start.events.input();
    assert.equal(submit.disabled, conflict, `${from}-${to}`);
}
facility.value = '2'; facility.events.change();
assert.equal(submit.disabled, false);
assert.equal(busy.textContent, '');
facility.value = '1'; date.value = '2026-10-24'; date.events.input();
assert.equal(submit.disabled, false);
assert.equal(start.error, '');
console.log('PASS: overlap, containment, adjacent slots, submission block, and facility/date resets.');
