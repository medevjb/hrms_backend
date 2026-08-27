<?php

namespace App\Console\Commands;

use App\Enums\Scope;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Bootstraps the first Admin account. Self-registration is closed
 * (docs/PRD.md §92.4), so this is how an Admin comes to exist at all
 * (§148 open decision #1). Refuses to run if any user already exists —
 * this is a one-time bootstrap, not a way to mint more admins.
 */
#[Signature('hrm:install {--name=Admin : Name for the first Admin account} {--email= : Email for the first Admin account} {--password= : Password for the first Admin account — generated and printed once if omitted}')]
#[Description('Bootstrap the first Admin account')]
class HrmInstallCommand extends Command
{
    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->components->error(
                'hrm:install refuses to run — at least one user already exists. '
                .'This command only bootstraps the very first Admin.',
            );

            return self::FAILURE;
        }

        $email = $this->option('email') ?? $this->ask('Admin email');
        $suppliedPassword = $this->option('password');
        $generatedPassword = $suppliedPassword === null ? Str::password(20) : null;
        $password = $suppliedPassword ?? $generatedPassword;

        $validator = validator(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => ['required', 'string', 'min:8']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $this->call(RolePermissionSeeder::class);

        $admin = User::query()->create([
            'name' => $this->option('name'),
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $adminRole = Role::query()->where('name', 'Admin')->firstOrFail();

        // Two grants, not one: Admin's permission set spans both
        // employee-data permissions (ALL_EMPLOYEES) and technical ones
        // like system.health.view (SYSTEM) — docs/PRD.md §10 scopes.
        $admin->roleAssignments()->create(['role_id' => $adminRole->id, 'scope' => Scope::AllEmployees]);
        $admin->roleAssignments()->create(['role_id' => $adminRole->id, 'scope' => Scope::System]);

        $this->components->info("Admin account created: {$email}");

        if ($generatedPassword !== null) {
            $this->components->warn("Generated password (shown once): {$generatedPassword}");
        }

        return self::SUCCESS;
    }
}
