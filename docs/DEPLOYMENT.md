# คู่มือเตรียม Deploy MONSTOPIA

เอกสารนี้เป็น Checklist สำหรับนำ Laravel + MySQL ขึ้นระบบจริง เว็บไซต์แบบ Static เช่น Netlify Drop ใช้ได้เฉพาะหน้าตาเว็บ แต่ไม่สามารถรัน Login, CRUD, Upload และฐานข้อมูลของระบบนี้ได้

## 1. สิ่งที่ Hosting ต้องมี

- PHP 8.4.1 ขึ้นไป พร้อม `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`
- MySQL 8 หรือระบบที่เข้ากันได้กับ InnoDB และ `utf8mb4`
- Composer 2 และสิทธิ์ตั้ง Document Root เป็นโฟลเดอร์ `public`
- Cron Job สำหรับ Laravel Scheduler
- SSL Certificate เพื่อเปิด HTTPS
- SMTP สำหรับส่งอีเมล และพื้นที่เก็บไฟล์ Private/Backup ที่ไม่เปิดเป็น Public URL

ถ้า Hosting เป็นระบบที่สร้างเครื่องประมวลผลใหม่ได้ เช่น Laravel Cloud ให้เพิ่ม Object Storage และตั้ง `FILESYSTEM_DISK=s3` กับ `PROJECT_FILE_DISK=s3` เพื่อไม่ให้ไฟล์งานผูกกับดิสก์ชั่วคราวของเครื่องประมวลผล

## 2. ตัวแปร Production สำคัญ

คัดลอก `.env.example` เป็น `.env` บน Server แล้วตั้งค่าอย่างน้อย:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
SESSION_SECURE_COOKIE=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=monstopia
DB_USERNAME=your_database_user
DB_PASSWORD=your_secret_password

MAIL_MAILER=smtp
```

ห้าม Commit `.env` เพราะมีรหัสผ่านฐานข้อมูล, SMTP และ LINE Token ส่วน `.env.example` มีเฉพาะชื่อ Config และค่าตัวอย่างจึง Commit ได้

## 3. ขั้นตอนติดตั้ง

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

ตั้งสิทธิ์ให้ Web Server เขียนได้เฉพาะ `storage` และ `bootstrap/cache` จากนั้นสร้าง Admin แรก:

```bash
php artisan monstopia:create-admin admin@example.com --name="Administrator" --generate
```

เก็บรหัสผ่านชั่วคราวทันที เพราะคำสั่งแสดงเพียงครั้งเดียว และระบบจะบังคับเปลี่ยนหลัง Login

## 4. Scheduler และ Backup

ตั้ง Cron ให้ทำงานทุกนาที:

```cron
* * * * * cd /path/to/monstopia && php artisan schedule:run >> /dev/null 2>&1
```

ระบบเรียก `monstopia:backup-all` เวลา 02:00 ตามเขตเวลา `MONSTOPIA_BUSINESS_TIMEZONE` (ค่าเริ่มต้น Asia/Bangkok) ทุกวัน เก็บชุด SQL + ไฟล์แนบ + manifest ที่ `storage/app/private/backups` และคัดลอกเพิ่มไป `BACKUP_EXTERNAL_DIRECTORY` หากตั้งค่าไว้ ไม่ลบชุดเก่าอัตโนมัติ ควรเก็บอีกชุดบนคนละอุปกรณ์หรือพื้นที่นอกสถานที่ที่เข้ารหัส เพราะ Backup บนเครื่องเดียวกันไม่ช่วยเมื่อเครื่องเสียทั้งหมด

คำสั่ง SQL-only เดิมไม่รวมไฟล์แนบ ส่วน `monstopia:backup-all` รวมไฟล์ที่อ้างอิงในฐานข้อมูลด้วย การตั้ง Scheduler อย่างเดียวไม่ทำให้เกิด Backup หากยังไม่มี Cron/ตัวเรียก Scheduler ทำงานจริง ใน Windows ใช้ `Install-MONSTOPIA-BackupTask.ps1` ได้ อ่านข้อจำกัดและวิธีกู้คืนใน `docs/BACKUP-GUIDE.md`

ทดสอบก่อนเปิดระบบจริง:

```bash
php artisan monstopia:backup-all
```

การ Restore ควรทำในฐานข้อมูลทดสอบก่อนเสมอ แตกไฟล์ `.sql.gz` แล้ว Import ด้วย MySQL Client ที่เวอร์ชันเข้ากับ Server ห้าม Restore ทับ Production โดยไม่สำรองชุดปัจจุบันก่อน

## 5. Email และ LINE

- SMTP ใช้ส่งคำตอบบรีฟและแจ้งความคืบหน้าให้ลูกค้า
- LINE Messaging API ใช้แจ้งทีมงานเมื่อมีบรีฟใหม่ ไม่ใช้ LINE Notify เพราะบริการเดิมยุติแล้ว
- ทดลองด้วยอีเมลทดสอบก่อน และตรวจ Spam/DNS ของโดเมน เช่น SPF, DKIM และ DMARC
- ถ้าส่งอีเมลความคืบหน้าล้มเหลว ข้อมูลหลักยังอยู่ ทีมงานเห็นสถานะในประวัติโครงการและลองใหม่ได้ไม่เกิน 3 ครั้ง ระบบไม่ส่งซ้ำขณะกำลังส่งหรือส่งไปแล้ว `sent` หมายถึงบริการรับข้อความ ไม่ใช่การยืนยันเข้ากล่องจดหมาย ส่วน `simulated` คือ array/log ที่ยังไม่ส่งจริง

## 6. Security Checklist ก่อนเปิดสาธารณะ

- บังคับ HTTPS และ Redirect HTTP ไป HTTPS
- `APP_DEBUG=false` และไม่แสดง Stack Trace ต่อผู้ใช้
- ใช้บัญชีฐานข้อมูลเฉพาะระบบ ไม่ใช้ `root`
- ตรวจว่า `.env`, `storage`, Backup และไฟล์ Private เปิดผ่าน URL ตรงไม่ได้
- เปลี่ยนรหัสผ่านชั่วคราวทุกบัญชี และลบบัญชีทดสอบ
- ตรวจว่าบัญชีที่ยังไม่เปลี่ยนรหัสผ่านชั่วคราวถูก API ตอบกลับ `403` และเข้า Dashboard/Project ไม่ได้
- ทดสอบ Login ผิดเกิน 5 ครั้งว่าถูกจำกัด
- ทดสอบ Client A ว่าเปิดโครงการ/ไฟล์ของ Client B ไม่ได้
- เปิด Log Rotation, Monitoring พื้นที่ดิสก์ และทดสอบ Restore Backup ตามรอบ

## 7. Smoke Test หลัง Deploy

1. เปิดหน้าแรกและฟอร์มบรีฟด้วยโทรศัพท์และคอมพิวเตอร์
2. ส่งบรีฟทดสอบและยืนยันว่าข้อมูลเข้า Dashboard
3. Admin ตอบกลับและตรวจอีเมล
4. สร้าง Client และ Project จากบรีฟ
5. เพิ่ม Milestone, Update และไฟล์ทดสอบ
6. Login เป็น Client ตรวจว่าเห็นเฉพาะข้อมูลตนเอง
7. ลบข้อมูลทดสอบหรือเปลี่ยนเป็น Archived แล้วรัน Backup หนึ่งครั้ง
