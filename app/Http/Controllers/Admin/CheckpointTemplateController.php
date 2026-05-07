<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Checkpoint;
use App\Models\CheckpointTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Admin controller for managing checkpoint templates (sequences).
 */
class CheckpointTemplateController extends Controller
{
    /**
     * Display a listing of checkpoint templates.
     */
    public function index(Request $request)
    {
        $query = CheckpointTemplate::withCount('checkpoints');

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by movement type
        if ($request->has('movement_type')) {
            $query->where('movement_type', $request->movement_type);
        }

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $templates = $query->orderBy('code')->paginate(20);

        return Inertia::render('Admin/CheckpointTemplates/Index', [
            'templates' => $templates,
            'filters' => $request->only(['search', 'movement_type', 'active']),
            'movementTypes' => ['arrival', 'departure', 'transfer', 'training', 'match'],
        ]);
    }

    /**
     * Show the form for creating a new checkpoint template.
     */
    public function create()
    {
        $checkpoints = Checkpoint::active()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type']);

        return Inertia::render('Admin/CheckpointTemplates/Create', [
            'movementTypes' => ['arrival', 'departure', 'transfer', 'training', 'match'],
            'checkpoints' => $checkpoints,
        ]);
    }

    /**
     * Store a newly created checkpoint template in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:checkpoint_templates,code',
            'name' => 'required|string|max:255',
            'movement_type' => 'required|in:arrival,departure,transfer,training,match',
            'description' => 'nullable|string',
            'estimated_duration_minutes' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'checkpoints' => 'nullable|array',
            'checkpoints.*.checkpoint_id' => 'required|exists:checkpoints,id',
            'checkpoints.*.order' => 'required|integer|min:1',
            'checkpoints.*.is_required' => 'boolean',
            'checkpoints.*.estimated_minutes' => 'nullable|integer|min:1',
        ]);

        $template = CheckpointTemplate::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'movement_type' => $validated['movement_type'],
            'description' => $validated['description'] ?? null,
            'estimated_duration_minutes' => $validated['estimated_duration_minutes'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Attach checkpoints if provided
        if (!empty($validated['checkpoints'])) {
            foreach ($validated['checkpoints'] as $checkpoint) {
                $template->checkpoints()->attach($checkpoint['checkpoint_id'], [
                    'order' => $checkpoint['order'],
                    'is_required' => $checkpoint['is_required'] ?? true,
                    'estimated_minutes' => $checkpoint['estimated_minutes'] ?? null,
                ]);
            }
        }

        return redirect()->route('library')
            ->with('success', "Checkpoint template '{$template->name}' created successfully");
    }

    /**
     * Display the specified checkpoint template.
     */
    public function show(CheckpointTemplate $checkpointTemplate)
    {
        $checkpointTemplate->load('checkpoints');
        
        $usage = [
            'movements_count' => $checkpointTemplate->movements()->count(),
            'template_legs_count' => $checkpointTemplate->movementTemplateLegs()->count(),
        ];

        return Inertia::render('Admin/CheckpointTemplates/Show', [
            'template' => $checkpointTemplate,
            'usage' => $usage,
            'checkpoints' => $checkpointTemplate->checkpoints->map(fn($cp) => [
                'id' => $cp->id,
                'code' => $cp->code,
                'name' => $cp->name,
                'type' => $cp->type,
                'order' => $cp->pivot->order,
                'is_required' => $cp->pivot->is_required,
                'estimated_minutes' => $cp->pivot->estimated_minutes,
                'requires_photo' => $cp->requires_photo,
                'requires_signature' => $cp->requires_signature,
            ]),
        ]);
    }

    /**
     * Show the form for editing the specified checkpoint template.
     */
    public function edit(CheckpointTemplate $checkpointTemplate)
    {
        $checkpointTemplate->load('checkpoints');
        
        $availableCheckpoints = Checkpoint::active()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type']);

        return Inertia::render('Admin/CheckpointTemplates/Edit', [
            'template' => $checkpointTemplate,
            'movementTypes' => ['arrival', 'departure', 'transfer', 'training', 'match'],
            'availableCheckpoints' => $availableCheckpoints,
            'attachedCheckpoints' => $checkpointTemplate->checkpoints->map(fn($cp) => [
                'checkpoint_id' => $cp->id,
                'code' => $cp->code,
                'name' => $cp->name,
                'type' => $cp->type,
                'order' => $cp->pivot->order,
                'is_required' => $cp->pivot->is_required,
                'estimated_minutes' => $cp->pivot->estimated_minutes,
            ]),
        ]);
    }

