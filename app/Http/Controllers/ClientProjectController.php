<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->projects()
            ->where('status', '!=', 'archived')
            ->with([
                'milestones',
                'attachments' => fn ($query) => $query->where('visibility', 'client')->with('user:id,name'),
                'updates' => fn ($query) => $query->where('visible_to_client', true)->with('user:id,name'),
            ])
            ->latest('updated_at')
            ->get()]);
    }
}
