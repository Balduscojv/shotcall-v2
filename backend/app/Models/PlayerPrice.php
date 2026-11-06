<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerPrice extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = null;
    protected $guarded = [];

    public function player(): BelongsTo { return $this->belongsTo(Player::class); }
}
