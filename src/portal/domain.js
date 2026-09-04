export const phases = {discovery:'วางแผนและเก็บความต้องการ',design:'ออกแบบประสบการณ์ใช้งาน',development:'พัฒนาระบบ',testing:'ทดสอบและตรวจรับ',delivery:'ส่งมอบและดูแล'};
export const health = {on_track:'เป็นไปตามแผน',at_risk:'มีประเด็นที่ต้องติดตาม',blocked:'รอการตัดสินใจ'};
export const milestoneStatus = {planned:'ตามแผน',in_progress:'กำลังดำเนินการ',submitted:'รอตรวจรับ',approved:'ตรวจรับแล้ว'};
export const requestStatus = {open:'รับเรื่องแล้ว',reviewing:'กำลังประเมิน',in_progress:'กำลังดำเนินการ',resolved:'ดำเนินการแล้ว',closed:'ปิดรายการ'};
export const updateKinds = {progress:'ความคืบหน้า',weekly:'สรุปประจำสัปดาห์',action:'ต้องการการตัดสินใจ'};
export function progress(items) { return items.length ? Math.round(items.filter(x=>x.status==='approved').length/items.length*100) : 0; }
export function safeUrl(value) { try { const u=new URL(value); return u.protocol==='https:' && !u.username && !u.password ? u.href : null; } catch { return null; } }
export function date(value) { if(!value) return 'ยังไม่กำหนด'; const d=new Date(value.length===10 ? value+'T12:00:00' : value); return Number.isNaN(+d) ? '—' : new Intl.DateTimeFormat('th-TH',{day:'numeric',month:'short',year:'numeric'}).format(d); }
export function normalizeEmail(value) { return value.trim().toLowerCase(); }
export function weeklyReport(project, data) { return [`สรุปโครงการ ${project.code} · ${project.title}`,`ขั้นตอน: ${phases[project.phase]}`,`สถานะ: ${health[project.health]}`,`ตรวจรับแล้ว ${data.milestones.filter(x=>x.status==='approved').length}/${data.milestones.length} Milestone (${progress(data.milestones)}%)`,`กำหนดส่ง: ${date(project.due_on)}`,`ขั้นตอนถัดไป: ${project.next_action||'รอทีมงานอัปเดต'}`,'',...data.milestones.map(x=>`• ${x.title} — ${milestoneStatus[x.status]}`),'',`คำขอที่ยังไม่ปิด: ${data.requests.filter(x=>!['resolved','closed'].includes(x.status)).length}`].join('\n'); }
export function friendlyError(error) {
  if(error?.code==='40001'||error?.code==='PGRST116') return 'ข้อมูลเปลี่ยนแปลงหรือสิทธิ์เข้าถึงถูกยกเลิก กรุณารีเฟรชแล้วลองอีกครั้ง';
  if(error?.code==='23505') return 'มีรหัสโครงการหรืออีเมลนี้แล้ว กรุณาตรวจสอบรายการเดิม';
  if(error?.code==='42501') return 'ไม่มีสิทธิ์ดำเนินการกับรายการนี้ กรุณาติดต่อผู้ดูแลโครงการ';
  return 'ดำเนินการไม่สำเร็จ กรุณาตรวจสอบการเชื่อมต่อแล้วลองอีกครั้ง หากยังพบปัญหาให้ติดต่อทีมงาน';
}
