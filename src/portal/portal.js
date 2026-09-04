import {createRepository} from './repository.js';
import {createDemoRepository} from './demo.js';
import {phases,health,milestoneStatus,requestStatus,updateKinds,progress,date,safeUrl,weeklyReport,friendlyError,normalizeEmail} from './domain.js';

const demo=document.body.dataset.mode==='demo';
const repo=demo?createDemoRepository():createRepository(window.MONSTOPIA_PORTAL);
const root=document.getElementById('workspace'),dialog=document.getElementById('editor'),notice=document.getElementById('notice');
let identity=null,projects=[],selectedId=null,detail=null,tab='overview',generation=0,noticeTimer,loading=false;
const manage=()=>['admin','staff'].includes(identity?.role);
const admin=()=>identity?.role==='admin';
const el=(tag,props={},...children)=>{const node=document.createElement(tag);for(const [key,value] of Object.entries(props)){if(value===null||value===undefined)continue;if(key==='class')node.className=value;else if(key.startsWith('on'))node.addEventListener(key.slice(2).toLowerCase(),value);else if(key==='text')node.textContent=value;else if(key in node)node[key]=value;else node.setAttribute(key,value);}for(const child of children.flat(Infinity)){if(child!==null&&child!==undefined&&child!==false)node.append(child instanceof Node?child:document.createTextNode(String(child)));}return node;};
const button=(label,action,style='')=>el('button',{type:'button',class:style,onClick:action},label);
const link=(label,href,style='')=>el('a',{href,class:style},label);
const badge=(label,status='')=>el('span',{class:'badge '+status},label);
const empty=message=>el('p',{class:'empty'},message);
function flash(message,error=false){notice.textContent=message;notice.className=error?'error':'';notice.hidden=false;clearTimeout(noticeTimer);noticeTimer=setTimeout(()=>notice.hidden=true,8000);}
function clearWorkspace(){generation++;identity=null;projects=[];detail=null;selectedId=null;dialog.close();root.replaceChildren();}
function field(name,label,{type='text',value='',required=false,options,maxLength=160,help}={}){
 const input=options?el('select',{name,required},Object.entries(options).map(([key,text])=>el('option',{value:key,selected:key===value},text))):el(type==='textarea'?'textarea':'input',{name,type:type==='textarea'?undefined:type,value:value??'',required,maxLength,autocomplete:type==='email'?'email':'off'});
 const wrapper=el('label',{class:'field'},label,input,help?el('small',{},help):null);return wrapper;
}
function showLogin(){
 const ready=Boolean(repo);
 const intro=el('section',{class:'login-intro'},el('p',{class:'eyebrow'},'A CLEARER WAY TO WORK TOGETHER'),el('h1',{},'ทุกความคืบหน้า',el('br'),'ในพื้นที่เดียวกัน'),el('p',{class:'muted'},'ติดตามงานที่กำลังพัฒนา ตรวจรับแต่ละขั้นตอน และคุยเรื่องสิ่งที่ต้องปรับแก้ จากข้อมูลชุดเดียวกับทีมงาน'),el('ul',{class:'login-features'},[['01','เห็นขั้นตอนปัจจุบันและสิ่งที่จะเกิดขึ้นถัดไป'],['02','ตรวจรับงานและย้อนดูประวัติการอัปเดต'],['03','ข้อมูลเข้าถึงได้เฉพาะผู้ได้รับสิทธิ์']].map(([n,text])=>el('li',{},el('b',{},n),text))));
 const card=el('section',{class:'panel login-card'},el('p',{class:'eyebrow'},'CLIENT ACCESS'),el('h2',{},'เข้าสู่พื้นที่โครงการ'),el('p',{class:'muted'},'ใช้อีเมลที่บริษัทของคุณแจ้งไว้กับทีม MONSTOPIA เพื่อรับรหัสยืนยันการเข้าสู่ระบบ'));
 if(!ready){card.append(el('div',{class:'note'},el('strong',{},'ระบบลูกค้าจริงยังไม่เปิดใช้งาน'),el('br'),'กำลังเตรียมการเชื่อมต่อระบบยืนยันตัวตนและฐานข้อมูล จึงยังไม่มีการส่งรหัสหรือรับข้อมูลลูกค้าในหน้านี้'),link('ทดลองใช้ Workspace →','portal-demo.html','button primary'),el('hr',{class:'divider'}),el('p',{class:'caption'},'เป็นลูกค้าปัจจุบัน? ติดต่อผู้ดูแลโครงการผ่านช่องทางที่ตกลงไว้ หรือ ',link('ติดต่อทีมงาน','index.html#contact')));}
 else{
  let email='',sentAt=0;
  const emailField=field('email','อีเมลที่ได้รับสิทธิ์',{type:'email',required:true,maxLength:254});
  const emailForm=el('form',{},emailField,el('button',{type:'submit',class:'primary'},'รับรหัสยืนยันทางอีเมล →'));
  const otpForm=el('form',{hidden:true});
  const otpInput=field('token','รหัสยืนยันจากอีเมล',{required:true,maxLength:10,help:'รหัสมีอายุจำกัดตามที่ระบุในอีเมล ห้ามส่งต่อรหัสให้บุคคลอื่น'});
  const token=otpInput.querySelector('input');token.inputMode='numeric';token.autocomplete='one-time-code';token.pattern='[0-9]{6,10}';
  const authMessage=el('p',{class:'caption','aria-live':'polite'}),authError=el('p',{class:'form-error',role:'alert'});
  const resend=button('ส่งรหัสอีกครั้ง',async()=>{if(Date.now()-sentAt<60000){authError.textContent='กรุณารออย่างน้อย 60 วินาทีก่อนขอรหัสใหม่';return;}resend.disabled=true;try{await repo.sendOtp(email);}catch{}finally{sentAt=Date.now();resend.disabled=false;authMessage.textContent='หากอีเมลนี้ได้รับสิทธิ์ ระบบจะส่งรหัสใหม่ให้ กรุณาตรวจสอบกล่องจดหมายและสแปม';}},'small');
  otpForm.append(otpInput,el('button',{type:'submit',class:'primary'},'ยืนยันและเข้าสู่โครงการ'),el('div',{class:'actions',style:'margin-top:16px'},resend,button('เปลี่ยนอีเมล',()=>{otpForm.hidden=true;emailForm.hidden=false;authMessage.textContent='';authError.textContent='';token.value='';emailField.querySelector('input').focus();},'small')));
  emailForm.addEventListener('submit',async event=>{event.preventDefault();const submit=emailForm.querySelector('button');submit.disabled=true;authError.textContent='';email=normalizeEmail(new FormData(emailForm).get('email'));try{await repo.sendOtp(email);}catch{}finally{sentAt=Date.now();submit.disabled=false;emailForm.hidden=true;otpForm.hidden=false;authMessage.textContent='หากอีเมลนี้ได้รับสิทธิ์ ระบบจะส่งรหัสยืนยันให้ กรุณาตรวจสอบกล่องจดหมายและสแปม';token.focus();}});
  otpForm.addEventListener('submit',async event=>{event.preventDefault();const submit=otpForm.querySelector('button');submit.disabled=true;authError.textContent='';try{await repo.verify(email,token.value.trim());await load();}catch{authError.textContent='รหัสไม่ถูกต้อง หมดอายุ หรือไม่สามารถเชื่อมต่อได้ กรุณาตรวจสอบรหัสหรือลองขอใหม่';}finally{submit.disabled=false;}});
  card.append(emailForm,otpForm,authMessage,authError,el('hr',{class:'divider'}),el('p',{class:'caption'},'ไม่มีระบบสมัครสมาชิกสาธารณะ หากยังไม่ได้รับสิทธิ์ กรุณาติดต่อผู้ดูแลโครงการ'));
 }
 root.replaceChildren(el('div',{class:'login-layout'},intro,card));
}
async function load(){
 if(!repo){showLogin();return;}
 const run=++generation;loading=true;detail=null;root.replaceChildren(el('p',{class:'loading',role:'status'},'กำลังตรวจสอบสิทธิ์และโหลดข้อมูล…'));
 try{
  const user=demo?(identity||await repo.session()):await repo.session();
  if(run!==generation)return;
  if(!user){clearWorkspace();showLogin();return;}
  identity=user;
  const list=await repo.projects();if(run!==generation)return;projects=list;
  selectedId=projects.some(p=>p.id===selectedId)?selectedId:projects[0]?.id;
  const data=selectedId?await repo.detail(selectedId,admin()):null;
  if(run!==generation)return;detail=data;render();
 }catch(error){if(run!==generation)return;projects=[];detail=null;root.replaceChildren(el('section',{class:'brief-shell panel'},el('h1',{},'ยังโหลดพื้นที่ทำงานไม่ได้'),el('p',{},friendlyError(error)),el('div',{class:'actions'},button('ลองอีกครั้ง',load,'primary'),button('กลับหน้าเข้าสู่ระบบ',signOut))));}
 finally{if(run===generation)loading=false;}
}
async function signOut(){clearWorkspace();try{await repo?.signOut();showLogin();}catch{root.replaceChildren(el('section',{class:'brief-shell panel'},el('h2',{},'ยังออกจากระบบไม่สำเร็จ'),el('p',{},'กรุณาลองอีกครั้ง หรือปิดแท็บนี้เพื่อจบการใช้งานในอุปกรณ์ที่ใช้ร่วมกัน'),button('ออกจากระบบอีกครั้ง',signOut,'primary')));}}
function render(){
 const project=projects.find(p=>p.id===selectedId);
 const sidebar=el('aside',{class:'sidebar'},el('div',{},el('p',{class:'eyebrow'},'YOUR PROJECTS'),el('nav',{class:'project-list','aria-label':'เลือกโครงการ'},projects.map(p=>button(el('span',{},el('small',{},p.code+(p.archived?' · ARCHIVED':'')),p.title),()=>{selectedId=p.id;tab='overview';load();},'project-choice'+(p.id===selectedId?' active':''))))),admin()?button('+ สร้างโครงการ',()=>edit('projects'), 'small'):null,el('div',{class:'account'},el('p',{},identity.email,el('br'),manage()?'พื้นที่ทีมงาน · '+identity.role:'พื้นที่ลูกค้า'),demo?link('เข้าสู่ระบบจริง','portal.html','button small'):button('ออกจากระบบ',signOut,'small')));
 const content=el('section',{class:'main-content'});
 if(!project){content.append(el('p',{class:'eyebrow'},'WORKSPACE'),el('h1',{},'ยังไม่มีโครงการที่เข้าถึงได้'),el('p',{class:'muted'},'หากคุณเป็นลูกค้าปัจจุบัน กรุณาตรวจสอบอีเมลกับผู้ดูแลโครงการ โครงการที่เก็บถาวรหรือยกเลิกสิทธิ์แล้วจะไม่แสดงที่นี่'),admin()?button('สร้างโครงการแรก',()=>edit('projects'),'primary'):link('ติดต่อทีมงาน','index.html#contact','button'));}
 else{
 const pending=detail.milestones.filter(x=>x.status==='submitted').length;
 const headerActions=el('div',{class:'actions'},button('รีเฟรช',load,'small'),manage()?button('แก้ไขโครงการ',()=>edit('projects',project),'small'):null);
 const preview=safeUrl(project.preview_url);if(preview){const a=link('เปิดระบบทดสอบ ↗',preview,'button small dark');a.target='_blank';a.rel='noopener noreferrer';headerActions.append(a);}
 content.append(el('div',{class:'page-heading'},el('div',{},el('div',{class:'page-meta'},el('span',{class:'code'},project.code),badge(project.archived?'เก็บถาวร':health[project.health],project.health)),el('h1',{},project.title),el('p',{class:'muted'},project.client_name)),headerActions));
 content.append(el('p',{class:'muted'},project.summary));
 const percent=progress(detail.milestones);
 content.append(el('div',{class:'metrics'},el('div',{class:'panel metric'},el('span',{},'MILESTONE ที่ตรวจรับแล้ว'),el('strong',{},percent+'%'),el('div',{class:'bar',role:'progressbar','aria-valuenow':percent,'aria-valuemin':0,'aria-valuemax':100,'aria-label':'สัดส่วน Milestone ที่ตรวจรับแล้ว'},el('i',{style:`width:${percent}%`})),el('p',{},'คิดจากจำนวนขั้นตอน ไม่ใช่ชั่วโมงพัฒนา')),el('div',{class:'panel metric'},el('span',{},'ขั้นตอนปัจจุบัน'),el('strong',{class:'phase-value'},phases[project.phase]),el('p',{},pending?`${pending} รายการรอตรวจรับ`:'ไม่มีรายการรอตรวจรับ')),el('div',{class:'panel metric'},el('span',{},'กำหนดส่งตามแผน'),el('strong',{class:'phase-value'},date(project.due_on)),el('p',{},'อัปเดตโครงการ '+date(project.updated_at)))));
 content.append(el('div',{class:'next-action'},el('div',{},el('h3',{},'NEXT STEP / สิ่งที่ต้องทำถัดไป'),el('p',{},project.next_action||'ทีมงานยังไม่ได้ระบุขั้นตอนถัดไป')),manage()?button('สรุปรายงาน',()=>report(project),'small'):button('ส่งคำขอถึงทีม',()=>edit('requests'),'small')));
 const tabs={overview:'ภาพรวมและ Milestone',updates:'บันทึกความคืบหน้า',requests:`คำขอ (${detail.requests.length})`,...(admin()?{members:'สิทธิ์เข้าถึง'}:{})};if(!tabs[tab])tab='overview';
 content.append(el('nav',{class:'content-tabs','aria-label':'ส่วนข้อมูลโครงการ'},Object.entries(tabs).map(([key,label])=>el('button',{type:'button',class:tab===key?'active':'','aria-current':tab===key?'page':null,onClick:()=>{tab=key;render();}},label))));
 if(tab==='overview')content.append(el('div',{class:'two-columns'},milestones(),updates(true)));
 if(tab==='updates')content.append(updates());
 if(tab==='requests')content.append(requests());
 if(tab==='members')content.append(members(project));
 content.append(el('footer',{class:'workspace-footer'},el('span',{},'MONSTOPIA · Shared clarity. Better delivery.'),el('span',{},demo?'ข้อมูลสมมติ · ไม่บันทึกเมื่อโหลดหน้าใหม่':'ข้อมูลโครงการไม่ถูกเก็บเป็นสำเนาในเบราว์เซอร์')));
 }
 const nodes=[];if(demo){const mode=el('select',{'aria-label':'เลือกมุมมองตัวอย่าง',onChange:event=>{identity.role=event.target.value;tab='overview';load();}},el('option',{value:'client',selected:!manage()},'มุมมองลูกค้า'),el('option',{value:'admin',selected:admin()},'มุมมองผู้ดูแล'));nodes.push(el('div',{class:'demo-banner'},el('p',{},el('strong',{},'INTERACTIVE DEMO — ข้อมูลสมมติทั้งหมด'),el('br'),'ลองตรวจรับงาน ส่งคำขอ หรือสลับมุมมองทีมงาน ข้อมูลจะเริ่มใหม่เมื่อรีเฟรชหน้า'),mode));}
 nodes.push(el('div',{class:'workspace-shell'},sidebar,content));root.replaceChildren(...nodes);
}
function sectionHeader(title,action){return el('div',{class:'section-heading'},el('h2',{},title),action);}
function milestones(){return el('section',{class:'panel'},sectionHeader('แผนงานและการตรวจรับ',manage()?button('+ เพิ่ม',()=>edit('milestones'),'small'):null),detail.milestones.length?el('ol',{class:'timeline'},detail.milestones.map(item=>el('li',{class:'timeline-item'},el('span',{class:'timeline-dot '+item.status,'aria-hidden':'true'}),el('h3',{},item.title),badge(milestoneStatus[item.status],item.status),el('p',{},item.description),el('span',{class:'caption'},'กำหนด: '+date(item.due_on)+(item.approved_at?' · ตรวจรับ: '+date(item.approved_at):'')),el('div',{class:'actions'},!manage()&&item.status==='submitted'?button('ตรวจรับงานนี้',()=>confirmAction('ตรวจรับ '+item.title,'ยืนยันว่าคุณตรวจสอบงานตามขอบเขตนี้แล้ว การตรวจรับจะถูกบันทึกพร้อมบัญชีผู้ยืนยันและเวลา หากต้องการแก้ไข ให้ส่งคำขอก่อนตรวจรับ',()=>repo.approve(item)),'primary small'):null,manage()&&item.status!=='approved'?button('แก้ไข',()=>edit('milestones',item),'small'):null,manage()&&item.status!=='approved'?button('ลบ',()=>remove('milestones',item),'small danger'):null)))):empty('ยังไม่มี Milestone ที่เผยแพร่'));}
function updates(compact=false){const rows=compact?detail.updates.slice(0,3):detail.updates;return el('section',{class:'panel'},sectionHeader(compact?'อัปเดตล่าสุด':'บันทึกความคืบหน้า',manage()?button('+ อัปเดต',()=>edit('updates'),'small'):null),rows.length?el('div',{class:'feed'},rows.map(item=>el('article',{class:'feed-item'},badge(updateKinds[item.kind]),el('h3',{},item.title),el('p',{},item.body),el('span',{class:'caption'},date(item.created_at)),manage()?el('div',{class:'actions'},button('แก้ไข',()=>edit('updates',item),'small'),button('ลบ',()=>remove('updates',item),'small danger')):null))):empty('ยังไม่มีบันทึกความคืบหน้า'));}
function requests(){return el('section',{},sectionHeader('คำขอและการติดตาม',!manage()?button('+ ส่งคำขอ',()=>edit('requests'),'primary small'):null),el('p',{class:'caption'},'คำขอเปลี่ยนขอบเขตงานจะต้องผ่านการประเมินเวลาและค่าใช้จ่าย การส่งคำขอยังไม่ถือเป็นการตกลงดำเนินงาน'),detail.requests.length?el('div',{class:'request-grid'},detail.requests.map(item=>el('article',{class:'panel request-card'},el('div',{class:'page-meta'},badge(requestStatus[item.status],item.status),badge(item.kind==='change'?'เปลี่ยนแปลง / เพิ่มงาน':'แจ้งปัญหา'),item.priority==='high'?badge('ความสำคัญสูง','high'):null),el('h3',{},item.title),el('p',{},item.body),el('p',{class:'caption'},'เปิดรายการ '+date(item.created_at)),item.response?el('div',{class:'response'},el('strong',{},'การตอบกลับจากทีม'),el('br'),item.response):null,manage()?el('div',{class:'actions',style:'margin-top:16px'},button('ตอบกลับ / เปลี่ยนสถานะ',()=>edit('requests',item),'small')):null))):el('div',{class:'panel'},empty('ยังไม่มีคำขอ หากพบปัญหาหรือต้องการเพิ่มงาน แจ้งทีมได้ที่นี่')));}
function members(project){return el('section',{class:'panel'},sectionHeader('อีเมลที่เข้าถึงโครงการ',button('+ เพิ่มอีเมล',()=>edit('members'),'primary small')),el('p',{class:'caption'},'การเพิ่มอีเมลเป็นเพียงการให้สิทธิ์ ไม่ส่งอีเมลเชิญอัตโนมัติ ผู้รับต้องยืนยันอีเมลของตนด้วย OTP สมาชิกประเภททีมงานต้องได้รับบทบาท staff จากผู้ดูแลฐานข้อมูลด้วย'),detail.members.length?el('ul',{class:'member-list'},detail.members.map(item=>el('li',{},el('div',{},item.email,el('br'),el('span',{class:'caption'},(item.role==='client'?'ลูกค้า':'ทีมงาน')+' · '+(item.revoked?'ยกเลิกสิทธิ์':item.user_id?'เชื่อมบัญชีแล้ว':'รอยืนยันอีเมล'))),button(item.revoked?'คืนสิทธิ์':'ยกเลิกสิทธิ์',()=>confirmAction(item.revoked?'คืนสิทธิ์เข้าถึง':'ยกเลิกสิทธิ์เข้าถึง',item.email,()=>repo.save('members',{revoked:!item.revoked},item)), 'small'+(item.revoked?'':' danger'))))):empty('ยังไม่มีอีเมลที่ได้รับสิทธิ์'),el('hr',{class:'divider'}),el('h3',{},'จัดเก็บโครงการ'),el('p',{class:'caption'},'โครงการที่เก็บถาวรจะไม่แสดงให้ลูกค้าเห็น ทีมงานยังเปิดดูประวัติและเรียกคืนได้'),button(project.archived?'นำโครงการกลับมาใช้งาน':'เก็บโครงการถาวร',()=>confirmAction('เปลี่ยนสถานะการจัดเก็บ',project.archived?'คืนการเข้าถึงให้ลูกค้าที่มีสิทธิ์เดิม':'ลูกค้าจะไม่สามารถเปิดโครงการนี้ได้จนกว่าจะนำกลับมาใช้งาน',()=>repo.save('projects',{archived:!project.archived},project)),'danger small'));}
function openDialog(title,body){dialog.replaceChildren(el('header',{},el('h2',{id:'dialog-title'},title),button('ปิด ×',()=>dialog.close(),'small')),body);dialog.showModal();}
function edit(table,record){
 const forms={
 projects:{title:record?'แก้ไขโครงการ':'สร้างโครงการ',fields:[...(!record?[['code','รหัสโครงการ',{required:true,maxLength:32,help:'ตัวอักษรอังกฤษตัวใหญ่ ตัวเลข และขีดกลาง เช่น MON-001'}]]:[]),['title','ชื่อโครงการ',{required:true}],['client_name','ชื่อบริษัทลูกค้า',{required:true}],['summary','ขอบเขตโดยสรุป',{type:'textarea',maxLength:4000}],['phase','ขั้นตอนปัจจุบัน',{options:phases}],['health','สถานะการดำเนินงาน',{options:health}],['due_on','กำหนดส่งตามแผน',{type:'date'}],['next_action','สิ่งที่ต้องทำถัดไป',{type:'textarea',maxLength:1000}],['preview_url','ลิงก์ระบบทดสอบ (ไม่บังคับ)',{type:'url',maxLength:2048,help:'HTTPS เท่านั้น ระบบปลายทางต้องมีการตรวจสิทธิ์ของตนเอง ห้ามใส่รหัสผ่านหรือโทเคนใน URL'}]]},
 milestones:{title:record?'แก้ไข Milestone':'เพิ่ม Milestone',fields:[['title','ชื่อขั้นตอน',{required:true}],['description','ขอบเขตและเกณฑ์ตรวจรับ',{type:'textarea',maxLength:4000}],['status','สถานะ',{options:{planned:'ตามแผน',in_progress:'กำลังดำเนินการ',submitted:'พร้อมให้ลูกค้าตรวจรับ'}}],['due_on','กำหนดส่ง',{type:'date'}],['position','ลำดับ',{type:'number',value:detail?.milestones.length+1||1}]]},
 updates:{title:record?'แก้ไขอัปเดต':'เผยแพร่อัปเดต',fields:[['title','หัวข้อ',{required:true}],['kind','ประเภท',{options:updateKinds}],['body','รายละเอียดที่ลูกค้าจะเห็น',{type:'textarea',required:true,maxLength:6000}]]},
 requests:{title:record?'ตอบกลับคำขอ':'ส่งคำขอถึงทีมงาน',fields:record?[['status','สถานะ',{options:requestStatus}],['response','ข้อความตอบกลับ',{type:'textarea',maxLength:6000}]]:[['kind','ประเภทคำขอ',{options:{change:'เปลี่ยนแปลง / เพิ่มงาน',support:'แจ้งปัญหาการใช้งาน'}}],['title','หัวข้อ',{required:true}],['body','รายละเอียดและผลลัพธ์ที่ต้องการ',{type:'textarea',required:true,maxLength:6000}],['priority','ความสำคัญ',{options:{normal:'ปกติ',high:'สูง — กระทบการใช้งาน'}}]]},
 members:{title:'เพิ่มสิทธิ์อีเมล',fields:[['email','อีเมลของผู้รับสิทธิ์',{type:'email',required:true,maxLength:254}],['role','ประเภทผู้ใช้งาน',{options:{client:'ลูกค้า',staff:'ทีมงาน'}}]]}
 };
 const spec=forms[table],form=el('form');
 for(const [name,label,options]of spec.fields){const f=field(name,label,{...options,value:record?.[name]??options.value??''});if(name==='code')f.querySelector('input').pattern='[A-Z0-9-]{3,32}';if(name==='position'){f.querySelector('input').min='0';f.querySelector('input').max='9999';f.querySelector('input').step='1';}form.append(f);}
 const error=el('p',{class:'form-error',role:'alert'}),submit=el('button',{type:'submit',class:'primary'},demo?'บันทึกตัวอย่าง':'บันทึก');
 form.append(error,el('div',{class:'actions'},button('ยกเลิก',()=>dialog.close()),submit));
 const activeProject=selectedId;
 form.addEventListener('submit',async event=>{event.preventDefault();submit.disabled=true;error.textContent='';const values=Object.fromEntries([...new FormData(form)].map(([key,value])=>[key,value.trim()]));if(values.email)values.email=normalizeEmail(values.email);if('due_on'in values)values.due_on=values.due_on||null;if('preview_url'in values){if(values.preview_url&&!safeUrl(values.preview_url)){error.textContent='ลิงก์ระบบทดสอบต้องเป็น HTTPS และไม่มีรหัสผ่านใน URL';submit.disabled=false;return;}values.preview_url=values.preview_url||null;}if('position'in values)values.position=Number(values.position);if(!record&&table!=='projects')values.project_id=activeProject;try{const row=await repo.save(table,values,record);if(table==='projects'&&!record)selectedId=row.id;dialog.close();await load();flash(demo?'บันทึกในตัวอย่างแล้ว — ไม่ได้ส่งข้อมูลจริง':'บันทึกเรียบร้อย');}catch(err){error.textContent=friendlyError(err);}finally{submit.disabled=false;}});
 openDialog(spec.title,form);
}
function confirmAction(title,message,action){const error=el('p',{class:'form-error',role:'alert'});const submit=button(demo?'ยืนยันในตัวอย่าง':'ยืนยัน',async()=>{submit.disabled=true;try{await action();dialog.close();await load();flash(demo?'อัปเดตข้อมูลตัวอย่างแล้ว':'ดำเนินการเรียบร้อย');}catch(err){error.textContent=friendlyError(err);}finally{submit.disabled=false;}},'primary');openDialog(title,el('div',{},el('p',{},message),error,el('div',{class:'actions'},button('ยกเลิก',()=>dialog.close()),submit)));}
function remove(table,item){confirmAction('ลบรายการนี้?',`“${item.title}” จะถูกลบออกจากโครงการ ประวัติการกระทำยังอยู่ในบันทึกผู้ดูแล การลบนี้ย้อนกลับจากหน้าจอไม่ได้`,()=>repo.remove(table,item));}
function report(project){const value=weeklyReport(project,detail);const output=el('textarea',{class:'report-preview',value,readOnly:true,'aria-label':'สรุปโครงการ'});openDialog('สรุปสำหรับอัปเดตลูกค้า',el('div',{},el('p',{class:'caption'},'สรุปจากสถานะปัจจุบัน กรุณาตรวจสอบก่อนนำไปส่ง ระบบไม่ได้ส่งอีเมลอัตโนมัติ'),output,el('div',{class:'actions'},button('คัดลอกสรุป',async()=>{try{await navigator.clipboard.writeText(value);flash('คัดลอกแล้ว');}catch{output.focus();output.select();flash('กรุณาคัดลอกข้อความที่เลือกด้วยตนเอง');}},'primary'))));}
if(repo&&!demo)repo.onSignOut(()=>{clearWorkspace();showLogin();});
document.addEventListener('visibilitychange',()=>{if(demo||!identity)return;if(document.hidden){generation++;detail=null;projects=[];dialog.close();root.replaceChildren(el('p',{class:'loading'},'กลับมาที่แท็บเพื่อโหลดข้อมูลล่าสุด'));}else load();});
window.addEventListener('pageshow',event=>{if(event.persisted&&!demo)load();});
load();
