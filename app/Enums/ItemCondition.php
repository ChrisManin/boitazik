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
}
