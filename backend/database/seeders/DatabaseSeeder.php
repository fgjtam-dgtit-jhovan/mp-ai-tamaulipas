<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            LegalCoreLevel1Seeder::class,  // Crea los documentos base
            RoboSimplePilotSeeder::class,  // Carga el delito piloto
            OffenseElementsSeeder::class,         // Carga los delitos
        ]);
    }
}
