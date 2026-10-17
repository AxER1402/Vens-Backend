<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Administrador Vens',
                'email' => 'admin@vens.com',
                'password' => Hash::make('Password123!'),
                'rol' => 'administrador',
                'activo' => true,
                'telefono' => '50370000001',
            ],
            [
                'name' => 'Dr. Carlos Mendoza (Flebólogo)',
                'email' => 'medico@vens.com',
                'password' => Hash::make('Password123!'),
                'rol' => 'medico',
                'activo' => true,
                'telefono' => '50370000002',
            ],
            [
                'name' => 'Licda. Sofia Rivas (Recepcionista)',
                'email' => 'recepcion@vens.com',
                'password' => Hash::make('Password123!'),
                'rol' => 'recepcionista',
                'activo' => true,
                'telefono' => '50370000003',
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
