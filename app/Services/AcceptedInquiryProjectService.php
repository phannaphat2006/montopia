<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptedInquiryProjectService
{
    /** Return the existing project or create one once, including for concurrent requests. */
    public function ensure(Inquiry $inquiry, ?User $actor): array
    {
        return DB::transaction(function () use ($inquiry, $actor) {
            $inquiry = Inquiry::query()->lockForUpdate()->findOrFail($inquiry->id);
            $existing = $inquiry->project()->first();
            if ($existing) {
                return ['project' => $existing, 'created' => false];
            }

            $client = User::query()->where('role', 'client')
                ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($inquiry->client_email))])->first();
            $project = Project::create([
                'inquiry_id' => $inquiry->id,
                'client_user_id' => $client?->id,
                'project_name' => mb_substr('โครงการของ '.$inquiry->client_name, 0, 150),
                'client_name' => $inquiry->client_name,
                // A budget range is not an agreed quotation. Keep an explicit placeholder.
                'total_budget' => 0,
                'status' => 'planned',
                'progress_percent' => 0,
                'start_date' => now(config('monstopia.business_timezone'))->toDateString(),
                'end_date' => null,
                'updated_by' => $actor?->id,
            ]);
            app(ProjectActivityService::class)->record($project, $actor, [
                'type' => 'project',
                'title' => 'รับงานและเปิดโครงการจากบรีฟ #'.$inquiry->id,
                'body' => "รายละเอียดบรีฟ:\n".$inquiry->project_scope
                    ."\n\nช่วงงบที่ลูกค้าระบุ: ".$inquiry->budget_range
                    ."\nมูลค่าโครงการเริ่มต้น 0 เป็นค่ารอระบุ ไม่ใช่ราคาที่ตกลงแล้ว"
                    ."\nวันเริ่มตั้งต้นเป็นวันที่เปิดโครงการ กรุณาปรับวันเริ่มจริงและวันส่งงานในข้อมูลโครงการ",
                'to_status' => 'planned',
                'progress_percent' => 0,
                'visible_to_client' => false,
            ], false);

            return ['project' => $project, 'created' => true];
        });
    }
}
