<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServicePackageRequest;
use App\Models\ServicePackage;
use Illuminate\Http\JsonResponse;

class AdminServicePackageController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ServicePackage::query()->with('service:id,title')->orderBy('display_order')->get(),
        ]);
    }

    public function store(ServicePackageRequest $request): JsonResponse
    {
        $package = ServicePackage::create($request->validated());

        return response()->json(['data' => $package->load('service:id,title')], 201);
    }

    public function update(ServicePackageRequest $request, ServicePackage $servicePackage): JsonResponse
    {
        $servicePackage->update($request->validated());

        return response()->json(['data' => $servicePackage->fresh()->load('service:id,title')]);
    }

    public function destroy(ServicePackage $servicePackage): JsonResponse
    {
        $servicePackage->delete();

        return response()->json(null, 204);
    }
}
