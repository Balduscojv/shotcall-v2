<?php

use Illuminate\Support\Facades\Storage;

it('redirects public media URLs to the configured object store', function () {
    config(['filesystems.disks.public.driver' => 's3']);

    $disk = Mockery::mock();
    $disk->shouldReceive('url')
        ->once()
        ->with('avatars/user.webp')
        ->andReturn('https://media.example.com/avatars/user.webp');

    Storage::shouldReceive('disk')
        ->once()
        ->with('public')
        ->andReturn($disk);

    $this->get('/storage/avatars/user.webp')
        ->assertRedirect('https://media.example.com/avatars/user.webp');
});

it('does not replace local public storage behavior', function () {
    config(['filesystems.disks.public.driver' => 'local']);

    $this->get('/storage/avatars/missing.webp')->assertNotFound();
});
