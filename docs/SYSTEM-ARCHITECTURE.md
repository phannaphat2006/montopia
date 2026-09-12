# สถาปัตยกรรมระบบ MONSTOPIA

เอกสารฉบับนี้อธิบายโครงสร้างสำหรับใช้อ้างอิงในบทที่ 3 ของปริญญานิพนธ์ โดย migration ใน `database/migrations` เป็นแหล่งข้อมูลจริงของ Schema

## 1. System Architecture

```text
Browser (HTML/CSS/Vanilla JS + Fetch API)
        │ HTTPS / JSON / Session cookie / CSRF token
        ▼
Laravel 12
├── Form Request Validation
├── Authentication + Role Middleware
├── REST-style Controllers
├── Eloquent Models / Transactions
├── SMTP Mail
└── LINE Messaging API
        │ PDO MySQL
        ▼
MySQL 8 (InnoDB, utf8mb4)
```

Frontend ไม่มีขั้นตอนคอมไพล์ Node/esbuild ไฟล์ที่ Browser ใช้อยู่ใน `public` โดยตรง Backend ใช้ Laravel Session แบบ HttpOnly, CSRF protection และ Bcrypt สำหรับรหัสผ่าน

## 2. สิทธิ์ผู้ใช้

| บทบาท | สิทธิ์ |
|---|---|
| Guest | ดูข้อมูลบริษัท บริการ แพ็กเกจ Portfolio ข่าวสาร และส่งบรีฟ |
| Client | สิทธิ์ Guest + ดูเฉพาะ Project/Milestone ที่ `projects.client_user_id` ตรงกับบัญชีตนเอง |
| Staff | CRUD เนื้อหาเว็บไซต์ และจัดการ Portfolio, Inquiry/Reply, Project และ Milestone แต่จัดการบัญชีผู้ใช้ไม่ได้ |
| Administrator | สิทธิ์ Staff + CRUD บัญชีและกำหนด Role |

ค่าเริ่มต้นของ `users.role` เป็น `client` ตามหลัก Least Privilege ไม่ใช้ `staff` เป็นค่าเริ่มต้น เพราะอาจให้สิทธิ์หลังบ้านโดยไม่ได้ตั้งใจ

## 3. ER Diagram

```mermaid
erDiagram
    USERS ||--o{ INQUIRY_REPLIES : writes
    USERS ||--o{ PROJECTS : owns_as_client
    USERS ||--o{ PROJECTS : updates
    USERS ||--o{ PROJECT_UPDATES : writes
    USERS ||--o{ PROJECT_ATTACHMENTS : uploads
    INQUIRIES ||--o{ INQUIRY_REPLIES : has
    INQUIRIES o|--o| PROJECTS : becomes
    PROJECTS ||--o{ MILESTONES : contains
    PROJECTS ||--o{ PROJECT_UPDATES : records
    PROJECTS ||--o{ PROJECT_ATTACHMENTS : stores
    SERVICES ||--o{ SERVICE_PACKAGES : groups

    USERS {
        bigint id PK
        varchar name
        varchar email UK
        varchar password
        enum role
        varchar phone
        boolean must_change_password
        timestamp last_login_at
    }
    PORTFOLIOS {
        bigint id PK
        varchar title
        varchar category
        text description
        varchar image_url
        varchar technologies
    }
    INQUIRIES {
        bigint id PK
        varchar client_name
        varchar client_email
        varchar client_phone
        varchar budget_range
        text project_scope
        enum status
    }
    INQUIRY_REPLIES {
        bigint id PK
        bigint inquiry_id FK
        bigint user_id FK
        text reply_message
        timestamp sent_at
    }
    PROJECTS {
        bigint id PK
        bigint inquiry_id FK,UK
        bigint client_user_id FK
        varchar project_name
        varchar client_name
        decimal total_budget
        enum status
        tinyint progress_percent
        date start_date
        date end_date
    }
    MILESTONES {
        bigint id PK
        bigint project_id FK
        varchar title
        text description
        date due_date
        enum status
        timestamp completed_at
    }
    PROJECT_UPDATES {
        bigint id PK
        bigint project_id FK
        bigint user_id FK
        varchar type
        varchar title
        text body
        varchar from_status
        varchar to_status
        tinyint progress_percent
        boolean visible_to_client
    }
    PROJECT_ATTACHMENTS {
        bigint id PK
        bigint project_id FK
        bigint user_id FK
        varchar original_name
        varchar stored_path
        varchar mime_type
        bigint size_bytes
        enum visibility
    }
    COMPANY_PROFILES {
        bigint id PK
        varchar name
        varchar email
        text address
        boolean is_published
    }
    SERVICES {
        bigint id PK
        varchar title
        varchar slug UK
        text description
        boolean is_published
    }
    SERVICE_PACKAGES {
        bigint id PK
        bigint service_id FK
        varchar name
        varchar price_label
        boolean is_featured
        boolean is_published
    }
    ARTICLES {
        bigint id PK
        varchar title
        varchar slug UK
        longtext content
        enum status
        timestamp published_at
    }
```

