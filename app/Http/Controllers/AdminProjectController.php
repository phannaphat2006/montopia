<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminProjectController extends Controller
{
    public function clients(): JsonResponse
    {
        return response()->json(['data' => User::query()->where('role', 'client')->select('id', 'name', 'email')->orderBy('name')->get()]);
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => Project::withCount('milestones')->with('client:id,name,email')->latest()->get()->makeVisible('total_budget')]);
    }

    public function store(ProjectRequest $r): JsonResponse
    {
        $project = Project::create($r->validated())->fresh();

        return response()->json(['data' => $project->makeVisible('total_budget')], 201);
    }

    public function update(ProjectRequest $r, Project $project): JsonResponse
    {
        $project->update($r->validated());

        return response()->json(['data' => $project->fresh()->makeVisible('total_budget')]);
    }

    public function destroy(Project $project): JsonResponse
    {
        $project->update(['status' => 'archived']);

        return response()->json(['data' => $project->fresh()->makeVisible('total_budget')]);
    }
}
