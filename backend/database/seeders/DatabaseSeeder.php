<?php

namespace Database\Seeders;

use App\Models\League;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([TeamSeeder::class, SeasonSeeder::class, CosmeticSeeder::class]);

        // Conta de sistema, dona da Liga Global em que todo usuário entra.
        $system = User::firstOrCreate(['email' => 'sistema@shotcall.app'], ['name' => 'ShotCall', 'handle' => 'shotcall']);

        League::firstOrCreate(['is_global' => true], [
            'slug' => 'global', 'name' => 'Liga Global', 'color' => '#FF5A1F', 'owner_id' => $system->id,
            'privacy' => 'PUBLIC', 'invite_code' => Str::upper(Str::random(6)),
            'competitions' => ['FANTASY', 'SHOTCALL'], 'max_members' => 65000,
        ]);
    }
}
