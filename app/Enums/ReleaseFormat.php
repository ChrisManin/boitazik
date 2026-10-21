<?php

namespace App\Enums;

enum ReleaseFormat: string
{
    case Cd = 'cd';
    case Vinyl = 'vinyl';
    case Cassette = 'cassette';
    case Digital = 'digital';
    case Other = 'other';
}
