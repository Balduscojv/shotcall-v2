<?php

namespace App\Services\Fantasy;

/** Estatísticas de um jogador em um jogo, já no formato do box score. */
final readonly class StatLine
{
    public function __construct(
        public int $pts = 0,
        public int $reb = 0,
        public int $ast = 0,
        public int $stl = 0,
        public int $blk = 0,
        public int $tov = 0,
        public int $pf = 0,
        public int $tech = 0,
        public int $flagrant = 0,
        public int $fgm = 0,
        public int $fga = 0,
        public int $fg3m = 0,
        public int $ftm = 0,
        public int $fta = 0,
        public int $plusMinus = 0,
    ) {}

    public static function fromArray(array $s): self
    {
        return new self(
            pts: (int) ($s['pts'] ?? 0),
            reb: (int) ($s['reb'] ?? 0),
            ast: (int) ($s['ast'] ?? 0),
            stl: (int) ($s['stl'] ?? 0),
            blk: (int) ($s['blk'] ?? 0),
            tov: (int) ($s['tov'] ?? 0),
            pf: (int) ($s['pf'] ?? 0),
            tech: (int) ($s['tech'] ?? 0),
            flagrant: (int) ($s['flagrant'] ?? 0),
            fgm: (int) ($s['fgm'] ?? 0),
            fga: (int) ($s['fga'] ?? 0),
            fg3m: (int) ($s['fg3m'] ?? 0),
            ftm: (int) ($s['ftm'] ?? 0),
            fta: (int) ($s['fta'] ?? 0),
            plusMinus: (int) ($s['plus_minus'] ?? 0),
        );
    }
}
