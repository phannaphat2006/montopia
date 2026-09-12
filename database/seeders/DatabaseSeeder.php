<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\CompanyProfile;
use App\Models\Service;
use App\Models\ServicePackage;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        CompanyProfile::firstOrCreate(
            ['registration_number' => '0135566029506'],
            [
                'name' => 'บริษัท มอนส์โทเปีย จำกัด',
                'tagline' => 'พัฒนาซอฟต์แวร์ให้เข้ากับธุรกิจของคุณ',
                'description' => 'ให้บริการออกแบบและพัฒนาเว็บแอปพลิเคชัน โมบายแอป ระบบหลังบ้าน และโครงสร้างระบบ โดยเริ่มจากความเข้าใจวิธีทำงานและเป้าหมายทางธุรกิจ',
                'email' => 'contact@monstopia.co.th',
                'phone' => null,
                'address' => 'เลขที่ 1 อาคารพาร์ค สีลม ชั้น 30 ยูนิต 30.20 ถนนคอนแวนต์ แขวงสีลม เขตบางรัก กรุงเทพมหานคร 10500',
                'is_published' => true,
            ]
        );

        $services = [
            ['title' => 'Web Application', 'slug' => 'web-application', 'short_description' => 'ระบบงานและเว็บแอปสำหรับองค์กร', 'description' => 'พัฒนาระบบหลังบ้าน งานอนุมัติ CRM สต็อก และระบบเฉพาะทางที่เชื่อมข้อมูลให้อยู่ในกระบวนการเดียวกัน', 'icon_label' => 'WEB', 'display_order' => 1],
            ['title' => 'Mobile Application', 'slug' => 'mobile-application', 'short_description' => 'แอปพลิเคชัน iOS และ Android', 'description' => 'ออกแบบแอปสำหรับลูกค้าหรือทีมงาน โดยคำนึงถึงประสบการณ์ใช้งาน การเชื่อมต่อ API และการดูแลต่อเนื่อง', 'icon_label' => 'APP', 'display_order' => 2],
            ['title' => 'Cloud & Infrastructure', 'slug' => 'cloud-infrastructure', 'short_description' => 'โครงสร้างระบบที่พร้อมใช้งานจริง', 'description' => 'วางแผนคลาวด์ การสำรองข้อมูล การติดตามระบบ และการย้ายข้อมูลตามขนาดการใช้งานและงบประมาณ', 'icon_label' => 'CLD', 'display_order' => 3],
            ['title' => 'Web3 Solutions', 'slug' => 'web3-solutions', 'short_description' => 'เชื่อมบริการดิจิทัลกับบล็อกเชน', 'description' => 'ออกแบบการเชื่อม Wallet, NFT และระบบสมาชิก โดยพิจารณาประโยชน์ต่อผู้ใช้และข้อจำกัดของแพลตฟอร์ม', 'icon_label' => 'W3', 'display_order' => 4],
        ];

        $savedServices = collect($services)->mapWithKeys(function (array $service) {
            $saved = Service::firstOrCreate(
                ['slug' => $service['slug']],
                $service + ['is_published' => true]
            );

            return [$service['slug'] => $saved];
        });

        $packages = [
            ['service' => 'web-application', 'name' => 'Discovery Sprint', 'price_label' => 'เริ่มต้น 35,000 บาท', 'delivery_time' => '2–3 สัปดาห์', 'description' => 'เหมาะสำหรับโครงการที่ต้องการสรุปความต้องการและขอบเขตก่อนเริ่มพัฒนา', 'features' => "วิเคราะห์กระบวนการปัจจุบัน\nUser Flow และขอบเขตระบบ\nหน้าจอตัวอย่างเบื้องต้น\nแผนพัฒนาและประมาณการ", 'is_featured' => false, 'display_order' => 1],
            ['service' => 'web-application', 'name' => 'Business Web Application', 'price_label' => 'ประเมินตามขอบเขต', 'delivery_time' => '8–16 สัปดาห์', 'description' => 'ระบบเว็บสำหรับงานภายในหรือให้บริการลูกค้า พร้อมฐานข้อมูลและสิทธิ์ผู้ใช้งาน', 'features' => "ออกแบบ UX/UI ตามกระบวนการ\nระบบผู้ใช้และสิทธิ์เข้าถึง\nDashboard และรายงาน\nทดสอบและส่งมอบ Source code", 'is_featured' => true, 'display_order' => 2],
            ['service' => 'mobile-application', 'name' => 'Mobile Product', 'price_label' => 'ประเมินตามฟังก์ชัน', 'delivery_time' => '10–20 สัปดาห์', 'description' => 'แอปสำหรับ iOS และ Android ที่เชื่อมต่อกับระบบหลังบ้านและ API', 'features' => "ออกแบบหน้าจอแอป\nพัฒนาและเชื่อม API\nระบบแจ้งเตือนตามขอบเขต\nเตรียมส่งขึ้น Store", 'is_featured' => false, 'display_order' => 3],
        ];

        foreach ($packages as $package) {
            ServicePackage::firstOrCreate(
                ['name' => $package['name']],
                [
                    'service_id' => $savedServices[$package['service']]->id,
                    'price_label' => $package['price_label'],
                    'delivery_time' => $package['delivery_time'],
                    'description' => $package['description'],
                    'features' => $package['features'],
                    'is_featured' => $package['is_featured'],
                    'is_published' => true,
                    'display_order' => $package['display_order'],
                ]
            );
        }

        $articles = [
            ['title' => 'เริ่มโครงการซอฟต์แวร์อย่างไรให้ขอบเขตชัดเจน', 'slug' => 'how-to-start-a-software-project', 'excerpt' => 'คำถามสำคัญที่ช่วยให้ทีมธุรกิจและทีมพัฒนาเห็นเป้าหมายเดียวกันก่อนเริ่มลงมือ', 'content' => 'โครงการที่ดีควรเริ่มจากปัญหาที่ต้องการแก้ ผู้ใช้งานหลัก และผลลัพธ์ที่วัดได้ จากนั้นจึงจัดลำดับฟังก์ชันที่จำเป็นสำหรับระยะแรก พร้อมกำหนดวิธีตรวจรับงานในแต่ละช่วงให้ชัดเจน', 'published_at' => '2026-09-01 09:00:00'],
            ['title' => 'Checklist ก่อนนำระบบขึ้นใช้งานจริง', 'slug' => 'production-readiness-checklist', 'excerpt' => 'รายการตรวจสอบเรื่องข้อมูล สิทธิ์ผู้ใช้ การสำรองข้อมูล และการดูแลระบบหลังส่งมอบ', 'content' => 'ก่อนเปิดใช้งานควรทดสอบสถานการณ์หลัก ตรวจสิทธิ์ของผู้ใช้แต่ละบทบาท เตรียมแผนสำรองและกู้คืนข้อมูล รวมถึงระบุผู้รับผิดชอบเมื่อระบบพบปัญหา เพื่อให้การเปลี่ยนผ่านกระทบงานประจำน้อยที่สุด', 'published_at' => '2026-08-20 09:00:00'],
            ['title' => 'เลือกทำ Web Application หรือ Mobile Application', 'slug' => 'web-or-mobile-application', 'excerpt' => 'เปรียบเทียบจากพฤติกรรมผู้ใช้ ฟังก์ชันของอุปกรณ์ งบประมาณ และการดูแลระยะยาว', 'content' => 'Web Application เหมาะกับระบบที่ต้องเข้าถึงได้หลายอุปกรณ์และปรับปรุงได้รวดเร็ว ส่วน Mobile Application เหมาะเมื่อจำเป็นต้องใช้ความสามารถเฉพาะของโทรศัพท์หรือสร้างประสบการณ์ใช้งานต่อเนื่อง การตัดสินใจควรเริ่มจากงานของผู้ใช้มากกว่าความนิยมของเทคโนโลยี', 'published_at' => '2026-08-08 09:00:00'],
        ];

        foreach ($articles as $article) {
            Article::firstOrCreate(
                ['slug' => $article['slug']],
                $article + ['image_url' => null, 'status' => 'published']
            );
        }

        if (app()->environment('local')) {
            $this->call(DemoBusinessSeeder::class);
        }

        // Production accounts are created explicitly with monstopia:create-admin.
    }
}
