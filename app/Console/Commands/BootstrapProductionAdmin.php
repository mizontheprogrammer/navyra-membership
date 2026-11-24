<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BootstrapProductionAdmin extends Command
{
    protected $signature = 'app:bootstrap-production-admin';

    protected $description = 'Initialize the deployment administrator once, without exposing public signup.';

    public function handle(): int
    {
        if (User::where('is_admin', true)->exists()) {
            $this->info('Administrator already initialized.');

            return self::SUCCESS;
        }
        $email = config('app.bootstrap_admin.email');
        $name = config('app.bootstrap_admin.name');
        $hash = config('app.bootstrap_admin.password_hash');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! is_string($name) || trim($name) === '' || ! is_string($hash) || (password_get_info($hash)['algoName'] ?? 'unknown') === 'unknown') {
            $this->error('Configure BOOTSTRAP_ADMIN_NAME, BOOTSTRAP_ADMIN_EMAIL, and a valid BOOTSTRAP_ADMIN_PASSWORD_HASH before the first deployment.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($email, $name, $hash) {
            DB::table('system_settings')->where('id', 1)->lockForUpdate()->first();
            if (User::where('is_admin', true)->exists()) {
                return;
            }
            if (User::where('email', $email)->exists()) {
                throw new \RuntimeException('Bootstrap email is already assigned. No existing account was changed.');
            }
            $user = new User(['name' => $name, 'email' => $email]);
            $user->forceFill(['password' => $hash, 'is_admin' => true])->save();
            DB::table('system_settings')->where('id', 1)->update(['setup_complete' => true]);
        });
        $this->info('Deployment administrator initialized.');

        return self::SUCCESS;
    }
}