`client_user_id` เป็นคอลัมน์เพิ่มเติมจากโครงร่างแรกและจำเป็นต่อการบังคับ Ownership ฝั่ง Server การใช้อีเมลค้นหา Project โดยตรงเพียงอย่างเดียวเสี่ยงต่อการเปิดเผยข้อมูลและการสะกดอีเมลไม่ตรงกัน

## 4. Data Dictionary

ทุกตารางใช้ `id BIGINT UNSIGNED AUTO_INCREMENT` เป็น Primary Key ตารางที่ระบุ timestamps มี `created_at` และ `updated_at`

### users

| Field | Type / Constraint | ความหมาย |
|---|---|---|
| name | VARCHAR(100), NOT NULL | ชื่อผู้ใช้ |
| email | VARCHAR(150), UNIQUE, NOT NULL | อีเมลเข้าสู่ระบบ |
| password | VARCHAR(255), NOT NULL | รหัสผ่าน Bcrypt |
| role | ENUM(admin, staff, client), default client | ระดับสิทธิ์ |
| phone | VARCHAR(20), NULL | เบอร์โทร |
| must_change_password | BOOLEAN, default false | บังคับเปลี่ยนรหัสผ่านชั่วคราว; Controller ตั้งเป็น true เมื่อสร้างบัญชี |
| last_login_at | TIMESTAMP, NULL | เวลาเข้าสู่ระบบล่าสุด |
| timestamps | TIMESTAMP, NULL | เวลาสร้าง/แก้ไข |

### portfolios

| Field | Type / Constraint | ความหมาย |
|---|---|---|
| title | VARCHAR(150), NOT NULL | ชื่อผลงาน |
| category | VARCHAR(50), INDEX, NOT NULL | หมวดหมู่ |
| description | TEXT, NOT NULL | รายละเอียด |
| image_url | VARCHAR(255), NOT NULL | URL ภาพหน้าจอจริง |
| technologies | VARCHAR(255), NULL | เทคโนโลยี |
| timestamps | TIMESTAMP, NULL | เวลาสร้าง/แก้ไข |

### inquiries

| Field | Type / Constraint | ความหมาย |
|---|---|---|
| client_name | VARCHAR(100), NOT NULL | ผู้ติดต่อ/บริษัท |
| client_email | VARCHAR(150), INDEX, NOT NULL | อีเมลติดต่อ |
| client_phone | VARCHAR(20), NOT NULL | เบอร์โทร |
| budget_range | VARCHAR(50), NOT NULL | ช่วงงบประมาณ |
| project_scope | TEXT, NOT NULL | รายละเอียดบรีฟ |
| status | ENUM(pending, contacted, accepted, rejected), default pending | สถานะการประสานงาน |
| timestamps | TIMESTAMP, NULL | เวลาสร้าง/แก้ไข |

### inquiry_replies

| Field | Type / Constraint | ความหมาย |
|---|---|---|
| inquiry_id | BIGINT UNSIGNED, FK, CASCADE DELETE | บรีฟที่อ้างอิง |
| user_id | BIGINT UNSIGNED, FK, RESTRICT DELETE | ผู้ตอบ |
| reply_message | TEXT, NOT NULL | ข้อความตอบกลับ |
| sent_at | TIMESTAMP, default CURRENT_TIMESTAMP | เวลาส่ง |

### projects

