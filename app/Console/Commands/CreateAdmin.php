<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'monstopia:create-admin {email? : Administrator email address}';

    protected $description = 'Create the first MONSTOPIA administrator without a default password';

    public function handle(): int
    {
        $email = (string) ($this->argument('email') ?: $this->ask('Administrator email'));
        $name = (string) $this->ask('Full name');
        $password = (string) $this->secret('Password (minimum 12 characters)');

        $validator = Validator::make(compact('email', 'name', 'password'), [
            'email' => ['required', 'email:rfc', 'max:150', 'unique:users,email'],
            'name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:12', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => 'admin',
        ]);

        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}
