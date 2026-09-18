<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\CompanyProfile;
use App\Models\Inquiry;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'projects' => Project::query()->whereIn('status', ['planned', 'in_progress', 'review'])->count(),
                'all_projects' => Project::query()->where('status', '!=', 'archived')->count(),
                'clients' => User::query()->where('role', 'client')->count(),
                'inquiries' => Inquiry::query()->count(),
                'pending_inquiries' => Inquiry::query()->where('status', 'pending')->count(),
                'portfolios' => Portfolio::query()->count(),
                'services' => Service::query()->count(),
                'packages' => ServicePackage::query()->count(),
                'published_articles' => Article::query()->where('status', 'published')->count(),
                'company_profiles' => CompanyProfile::query()->count(),
                'recent_inquiries' => Inquiry::query()
                    ->latest()
                    ->orderByDesc('id')
                    ->limit(5)
                    ->get(['id', 'client_name', 'budget_range', 'status', 'created_at']),
                'project_statuses' => Project::query()
                    ->selectRaw('status, COUNT(*) AS total')
                    ->where('status', '!=', 'archived')
                    ->groupBy('status')
                    ->pluck('total', 'status'),
            ],
        ]);
    }
}
