# MONSTOPIA Information System

เว็บไซต์บริษัท ระบบรับบรีฟ ระบบหลังบ้าน และ Client Workspace บน Laravel 12 + MySQL โดยไม่ใช้ Supabase, BaaS, Node.js หรือ esbuild

## ความสามารถหลัก

- เว็บไซต์บริษัท บริการ แพ็กเกจ Portfolio และบทความจากฐานข้อมูล
- ฟอร์มบรีฟส่ง JSON ด้วย Fetch API และบันทึกลง `inquiries`
- Session authentication พร้อม RBAC: `admin`, `staff`, `client`
- Dashboard สรุปบรีฟ โครงการ ลูกค้า และสถานะงานล่าสุด
- หลังบ้าน CRUD ข้อมูลบริษัท บริการ แพ็กเกจ ผลงาน ข่าวสาร และบัญชีผู้ใช้
- หลังบ้านจัดการ Inquiry/Reply, Project, Milestone, เปอร์เซ็นต์งาน, ไฟล์ส่งมอบ และประวัติอัปเดต
- ลูกค้าเห็นเฉพาะโครงการ ประวัติ และไฟล์ที่ผูกกับบัญชีของตน
- อีเมลตอบกลับลูกค้าและแจ้งความคืบหน้า พร้อม LINE Messaging API สำหรับแจ้งทีมงาน
- สำรอง MySQL รายวัน เก็บย้อนหลังตามจำนวนวันที่ตั้งค่า และมี Laravel SQL fallback เมื่อ `mysqldump` ใช้ไม่ได้

## ความต้องการระบบ

- PHP 8.4.1 ขึ้นไป พร้อมส่วนขยาย `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`
- Composer 2
- MySQL 8 หรือ MySQL-compatible server ที่รองรับ InnoDB และ `utf8mb4`
- เว็บเซิร์ฟเวอร์ที่กำหนด Document Root ไปยังโฟลเดอร์ `public`

## ติดตั้งบนเครื่องพัฒนา

```bash
composer install
copy .env.example .env
php artisan key:generate
```

แก้ค่า `DB_*`, `MAIL_*` และ `APP_URL` ใน `.env` แล้วสร้างฐานข้อมูล MySQL เปล่าที่เข้ารหัส `utf8mb4` จากนั้นรัน:

```bash
php artisan migrate
php artisan db:seed
php artisan monstopia:create-admin admin@example.com --name="Administrator" --generate
php artisan serve
```

ในโหมด `local` Seeder จะสร้างเนื้อหาและข้อมูลจำลองสำหรับสาธิต Workflow แต่ไม่สร้างบัญชี Admin ที่มีรหัสผ่านตายตัว ผู้ดูแลต้องสร้างบัญชีด้วยคำสั่งข้างต้น ระบบจะแสดงรหัสผ่านชั่วคราวเพียงครั้งเดียวและบังคับเปลี่ยนหลัง Login ครั้งแรก

## ทดสอบ

```bash
php artisan test
php artisan monstopia:backup-database
```

ชุดทดสอบใช้ SQLite in-memory เพื่อทดสอบ migration, validation, session, RBAC, การแยกข้อมูลลูกค้า, อีเมล และ LINE request โดยไม่ส่งข้อมูลออกจริง ก่อนขึ้น Production ควรทดสอบ migration และ flow สำคัญกับ MySQL staging อีกครั้ง

## การ Deploy

ระบบนี้ไม่สามารถใช้งาน Backend จริงบน Netlify Drop หรือ static hosting ได้ เพราะ Laravel ต้องรัน PHP และเชื่อม MySQL ให้ใช้โฮสต์ที่รองรับ Laravel/PHP + MySQL เช่น VPS, managed Laravel host หรือ cPanel ที่ตั้ง Document Root เป็น `public`

ขั้นตอน Production อย่างย่อ:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

ตั้ง `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, เปิด HTTPS และให้เว็บเซิร์ฟเวอร์เขียนได้เฉพาะ `storage` กับ `bootstrap/cache`

## LINE

LINE Notify ยุติบริการแล้วเมื่อ 31 มีนาคม 2025 ระบบจึงใช้ LINE Messaging API แทน กำหนด `LINE_CHANNEL_ACCESS_TOKEN` และ `LINE_TARGET_ID` ใน `.env` หากไม่กำหนด ฟอร์มยังบันทึกลงฐานข้อมูลได้ตามปกติ

รายละเอียดสถาปัตยกรรม, ER Diagram, Data Dictionary และ Endpoint อยู่ที่ [`docs/SYSTEM-ARCHITECTURE.md`](docs/SYSTEM-ARCHITECTURE.md) คู่มือใช้งานหลังบ้านอยู่ที่ [`docs/ADMIN-GUIDE.md`](docs/ADMIN-GUIDE.md) และขั้นตอนขึ้นระบบจริงอยู่ที่ [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md)
