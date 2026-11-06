<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeasonPick extends Model
{
    protected $guarded = [];

    protected function casts(): array { return ['answers' => 'array']; }
}
