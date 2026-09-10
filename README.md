# MONSTOPIA Information System

เว็บไซต์บริษัท ระบบรับบรีฟ ระบบหลังบ้าน และ Client Workspace บน Laravel 12 + MySQL โดยไม่ใช้ Supabase, BaaS, Node.js หรือ esbuild

## ความสามารถหลัก

- เว็บไซต์บริษัท บริการ แพ็กเกจ Portfolio และบทความจากฐานข้อมูล
- ฟอร์มบรีฟส่ง JSON ด้วย Fetch API และบันทึกลง `inquiries`
- Session authentication พร้อม RBAC: `admin`, `staff`, `client`
- Dashboard สรุปความพร้อมของเว็บไซต์และบรีฟล่าสุด
- หลังบ้าน CRUD ข้อมูลบริษัท บริการ แพ็กเกจ ผลงาน ข่าวสาร และบัญชีผู้ใช้
- หลังบ้านจัดการ Inquiry/Reply, Project และ Milestone
- ลูกค้าเห็นเฉพาะโครงการที่ผูกกับบัญชีอีเมลของตน
- อีเมลตอบกลับลูกค้า และการแจ้งเตือนผ่าน LINE Messaging API แบบไม่ทำให้การบันทึกข้อมูลล้มเหลว

## ความต้องการระบบ

- PHP 8.2 ขึ้นไป พร้อมส่วนขยาย `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`
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
php artisan monstopia:create-admin
php artisan serve
```

Seeder สร้างเฉพาะเนื้อหาเริ่มต้นสำหรับหน้าเว็บไซต์ ไม่สร้างบัญชีหรือรหัสผ่านตัวอย่าง ผู้ดูแลต้องสร้างบัญชีแรกผ่านคำสั่งข้างต้น แล้วจึงสร้างบัญชี Staff/Client จากหน้า Workspace

## ทดสอบ

```bash
php artisan test
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

รายละเอียดสถาปัตยกรรม, ER Diagram, Data Dictionary และ Endpoint อยู่ที่ [`docs/SYSTEM-ARCHITECTURE.md`](docs/SYSTEM-ARCHITECTURE.md) และคู่มือใช้งานหลังบ้านอยู่ที่ [`docs/ADMIN-GUIDE.md`](docs/ADMIN-GUIDE.md)
