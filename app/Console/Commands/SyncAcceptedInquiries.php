<?php

namespace App\Console\Commands;

use App\Models\Inquiry;
use App\Services\AcceptedInquiryProjectService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncAcceptedInquiries extends Command
{
    protected $signature = 'monstopia:sync-accepted-inquiries {--dry-run : Count missing projects without changing data}';

    protected $description = 'Create missing projects for previously accepted inquiries without duplicating existing projects';

    public function handle(AcceptedInquiryProjectService $projects): int
    {
        $query = Inquiry::query()->where('status', 'accepted')->whereDoesntHave('project');
        if ($this->option('dry-run')) {
            $this->info('Accepted inquiries missing projects: '.$query->count());

            return self::SUCCESS;
        }
        $created = 0;
        $query->chunkById(100, function ($inquiries) use ($projects, &$created) {
            foreach ($inquiries as $inquiry) {
                // Also verify status after locking, in case staff changed it meanwhile.
                DB::transaction(function () use ($inquiry, $projects, &$created) {
                    $locked = Inquiry::query()->lockForUpdate()->findOrFail($inquiry->id);
                    if ($locked->status === 'accepted') {
                        $created += (int) $projects->ensure($locked, null)['created'];
                    }
                });
            }
        });
        $this->info('Created projects: '.$created);

        return self::SUCCESS;
    }
}
