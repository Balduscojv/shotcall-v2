<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cosmetic extends Model
{
    protected $guarded = [];

    protected function casts(): array { return ['anchors' => 'array']; }
}
