const fs=require('fs');
const path=require('path');
const {renderPage}=require('./generate_onepage_erd.cjs');
const pages=[
 renderPage([
  ['roles','users','residents','barangay_officials'],
  ['resident_registrations','registration_email_verifications','login_email_otps','residency_proofs'],
  ['announcements','incident_reports','events','messages'],
  ['activity_logs','iot_devices','sms_queue','sms_logs']
 ],'Part 1 — Accounts and community','part1'),
 renderPage([
  ['users','residents','document_types','document_requests'],
  ['facilities','facility_reservations','payments','document_files'],
  ['appointments','consultations','health_records','financial_records'],
  ['medicines','medicine_dispensing']
 ],'Part 2 — Services and payments','part2')
];
const total=key=>new Set(pages.flatMap(p=>p[key])).size;
if(total('names')!==28||total('fields')!==254||total('edges')!==40)throw Error('Missing schema content');
fs.writeFileSync(path.resolve(__dirname,'../docs/onelara-erd.drawio'),`<mxfile host="app.diagrams.net" type="device">${pages.map(p=>p.xml).join('')}</mxfile>`);
console.log('Verified both parts cover all 28 tables, 254 fields, and 40 relationships.');
