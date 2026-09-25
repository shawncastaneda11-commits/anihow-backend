<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\CropCareArticle;
use App\Models\FaqEntry;
use App\Models\FarmAnnouncement;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Services\AnalyticsService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_is_idempotent_and_keeps_farm_isolation(): void
    {
        $this->seed(DemoSeeder::class);
        $first = $this->snapshot();

        $this->seed(DemoSeeder::class);
        $this->assertSame($first, $this->snapshot());

        $admin = User::factory()->create();
        $admin->syncRoles(Role::SuperAdmin);

        $summary = app(AnalyticsService::class)->completedSummary($admin);

        $this->assertGreaterThan(0, $summary['completed_orders']);
        $this->assertGreaterThan(0, $summary['units_sold']);
        $this->assertGreaterThan(0, $summary['gross_sales']);

        $sellers = User::query()->role(Role::FarmerSeller->value)->get();

        $this->assertNotEmpty($sellers);

        foreach ($sellers as $seller) {
            $this->assertTrue(
                Order::query()
                    ->where('farmer_seller_id', $seller->id)
                    ->where('status', OrderStatus::Completed)
                    ->exists(),
                "Farmer-seller {$seller->email} has no completed order.",
            );
        }

        $mismatched = Order::query()
            ->join('users as sellers', 'sellers.id', '=', 'orders.farmer_seller_id')
            ->whereColumn('orders.farm_id', '!=', 'sellers.farm_id')
            ->count();

        $this->assertSame(0, $mismatched);
    }

    public function test_demo_seeder_sends_and_queues_no_mail_to_demo_addresses(): void
    {
        Mail::fake();

        $this->seed(DemoSeeder::class);

        $demoEmails = User::query()
            ->where('email', 'like', '%'.DemoSeeder::EMAIL_DOMAIN)
            ->pluck('email');

        $this->assertNotEmpty($demoEmails);

        foreach ($demoEmails as $email) {
            Mail::assertNotOutgoing(Mailable::class, $email);
        }

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    /**
     * @return array{orders: int, completed: int, listings: int, reviews: int, announcements: int, articles: int, faq: int}
     */
    private function snapshot(): array
    {
        return [
            'orders' => Order::query()->count(),
            'completed' => Order::query()->where('status', OrderStatus::Completed)->count(),
            'listings' => Listing::query()->count(),
            'reviews' => Review::query()->count(),
            'announcements' => FarmAnnouncement::query()->count(),
            'articles' => CropCareArticle::query()->count(),
            'faq' => FaqEntry::query()->whereNotNull('farm_id')->count(),
        ];
    }
}
