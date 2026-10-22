const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
class Element {
    constructor() { this.value = ''; this.events = {}; this.options = []; }
    addEventListener(name, fn) { this.events[name] = fn; }
    replaceChildren(...options) { this.options = options; this.value = ''; }
    add(option) { this.options.push(option); }
}
async function run() {
    const elements = Object.fromEntries(['province', 'city', 'barangay', 'street', 'status', 'retry', 'form'].map(key => [key, new Element()]));
    const picker = {
        dataset: {source: '', selection: JSON.stringify({province: '', city: '', barangay: ''})},
        querySelector(selector) {
            if (selector === '.address-status') return elements.status;
            if (selector === '.address-retry') return elements.retry;
            return elements[selector.match(/address_(\w+)/)[1]];
        },
        closest() { return elements.form; }
    };
    const context = {
        document: {querySelector: () => picker, getElementById: () => elements.street},
        window: {}, Option: function(text, value) { this.text = text; this.value = value; },
        fetch: async filename => ({ok: true, json: async () => JSON.parse(fs.readFileSync(path.join(__dirname, '../assets/data', filename), 'utf8'))})
    };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../assets/js/address-picker.js'), 'utf8'), context);
    await new Promise(resolve => setImmediate(resolve));
    const {province, city, barangay, street, form} = elements;
    assert(province.options.some(row => row.text === 'Pampanga'));
    province.value = '035400000'; province.events.change();
    assert(city.options.some(row => row.text.includes('San Fernando')));
    city.value = city.options.find(row => row.text.includes('San Fernando')).value; city.events.change();
    barangay.value = barangay.options.find(row => row.text === 'Lara').value;
    street.value = '12 Sample Street';
    let prevented = false;
    form.events.submit({preventDefault() { prevented = true; }});
    assert.equal(prevented, false);
    const complete = '12 Sample Street, Lara, ' + city.options.find(row => row.value === city.value).text + ', Pampanga';
    context.window.setResidentAddress(complete);
    assert.equal(street.value, '12 Sample Street');
    assert.equal(barangay.options.find(row => row.value === barangay.value).text, 'Lara');
    province.value = '012800000'; province.events.change();
    assert.equal(city.value, ''); assert.equal(barangay.value, '');
    form.events.submit({preventDefault() { prevented = true; }});
    assert.equal(prevented, true);
    context.window.setResidentAddress('Old unstructured address');
    assert.equal(street.value, 'Old unstructured address');
    assert.equal(province.required, false);
    street.value = 'Changed address'; street.events.input();
    assert.equal(province.required, true);
    context.window.setResidentAddress('');
    assert.equal(province.value, ''); assert.equal(street.value, '');
    console.log('PASS: dropdown filtering, parent resets, address restore, legacy editing, and submit checks');
}
run().catch(error => { console.error(error); process.exitCode = 1; });
