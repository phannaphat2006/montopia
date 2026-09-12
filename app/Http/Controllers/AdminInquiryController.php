<?php

namespace App\Http\Controllers;

use App\Mail\InquiryReplied;
use App\Models\Inquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AdminInquiryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Inquiry::with(['replies.user:id,name', 'project:id,inquiry_id,project_name'])->latest()->paginate(20)]);
    }

    public function reply(Request $request, Inquiry $inquiry): JsonResponse
    {
        $data = $request->validate(['reply_message' => ['required', 'string', 'max:5000'], 'status' => ['required', 'in:pending,contacted,accepted,rejected']]);
        $reply = DB::transaction(function () use ($inquiry, $data, $request) {
            $reply = $inquiry->replies()->create(['user_id' => $request->user()->id, 'reply_message' => $data['reply_message'], 'sent_at' => now()]);
            $inquiry->update(['status' => $data['status']]);

            return $reply;
        });
        $sent = true;
        try {
            Mail::to($inquiry->client_email)->send(new InquiryReplied($inquiry->fresh(), $reply));
        } catch (Throwable $e) {
            $sent = false;
            Log::warning('Inquiry reply email failed', ['inquiry_id' => $inquiry->id, 'exception' => $e::class]);
        }

        return response()->json(['data' => $reply->load('user:id,name'), 'email_sent' => $sent], 201);
    }
}
