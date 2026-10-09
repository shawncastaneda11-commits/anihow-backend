<?php

namespace App\Enums;

enum HarvestRecordKind: string
{
    case Opening = 'opening';
    case Initial = 'initial';
    case Added = 'added';
    case Actual = 'actual';
    case Estimated = 'estimated';

    /**
     * Opening stock is a starting balance, not a harvest. Harvest charts
     * exclude this kind.
     */
    public function isHarvest(): bool
    {
        return $this !== self::Opening;
    }
}
