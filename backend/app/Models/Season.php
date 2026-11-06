<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Season extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'season_picks_lock_at' => 'datetime', 'playoffs_start_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function rounds(): HasMany { return $this->hasMany(Round::class); }
    public function games(): HasMany { return $this->hasMany(Game::class); }
}
