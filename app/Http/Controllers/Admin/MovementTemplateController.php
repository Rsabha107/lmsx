<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CheckpointTemplate;
use App\Models\MovementTemplate;
use App\Models\MovementTemplateLeg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Admin controller for managing movement templates (multi-leg itineraries).
 */
class MovementTemplateController extends Controller
{
    /**
     * Display a listing of movement templates.
     */
    public function index(Request $request)
    {
        $query = MovementTemplate::withCount('legs');

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by scenario type
        if ($request->has('scenario_type')) {
            $query->where('scenario_type', $request->scenario_type);
        }

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $templates = $query->orderBy('code')->paginate(20);

        return Inertia::render('Admin/MovementTemplates/Index', [
            'templates' => $templates,
            'filters' => $request->only(['search', 'scenario_type', 'active']),
            'scenarioTypes' => ['match_day', 'training_day', 'arrival_day', 'departure_day', 'custom'],
        ]);
    }

    /**
     * Show the form for creating a new movement template.
     */
    public function create()
    {
        $checkpointTemplates = CheckpointTemplate::active()
            ->select('id', 'code', 'name', 'movement_type', 'estimated_duration_minutes')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/MovementTemplates/Create', [
            'scenarioTypes' => ['match_day', 'training_day', 'arrival_day', 'departure_day', 'custom'],
            'checkpointTemplates' => $checkpointTemplates,
            'legTypes' => ['arrival', 'departure', 'transfer', 'training', 'match'],
            'vehicleTypes' => ['coach', 'van', 'car', 'walk', 'auto'],
        ]);
    }

    /**
     * Store a newly created movement template in storage.
     */
    public function store(Request $request)
    {
        Log::info('Creating movement template', ['request' => $request->all()]);
        
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:movement_templates,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'scenario_type' => 'required|in:match_day,training_day,arrival_day,departure_day,full_day,custom',
            'estimated_duration_minutes' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'legs' => 'nullable|array',
            'legs.*.checkpoint_template_id' => 'required|exists:checkpoint_templates,id',
            'legs.*.order' => 'required|integer|min:1',
            'legs.*.leg_type' => 'required|in:arrival,departure,transfer,training,match',
            'legs.*.from_location' => 'nullable|string|max:255',
            'legs.*.to_location' => 'nullable|string|max:255',
            'legs.*.transport_type' => 'required|in:bus,walk,car,other',
            'legs.*.estimated_duration_minutes' => 'nullable|integer|min:1',
        ]);

        Log::info('Validated movement template data', ['validated' => $validated]);

        try {
            DB::beginTransaction();

            $template = MovementTemplate::create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'scenario_type' => $validated['scenario_type'],
                'total_legs' => isset($validated['legs']) ? count($validated['legs']) : 0,
                'estimated_duration_minutes' => $validated['estimated_duration_minutes'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Create legs if provided
            if (!empty($validated['legs'])) {
                foreach ($validated['legs'] as $leg) {
                    Log::info('Creating leg for movement template', ['leg' => $leg]);
                    MovementTemplateLeg::create([
                        'movement_template_id' => $template->id,
                        'checkpoint_template_id' => $leg['checkpoint_template_id'],
                        'order' => $leg['order'],
                        'leg_type' => $leg['leg_type'],
                        'from_location' => $leg['from_location'] ?? null,
                        'to_location' => $leg['to_location'] ?? null,
                        'estimated_duration_minutes' => $leg['estimated_duration_minutes'] ?? null,
                        'vehicle_type' => $leg['transport_type'], // Map transport_type to vehicle_type column
                    ]);
                }
            }

            DB::commit();
            Log::info('Movement template created', ['template' => $template]);

            return redirect()->route('library')
                ->with('success', "Movement template '{$template->name}' created successfully");
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create movement template', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create movement template: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified movement template.
     */
    public function show(MovementTemplate $movementTemplate)
    {
        $movementTemplate->load('legs.checkpointTemplate.checkpoints');
        
        $usage = [
            'plans_count' => $movementTemplate->plans()->count(),
        ];

        return Inertia::render('Admin/MovementTemplates/Show', [
            'template' => $movementTemplate,
            'usage' => $usage,
            'legs' => $movementTemplate->legs->map(fn($leg) => [
                'id' => $leg->id,
                'order' => $leg->order,
                'name' => $leg->name,
                'leg_type' => $leg->leg_type,
                'from_location' => $leg->from_location,
                'to_location' => $leg->to_location,
                'estimated_duration_minutes' => $leg->estimated_duration_minutes,
                'vehicle_type' => $leg->vehicle_type,
                'estimated_passengers' => $leg->estimated_passengers,
                'checkpoint_template' => [
                    'id' => $leg->checkpointTemplate->id,
                    'code' => $leg->checkpointTemplate->code,
                    'name' => $leg->checkpointTemplate->name,
                    'movement_type' => $leg->checkpointTemplate->movement_type,
                    'checkpoints_count' => $leg->checkpointTemplate->checkpoints->count(),
                ],
            ]),
        ]);
    }

    /**
     * Show the form for editing the specified movement template.
     */
    public function edit(MovementTemplate $movementTemplate)
    {
        $movementTemplate->load('legs.checkpointTemplate');
        
        $checkpointTemplates = CheckpointTemplate::active()
            ->select('id', 'code', 'name', 'movement_type', 'estimated_duration_minutes')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/MovementTemplates/Edit', [
            'template' => $movementTemplate,
            'scenarioTypes' => ['match_day', 'training_day', 'arrival_day', 'departure_day', 'custom'],
            'checkpointTemplates' => $checkpointTemplates,
            'legTypes' => ['arrival', 'departure', 'transfer', 'training', 'match'],
            'vehicleTypes' => ['coach', 'van', 'car', 'walk', 'auto'],
            'legs' => $movementTemplate->legs->map(fn($leg) => [
                'id' => $leg->id,
                'checkpoint_template_id' => $leg->checkpoint_template_id,
                'order' => $leg->order,
                'name' => $leg->name,
                'leg_type' => $leg->leg_type,
                'from_location' => $leg->from_location,
                'to_location' => $leg->to_location,
                'estimated_duration_minutes' => $leg->estimated_duration_minutes,
                'transport_type' => $leg->vehicle_type,
                'estimated_passengers' => $leg->estimated_passengers,
            ]),
        ]);
    }

    /**
     * Update the specified movement template in storage.
     */
    public function update(Request $request, MovementTemplate $movementTemplate)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:movement_templates,code,' . $movementTemplate->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'scenario_type' => 'required|in:match_day,training_day,arrival_day,departure_day,full_day,custom',
            'estimated_duration_minutes' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'legs' => 'nullable|array',
            'legs.*.checkpoint_template_id' => 'required|exists:checkpoint_templates,id',
            'legs.*.order' => 'required|integer|min:1',
            'legs.*.leg_type' => 'required|in:arrival,departure,transfer,training,match',
            'legs.*.from_location' => 'nullable|string|max:255',
            'legs.*.to_location' => 'nullable|string|max:255',
            'legs.*.transport_type' => 'required|in:bus,walk,car,other',
            'legs.*.estimated_duration_minutes' => 'nullable|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $movementTemplate->update([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'scenario_type' => $validated['scenario_type'],
                'estimated_duration_minutes' => $validated['estimated_duration_minutes'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Sync legs: delete existing and create new ones
            $movementTemplate->legs()->delete();
            
            if (!empty($validated['legs'])) {
                foreach ($validated['legs'] as $leg) {
                    MovementTemplateLeg::create([
                        'movement_template_id' => $movementTemplate->id,
                        'checkpoint_template_id' => $leg['checkpoint_template_id'],
                        'order' => $leg['order'],
                        'leg_type' => $leg['leg_type'],
                        'from_location' => $leg['from_location'] ?? null,
                        'to_location' => $leg['to_location'] ?? null,
                        'estimated_duration_minutes' => $leg['estimated_duration_minutes'] ?? null,
                        'vehicle_type' => $leg['transport_type'], // Map transport_type to vehicle_type column
                    ]);
                }
            }

            // Update total legs count
            $movementTemplate->update([
                'total_legs' => count($validated['legs'] ?? []),
            ]);

            DB::commit();

            return redirect()->route('library')
                ->with('success', "Movement template '{$movementTemplate->name}' updated successfully");
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update movement template', [
                'template_id' => $movementTemplate->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update movement template: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified movement template from storage.
     */
    public function destroy(MovementTemplate $movementTemplate)
    {
        // Check if template is being used
        $plansCount = $movementTemplate->plans()->count();
        
        if ($plansCount > 0) {
            return back()->with('error', "Cannot delete template. It is used in {$plansCount} plan(s).");
        }

        $name = $movementTemplate->name;
        $movementTemplate->delete();

        return redirect()->route('library')
            ->with('success', "Movement template '{$name}' deleted successfully");
    }

    /**
     * Add a leg to the movement template.
     */
    public function addLeg(Request $request, MovementTemplate $movementTemplate)
    {
        $validated = $request->validate([
            'checkpoint_template_id' => 'required|exists:checkpoint_templates,id',
            'order' => 'required|integer|min:1',
            'from_location' => 'nullable|string|max:255',
            'to_location' => 'nullable|string|max:255',
            'estimated_duration_minutes' => 'nullable|integer|min:1',
            'transport_type' => 'required|in:coach,van,car,walk,auto,bus',
        ]);

        $leg = MovementTemplateLeg::create([
            'movement_template_id' => $movementTemplate->id,
            'checkpoint_template_id' => $validated['checkpoint_template_id'],
            'order' => $validated['order'],
            'from_location' => $validated['from_location'] ?? null,
            'to_location' => $validated['to_location'] ?? null,
            'estimated_duration_minutes' => $validated['estimated_duration_minutes'] ?? null,
            'vehicle_type' => $validated['transport_type'], // Map transport_type to vehicle_type column
        ]);

        // Update total legs count
        $movementTemplate->update([
            'total_legs' => $movementTemplate->legs()->count(),
        ]);

        return back()->with('success', 'Leg added to movement template');
    }

    /**
     * Update a leg in the movement template.
     */
    public function updateLeg(Request $request, MovementTemplate $movementTemplate, MovementTemplateLeg $leg)
    {
        // Verify leg belongs to this template
        if ($leg->movement_template_id !== $movementTemplate->id) {
            return back()->with('error', 'Leg does not belong to this template');
        }

        $validated = $request->validate([
            'checkpoint_template_id' => 'required|exists:checkpoint_templates,id',
            'order' => 'required|integer|min:1',
            'from_location' => 'nullable|string|max:255',
            'to_location' => 'nullable|string|max:255',
            'estimated_duration_minutes' => 'nullable|integer|min:1',
            'transport_type' => 'required|in:coach,van,car,walk,auto,bus',
        ]);

        $leg->update([
            'checkpoint_template_id' => $validated['checkpoint_template_id'],
            'order' => $validated['order'],
            'from_location' => $validated['from_location'] ?? null,
            'to_location' => $validated['to_location'] ?? null,
            'estimated_duration_minutes' => $validated['estimated_duration_minutes'] ?? null,
            'vehicle_type' => $validated['transport_type'], // Map transport_type to vehicle_type column
        ]);

        return back()->with('success', 'Leg updated successfully');
    }

    /**
     * Remove a leg from the movement template.
     */
    public function removeLeg(MovementTemplate $movementTemplate, MovementTemplateLeg $leg)
    {
        // Verify leg belongs to this template
        if ($leg->movement_template_id !== $movementTemplate->id) {
            return back()->with('error', 'Leg does not belong to this template');
        }

        $leg->delete();

        // Update total legs count
        $movementTemplate->update([
            'total_legs' => $movementTemplate->legs()->count(),
        ]);

        return back()->with('success', 'Leg removed from movement template');
    }
}
