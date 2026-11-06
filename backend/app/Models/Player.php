<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    public $incrementing = false;
    protected $guarded = [];

    protected function casts(): array { return ['active' => 'boolean']; }

    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function prices(): HasMany { return $this->hasMany(PlayerPrice::class); }
}
