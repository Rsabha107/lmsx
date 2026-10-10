<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * One-off: gives every field supervisor without a directory entry a contact (or links the same-named
 * unlinked one), so they show in the Contacts page and the mobile app's contact list.
 */
class SupervisorsToContacts extends Command
{
    protected $signature = 'fleet:supervisors-to-contacts {--apply : Write the changes (default is a dry run)}';

    protected $description = 'Create or link a contact for each field supervisor that has none';

    public function handle(): int
    {
        $linked = Contact::whereNotNull('user_id')->pluck('user_id');
        $supervisors = User::role('ground_control')->with('fleetProvider:id,name')->whereNotIn('users.id', $linked)->orderBy('name')->get();
        $unlinked = Contact::whereNull('user_id')->get();
        $apply = (bool) $this->option('apply');
        $created = $adopted = 0;

        foreach ($supervisors as $user) {
            $match = $unlinked->first(fn (Contact $c) => mb_strtolower(trim($c->name)) === mb_strtolower(trim($user->name)));

            if ($match) {
                $adopted++;
                $this->line("  link   {$user->name} -> existing contact #{$match->id}");
                if ($apply) {
                    $unlinked = $unlinked->reject(fn ($c) => $c->id === $match->id);
                    $match->forceFill(['user_id' => $user->id])->save();
                    $user->update(array_filter(['phone' => $user->phone ?: $match->phone, 'job_title' => $user->job_title ?: $match->role]));
                }

                continue;
            }

            $created++;
            $this->line("  create {$user->name}");
            if ($apply) {
                Contact::create([
                    'name' => $user->name,
                    'role' => $user->job_title ?: 'Field Supervisor',
                    'org' => $user->fleetProvider?->name,
                    'phone' => $user->phone,
                ])->forceFill(['user_id' => $user->id])->save();
            }
        }

        $this->info(sprintf('%d to create, %d to link%s', $created, $adopted, $apply ? ' (done)' : ''));

        if (! $apply) {
            $this->warn('Dry run. Re-run with --apply to write these changes.');

            return self::SUCCESS;
        }

        AuditLog::change('Supervisors added to contacts', null, ['created' => $created, 'linked' => $adopted]);

        return self::SUCCESS;
    }
}
