<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Checkpoint;
use App\Models\CheckpointTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Admin controller for managing the global checkpoint library.
 */
class CheckpointController extends Controller
{
    public const CATEGORIES = ['NA', 'Logistics', 'A&D', 'Transportation', 'Guest Services'];

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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|in:'.implode(',', self::CATEGORIES),
            'type' => 'required|in:dispatch,arrival,boarding,departure,handoff,manual,auto',
            'description' => 'nullable|string',
            'capture_method' => 'required|in:auto,manual,gps,photo,signature',
            'requires_photo' => 'boolean',
            'requires_signature' => 'boolean',
            'requires_baggage_count' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Generate unique checkpoint code
        $validated['code'] = $this->generateCheckpointCode();

        $checkpoint = Checkpoint::create($validated);

        AuditLog::change('Checkpoint created', $checkpoint->code, ['name' => $checkpoint->name, 'type' => $checkpoint->type], $checkpoint);

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
            'category' => 'required|in:'.implode(',', self::CATEGORIES),
            'type' => 'required|in:dispatch,arrival,boarding,departure,handoff,manual,auto',
            'description' => 'nullable|string',
            'capture_method' => 'required|in:auto,manual,gps,photo,signature',
            'requires_photo' => 'boolean',
            'requires_signature' => 'boolean',
            'requires_baggage_count' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $original = $checkpoint->getOriginal();
        $checkpoint->update($validated);

        AuditLog::change('Checkpoint updated', $checkpoint->code, AuditLog::changes($checkpoint, $original), $checkpoint);

        // A rename carries through to every job's copy of the step, past and future.
        $renamed = $checkpoint->wasChanged('name')
            ? app(\App\Services\CheckpointNameSync::class)->apply([$checkpoint->id], "Job steps renamed after checkpoint {$checkpoint->code}")
            : 0;

        return redirect()->route('library')
            ->with('success', "Checkpoint '{$checkpoint->name}' updated successfully".($renamed ? " and renamed on {$renamed} job step(s)." : ''));
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
        $code = $checkpoint->code;
        $checkpoint->delete();

        AuditLog::change('Checkpoint deleted', $code, ['name' => $name]);

        return redirect()->route('library')
            ->with('success', "Checkpoint '{$name}' deleted successfully");
    }

    /**
     * Remove multiple checkpoints from storage in one request. Checkpoints
     * still in use by a template are skipped rather than blocking the
     * whole batch.
     */
    public function destroyBulk(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:checkpoints,id',
        ]);

        $checkpoints = Checkpoint::withCount('checkpointTemplates')
            ->whereIn('id', $validated['ids'])
            ->get();

        $deletableIds = $checkpoints
            ->filter(fn ($checkpoint) => $checkpoint->checkpoint_templates_count === 0)
            ->pluck('id');

        $skippedCount = $checkpoints->count() - $deletableIds->count();

        Checkpoint::whereIn('id', $deletableIds)->delete();

        AuditLog::change('Checkpoints bulk deleted', "{$deletableIds->count()} checkpoint(s)", ['ids' => $deletableIds->values()->all(), 'skipped' => $skippedCount]);

        $message = "Deleted {$deletableIds->count()} checkpoint(s).";
        if ($skippedCount > 0) {
            $message .= " {$skippedCount} skipped (in use by a template).";
        }

        return redirect()->route('library')->with(
            $deletableIds->count() > 0 ? 'success' : 'error',
            $message
        );
    }

    /**
     * Set the category of several checkpoints at once.
     */
    public function updateCategoryBulk(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:checkpoints,id',
            'category' => 'required|in:'.implode(',', self::CATEGORIES),
        ]);

        $changed = Checkpoint::whereIn('id', $validated['ids'])->where('category', '!=', $validated['category'])->get(['id', 'code']);
        Checkpoint::whereIn('id', $changed->pluck('id'))->update(['category' => $validated['category']]);

        AuditLog::change('Checkpoints category changed', "{$changed->count()} checkpoint(s)", [
            'category' => $validated['category'],
            'codes' => $changed->pluck('code')->all(),
        ]);

        return redirect()->route('library')->with('success', $changed->count()
            ? "Category set to {$validated['category']} for {$changed->count()} checkpoint(s)."
            : 'Those checkpoints already have that category.');
    }

    /**
     * Append checkpoints to a checkpoint template of the active event, after its last step,
     * in code order. Ones already in the template are skipped.
     */
    public function addToTemplate(Request $request)
    {
        $validated = $request->validate([
            'template_id' => ['required', 'integer', Rule::exists('checkpoint_templates', 'id')
                ->where('event_id', $request->session()->get('active_event_id'))],
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:checkpoints,id',
        ], ['template_id.exists' => 'Pick a checkpoint template of the active event.']);

        $template = CheckpointTemplate::findOrFail($validated['template_id']);
        $existing = DB::table('checkpoint_checkpoint_template')->where('checkpoint_template_id', $template->id)->pluck('checkpoint_id')->all();
        $adding = Checkpoint::whereIn('id', $validated['ids'])->whereNotIn('id', $existing)->get(['id', 'code'])
            ->sortBy('code', SORT_NATURAL)->values();

        DB::transaction(function () use ($template, $adding) {
            $order = (int) DB::table('checkpoint_checkpoint_template')->where('checkpoint_template_id', $template->id)->max('order');
            foreach ($adding as $checkpoint) {
                $template->checkpoints()->attach($checkpoint->id, ['order' => ++$order, 'is_required' => true, 'estimated_minutes' => null]);
            }
        });

        $skipped = count(array_unique($validated['ids'])) - $adding->count();
        AuditLog::change('Checkpoints added to template', $template->code, [
            'added' => $adding->pluck('code')->all(),
            'skipped' => $skipped ?: null,
        ], $template, $template->event_id);

        $message = $adding->count()
            ? "Added {$adding->count()} checkpoint(s) to {$template->name}."
            : "Nothing added: they are all in {$template->name} already.";
        if ($adding->count() && $skipped) {
            $message .= " {$skipped} already in it.";
        }

        return redirect()->route('library')->with($adding->count() ? 'success' : 'error', $message);
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
