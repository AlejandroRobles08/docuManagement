<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea (o actualiza la contraseña de) el usuario administrador inicial,
     * a partir de las variables ADMIN_SEED_* del .env. Es el único usuario
     * que existe hasta que alguien inicia sesión y da de alta a más
     * compañeros desde la pantalla "Agregar usuario".
     */
    public function run(): void
    {
        $email = config('documanagement.admin_seed.email');
        $password = config('documanagement.admin_seed.password');
        $name = config('documanagement.admin_seed.name');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        $this->command->info("Usuario administrador listo -> {$email} / {$password}");
    }
}