| Field | Type / Constraint | ความหมาย |
|---|---|---|
| inquiry_id | BIGINT UNSIGNED, UNIQUE, FK, NULL | บรีฟตั้งต้น |
| client_user_id | BIGINT UNSIGNED, FK, NULL ในฐานข้อมูล/Required ใน API | เจ้าของโครงการที่เข้าระบบได้ |
| project_name | VARCHAR(150), NOT NULL | ชื่อโครงการ |
| client_name | VARCHAR(100), NOT NULL | ชื่อลูกค้า/องค์กร |
| total_budget | DECIMAL(10,2), NOT NULL | มูลค่าโครงการ; ไม่ส่งให้ Client API |
| status | ENUM(planned, in_progress, review, completed, archived), default planned | สถานะโครงการ |
| progress_percent | TINYINT UNSIGNED, default 0 | เปอร์เซ็นต์ความคืบหน้า 0-100 |
| start_date | DATE, NOT NULL | วันเริ่ม |
| end_date | DATE, NULL | วันสิ้นสุด |
| updated_by | BIGINT UNSIGNED, FK, NULL | เจ้าหน้าที่ที่แก้ล่าสุด |
| timestamps | TIMESTAMP, NULL | เวลาสร้าง/แก้ไข |

### milestones

| Field | Type / Constraint | ความหมาย |
|---|---|---|
| project_id | BIGINT UNSIGNED, FK, CASCADE DELETE | โครงการ |
| title | VARCHAR(150), NOT NULL | ชื่องวดงาน |
| description | TEXT, NULL | รายละเอียดส่งมอบ |
| due_date | DATE, NOT NULL | กำหนดส่ง |
| status | ENUM(pending, in_progress, delivered, approved), default pending | สถานะงวดงาน |
| completed_at | TIMESTAMP, NULL | เวลาอนุมัติ/จบงวดงาน |
| timestamps | TIMESTAMP, NULL | เวลาสร้าง/แก้ไข |

### project_updates

| Field | Type / Constraint | ความหมาย |
|---|---|---|
| project_id | BIGINT UNSIGNED, FK, CASCADE DELETE | โครงการที่อัปเดต |
| user_id | BIGINT UNSIGNED, FK, NULL | ผู้บันทึก |
| type | VARCHAR(40), default note, INDEX | ประเภทเหตุการณ์ เช่น note, status, file |
| title | VARCHAR(180), NOT NULL | หัวข้ออัปเดต |
| body | TEXT, NULL | รายละเอียดความคืบหน้า |
| from_status / to_status | VARCHAR(30), NULL | สถานะก่อนและหลังการเปลี่ยน |
| progress_percent | TINYINT UNSIGNED, NULL, 0-100 | ค่า Progress ณ เวลานั้น |
| visible_to_client | BOOLEAN, default true | ลูกค้าเห็นใน Workspace หรือไม่ |
| timestamps | TIMESTAMP, NULL | เวลาสร้าง/แก้ไข |

### project_attachments

| Field | Type / Constraint | ความหมาย |
|---|---|---|
| project_id | BIGINT UNSIGNED, FK, CASCADE DELETE | โครงการเจ้าของไฟล์ |
| user_id | BIGINT UNSIGNED, FK, NULL | เจ้าหน้าที่ผู้อัปโหลด |
| original_name | VARCHAR(255), NOT NULL | ชื่อไฟล์ที่ผู้ใช้เห็น |
| stored_path | VARCHAR(500), UNIQUE | ที่อยู่ไฟล์ Private; ไม่ส่งผ่าน API |
| mime_type | VARCHAR(120), NOT NULL | ชนิดไฟล์ที่ Server ตรวจพบ |
| size_bytes | BIGINT UNSIGNED | ขนาดไฟล์ ไม่เกินค่าที่กำหนด |
| visibility | ENUM(client, internal), default client | Client เจ้าของโครงการดาวน์โหลดได้หรือเก็บภายใน |
| timestamps | TIMESTAMP, NULL | เวลาสร้าง/แก้ไข |

### company_profiles

| Field | Type / Constraint | ความหมาย |
|---|---|---|
| name | VARCHAR(150), NOT NULL | ชื่อบริษัท |
| tagline | VARCHAR(255), NULL | ข้อความแนะนำสั้น |
| description | TEXT, NOT NULL | รายละเอียดบริษัท |
| email | VARCHAR(150), NOT NULL | อีเมลติดต่อ |
| phone | VARCHAR(30), NULL | เบอร์โทร |
| address | TEXT, NOT NULL | ที่อยู่บริษัท |
| registration_number | VARCHAR(30), NULL | เลขทะเบียนนิติบุคคล |
| is_published | BOOLEAN, default true | สถานะการเผยแพร่ |

