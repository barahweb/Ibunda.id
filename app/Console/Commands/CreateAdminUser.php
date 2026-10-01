<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-admin
        {--name= : Nama admin}
        {--email= : Email admin}
        {--password= : Password admin (kalau kosong akan ditanya, lebih aman karena gak masuk riwayat shell)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Buat akun admin baru, atau jadikan user yang sudah ada sebagai admin';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->option('email') ?: text('Email admin', required: true);
        $existing = User::where('email', $email)->first();

        if ($existing) {
            $existing->forceFill(['role' => User::ROLE_ADMIN, 'email_verified_at' => $existing->email_verified_at ?? now()])->save();
            $this->components->info("User {$email} sudah ada, sekarang jadi admin. Password-nya tidak diubah.");

            return self::SUCCESS;
        }

        $data = [
            'name' => $this->option('name') ?: text('Nama admin', required: true),
            'email' => $email,
            'password' => $this->option('password') ?: password('Password admin', required: true),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $admin = new User($data);
        $admin->forceFill(['role' => User::ROLE_ADMIN, 'email_verified_at' => now()])->save();

        $this->components->info("Admin {$email} berhasil dibuat.");

        return self::SUCCESS;
    }
}
