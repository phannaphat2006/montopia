// Public synthetic examples ONLY. Never import private data or connect this adapter to production.
export function createDemoRepository(){
 const stamp=new Date().toISOString();
 const project={id:'demo-project',code:'DEMO-001',title:'Customer Experience Platform',client_name:'บริษัทตัวอย่าง · ข้อมูลสมมติ',summary:'ตัวอย่างพื้นที่ทำงานร่วมกันระหว่างทีมพัฒนากับลูกค้า สำหรับระบบจัดการลูกค้าและงานขาย',phase:'development',health:'on_track',due_on:null,next_action:'ตรวจรับต้นแบบหน้าจอ เพื่อให้ทีมเริ่มพัฒนาขั้นตอนถัดไป',archived:false,updated_at:stamp};
 const state={projects:[project],milestones:[
  {id:'m1',project_id:project.id,title:'สรุปขอบเขตและความต้องการ',description:'แผนผังการทำงาน ขอบเขตฟังก์ชัน และเกณฑ์การตรวจรับ',status:'approved',position:1,approved_at:stamp,updated_at:stamp},
  {id:'m2',project_id:project.id,title:'ต้นแบบหน้าจอและขั้นตอนใช้งาน',description:'ตรวจสอบหน้าภาพรวม รายชื่อลูกค้า และขั้นตอนบันทึกงานขาย ก่อนเริ่มพัฒนา',status:'submitted',position:2,updated_at:stamp},
  {id:'m3',project_id:project.id,title:'พัฒนาระบบและเชื่อมต่อข้อมูล',description:'ส่วนติดต่อผู้ใช้ ระบบหลังบ้าน และการเชื่อมต่อ API',status:'in_progress',position:3,updated_at:stamp},
  {id:'m4',project_id:project.id,title:'ทดสอบร่วมกันและส่งมอบ',description:'ทดสอบตามกรณีใช้งาน อบรม และส่งมอบเอกสาร',status:'planned',position:4,updated_at:stamp}],
 updates:[{id:'u1',project_id:project.id,title:'พร้อมตรวจรับต้นแบบหน้าจอ',body:'ทีมออกแบบอัปเดตขั้นตอนเพิ่มลูกค้าและหน้าสรุปยอดขายแล้ว กรุณาตรวจสอบ Milestone ต้นแบบหน้าจอ หากต้องการปรับแก้ สามารถส่งคำขอในพื้นที่นี้ได้',kind:'action',created_at:stamp,updated_at:stamp}],
 requests:[],members:[]};
 return {
 async session(){return {role:'client',email:'demo@example.com'};},
 async projects(){return structuredClone(state.projects);},
 async detail(id){return structuredClone(Object.fromEntries(['milestones','updates','requests','members'].map(k=>[k,state[k].filter(x=>x.project_id===id)])));},
 async save(table,values,record){const row=record?state[table].find(x=>x.id===record.id):{id:crypto.randomUUID(),created_at:new Date().toISOString(),status:table==='requests'?'open':undefined};Object.assign(row,values,{updated_at:new Date().toISOString()});if(!record)state[table].push(row);return structuredClone(row);},
 async remove(table,record){state[table]=state[table].filter(x=>x.id!==record.id);},
 async approve(record){const item=state.milestones.find(x=>x.id===record.id);if(item.status!=='submitted')throw {code:'40001'};item.status='approved';item.approved_at=new Date().toISOString();},
 async signOut(){},onSignOut(){return {unsubscribe(){}};}
 };
}
