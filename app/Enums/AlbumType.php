<?php

namespace App\Enums;

enum AlbumType: string
{
    case Album = 'album';
    case Ep = 'ep';
    case Single = 'single';
    case Live = 'live';
    case Compilation = 'compilation';
    case Soundtrack = 'soundtrack';
    case Other = 'other';
}
