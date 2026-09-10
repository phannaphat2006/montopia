<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArticleRequest;
use App\Models\Article;
use Illuminate\Http\JsonResponse;

class AdminArticleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Article::query()->latest()->get()]);
    }

    public function store(ArticleRequest $request): JsonResponse
    {
        return response()->json(['data' => Article::create($request->validated())], 201);
    }

    public function update(ArticleRequest $request, Article $article): JsonResponse
    {
        $article->update($request->validated());

        return response()->json(['data' => $article->fresh()]);
    }

    public function destroy(Article $article): JsonResponse
    {
        $article->delete();

        return response()->json(null, 204);
    }
}
