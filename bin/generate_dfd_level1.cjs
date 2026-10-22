const fs=require('fs');
const path=require('path');
const esc=s=>String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/\n/g,'&#xa;');
const geometry=(x,y,w,h)=>`<mxGeometry x="${x}" y="${y}" width="${w}" height="${h}" as="geometry"/>`;
const nodes=new Map();let cells='',edgeCount=0;
function node(id,label,x,y,w,h,kind){
 const styles={entity:'rounded=0;',process:'rounded=1;arcSize=18;',store:'shape=partialRectangle;left=0;right=0;',text:'text;strokeColor=none;'};
 const n={id,x,y,w,h,kind};nodes.set(id,n);
 cells+=`<mxCell id="${id}" value="${esc(label)}" style="${styles[kind]}html=0;whiteSpace=wrap;fillColor=#FFFFFF;${kind==='text'?'':'strokeColor=#000000;'}fontColor=#000000;fontSize=${kind==='process'?19:16};align=${kind==='text'?'left':'center'};verticalAlign=middle;spacing=8;" vertex="1" parent="1">${geometry(x,y,w,h)}</mxCell>`;
 return n;
}
const edgePaths=[];
function edge(id,label,from,to,sx,sy,tx,ty,via=[]){
 const a=nodes.get(from),b=nodes.get(to),start={x:a.x+a.w*sx,y:a.y+a.h*sy},end={x:b.x+b.w*tx,y:b.y+b.h*ty};
 const points=[start,...via,end];
 for(let i=1;i<points.length;i++){
  const p=points[i-1],q=points[i];
  if(p.x!==q.x&&p.y!==q.y)throw Error('Non-orthogonal route');
  for(const n of nodes.values()){
   if(n.kind==='text')continue;
   const hit=p.x===q.x?p.x>n.x&&p.x<n.x+n.w&&Math.max(p.y,q.y)>n.y&&Math.min(p.y,q.y)<n.y+n.h:p.y>n.y&&p.y<n.y+n.h&&Math.max(p.x,q.x)>n.x&&Math.min(p.x,q.x)<n.x+n.w;
   if(hit)throw Error(`${id} crosses ${n.id}`);
  }
 }
 edgePaths.push(points);edgeCount++;
 cells+=`<mxCell id="${id}" value="${esc(label)}" style="edgeStyle=none;noEdgeStyle=1;rounded=0;html=0;strokeColor=#000000;strokeWidth=1.4;dashed=0;jumpStyle=none;endArrow=block;endFill=1;startArrow=none;fontColor=#000000;fontSize=14;labelBackgroundColor=#FFFFFF;exitX=${sx};exitY=${sy};exitPerimeter=0;entryX=${tx};entryY=${ty};entryPerimeter=0;" edge="1" parent="1" source="${from}" target="${to}"><mxGeometry relative="1" as="geometry"><Array as="points">${via.map(p=>`<mxPoint x="${p.x}" y="${p.y}"/>`).join('')}</Array></mxGeometry></mxCell>`;
}
const rows=[
 {p:'1.0\nManage accounts\nand residents',staff:'Barangay staff / Admin',store:'D1  Accounts and residents\n\nroles • users • residents',input:'Registration / login details',output:'Account / profile status',staffIn:'Account / resident updates',staffOut:'Account / resident records',write:'Account / profile changes',read:'Accounts, roles and profiles'},
 {p:'2.0\nManage documents,\nreservations and payments',staff:'Barangay staff / Admin',store:'D2  Barangay service records\n\ndocument_types • document_requests\nfacilities • facility_reservations\npayments',input:'Service request / payment details',output:'Request / reservation / payment status',staffIn:'Approval / payment verification',staffOut:'Requests / payment submissions',write:'Requests, bookings, payments',read:'Fees, availability and status'},
 {p:'3.0\nManage appointments\nand health services',staff:'Health worker / Authorized staff',store:'D3  Health service records\n\nappointments • consultations\nhealth_records • medicines\nmedicine_dispensing',input:'Appointment details',output:'Appointment / consultation details',staffIn:'Schedule / clinical / stock updates',staffOut:'Appointments / patient / stock records',write:'Appointments / clinical / stock records',read:'Schedules / health / stock data'}
];
const storeGroups=[['roles','users','residents'],['document_types','document_requests','facilities','facility_reservations','payments'],['appointments','consultations','health_records','medicines','medicine_dispensing']];
const flowNames={roles:'Role definitions',users:'Account records',residents:'Resident profiles',document_types:'Document types / fees',document_requests:'Document requests',facilities:'Facilities / fees',facility_reservations:'Facility reservations',payments:'Payment records',appointments:'Appointment records',consultations:'Consultation records',health_records:'Health records',medicines:'Medicine inventory',medicine_dispensing:'Dispensing records'};
let storeNumber=0;
node('title','ONELARA — LEVEL 1 DATA FLOW DIAGRAM',60,25,1500,45,'text');
node('subtitle','Simplified main workflows • One page • Black arrows show the direction of data flow',60,80,1500,35,'text');
for(let i=0;i<rows.length;i++){
 const r=rows[i],y=170+i*440;
 node(`resident${i}`,'Resident',60,y+20,210,100,'entity');
 node(`staff${i}`,r.staff,60,y+225,250,90,'entity');
 node(`process${i}`,r.p,650,y+20,340,140,'process');
 storeGroups[i].forEach((name,j)=>node(name,`D${++storeNumber}  ${name}`,1350,y+j*64,410,48,'store'));
}
for(let i=0;i<rows.length;i++){
 const r=rows[i],y=170+i*440;
 edge(`input${i}`,r.input,`resident${i}`,`process${i}`,1,0.3,0,30/140);
 edge(`output${i}`,r.output,`process${i}`,`resident${i}`,0,70/140,1,0.7);
 edge(`staffin${i}`,r.staffIn,`staff${i}`,`process${i}`,1,0.25,0.25,1,[{x:735,y:y+247.5}]);
 edge(`staffout${i}`,r.staffOut,`process${i}`,`staff${i}`,0.5,1,1,0.75,[{x:820,y:y+292.5}]);
 storeGroups[i].forEach((name,j)=>{
  const writeY=y+44+j*20,readY=y+54+j*20,storeY=y+j*64;
  const wx=1040+j*36,rx=1058+j*36;
  if(!['roles','document_types','facilities'].includes(name))edge(`write_${name}`,`${flowNames[name]} updates`,`process${i}`,name,1,(writeY-y-20)/140,0,0.25,[{x:wx,y:writeY},{x:wx,y:storeY+12}]);
  edge(`read_${name}`,flowNames[name],name,`process${i}`,0,0.75,1,(readY-y-20)/140,[{x:rx,y:storeY+36},{x:rx,y:readY}]);
 });
 if(i>0){
  const sourceY=170+2*64+(i===1?12:36),outerX=1780+i*24,endX=i===1?939:963;
  edge(`identityread${i}`,'Resident profile', 'residents',`process${i}`,1,i===1?0.25:0.75,(endX-650)/340,1,[{x:outerX,y:sourceY},{x:outerX,y:y+370},{x:endX,y:y+370}]);
 }
}
node('note','D1–D13 match the 13 table names in the simplified ERD exactly. Each data store represents one SQL table.\nRepeated Resident and Staff symbols represent the same external actors. Supporting and community tables are omitted in both diagrams.',60,1485,1780,65,'text');
const erd=fs.readFileSync(path.resolve(__dirname,'../docs/onelara-erd.drawio'),'utf8');
const erdTables=[...erd.matchAll(/<object id="([^"]+)"[^>]*><mxCell[^>]*vertex="1" parent="1"/g)].map(m=>m[1]).sort();
if(JSON.stringify(erdTables)!==JSON.stringify(storeGroups.flat().sort()))throw Error('DFD stores do not match current ERD tables');
const xml=`<mxfile host="app.diagrams.net" type="device"><diagram id="dfd-level-1" name="Level 1 DFD"><mxGraphModel grid="1" gridSize="10" page="1" pageWidth="1900" pageHeight="1580"><root><mxCell id="0"/><mxCell id="1" parent="0"/>${cells}</root></mxGraphModel></diagram></mxfile>`;
fs.writeFileSync(path.resolve(__dirname,'../docs/onelara-dfd-level-1.drawio'),xml);
console.log(`Created one editable page: 3 processes, 13 data stores matching the ERD, ${edgeCount} labelled data flows. Routes checked against node interiors.`);
