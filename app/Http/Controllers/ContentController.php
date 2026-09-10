<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\CompanyProfile;
use App\Models\Service;
use App\Models\ServicePackage;
use Illuminate\Http\JsonResponse;

class ContentController extends Controller
{
    public function company(): JsonResponse
    {
        return response()->json([
            'data' => CompanyProfile::query()->where('is_published', true)->latest()->first(),
        ]);
    }

    public function services(): JsonResponse
    {
        return response()->json([
            'data' => Service::query()
                ->where('is_published', true)
                ->orderBy('display_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function packages(): JsonResponse
    {
        return response()->json([
            'data' => ServicePackage::query()
                ->with('service:id,title,slug')
                ->where('is_published', true)
                ->orderByDesc('is_featured')
                ->orderBy('display_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function articles(): JsonResponse
    {
        return response()->json([
            'data' => Article::query()
                ->where('status', 'published')
                ->where(function ($query) {
                    $query->whereNull('published_at')->orWhere('published_at', '<=', now());
                })
                ->latest('published_at')
                ->get(),
        ]);
    }

    public function article(string $slug): JsonResponse
    {
        $article = Article::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->where(function ($query) {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->firstOrFail();

        return response()->json(['data' => $article]);
    }
}
