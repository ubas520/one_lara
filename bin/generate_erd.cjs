// Generate editable diagrams.net cells directly from the checked-in SQL schemas.
const fs = require('fs');
const path = require('path');
const base = path.resolve(__dirname, '..');
const sources = ['onelara_database.sql', 'bin/registration_email_schema.sql', 'bin/email_otp_schema.sql'];
const tables = new Map();
const relations = [];
for (const source of sources) {
  const sql = fs.readFileSync(path.join(base, source), 'utf8');
  for (const match of sql.matchAll(/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?\s*\(([\s\S]*?)\) ENGINE=/gi)) {
    const [, name, body] = match;
    const columns = [];
    const pk = [...(body.match(/PRIMARY KEY\s*\(([^)]+)\)/i)?.[1] || '').matchAll(/`(\w+)`/g)].map(m => m[1]);
    const unique = [...body.matchAll(/UNIQUE KEY\s+`\w+`\s*\(`(\w+)`\)/g)].map(m => m[1]);
    for (const line of body.split('\n')) {
      const col = line.match(/^\s*(?:`(\w+)`|(\w+))\s+((?:enum\([^\n]*?\)|\w+(?:\([^)]*\))?)(?:\s+unsigned)?)(.*?)(?:,)?\s*$/i);
      if (!col || /^(PRIMARY|KEY|UNIQUE|CONSTRAINT)$/i.test(col[1] || col[2])) continue;
      const n = col[1] || col[2];
      if (/PRIMARY KEY/i.test(col[4])) pk.push(n);
      columns.push({name:n, type:col[3].toLowerCase(), nullable:!(/NOT NULL|PRIMARY KEY/i.test(col[4])), definition:line.trim().replace(/,$/, '')});
    }
    tables.set(name, {name, columns, pk, unique, source});
    for (const fk of body.matchAll(/CONSTRAINT `([^`]+)` FOREIGN KEY \(`([^`]+)`\) REFERENCES `([^`]+)` \(`([^`]+)`\)([^\n]*)/g)) {
      relations.push({child:name, column:fk[2], parent:fk[3], target:fk[4], constraint:fk[1], logical:false});
    }
  }
}
for (const [child,column,parent,target,note] of [
  ['resident_registrations','user_id','users','id','Application-managed'],
  ['resident_registrations','reviewed_by','users','id','Application-managed'],
  ['residency_proofs','resident_id','residents','id','Application-managed'],
  ['registration_email_verifications','user_id','users','id','Application-managed'],
  ['login_email_otps','user_id','users','id','Application-managed'],
  ['payments','payable_id','document_requests','id',"payable_type = 'document_request'"],
  ['payments','payable_id','facility_reservations','id',"payable_type = 'facility_reservation'"]
]) relations.push({child,column,parent,target,note,logical:true});
module.exports = { tables, relations };
if (require.main === module) {
const pages = [
  ['01 — Complete overview', [...tables.keys()], true],
  ['02 — Accounts and registration', ['roles','users','barangay_officials','resident_registrations','residents','registration_email_verifications','login_email_otps','residency_proofs']],
  ['03 — Documents and payments', ['users','residents','document_types','document_requests','payments','document_files','facility_reservations','facilities']],
  ['04 — Health services', ['users','residents','appointments','consultations','health_records','medicine_dispensing','medicines']],
  ['05 — Community and administration', ['users','barangay_officials','residents','announcements','incident_reports','events','financial_records','activity_logs','messages']],
  ['06 — SMS and devices', ['iot_devices','sms_queue','sms_logs']]
];
const esc = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
let serial = 0;
const cell = (attrs, geo='') => `<mxCell ${Object.entries(attrs).map(([k,v])=>`${k}="${esc(v)}"`).join(' ')}>${geo}</mxCell>`;
const geometry = (x,y,w,h) => `<mxGeometry x="${x}" y="${y}" width="${w}" height="${h}" as="geometry"/>`;
const coverage = new Set();
const renderedEdges = new Set();
let xml = '<mxfile host="app.diagrams.net" version="24.7.17" type="device">';
for (const [pageName, names, overview] of pages) {
  const cols = overview ? 5 : 3, width = overview ? 340 : 440;
  const gap = overview ? 170 : 220;
  const positions = new Map(), ids = new Map();
  let y = 180;
  for (let offset=0; offset<names.length; offset+=cols) {
    let height=0;
    names.slice(offset, offset+cols).forEach((name, i) => {
      const t=tables.get(name);
      const shown=t.columns.filter(c=>!overview || t.pk.includes(c.name) || relations.some(r=>r.child===name && r.column===c.name));
      const h=38+shown.length*26;
      positions.set(name,{x:60+i*(width+gap),y,h,shown}); height=Math.max(height,h);
      ids.set(name,`t${++serial}`);
    });
    y+=height+160;
  }
  const pageWidth=cols*(width+gap)+60;
  xml+=`<diagram id="page${serial}" name="${esc(pageName)}"><mxGraphModel dx="1400" dy="900" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="${pageWidth}" pageHeight="${y+40}" math="0" shadow="0"><root><mxCell id="0"/><mxCell id="1" parent="0"/>`;
  xml+=cell({id:`title${serial}`,value:`ONELARA | ${pageName}`,style:'text;html=0;align=left;fontSize=26;fontStyle=1;fontColor=#16324F;',vertex:1,parent:1},geometry(60,25,pageWidth-120,45));
  xml+=cell({id:`legend${serial}`,value:`${overview?'All 28 tables • key columns only • full attributes on pages 02–06':'Full attributes • repeated tables are context copies of the same entity'}\nPK = primary key | FK = database foreign key | UK = unique key | REF = application reference | ? = nullable\nSolid = database constraint; dashed = application-managed. Crow’s foot = 0..many; circle/bar = 0..1; double bar = exactly 1.\nEnum values and complete column definitions are available on hover. Source: checked-in SQL + application references; not a live database inspection.`,style:'text;html=0;align=left;verticalAlign=top;fontSize=13;fontColor=#475569;whiteSpace=wrap;',vertex:1,parent:1},geometry(60,80,pageWidth-120,90));
  for (const name of names) {
    const t=tables.get(name), p=positions.get(name), id=ids.get(name);
    const core=['users','residents','roles'].includes(name);
    xml+=cell({id,value:name,style:`swimlane;html=0;startSize=38;horizontal=1;rounded=0;collapsible=0;fontStyle=1;fontSize=16;align=left;spacingLeft=12;fillColor=${core?'#DAE8FC':'#D5E8D4'};swimlaneFillColor=#FFFFFF;strokeColor=${core?'#6C8EBF':'#82B366'};`,vertex:1,parent:1},geometry(p.x,p.y,width,p.h));
    p.shown.forEach((c,i)=>{
      const marks=[];
      if(t.pk.includes(c.name)) marks.push('PK');
      if(relations.some(r=>r.child===name && r.column===c.name && !r.logical)) marks.push('FK');
      if(t.unique.includes(c.name)) marks.push('UK');
      if(relations.some(r=>r.child===name && r.column===c.name && r.logical)) marks.push('REF');
      const type=c.type.startsWith('enum(')?'enum':c.type;
      const rowId=`r${++serial}`;
      xml+=`<object id="${rowId}" label="${esc(`${marks.length?marks.join(',')+'  ':''}${c.name} : ${type}${c.nullable?' ?':''}`)}" tooltip="${esc(c.definition)}">`;
      xml+=cell({style:`text;html=0;align=left;verticalAlign=middle;spacingLeft=12;fontFamily=Consolas;fontSize=${overview?11:12};fillColor=${i%2?'#F1F5F9':'#FFFFFF'};strokeColor=none;`,vertex:1,parent:id},geometry(0,38+i*26,width,26))+'</object>';
      if(!overview) coverage.add(`${name}.${c.name}`);
    });
  }
  for (const [i,r] of relations.entries()) {
    if(!ids.has(r.child)||!ids.has(r.parent)) continue;
    const t=tables.get(r.child), c=t.columns.find(c=>c.name===r.column);
    const unique=t.pk.includes(r.column)||t.unique.includes(r.column);
    const optional=c.nullable||r.column==='payable_id';
    const style=`edgeStyle=orthogonalEdgeStyle;rounded=0;html=0;startArrow=${optional?'ERzeroToOne':'ERone'};startFill=0;endArrow=${unique?'ERzeroToOne':'ERzeroToMany'};endFill=0;startSize=14;endSize=14;strokeColor=${r.logical?'#B57324':'#64748B'};${r.logical?'dashed=1;':''}fontSize=10;labelBackgroundColor=#FFFFFF;`;
    xml+=cell({id:`e${++serial}`,value:overview?'':r.column+(r.note&&r.column==='payable_id'?'\n'+r.note:''),style,edge:1,parent:1,source:ids.get(r.parent),target:ids.get(r.child)},'<mxGeometry relative="1" as="geometry"/>');
    renderedEdges.add(i);
  }
  xml+='</root></mxGraphModel></diagram>';
}
xml+='</mxfile>';
const allColumns=[...tables.values()].flatMap(t=>t.columns.map(c=>`${t.name}.${c.name}`));
if(tables.size!==28 || coverage.size!==allColumns.length || renderedEdges.size!==relations.length) throw Error('Incomplete schema coverage');
fs.mkdirSync(path.join(base,'docs'),{recursive:true});
fs.writeFileSync(path.join(base,'docs','onelara-erd.drawio'),xml);
console.log(`Created 6 editable pages: ${tables.size} tables, ${allColumns.length} columns, ${relations.filter(r=>!r.logical).length} database foreign keys, ${relations.filter(r=>r.logical).length} application references. Coverage verified.`);
}
