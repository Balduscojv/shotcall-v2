<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    public $incrementing = false;
    protected $guarded = [];

    public function players(): HasMany { return $this->hasMany(Player::class); }
}
