<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/storage/{path}', function (string $path) {
    abort_unless(config('filesystems.disks.public.driver') === 's3', 404);

    return redirect()->away(Storage::disk('public')->url($path));
})->where('path', '.*');
