<?php

namespace App\Services\Bracket;

use App\Models\Team;

class TeamColors
{
    /**
     * Each team's iconic flag/kit color, used to highlight their line as
     * they advance through the bracket. Keyed by the exact team name as
     * imported from football-data.org. Falls back to the bracket's base
     * gold for any team not listed here.
     *
     * @var array<string, string>
     */
    protected const COLORS = [
        'Algeria' => '#006233',
        'Argentina' => '#75AADB',
        'Australia' => '#00843D',
        'Austria' => '#ED2939',
        'Belgium' => '#ED2939',
        'Bosnia-Herzegovina' => '#002395',
        'Brazil' => '#FFDF00',
        'Canada' => '#FF0000',
        'Cape Verde Islands' => '#003893',
        'Colombia' => '#FCD116',
        'Congo DR' => '#007FFF',
        'Croatia' => '#FF0000',
        'Curaçao' => '#002B7F',
        'Czechia' => '#D7141A',
        'Ecuador' => '#FFDD00',
        'Egypt' => '#CE1126',
        'England' => '#CE1124',
        'France' => '#0055A4',
        'Germany' => '#333333',
        'Ghana' => '#CE1126',
        'Haiti' => '#00209F',
        'Iran' => '#239F40',
        'Iraq' => '#CE1126',
        'Ivory Coast' => '#FF8200',
        'Japan' => '#BC002D',
        'Jordan' => '#CE1126',
        'Mexico' => '#006847',
        'Morocco' => '#C1272D',
        'Netherlands' => '#FF6C00',
        'New Zealand' => '#000000',
        'Norway' => '#EF2B2D',
        'Panama' => '#D21034',
        'Paraguay' => '#D52B1E',
        'Portugal' => '#FF0000',
        'Qatar' => '#8D1B3D',
        'Saudi Arabia' => '#006C35',
        'Scotland' => '#0065BF',
        'Senegal' => '#00853F',
        'South Africa' => '#007A4D',
        'South Korea' => '#CD2E3A',
        'Spain' => '#C60B1E',
        'Sweden' => '#FECC02',
        'Switzerland' => '#FF0000',
        'Tunisia' => '#E70013',
        'Turkey' => '#E30A17',
        'United States' => '#3C3B6E',
        'Uruguay' => '#5CB8E4',
        'Uzbekistan' => '#0099B5',
    ];

    protected const FALLBACK = '#fbbf24';

    public static function for(?Team $team): string
    {
        if (! $team) {
            return self::FALLBACK;
        }

        return self::COLORS[$team->name] ?? self::FALLBACK;
    }
}
