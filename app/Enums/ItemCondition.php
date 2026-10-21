<?php

namespace App\Enums;

// Goldmine grading scale used by record collectors
enum ItemCondition: string
{
    case Mint = 'mint';
    case NearMint = 'near_mint';
    case VeryGoodPlus = 'very_good_plus';
    case VeryGood = 'very_good';
    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';

    /**
     * Label shown in the interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Mint => 'Neuf (Mint)',
            self::NearMint => 'Quasi neuf (Near Mint)',
            self::VeryGoodPlus => 'Très bon état + (VG+)',
            self::VeryGood => 'Très bon état (VG)',
            self::Good => 'Bon état (G)',
            self::Fair => 'État moyen (Fair)',
            self::Poor => 'Mauvais état (Poor)',
        };
    }
}
