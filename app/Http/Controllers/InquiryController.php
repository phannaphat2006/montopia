<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInquiryRequest;
use App\Models\Inquiry;
use App\Services\LineMessagingService;
use Illuminate\Http\JsonResponse;

class InquiryController extends Controller
{
    public function store(StoreInquiryRequest $request, LineMessagingService $line): JsonResponse
    {
        $inquiry = Inquiry::create($request->validated());
        $line->notifyNewInquiry($inquiry);

        return response()->json(['message' => 'บันทึกบรีฟเรียบร้อย', 'reference' => 'INQ-'.str_pad((string) $inquiry->id, 6, '0', STR_PAD_LEFT)], 201);
    }
}
