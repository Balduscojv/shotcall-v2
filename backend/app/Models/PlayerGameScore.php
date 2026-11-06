<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerGameScore extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = null;
    protected $guarded = [];

    protected function casts(): array { return ['breakdown' => 'array']; }
}
