<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Checkpoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Admin controller for managing the global checkpoint library.
 */
class CheckpointController extends Controller
{
    /**
     * Display a listing of checkpoints.
     */
    public function index(Request $request)
    {
        $query = Checkpoint::query();

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $checkpoints = $query->orderBy('code')->paginate(20);

        return Inertia::render('Admin/Checkpoints/Index', [
            'checkpoints' => $checkpoints,
            'filters' => $request->only(['search', 'type', 'active']),
        ]);
    }

    /**
     * Show the form for creating a new checkpoint.
     */
    public function create()
    {
        return Inertia::render('Admin/Checkpoints/Create', [
            'types' => ['dispatch', 'arrival', 'boarding', 'departure', 'handoff', 'manual', 'auto'],
            'captureMethods' => ['auto', 'manual', 'gps', 'photo', 'signature'],
        ]);
    }

    /**
     * Store a newly created checkpoint in storage.
     */
    public function store(Request $request)
    {
        Log::info('Creating checkpoint', ['request' => $request->all()]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:dispatch,arrival,boarding,departure,handoff,manual,auto',
            'description' => 'nullable|string',
            'capture_method' => 'required|in:auto,manual,gps,photo,signature',
            'requires_photo' => 'boolean',
            'requires_signature' => 'boolean',
            'is_active' => 'boolean',
        ]);

        Log::info('Validated checkpoint data', ['validated' => $validated]);

        // Generate unique checkpoint code
        $validated['code'] = $this->generateCheckpointCode();

        $checkpoint = Checkpoint::create($validated);

        return redirect()->route('library')
            ->with('success', "Checkpoint '{$checkpoint->name}' created successfully");
    }

    /**
     * Display the specified checkpoint.
     */
    public function show(Checkpoint $checkpoint)
    {
        $checkpoint->load('checkpointTemplates');

        $usage = [
            'templates_count' => $checkpoint->checkpointTemplates()->count(),
            'templates' => $checkpoint->checkpointTemplates()
                ->select('id', 'code', 'name', 'movement_type')
                ->get(),
        ];

        return Inertia::render('Admin/Checkpoints/Show', [
            'checkpoint' => $checkpoint,
            'usage' => $usage,
        ]);
    }

    /**
     * Show the form for editing the specified checkpoint.
     */
    public function edit(Checkpoint $checkpoint)
    {
        return Inertia::render('Admin/Checkpoints/Edit', [
            'checkpoint' => $checkpoint,
            'types' => ['dispatch', 'arrival', 'boarding', 'departure', 'handoff', 'manual', 'auto'],
            'captureMethods' => ['auto', 'manual', 'gps', 'photo', 'signature'],
        ]);
    }

    /**
     * Update the specified checkpoint in storage.
     */
    public function update(Request $request, Checkpoint $checkpoint)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:checkpoints,code,' . $checkpoint->id,
            'name' => 'required|string|max:255',
            'type' => 'required|in:dispatch,arrival,boarding,departure,handoff,manual,auto',
            'description' => 'nullable|string',
            'capture_method' => 'required|in:auto,manual,gps,photo,signature',
            'requires_photo' => 'boolean',
            'requires_signature' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $checkpoint->update($validated);

        return redirect()->route('library')
            ->with('success', "Checkpoint '{$checkpoint->name}' updated successfully");
    }

    /**
     * Remove the specified checkpoint from storage.
     */
    public function destroy(Checkpoint $checkpoint)
    {
        // Check if checkpoint is being used in any templates
        $templatesCount = $checkpoint->checkpointTemplates()->count();
        
        if ($templatesCount > 0) {
            return back()->with('error', "Cannot delete checkpoint. It is used in {$templatesCount} template(s). Remove it from templates first.");
        }

        $name = $checkpoint->name;
        $checkpoint->delete();

        return redirect()->route('library')
            ->with('success', "Checkpoint '{$name}' deleted successfully");
    }

    /**
     * Generate a unique checkpoint code.
     */
    protected function generateCheckpointCode(): string
    {
        // Generate globally unique checkpoint code
        $latestCheckpoint = Checkpoint::orderBy('id', 'desc')->first();
        $nextNumber = $latestCheckpoint ? (intval(ltrim($latestCheckpoint->code, 'CK')) + 1) : 1;
        
        return sprintf('CK%d', $nextNumber);
    }
}
