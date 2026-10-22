const fs = require('fs');
const path = require('path');
const {tables, relations} = require('./generate_erd.cjs');
const out = path.resolve(__dirname, '../docs');
const pages = [
  ['Overview', [...tables.keys()]],
  ['Accounts', ['roles','users','barangay_officials','resident_registrations','residents','registration_email_verifications','login_email_otps','residency_proofs']],
  ['Documents and payments', ['users','residents','document_types','document_requests','payments','document_files','facility_reservations','facilities']],
  ['Health', ['users','residents','appointments','consultations','health_records','medicine_dispensing','medicines']],
  ['Community', ['users','barangay_officials','residents','announcements','incident_reports','events','financial_records','activity_logs','messages']],
  ['SMS', ['iot_devices','sms_queue','sms_logs']]
];
const esc = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const geom=(x,y,w,h)=>`<mxGeometry x="${x}" y="${y}" width="${w}" height="${h}" as="geometry"/>`;
let serial=0, checked=0;
const edgeCoverage=new Set();
// Check open rectangle interiors, allowing connections to terminate on a border.
function hits(a,b,r,pad=0) {
  const l=r.x-pad, t=r.y-pad, right=r.x+r.w+pad, bottom=r.y+r.h+pad;
  return a.x===b.x ? a.x>l && a.x<right && Math.max(a.y,b.y)>t && Math.min(a.y,b.y)<bottom
    : a.y>t && a.y<bottom && Math.max(a.x,b.x)>l && Math.min(a.x,b.x)<right;
}
function route(start,end,rects,usedX,usedY,ports,offset) {
  const free=(v,blocked,direction)=>{while(blocked.some(n=>Math.abs(n-v)<12)) v+=12*direction;return v;};
  const xs=[...new Set([start.x,end.x,...rects.flatMap(r=>[free(r.x-offset,usedX,-1),free(r.x+r.w+offset,usedX,1)])])].sort((a,b)=>a-b);
  const blockedY=[...usedY,...ports];
  const ys=[...new Set([start.y,end.y,...rects.flatMap(r=>[free(r.y-offset,blockedY,-1),free(r.y+r.h+offset,blockedY,1)])])].sort((a,b)=>a-b);
  const key=(x,y)=>`${x},${y}`;
  const first=key(start.x,start.y), last=key(end.x,end.y);
  const dist=new Map([[first,0]]), prev=new Map(), queue=[{...start,d:0}];
  while(queue.length) {
    queue.sort((a,b)=>b.d-a.d);
    const a=queue.pop(), k=key(a.x,a.y);
    if(a.d!==dist.get(k)) continue;
    if(k===last) break;
    const xi=xs.indexOf(a.x), yi=ys.indexOf(a.y);
    const candidates=[{x:xs[xi-1],y:a.y},{x:xs[xi+1],y:a.y},{x:a.x,y:ys[yi-1]},{x:a.x,y:ys[yi+1]}];
    for(const b of candidates) {
      if(b.x===undefined||b.y===undefined||rects.some(r=>hits(a,b,r,18))) continue;
      const bk=key(b.x,b.y), d=a.d+Math.abs(a.x-b.x)+Math.abs(a.y-b.y);
      if(d<(dist.get(bk)??Infinity)) {dist.set(bk,d);prev.set(bk,k);queue.push({...b,d});}
    }
  }
  if(!dist.has(last)) throw Error('No clear connector route');
  const points=[];
  for(let k=last;k;k=prev.get(k)) {const [x,y]=k.split(',').map(Number);points.unshift({x,y});}
  return points.filter((p,i)=>i===0||i===points.length-1||!((points[i-1].x===p.x&&points[i+1].x===p.x)||(points[i-1].y===p.y&&points[i+1].y===p.y)));
}
let xml='<mxfile host="app.diagrams.net" type="device">';
for(const [title,names] of pages) {
  const overview=title==='Overview', cols=overview?5:3, positions=new Map(), ids=new Map();
  const pageRelations=relations.map((r,index)=>({...r,index})).filter(r=>names.includes(r.parent)&&names.includes(r.child));
  const gap=120+pageRelations.length*28, margin=gap/2+180;
  const portStep=cols*24;
  const maxDegree=Math.max(...names.map(n=>pageRelations.filter(r=>r.parent===n||r.child===n).length));
  const rowHeight=Math.max(230,80+maxDegree*portStep);
  names.forEach((name,i)=>{
    const table=tables.get(name);
    const fields=table.columns.filter(c=>table.pk.includes(c.name)||relations.some(r=>r.child===name&&r.column===c.name));
    const degree=pageRelations.filter(r=>r.parent===name||r.child===name).length;
    positions.set(name,{x:margin+(i%cols)*(350+gap),y:margin+160+Math.floor(i/cols)*(rowHeight+gap),w:350,h:Math.max(42+fields.length*29,80+degree*portStep),fields,col:i%cols,port:0});
    ids.set(name,`t${++serial}`);
  });
  const rects=[...positions.values()], pageW=cols*(350+gap)+2*margin,pageH=Math.ceil(names.length/cols)*(rowHeight+gap)+2*margin+160;
  const ports=[];
  for(const r of pageRelations) {
    for(const [key,name] of [['startY',r.parent],['endY',r.child]]) {
      const p=positions.get(name);
      r[key]=p.y+50+p.port++*portStep+p.col*24;
      ports.push(r[key]);
    }
  }
  const usedX=[],usedY=[],segments=[];
  xml+=`<diagram id="p${serial}" name="${esc(title)}"><mxGraphModel grid="1" gridSize="10" page="1" pageWidth="${pageW}" pageHeight="${pageH}" tooltips="1"><root><mxCell id="0"/><mxCell id="1" parent="0"/>`;
  xml+=`<mxCell id="heading${serial}" value="ONELARA / ${esc(title)}" style="text;align=left;fontSize=26;fontStyle=1;" vertex="1" parent="1">${geom(80,30,pageW-160,45)}</mxCell>`;
  xml+=`<mxCell id="legend${serial}" value="Primary keys and relationship fields only. PK = primary key; FK = database foreign key; REF = application reference.&#xa;Solid lines = database constraints; dashed lines = application links. Circle = optional; crow's foot = many.&#xa;Full column definitions are on table hover. Tables repeated across pages refer to the same entity." style="text;align=left;verticalAlign=top;fontSize=14;" vertex="1" parent="1">${geom(80,85,pageW-160,70)}</mxCell>`;
  // Draw connectors behind tables and use fixed perimeter ports with explicit bend points.
  for(const [lane,r] of pageRelations.entries()) {
    const index=r.index;
    const a=positions.get(r.parent), b=positions.get(r.child);
    const right=b.x>=a.x, sy=(r.startY-a.y)/a.h, ty=(r.endY-b.y)/b.h;
    const start={x:a.x+(right?a.w:0),y:a.y+a.h*sy};
    const end={x:b.x+(right?0:b.w),y:b.y+b.h*ty};
    const offset=30+lane*12;
    const via=route({x:start.x+(right?offset:-offset),y:start.y},{x:end.x+(right?-offset:offset),y:end.y},rects,usedX,usedY,ports,offset);
    const points=[start,...via,end];
    for(let i=1;i<points.length;i++) {
      if(rects.some(rect=>hits(points[i-1],points[i],rect))) throw Error(`Connector intersects table: ${r.parent} -> ${r.child}`);
      const u=points[i-1],v=points[i],vertical=u.x===v.x;
      for(const [s,t] of segments) {
        if(vertical && s.x===t.x && Math.abs(s.x-u.x)<0.001 && Math.min(Math.max(u.y,v.y),Math.max(s.y,t.y))-Math.max(Math.min(u.y,v.y),Math.min(s.y,t.y))>0.001) throw Error('Overlapping vertical lines');
        if(!vertical && s.y===t.y && Math.abs(s.y-u.y)<0.001 && Math.min(Math.max(u.x,v.x),Math.max(s.x,t.x))-Math.max(Math.min(u.x,v.x),Math.min(s.x,t.x))>0.001) throw Error('Overlapping horizontal lines');
      }
      if(vertical) usedX.push(u.x);else usedY.push(u.y);
      checked++;
    }
    for(let i=1;i<points.length;i++) segments.push([points[i-1],points[i]]);
    const t=tables.get(r.child), c=t.columns.find(c=>c.name===r.column);
    const one=t.pk.includes(r.column)||t.unique.includes(r.column);
    const style=`edgeStyle=none;noEdgeStyle=1;rounded=0;html=0;exitX=${right?1:0};exitY=${sy};exitPerimeter=0;entryX=${right?0:1};entryY=${ty};entryPerimeter=0;startArrow=${c.nullable||r.column==='payable_id'?'ERzeroToOne':'ERone'};endArrow=${one?'ERzeroToOne':'ERzeroToMany'};startSize=13;endSize=13;strokeWidth=1.5;strokeColor=${r.logical?'#B7791F':'#64748B'};${r.logical?'dashed=1;':''}`;
    xml+=`<object id="e${++serial}" label="" tooltip="${esc(`${r.parent}.${r.target} to ${r.child}.${r.column}${r.note?' / '+r.note:''}`)}"><mxCell style="${style}" edge="1" parent="1" source="${ids.get(r.parent)}" target="${ids.get(r.child)}"><mxGeometry relative="1" as="geometry"><Array as="points">${via.map(p=>`<mxPoint x="${p.x}" y="${p.y}"/>`).join('')}</Array></mxGeometry></mxCell></object>`;
    edgeCoverage.add(index);
  }
  for(const name of names) {
    const t=tables.get(name), p=positions.get(name), id=ids.get(name);
    xml+=`<object id="${id}" label="${name}" tooltip="${esc(t.columns.map(c=>c.definition).join('\n'))}"><mxCell style="swimlane;startSize=42;horizontal=1;collapsible=0;rounded=0;fillColor=#E8EFF7;swimlaneFillColor=#FFFFFF;strokeColor=#94A3B8;fontSize=16;fontStyle=1;align=left;spacingLeft=12;" vertex="1" parent="1">${geom(p.x,p.y,p.w,p.h)}</mxCell></object>`;
    p.fields.forEach((c,i)=>{
      const marks=[];
      if(t.pk.includes(c.name)) marks.push('PK');
      if(relations.some(r=>r.child===name&&r.column===c.name&&!r.logical)) marks.push('FK');
      if(relations.some(r=>r.child===name&&r.column===c.name&&r.logical)) marks.push('REF');
      xml+=`<mxCell id="f${++serial}" value="${esc(marks.join('/')+'   '+c.name)}" style="text;align=left;verticalAlign=middle;spacingLeft=12;fontSize=14;fillColor=#FFFFFF;" vertex="1" parent="${id}">${geom(0,42+i*29,p.w,29)}</mxCell>`;
    });
  }
  xml+='</root></mxGraphModel></diagram>';
}
xml+='</mxfile>';
if(edgeCoverage.size!==relations.length) throw Error('Missing relationships');
const main=path.join(out,'onelara-erd.drawio'), detailed=path.join(out,'onelara-erd-detailed.drawio');
if(!fs.existsSync(detailed)) fs.copyFileSync(main,detailed);
fs.writeFileSync(main,xml);
console.log(`Simplified 6 pages; all ${tables.size} tables and ${relations.length} relationships retained. Checked ${checked} connector segments: zero table intersections and zero shared line segments.`);
