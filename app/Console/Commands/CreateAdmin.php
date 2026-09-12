<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'monstopia:create-admin
        {email? : Administrator email address}
        {--name= : Name shown in the dashboard and activity history}
        {--generate : Generate a one-time temporary password}
        {--reset-existing : Reset an existing account as an administrator}';

    protected $description = 'Create a MONSTOPIA administrator with a one-time password';

    public function handle(): int
    {
        $email = (string) ($this->argument('email') ?: $this->ask('Administrator email'));
        $name = (string) ($this->option('name') ?: $this->ask('Full name'));
        $password = $this->option('generate')
            ? $this->temporaryPassword()
            : (string) $this->secret('Temporary password (minimum 12 characters)');
        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser && ! $this->option('reset-existing')) {
            $this->error('This email already exists. Use --reset-existing only when you intend to reset it.');

            return self::FAILURE;
        }

        $validator = Validator::make(compact('email', 'name', 'password'), [
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($existingUser)],
            'name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255', Password::min(12)->mixedCase()->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $values = [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => 'admin',
            'must_change_password' => true,
        ];

        if ($existingUser) {
            $existingUser->update($values);
        } else {
            User::create($values);
        }

        $this->info($existingUser ? 'Administrator reset.' : 'Administrator created.');
        if ($this->option('generate')) {
            $this->warn("Temporary password: {$password}");
        }
        $this->line('The administrator must change this password after the first login.');

        return self::SUCCESS;
    }

    private function temporaryPassword(): string
    {
        return 'Mon-'.Str::lower(Str::random(6)).'-'.Str::upper(Str::random(6)).'-26';
    }
}
