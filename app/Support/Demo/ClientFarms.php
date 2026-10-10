<?php

namespace App\Support\Demo;

use App\Models\Farm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The three farms the defense demo keeps. Map pins are set in the CMS.
 */
final class ClientFarms
{
    public const PYAP_SLUG = 'pyap-manggahan-chapter';

    public const TRUOFA_SLUG = 'truofa';

    public const SANCTUARIO_SLUG = 'sanctuario-nature-farm';

    public const TRUOFA_ASSOCIATION = 'Tanza Rural and Urban Organic Farmers Association';

    /**
     * @return list<array{slug: string, name: string, municipality: ?string, barangay: ?string, description: ?string, also_names: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'slug' => self::PYAP_SLUG,
                'name' => 'PYAP Manggahan Chapter',
                'municipality' => 'General Trias',
                'barangay' => 'Manggahan',
                'description' => null,
                'also_names' => [],
            ],
            [
                'slug' => self::TRUOFA_SLUG,
                'name' => 'TRUOFA',
                'municipality' => 'Tanza',
                'barangay' => null,
                'description' => self::TRUOFA_ASSOCIATION.'.',
                'also_names' => [self::TRUOFA_ASSOCIATION],
            ],
            [
                'slug' => self::SANCTUARIO_SLUG,
                'name' => 'Sanctuario Nature Farm',
                'municipality' => null,
                'barangay' => null,
                'description' => null,
                'also_names' => [],
            ],
        ];
    }

    /**
     * Farms to keep: one of the three slugs, or the same name typed by hand.
     *
     * @return Builder<Farm>
     */
    public static function kept(): Builder
    {
        $slugs = array_column(self::definitions(), 'slug');

        return Farm::query()->where(function (Builder $query) use ($slugs): void {
            $query->whereIn('slug', $slugs)
                ->orWhereIn(DB::raw('LOWER(farms.name)'), self::keptNames());
        });
    }

    /**
     * @return list<string>
     */
    public static function keptNames(): array
    {
        $names = [self::TRUOFA_ASSOCIATION];

        foreach (self::definitions() as $definition) {
            $names[] = $definition['name'];
            array_push($names, ...$definition['also_names']);
        }

        return array_values(array_unique(array_map(
            fn (string $name): string => mb_strtolower($name),
            $names,
        )));
    }
}