### services

| Field | Type / Constraint | ความหมาย |
|---|---|---|
| title | VARCHAR(150), NOT NULL | ชื่อบริการ |
| slug | VARCHAR(160), UNIQUE | ชื่อสำหรับอ้างอิงใน URL/API |
| short_description | VARCHAR(255), NOT NULL | คำอธิบายสั้น |
| description | TEXT, NOT NULL | รายละเอียดบริการ |
| icon_label | VARCHAR(20), NULL | อักษรย่อบนการ์ด |
| display_order | INT UNSIGNED, default 0 | ลำดับการแสดง |
| is_published | BOOLEAN, default true | สถานะการเผยแพร่ |

### service_packages

| Field | Type / Constraint | ความหมาย |
|---|---|---|
| service_id | BIGINT UNSIGNED, FK, NULL | บริการที่เกี่ยวข้อง |
| name | VARCHAR(150), NOT NULL | ชื่อแพ็กเกจ |
| price_label | VARCHAR(100), NOT NULL | ข้อความราคา |
| delivery_time | VARCHAR(100), NULL | ระยะเวลาดำเนินงาน |
| description | TEXT, NOT NULL | รายละเอียดแพ็กเกจ |
| features | TEXT, NULL | รายการสิ่งที่ได้รับ คั่นด้วยบรรทัดใหม่ |
| is_featured | BOOLEAN, default false | แพ็กเกจแนะนำ |
| is_published | BOOLEAN, default true | สถานะการเผยแพร่ |
| display_order | INT UNSIGNED, default 0 | ลำดับการแสดง |

### articles

| Field | Type / Constraint | ความหมาย |
|---|---|---|
| title | VARCHAR(180), NOT NULL | ชื่อบทความ |
| slug | VARCHAR(190), UNIQUE | ชื่อสำหรับอ้างอิงบทความ |
| excerpt | VARCHAR(300), NOT NULL | ข้อความเกริ่นนำ |
| content | LONGTEXT, NOT NULL | เนื้อหาบทความ |
| image_url | VARCHAR(255), NULL | URL รูปภาพ |
| status | ENUM(draft, published), default draft | สถานะบทความ |
| published_at | TIMESTAMP, NULL | วันเวลาเผยแพร่ |

## 5. Internal API

