<?php

namespace App\Console\Commands;

use App\Customer;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeInactiveUsers extends Command
{
    protected $signature = 'users:purge-inactive {--dry-run : Preview users without deleting them}';

    protected $description = 'Permanently remove all inactive users from the system';

    public function handle()
    {
        $users = User::where('is_active', false)->orderBy('id')->get();

        if ($users->isEmpty()) {
            $this->info('No inactive users found.');
            return 0;
        }

        $this->info('Found ' . $users->count() . ' inactive user(s).');

        $deleted = 0;
        $skipped = 0;

        foreach ($users as $user) {
            if ((int) $user->id === 1) {
                $this->warn("Skipping primary admin user #{$user->id}.");
                $skipped++;
                continue;
            }

            $label = "#{$user->id} {$user->name} ({$user->email})";

            if ($this->option('dry-run')) {
                $this->line("- {$label}");
                continue;
            }

            try {
                $this->deleteUserRelations($user->id);
                Customer::where('user_id', $user->id)->update(['user_id' => null]);
                $user->delete();
                $deleted++;
                $this->line("Deleted {$label}");
            } catch (\Throwable $e) {
                $skipped++;
                $this->error("Could not delete {$label}: " . $e->getMessage());
            }
        }

        if (!$this->option('dry-run')) {
            $remaining = User::where('is_active', false)->where('id', '!=', 1)->count();
            $this->info("Removed {$deleted} inactive user(s). {$remaining} inactive user(s) remain.");
        }

        return 0;
    }

    private function deleteUserRelations($userId)
    {
        if (Schema::hasTable('model_has_roles')) {
            DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->where('model_id', $userId)
                ->delete();
        }

        if (Schema::hasTable('model_has_permissions')) {
            DB::table('model_has_permissions')
                ->where('model_type', User::class)
                ->where('model_id', $userId)
                ->delete();
        }

        if (Schema::hasTable('notifications')) {
            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $userId)
                ->delete();
        }
    }
}
