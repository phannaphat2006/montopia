<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceRequest;
use App\Models\Service;
use Illuminate\Http\JsonResponse;

class AdminServiceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Service::query()->withCount('packages')->orderBy('display_order')->get()]);
    }

    public function store(ServiceRequest $request): JsonResponse
    {
        return response()->json(['data' => Service::create($request->validated())], 201);
    }

    public function update(ServiceRequest $request, Service $service): JsonResponse
    {
        $service->update($request->validated());

        return response()->json(['data' => $service->fresh()->loadCount('packages')]);
    }

    public function destroy(Service $service): JsonResponse
    {
        $service->delete();

        return response()->json(null, 204);
    }
}
