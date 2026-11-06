<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameShotCall extends Model
{
    protected $guarded = [];

    protected function casts(): array { return ['settled_at' => 'datetime']; }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function game(): BelongsTo { return $this->belongsTo(Game::class); }
    public function winner(): BelongsTo { return $this->belongsTo(Team::class, 'winner_team_id'); }
    public function mvp(): BelongsTo { return $this->belongsTo(Player::class, 'mvp_player_id'); }
}
