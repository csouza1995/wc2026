<?php

namespace App\Enums;

enum FixtureRound: string
{
    case Group = 'group';
    case RoundOf32 = 'ro32';
    case RoundOf16 = 'ro16';
    case QuarterFinal = 'qf';
    case SemiFinal = 'sf';
    case ThirdPlace = 'third_place';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Group => 'Fase de Grupos',
            self::RoundOf32 => 'Rodada de 32',
            self::RoundOf16 => 'Oitavas de Final',
            self::QuarterFinal => 'Quartas de Final',
            self::SemiFinal => 'Semifinal',
            self::ThirdPlace => 'Disputa de 3º Lugar',
            self::Final => 'Final',
        };
    }

    public function isKnockout(): bool
    {
        return $this !== self::Group;
    }
}
