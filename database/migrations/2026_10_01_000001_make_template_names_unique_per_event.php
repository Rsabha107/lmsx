<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['checkpoint_templates', 'movement_templates'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $this->renameDuplicates($table);

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unique(['event_id', 'name']);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropUnique(['event_id', 'name']);
            });
        }
    }

    /**
     * Existing duplicates keep the oldest row's name; later ones get " (2)", " (3)", ...
     */
    private function renameDuplicates(string $table): void
    {
        $taken = [];
        $key = fn ($eventId, string $name) => ($eventId ?? '-').'|'.mb_strtolower(trim($name));

        foreach (DB::table($table)->orderBy('id')->get(['id', 'event_id', 'name']) as $row) {
            $name = $row->name;
            $suffix = 2;
            while (isset($taken[$key($row->event_id, $name)])) {
                $name = "{$row->name} ({$suffix})";
                $suffix++;
            }
            $taken[$key($row->event_id, $name)] = true;

            if ($name !== $row->name) {
                DB::table($table)->where('id', $row->id)->update(['name' => $name]);
            }
        }
    }
};
