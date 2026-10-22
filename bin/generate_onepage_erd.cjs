const fs = require('fs');
const path = require('path');
const { tables, relations } = require('./generate_erd.cjs');
const out = path.resolve(__dirname, '../docs');
const esc = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const defaultLayout = [
 ['roles','resident_registrations','registration_email_verifications','login_email_otps','activity_logs'],
 ['barangay_officials','users','residents','residency_proofs','health_records'],
 ['announcements','incident_reports','appointments','consultations','medicines'],
 ['events','document_types','document_requests','medicine_dispensing','messages'],
 ['financial_records','facilities','facility_reservations','payments','document_files'],
 ['iot_devices','sms_queue','sms_logs']
];
function renderPage(layout, title='OneLara ERD', pageId='onelara') {
const W=420, STEP=12, PW=Math.max(...layout.map(row=>row.length))*696+192, PH=layout.length*720+228;
const names=new Set(layout.flat());
const boxes=new Map(), edges=relations.map((r,i)=>({...r,i})).filter(r=>names.has(r.parent)&&names.has(r.child));
layout.forEach((row,j)=>row.forEach((name,i)=>{
 const t=tables.get(name), fields=t.columns;
 boxes.set(name,{name,x:156+i*696,y:204+j*720,w:W,h:48+fields.length*24,fields,ports:{left:[],right:[]}});
}));
for(const e of edges) {
 const a=boxes.get(e.parent),b=boxes.get(e.child);
 e.as=b.x>=a.x?'right':'left';e.bs=b.x>=a.x?'left':'right';
 a.ports[e.as].push(e);b.ports[e.bs].push(e);
}
for(const b of boxes.values()) {
 b.h=Math.max(b.h,48+24*Math.max(b.ports.left.length,b.ports.right.length));
 for(const side of ['left','right']) {
  b.ports[side].sort((a,c)=>{
   const other=e=>boxes.get(e.parent===b.name?e.child:e.parent);
   return other(a).y-other(c).y||other(a).x-other(c).x;
  });
  b.ports[side].forEach((e,i)=>{
   const p={x:b.x+(side==='right'?b.w:0),y:b.y+48+i*24};
   if(e.parent===b.name)e.start=p;else e.end=p;
  });
 }
}
const key=p=>`${p.x},${p.y}`;
const edgeKey=(a,b)=>[key(a),key(b)].sort().join('|');
const used=new Set(), occupied=new Map(), reserved=new Map(),reservedNodes=new Map();
function steps(a,b){const result=[];const dx=Math.sign(b.x-a.x)*STEP,dy=Math.sign(b.y-a.y)*STEP;let p={...a};while(p.x!==b.x||p.y!==b.y){const q={x:p.x+dx,y:p.y+dy};result.push([p,q]);p=q;}return result;}
for(const e of edges) {
 e.from={x:e.start.x+(e.as==='right'?24:-24),y:e.start.y};
 e.to={x:e.end.x+(e.bs==='right'?24:-24),y:e.end.y};
 for(const [a,b] of [...steps(e.start,e.from),...steps(e.to,e.end)]) {reserved.set(edgeKey(a,b),e.i);reservedNodes.set(key(a),e.i);reservedNodes.set(key(b),e.i);}
}
const blocked=new Set();
for(const b of boxes.values())for(let x=b.x;x<=b.x+b.w;x+=STEP)for(let y=b.y;y<=b.y+b.h;y+=STEP)blocked.add(`${x},${y}`);
class Heap {
 constructor(){this.a=[];}
 push(v){let i=this.a.length;this.a.push(v);while(i){const p=(i-1)>>1;if(this.a[p].f<=v.f)break;this.a[i]=this.a[p];i=p;}this.a[i]=v;}
 pop(){const top=this.a[0],v=this.a.pop();if(this.a.length){let i=0;while(i*2+1<this.a.length){let c=i*2+1;if(c+1<this.a.length&&this.a[c+1].f<this.a[c].f)c++;if(this.a[c].f>=v.f)break;this.a[i]=this.a[c];i=c;}this.a[i]=v;}return top;}
}
function route(e){
 const heap=new Heap(),dist=new Map(),prev=new Map();
 const sk=(p,d)=>`${p.x},${p.y},${d}`;
 const heuristic=p=>Math.abs(p.x-e.to.x)+Math.abs(p.y-e.to.y);
 const initial={...e.from,d:0,g:0,f:heuristic(e.from)};
 heap.push(initial);dist.set(sk(initial,0),0);let last;
 while(heap.a.length){
  const a=heap.pop(),ak=sk(a,a.d);if(a.g!==dist.get(ak))continue;
  if(a.x===e.to.x&&a.y===e.to.y){last=ak;break;}
  for(const [dx,dy,d] of [[STEP,0,1],[-STEP,0,1],[0,STEP,2],[0,-STEP,2]]){
   const b={x:a.x+dx,y:a.y+dy},bk=sk(b,d),ek=edgeKey(a,b);
   if(b.x<36||b.x>PW-36||b.y<156||b.y>PH-36||blocked.has(key(b))||used.has(ek)||reserved.has(ek)||(reservedNodes.has(key(b))&&reservedNodes.get(key(b))!==e.i))continue;
   const oa=occupied.get(key(a)),ob=occupied.get(key(b));
   // Existing lines may only be crossed at right angles, never followed or joined.
   if(oa&&(oa.size!==1||oa.has(d)||a.d!==d))continue;
   if(ob&&(ob.size!==1||ob.has(d)))continue;
   const g=a.g+STEP+(a.d&&a.d!==d?30:0)+(ob?60:0);
   if(g<(dist.get(bk)??Infinity)){dist.set(bk,g);prev.set(bk,ak);heap.push({...b,d,g,f:g+heuristic(b)});}
  }
 }
 if(!last)throw Error(`No separated route for ${e.parent} -> ${e.child}`);
 const points=[];for(let k=last;k;k=prev.get(k)){const [x,y]=k.split(',').map(Number);points.unshift({x,y});}
 const all=[e.start,...points,e.end];
 for(let i=1;i<all.length;i++)for(const [a,b] of steps(all[i-1],all[i])){
  const ek=edgeKey(a,b);if(used.has(ek))throw Error('Shared segment');used.add(ek);
  const axis=a.x===b.x?2:1;
  for(const p of [a,b]){if(!occupied.has(key(p)))occupied.set(key(p),new Set());occupied.get(key(p)).add(axis);}
 }
 e.points=all.filter((p,i)=>i===0||i===all.length-1||!((all[i-1].x===p.x&&all[i+1].x===p.x)||(all[i-1].y===p.y&&all[i+1].y===p.y)));
}
// Short local links first leave the outer corridors for longer relationships.
edges.sort((a,b)=>(Math.abs(a.from.x-a.to.x)+Math.abs(a.from.y-a.to.y))-(Math.abs(b.from.x-b.to.x)+Math.abs(b.from.y-b.to.y)));
for(const e of edges)route(e);
const geometry=(x,y,w,h)=>`<mxGeometry x="${x}" y="${y}" width="${w}" height="${h}" as="geometry"/>`;
let xml=`<diagram id="${esc(pageId)}" name="${esc(title)}"><mxGraphModel grid="1" gridSize="12" page="1" pageWidth="${PW}" pageHeight="${PH}" tooltips="1"><root><mxCell id="0"/><mxCell id="1" parent="0"/>`;
xml+=`<mxCell id="title" value="ONELARA — ${esc(title)}" style="text;align=left;fontSize=28;fontStyle=1;" vertex="1" parent="1">${geometry(156,36,PW-312,45)}</mxCell>`;
xml+=`<mxCell id="legend" value="${boxes.size} tables • All fields shown • PK = primary key / FK = foreign key / UK = unique key / REF = application reference&#xa;Crossed lines are not junctions. Users and residents repeated on both parts are the same entities. Full SQL details on hover." style="text;align=left;fontSize=15;" vertex="1" parent="1">${geometry(156,90,PW-312,48)}</mxCell>`;
let count=0;
for(const e of edges){
 const a=boxes.get(e.parent),b=boxes.get(e.child),t=tables.get(e.child),c=t.columns.find(c=>c.name===e.column);
 const one=t.pk.includes(e.column)||t.unique.includes(e.column);
 const style=`edgeStyle=none;noEdgeStyle=1;rounded=0;html=0;jumpStyle=none;dashed=0;exitX=${e.as==='right'?1:0};exitY=${(e.start.y-a.y)/a.h};exitPerimeter=0;entryX=${e.bs==='right'?1:0};entryY=${(e.end.y-b.y)/b.h};entryPerimeter=0;startArrow=${c.nullable||e.column==='payable_id'?'ERzeroToOne':'ERone'};endArrow=${one?'ERzeroToOne':'ERzeroToMany'};startSize=10;endSize=10;strokeWidth=1.3;strokeColor=#000000;`;
 xml+=`<object id="e${e.i}" label="" tooltip="${esc(`${e.parent}.${e.target} → ${e.child}.${e.column}${e.note?' / '+e.note:''}`)}"><mxCell style="${style}" edge="1" parent="1" source="${e.parent}" target="${e.child}"><mxGeometry relative="1" as="geometry"><Array as="points">${e.points.slice(1,-1).map(p=>`<mxPoint x="${p.x}" y="${p.y}"/>`).join('')}</Array></mxGeometry></mxCell></object>`;
 for(let i=1;i<e.points.length;i++){
  const p=e.points[i-1],q=e.points[i];
  for(const r of boxes.values()){
   const hit=p.x===q.x?p.x>r.x&&p.x<r.x+r.w&&Math.max(p.y,q.y)>r.y&&Math.min(p.y,q.y)<r.y+r.h:p.y>r.y&&p.y<r.y+r.h&&Math.max(p.x,q.x)>r.x&&Math.min(p.x,q.x)<r.x+r.w;
   if(hit)throw Error('Connector entered table');
  }
  count++;
 }
}
for(const b of boxes.values()){
 const t=tables.get(b.name);
 xml+=`<object id="${b.name}" label="${b.name}" tooltip="${esc(t.columns.map(c=>c.definition).join('\n'))}"><mxCell style="swimlane;startSize=36;collapsible=0;rounded=0;fillColor=#E8EFF7;swimlaneFillColor=#FFFFFF;strokeColor=#94A3B8;fontSize=15;fontStyle=1;align=left;spacingLeft=10;" vertex="1" parent="1">${geometry(b.x,b.y,b.w,b.h)}</mxCell></object>`;
 b.fields.forEach((c,i)=>{
  const marks=[];if(t.pk.includes(c.name))marks.push('PK');if(relations.some(r=>r.child===b.name&&r.column===c.name&&!r.logical))marks.push('FK');if(t.unique.includes(c.name))marks.push('UK');if(relations.some(r=>r.child===b.name&&r.column===c.name&&r.logical))marks.push('REF');
  const type=c.type.startsWith('enum(')?'enum':c.type;
  xml+=`<object id="${b.name}_${c.name}" label="${esc((marks.length?marks.join('/')+'  ':'')+c.name+' : '+type)}" tooltip="${esc(c.definition)}"><mxCell style="text;align=left;verticalAlign=middle;spacingLeft=10;fontSize=13;" vertex="1" parent="${b.name}">${geometry(0,36+i*24,b.w,24)}</mxCell></object>`;
 });
}
xml+='</root></mxGraphModel></diagram>';
const fieldCount=[...boxes.values()].reduce((sum,b)=>sum+b.fields.length,0);
console.log(`${title}: ${boxes.size} tables, ${fieldCount} fields, ${edges.length} relationships. ${count} segments checked: no table intersections or shared segments.`);
return {xml, names:[...names], edges:edges.map(e=>e.i),fields:[...boxes.values()].flatMap(b=>b.fields.map(c=>`${b.name}.${c.name}`))};
}
module.exports={renderPage};
if(require.main===module){
 const page=renderPage(defaultLayout);
 if(page.names.length!==28||page.edges.length!==40||page.fields.length!==254)throw Error('Incomplete schema');
 fs.writeFileSync(path.join(out,'onelara-erd.drawio'),`<mxfile host="app.diagrams.net" type="device">${page.xml}</mxfile>`);
}
