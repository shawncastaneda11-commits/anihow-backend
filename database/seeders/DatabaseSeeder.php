<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SuperAdminSeeder::class,
            CategorySeeder::class,
            FarmerSellerSeeder::class,
            ListingSeeder::class,
            CropCareArticleSeeder::class,
            BuyerSeeder::class,
            ReservationAndSaleSeeder::class,
            TrustAndUsabilitySeeder::class,
        ]);
    }
}