    /**
     * Update the specified checkpoint template in storage.
     */
    public function update(Request $request, CheckpointTemplate $checkpointTemplate)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:checkpoint_templates,code,' . $checkpointTemplate->id,
            'name' => 'required|string|max:255',
            'movement_type' => 'required|in:arrival,departure,transfer,training,match',
            'description' => 'nullable|string',
            'estimated_duration_minutes' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'checkpoints' => 'nullable|array',
            'checkpoints.*.checkpoint_id' => 'required|exists:checkpoints,id',
            'checkpoints.*.order' => 'required|integer|min:1',
            'checkpoints.*.is_required' => 'boolean',
            'checkpoints.*.estimated_minutes' => 'nullable|integer|min:1',
        ]);

        $checkpointTemplate->update([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'movement_type' => $validated['movement_type'],
            'description' => $validated['description'] ?? null,
            'estimated_duration_minutes' => $validated['estimated_duration_minutes'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Sync checkpoints if provided
        if (isset($validated['checkpoints'])) {
            $syncData = [];
            foreach ($validated['checkpoints'] as $checkpoint) {
                $syncData[$checkpoint['checkpoint_id']] = [
                    'order' => $checkpoint['order'],
                    'is_required' => $checkpoint['is_required'] ?? true,
                    'estimated_minutes' => $checkpoint['estimated_minutes'] ?? null,
                ];
            }
            $checkpointTemplate->checkpoints()->sync($syncData);
        }

        return redirect()->route('library')
            ->with('success', "Checkpoint template '{$checkpointTemplate->name}' updated successfully");
    }

    /**
     * Remove the specified checkpoint template from storage.
     */
    public function destroy(CheckpointTemplate $checkpointTemplate)
    {
        // Check if template is being used
        $movementsCount = $checkpointTemplate->movements()->count();
        $legsCount = $checkpointTemplate->movementTemplateLegs()->count();
        
        if ($movementsCount > 0 || $legsCount > 0) {
            return back()->with('error', "Cannot delete template. It is used in {$movementsCount} movement(s) and {$legsCount} template leg(s).");
        }

        $name = $checkpointTemplate->name;
        $checkpointTemplate->delete();

        return redirect()->route('library')
            ->with('success', "Checkpoint template '{$name}' deleted successfully");
    }

    /**
     * Attach a checkpoint to the template.
     */
    public function attachCheckpoint(Request $request, CheckpointTemplate $checkpointTemplate)
    {
        $validated = $request->validate([
            'checkpoint_id' => 'required|exists:checkpoints,id',
            'order' => 'required|integer|min:1',
            'is_required' => 'boolean',
            'estimated_minutes' => 'nullable|integer|min:1',
        ]);

        // Check if checkpoint is already attached
        if ($checkpointTemplate->checkpoints()->where('checkpoint_id', $validated['checkpoint_id'])->exists()) {
            return back()->with('error', 'Checkpoint is already attached to this template');
        }

        $checkpointTemplate->checkpoints()->attach($validated['checkpoint_id'], [
            'order' => $validated['order'],
            'is_required' => $validated['is_required'] ?? true,
            'estimated_minutes' => $validated['estimated_minutes'] ?? null,
        ]);

        $checkpoint = Checkpoint::find($validated['checkpoint_id']);

        return back()->with('success', "Checkpoint '{$checkpoint->name}' added to template");
    }

    /**
     * Detach a checkpoint from the template.
     */
    public function detachCheckpoint(CheckpointTemplate $checkpointTemplate, Checkpoint $checkpoint)
    {
        if (!$checkpointTemplate->checkpoints()->where('checkpoint_id', $checkpoint->id)->exists()) {
            return back()->with('error', 'Checkpoint is not attached to this template');
        }

        $checkpointTemplate->checkpoints()->detach($checkpoint->id);

        return back()->with('success', "Checkpoint '{$checkpoint->name}' removed from template");
    }
}
