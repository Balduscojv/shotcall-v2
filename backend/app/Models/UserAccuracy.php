<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAccuracy extends Model
{
    public $incrementing = false;
    protected $table = 'user_accuracy';
    protected $primaryKey = null;
    protected $guarded = [];
}
