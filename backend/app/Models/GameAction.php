<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameAction extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = null;
    protected $guarded = [];

    public function scopeShots($q) { return $q->whereNotNull('shot_result'); }
}
