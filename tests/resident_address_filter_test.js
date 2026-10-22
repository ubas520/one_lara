const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
class Element {
    constructor() { this.value = ''; this.events = {}; this.options = []; this.dataset = {source: ''}; }
    addEventListener(name, fn) { this.events[name] = fn; }
    replaceChildren(...options) { this.options = options; this.value = ''; }
    add(option) { this.options.push(option); }
    focus() { this.focused = true; }
}
(async () => {
    const ids = ['residentAddressFilters', 'filterAddressProvince', 'filterAddressCity', 'filterAddressBarangay', 'residentAddressFilterStatus', 'retryResidentAddressFilter', 'clearResidentAddressFilter'];
    const elements = Object.fromEntries(ids.map(id => [id, new Element()]));
    let query = '', draws = 0, fail = false;
    const context = {
        window: {}, document: {getElementById: id => elements[id]},
        Option: function(text, value) { this.text = text; this.value = value; },
        fetch: async name => ({ok: !fail, json: async () => JSON.parse(fs.readFileSync(path.join(__dirname, '../assets/data', name), 'utf8'))})
    };
    const table = {column(name) {
        assert.equal(name, 'address:name');
        return {search(value) { query = value; return {draw() { draws++; }}; }};
    }};
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../assets/js/resident-address-filter.js'), 'utf8'), context);
    await context.window.initResidentAddressFilter(table);
    const p = elements.filterAddressProvince, c = elements.filterAddressCity, b = elements.filterAddressBarangay;
    const select = (element, name) => { element.value = element.options.find(o => o.text === name).value; element.events.change(); };
    assert.equal(c.disabled, true);
    select(p, 'Pampanga');
    assert.match('12 Main St, Lara, City of San Fernando (Capital), Pampanga', new RegExp(query, 'i'));
    const cityOption = c.options.find(o => o.text.includes('San Fernando'));
    select(c, cityOption.text);
    select(b, 'Lara');
    assert.match(`12 Main St, Lara, ${cityOption.text}, Pampanga`, new RegExp(query, 'i'));
    assert.doesNotMatch(`12 Main St, Other Barangay, ${cityOption.text}, Pampanga`, new RegExp(query, 'i'));
    assert.doesNotMatch('12 Main St, Lara, Other City, Pampanga', new RegExp(query, 'i'));
    select(p, 'Pampanga');
    assert.equal(c.value, ''); assert.equal(b.value, ''); assert.equal(b.disabled, true);
    elements.clearResidentAddressFilter.events.click();
    assert.equal(query, ''); assert.equal(p.value, ''); assert.equal(c.disabled, true); assert.equal(b.disabled, true);
    assert.equal(p.focused, true); assert(draws > 0);
    fail = true;
    await context.window.initResidentAddressFilter(table);
    assert.equal(elements.retryResidentAddressFilter.hidden, false);
    fail = false;
    await elements.retryResidentAddressFilter.events.click();
    assert.equal(elements.retryResidentAddressFilter.hidden, true);
    assert.equal(elements.residentAddressFilterStatus.textContent, '');
    console.log('PASS: cascading dropdowns, exact hierarchical matching, parent reset, Clear, and load retry.');
})().catch(error => { console.error(error); process.exitCode = 1; });