| Endpoint | Method | Role | การทำงาน |
|---|---|---|---|
| `/api/csrf` | GET | Public | รับ CSRF token สำหรับ same-origin Fetch |
| `/api/portfolios` | GET | Public | อ่านผลงาน |
| `/api/company` | GET | Public | อ่านข้อมูลบริษัทที่เผยแพร่ |
| `/api/services` | GET | Public | อ่านบริการที่เผยแพร่ |
| `/api/service-packages` | GET | Public | อ่านแพ็กเกจที่เผยแพร่ |
| `/api/articles` | GET | Public | อ่านข่าวสารที่เผยแพร่แล้ว |
| `/api/inquiries` | POST | Public, rate limited | Validation + บันทึกบรีฟ + แจ้ง LINE |
| `/api/auth/login` | POST | Public, rate limited | สร้าง Session |
| `/api/auth/me` | GET | Public | ตรวจ Session ปัจจุบัน |
| `/api/auth/logout` | POST | Authenticated | ยกเลิก Session |
| `/api/account/password` | PUT | Authenticated | เปลี่ยนรหัสผ่านและยกเลิกสถานะรหัสผ่านชั่วคราว |
| `/api/project-files/{attachment}/download` | GET | Team/Project owner | ดาวน์โหลดไฟล์ผ่าน Controller หลังตรวจสิทธิ์ |
| `/api/admin/clients` | GET | Admin, Staff | อ่านบัญชี Client สำหรับผูก Project |
| `/api/admin/dashboard` | GET | Admin, Staff | อ่านตัวเลขสรุปและบรีฟล่าสุด |
| `/api/admin/company-profiles` | GET/POST | Admin, Staff | อ่าน/เพิ่มข้อมูลบริษัท |
| `/api/admin/company-profiles/{id}` | PUT/DELETE | Admin, Staff | แก้ไข/ลบข้อมูลบริษัท |
| `/api/admin/services` | GET/POST | Admin, Staff | อ่าน/เพิ่มบริการ |
| `/api/admin/services/{id}` | PUT/DELETE | Admin, Staff | แก้ไข/ลบบริการ |
| `/api/admin/service-packages` | GET/POST | Admin, Staff | อ่าน/เพิ่มแพ็กเกจ |
| `/api/admin/service-packages/{id}` | PUT/DELETE | Admin, Staff | แก้ไข/ลบแพ็กเกจ |
| `/api/admin/articles` | GET/POST | Admin, Staff | อ่าน/เพิ่มข่าวสาร |
| `/api/admin/articles/{id}` | PUT/DELETE | Admin, Staff | แก้ไข/ลบข่าวสาร |
| `/api/admin/portfolios` | POST | Admin, Staff | เพิ่มผลงาน |
| `/api/admin/portfolios/{id}` | PUT/DELETE | Admin, Staff | แก้ไข/ลบผลงาน |
| `/api/admin/inquiries` | GET | Admin, Staff | อ่านรายการบรีฟและคำตอบ |
| `/api/admin/inquiries/{id}/reply` | POST | Admin, Staff | บันทึก Reply/Status แล้วส่งอีเมล |
| `/api/admin/projects` | GET/POST | Admin, Staff | อ่าน/เปิดโครงการ |
| `/api/admin/projects/{id}` | GET/PUT/DELETE | Admin, Staff | อ่านรายละเอียด แก้ไข หรือ Archive โครงการ |
| `/api/admin/projects/{id}/updates` | POST | Admin, Staff | บันทึก Progress/History และเลือกแจ้งอีเมลลูกค้า |
| `/api/admin/projects/{id}/milestones` | GET/POST | Admin, Staff | อ่าน/เพิ่ม Milestone |
| `/api/admin/projects/{id}/milestones/{milestone}` | PUT | Admin, Staff | อัปเดต Milestone ใน Project ที่ตรงกัน |
| `/api/admin/projects/{id}/attachments` | GET/POST | Admin, Staff | อ่าน/อัปโหลดไฟล์ Private ขนาดไม่เกิน 10 MB |
| `/api/admin/projects/{id}/attachments/{attachment}` | DELETE | Admin, Staff | ลบไฟล์หลังตรวจว่าอยู่ใน Project เดียวกัน |
| `/api/admin/users` | GET/POST | Admin | อ่าน/สร้างบัญชี |
| `/api/admin/users/{id}` | PUT/DELETE | Admin | แก้ไข/ลบบัญชี |
| `/api/client/projects` | GET | Client | อ่าน Project/Milestone/Update/ไฟล์ที่อนุญาตของบัญชีปัจจุบัน |

## 6. Security Decisions

- Session ID อยู่ใน cookie แบบ HttpOnly; Production บังคับ Secure cookie ผ่าน `.env`
- State-changing request ผ่าน CSRF middleware และ Validate Request ฝั่ง Server
- Login และฟอร์ม Public มี rate limiting
- Password ใช้ Laravel Hash driver `bcrypt`; บัญชีใหม่ใช้รหัสผ่านชั่วคราวและบังคับเปลี่ยนครั้งแรก
- Client query เริ่มจาก Relationship ของผู้ใช้ ไม่รับ client/project id จาก Browser
- Nested Milestone update ตรวจว่า `milestone.project_id` ตรงกับ Project ใน URL
- ไฟล์เก็บใน Private disk, เปลี่ยนชื่อเป็น UUID และ Download ผ่าน Controller ที่ตรวจ Role/Ownership
- Email/LINE failures ถูก Log โดยไม่เปิดเผย token และไม่ย้อนข้อมูล Inquiry ที่บันทึกสำเร็จแล้ว
- Project delete ใน API หมายถึง Archive เพื่อรักษาประวัติการดำเนินงาน
- Database Backup ทำรายวันและลบชุดเก่าตาม Retention โดยไม่ส่งรหัสผ่านฐานข้อมูลผ่าน Process Argument

## 7. External references

- Laravel 12 Authentication: <https://laravel.com/docs/12.x/authentication>
- Laravel 12 CSRF Protection: <https://laravel.com/docs/12.x/csrf>
- Laravel 12 Database: <https://laravel.com/docs/12.x/database>
- LINE Notify discontinuation: <https://notify-bot.line.me/closing-announce>
- LINE Messaging API push: <https://developers.line.biz/en/reference/messaging-api/#send-push-message>
