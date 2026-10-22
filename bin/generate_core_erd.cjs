// A focused view of the main resident service workflows, not the full schema.
const fs = require('fs');
const path = require('path');
const { renderPage } = require('./generate_onepage_erd.cjs');
const { tables, relations } = require('./generate_erd.cjs');
const layout = [
  ['roles', 'users', 'residents', 'health_records'],
  ['document_types', 'document_requests', 'appointments', 'consultations'],
  ['facilities', 'facility_reservations', 'payments', 'medicine_dispensing'],
  ['medicines']
];
const page = renderPage(layout, 'Main tables — simplified ERD', 'main-tables');
const selected = new Set(layout.flat());
const expectedFields = [...selected].flatMap(name => tables.get(name).columns.map(c => `${name}.${c.name}`));
const expectedEdges = relations.map((r, i) => ({ ...r, i })).filter(r => selected.has(r.parent) && selected.has(r.child));
if (page.fields.length !== expectedFields.length || expectedFields.some(f => !page.fields.includes(f)) || page.edges.length !== expectedEdges.length) throw Error('Incomplete main-table view');
page.xml = page.xml.replace('Crossed lines are not junctions. Users and residents repeated on both parts are the same entities. Full SQL details on hover.', 'Simplified view: accounts, documents, reservations, payments, and health services. Supporting and community tables omitted. Full SQL details on hover.');
const output = path.resolve(__dirname, '../docs/onelara-erd.drawio');
const backup = path.resolve(__dirname, '../docs/onelara-erd-complete.drawio');
if (!fs.existsSync(backup)) fs.copyFileSync(output, backup);
fs.writeFileSync(output, `<mxfile host="app.diagrams.net" type="device">${page.xml}</mxfile>`);
console.log(`Saved one simplified page with ${selected.size} tables, ${expectedFields.length} fields, and ${expectedEdges.length} relationships. Complete diagram preserved separately.`);
