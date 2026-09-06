<?php

namespace App\Http\Controllers;

use App\Http\Requests\PortfolioRequest;
use App\Models\Portfolio;
use Illuminate\Http\JsonResponse;

class AdminPortfolioController extends Controller
{
    public function store(PortfolioRequest $r): JsonResponse
    {
        return response()->json(['data' => Portfolio::create($r->validated())], 201);
    }

    public function update(PortfolioRequest $r, Portfolio $portfolio): JsonResponse
    {
        $portfolio->update($r->validated());

        return response()->json(['data' => $portfolio->fresh()]);
    }

    public function destroy(Portfolio $portfolio): JsonResponse
    {
        $portfolio->delete();

        return response()->json(null, 204);
    }
}
