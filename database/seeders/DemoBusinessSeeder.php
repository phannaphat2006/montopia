<?php

namespace Database\Seeders;

use App\Models\Inquiry;
use App\Models\Milestone;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoBusinessSeeder extends Seeder
{
    public function run(): void
    {
        $client = User::firstOrCreate(
            ['email' => 'demo.client@monstopia.test'],
            [
                'name' => 'บริษัทตัวอย่าง จำกัด',
                'password' => Str::password(32),
                'role' => 'client',
                'phone' => '081-234-5678',
                'must_change_password' => true,
            ]
        );

        $inquiry = Inquiry::firstOrCreate(
            ['client_email' => 'demo.client@monstopia.test'],
            [
                'client_name' => 'บริษัทตัวอย่าง จำกัด',
                'client_phone' => '081-234-5678',
                'budget_range' => '300,000–1,000,000 บาท',
                'project_scope' => 'ต้องการระบบติดตามงานบริการลูกค้า Dashboard สำหรับผู้บริหาร และรายงานสถานะแบบเรียลไทม์',
                'status' => 'accepted',
            ]
        );

        $project = Project::firstOrCreate(
            ['inquiry_id' => $inquiry->id],
            [
                'client_user_id' => $client->id,
                'project_name' => 'Customer Service Operations Platform',
                'client_name' => $client->name,
                'total_budget' => 480000,
                'status' => 'in_progress',
                'progress_percent' => 45,
                'start_date' => now()->subDays(30)->toDateString(),
                'end_date' => now()->addDays(60)->toDateString(),
            ]
        );

        $milestones = [
            ['title' => 'วิเคราะห์ความต้องการและออกแบบ UX/UI', 'description' => 'สรุป User Flow และหน้าจอหลักสำหรับทีมบริการลูกค้า', 'due_date' => now()->subDays(12)->toDateString(), 'status' => 'approved', 'completed_at' => now()->subDays(11)],
            ['title' => 'พัฒนาระบบสมาชิกและสิทธิ์ผู้ใช้', 'description' => 'ระบบเข้าสู่ระบบและกำหนดสิทธิ์ตามแผนก', 'due_date' => now()->addDays(7)->toDateString(), 'status' => 'in_progress', 'completed_at' => null],
            ['title' => 'Dashboard และรายงาน', 'description' => 'สรุปข้อมูลสำคัญและส่งออกรายงาน', 'due_date' => now()->addDays(32)->toDateString(), 'status' => 'pending', 'completed_at' => null],
            ['title' => 'ทดสอบและส่งมอบ', 'description' => 'UAT เอกสาร และอบรมผู้ดูแลระบบ', 'due_date' => now()->addDays(55)->toDateString(), 'status' => 'pending', 'completed_at' => null],
        ];
        foreach ($milestones as $milestone) {
            Milestone::firstOrCreate(
                ['project_id' => $project->id, 'title' => $milestone['title']],
                $milestone
            );
        }

        ProjectUpdate::firstOrCreate(
            ['project_id' => $project->id, 'title' => 'อนุมัติแบบหน้าจอระยะแรก'],
            [
                'type' => 'progress',
                'body' => 'ลูกค้าอนุมัติ User Flow และหน้าจอหลักแล้ว ทีมพัฒนาเริ่มทำระบบสมาชิกและสิทธิ์ผู้ใช้',
                'from_status' => 'planned',
                'to_status' => 'in_progress',
                'progress_percent' => 45,
                'visible_to_client' => true,
            ]
        );

        Portfolio::firstOrCreate(
            ['title' => 'BullMoonJR NFT Collaboration'],
            [
                'category' => 'Web3 Integration',
                'description' => 'ความร่วมมือพัฒนาโครงการ BullMoon Junior NFT และเชื่อมประสบการณ์สินทรัพย์ดิจิทัล',
                'image_url' => '/bullmoon-launch.webp',
                'technologies' => 'Laravel, JavaScript, Bitkub Chain',
            ]
        );
    }
}
