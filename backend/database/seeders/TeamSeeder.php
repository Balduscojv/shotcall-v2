<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

/** Os 30 times com ids da NBA e as cores da prancha "Paleta dos 30 times". Jazz: cores aproximadas. */
class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $teams = [
            [1610612737, 'ATL', 'Atlanta', 'Hawks', 'EAST', '#E03A3E', '#C1D32F'],
            [1610612738, 'BOS', 'Boston', 'Celtics', 'EAST', '#007A33', '#BA9653'],
            [1610612751, 'BKN', 'Brooklyn', 'Nets', 'EAST', '#000000', '#FFFFFF'],
            [1610612766, 'CHA', 'Charlotte', 'Hornets', 'EAST', '#1D1160', '#00788C'],
            [1610612741, 'CHI', 'Chicago', 'Bulls', 'EAST', '#CE1141', '#000000'],
            [1610612739, 'CLE', 'Cleveland', 'Cavaliers', 'EAST', '#860038', '#FDBB30'],
            [1610612765, 'DET', 'Detroit', 'Pistons', 'EAST', '#C8102E', '#1D42BA'],
            [1610612754, 'IND', 'Indiana', 'Pacers', 'EAST', '#002D62', '#FDBB30'],
            [1610612748, 'MIA', 'Miami', 'Heat', 'EAST', '#98002E', '#F9A01B'],
            [1610612749, 'MIL', 'Milwaukee', 'Bucks', 'EAST', '#00471B', '#EEE1C6'],
            [1610612752, 'NYK', 'New York', 'Knicks', 'EAST', '#006BB6', '#F58426'],
            [1610612753, 'ORL', 'Orlando', 'Magic', 'EAST', '#0077C0', '#C4CED4'],
            [1610612755, 'PHI', 'Philadelphia', '76ers', 'EAST', '#006BB6', '#ED174C'],
            [1610612761, 'TOR', 'Toronto', 'Raptors', 'EAST', '#CE1141', '#000000'],
            [1610612764, 'WAS', 'Washington', 'Wizards', 'EAST', '#002B5C', '#E31837'],
            [1610612742, 'DAL', 'Dallas', 'Mavericks', 'WEST', '#00538C', '#B8C4CA'],
            [1610612743, 'DEN', 'Denver', 'Nuggets', 'WEST', '#0E2240', '#FEC524'],
            [1610612744, 'GSW', 'Golden State', 'Warriors', 'WEST', '#1D428A', '#FFC72C'],
            [1610612745, 'HOU', 'Houston', 'Rockets', 'WEST', '#CE1141', '#C4CED4'],
            [1610612746, 'LAC', 'LA', 'Clippers', 'WEST', '#C8102E', '#1D428A'],
            [1610612747, 'LAL', 'Los Angeles', 'Lakers', 'WEST', '#552583', '#FDB927'],
            [1610612763, 'MEM', 'Memphis', 'Grizzlies', 'WEST', '#5D76A9', '#12173F'],
            [1610612750, 'MIN', 'Minnesota', 'Timberwolves', 'WEST', '#0C2340', '#78BE20'],
            [1610612740, 'NOP', 'New Orleans', 'Pelicans', 'WEST', '#0C2340', '#C8102E'],
            [1610612760, 'OKC', 'Oklahoma City', 'Thunder', 'WEST', '#007AC1', '#EF3B24'],
            [1610612756, 'PHX', 'Phoenix', 'Suns', 'WEST', '#1D1160', '#E56020'],
            [1610612757, 'POR', 'Portland', 'Trail Blazers', 'WEST', '#E03A3E', '#000000'],
            [1610612758, 'SAC', 'Sacramento', 'Kings', 'WEST', '#5A2D81', '#63727A'],
            [1610612759, 'SAS', 'San Antonio', 'Spurs', 'WEST', '#C4CED4', '#000000'],
            [1610612762, 'UTA', 'Utah', 'Jazz', 'WEST', '#3E2A78', '#7AB2E0'],
        ];

        foreach ($teams as [$id, $tri, $city, $name, $conf, $primary, $secondary]) {
            Team::updateOrCreate(['id' => $id], [
                'tricode' => $tri, 'city' => $city, 'name' => $name, 'conference' => $conf,
                'primary_color' => $primary, 'secondary_color' => $secondary,
            ]);
        }
    }
}
