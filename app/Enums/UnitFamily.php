<?php

namespace App\Enums;

enum UnitFamily: string
{
    case Weight = 'weight';
    case Volume = 'volume';
    case Count = 'count';
    case Package = 'package';
}
