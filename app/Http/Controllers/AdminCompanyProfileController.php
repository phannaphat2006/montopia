<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyProfileRequest;
use App\Models\CompanyProfile;
use Illuminate\Http\JsonResponse;

class AdminCompanyProfileController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => CompanyProfile::query()->latest()->get()]);
    }

    public function store(CompanyProfileRequest $request): JsonResponse
    {
        return response()->json(['data' => CompanyProfile::create($request->validated())], 201);
    }

    public function update(CompanyProfileRequest $request, CompanyProfile $companyProfile): JsonResponse
    {
        $companyProfile->update($request->validated());

        return response()->json(['data' => $companyProfile->fresh()]);
    }

    public function destroy(CompanyProfile $companyProfile): JsonResponse
    {
        $companyProfile->delete();

        return response()->json(null, 204);
    }
}
