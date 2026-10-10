<?php

namespace App\Actions\Testing;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Support\Demo\ClientFarms;
use App\Support\ImageVariants;
use App\Support\ListingStorage;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes every account except Super Admins, and every farm except the
 * client farms. Query-builder deletes, one transaction, files after commit.
 */
class ResetAccounts
{
    /**
     * @return array{
     *     refused: bool,
     *     message: string,
     *     executed: bool,
     *     rows: list<array{table: string, count: int}>,
     *     super_admins: list<string>,
     *     kept_farms: list<string>,
     *     removed_farms: list<string>
     * }
     */
    public function handle(bool $execute): array
    {
        if (! $this->activeSuperAdminRemains()) {
            return $this->refused('Refusing to reset accounts: no active Super Admin would remain.');
        }

        $superAdmins = $this->superAdminEmails();
        $keptFarms = ClientFarms::kept()->orderBy('name')->pluck('name')->all();
        $removedFarms = DB::table('farms')
            ->whereIn('id', $this->removedFarmIds())
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $files = [];
        $rows = [];

        $purge = function () use ($execute, &$files, &$rows): void {
            if ($execute) {
                $files = $this->filesToDelete();
            }

            $rows = $this->deleteRows($execute);
        };

        if ($execute) {
            DB::transaction($purge);
            $this->deleteFiles($files);
        } else {
            $purge();
        }

        return [
            'refused' => false,
            'message' => '',
            'executed' => $execute,
            'rows' => $rows,
            'super_admins' => $superAdmins,
            'kept_farms' => array_values($keptFarms),
            'removed_farms' => array_values($removedFarms),
        ];
    }

    /**
     * @return array{
     *     refused: bool,
     *     message: string,
     *     executed: bool,
     *     rows: list<array{table: string, count: int}>,
     *     super_admins: list<string>,
     *     kept_farms: list<string>,
     *     removed_farms: list<string>
     * }
     */
    private function refused(string $message): array
    {
        return [
            'refused' => true,
            'message' => $message,
            'executed' => false,
            'rows' => [],
            'super_admins' => [],
            'kept_farms' => [],
            'removed_farms' => [],
        ];
    }

    /**
     * Children before parents. Restrict and no-action foreign keys cannot
     * wait for a cascade, and soft-deleted rows are included because these
     * are query-builder deletes.
     *
     * @return list<array{table: string, count: int}>
     */
    private function deleteRows(bool $execute): array
    {
        $steps = [
            'reports' => fn (Builder $query): Builder => $this->reports($query),
            'reviews' => fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                $inner->whereIn('buyer_id', $this->removedUserIds())
                    ->orWhereIn('farmer_seller_id', $this->removedUserIds())
                    ->orWhereIn('order_id', $this->removedOrderIds());
            }),
            'payment_proofs' => fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                $inner->whereIn('buyer_id', $this->removedUserIds())
                    ->orWhereIn('farmer_seller_id', $this->removedUserIds())
                    ->orWhereIn('order_id', $this->removedOrderIds())
                    ->orWhereIn('reservation_id', $this->removedReservationIds());
            }),
            'order_payment_events' => fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                $inner->whereIn('order_id', $this->removedOrderIds())
                    ->orWhereIn('reservation_id', $this->removedReservationIds());
            }),
            'order_messages' => fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                $inner->whereIn('user_id', $this->removedUserIds())
                    ->orWhereIn('order_id', $this->removedOrderIds());
            }),
            'order_status_histories' => fn (Builder $query): Builder => $query->whereIn('order_id', $this->removedOrderIds()),
            'order_items' => fn (Builder $query): Builder => $query->whereIn('order_id', $this->removedOrderIds()),
            'stall_messages' => fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                $inner->whereIn('user_id', $this->removedUserIds())
                    ->orWhereIn('stall_conversation_id', $this->removedConversationIds());
            }),
            'stall_conversations' => fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                $inner->whereIn('buyer_id', $this->removedUserIds())
                    ->orWhereIn('farmer_seller_id', $this->removedUserIds());
            }),
            'reservations' => fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                $inner->whereIn('buyer_id', $this->removedUserIds())
                    ->orWhereIn('farmer_seller_id', $this->removedUserIds());
            }),
            'orders' => fn (Builder $query): Builder => $query->whereIn('id', $this->removedOrderIds()),
            'harvest_records' => fn (Builder $query): Builder => $this->harvestOrStock($query),
            'stock_removals' => fn (Builder $query): Builder => $this->harvestOrStock($query),
            'listing_photos' => fn (Builder $query): Builder => $query->whereIn('listing_id', $this->removedListingIds()),
            'tawad_rules' => fn (Builder $query): Builder => $query->whereIn('listing_id', $this->removedListingIds()),
            'cart_items' => fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                $inner->whereIn('buyer_id', $this->removedUserIds())
                    ->orWhereIn('listing_id', $this->removedListingIds());
            }),
            'favorites' => fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                $inner->whereIn('buyer_id', $this->removedUserIds())
                    ->orWhereIn('listing_id', $this->removedListingIds());
            }),
            'listings' => fn (Builder $query): Builder => $query->whereIn('id', $this->removedListingIds()),
            'farm_favorites' => fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                $inner->whereIn('buyer_id', $this->removedUserIds())
                    ->orWhereIn('farm_id', $this->removedFarmIds());
            }),
            'shop_favorites' => fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                $inner->whereIn('buyer_id', $this->removedUserIds())
                    ->orWhereIn('farmer_seller_id', $this->removedUserIds());
            }),
            'in_app_notifications' => fn (Builder $query): Builder => $query->whereIn('user_id', $this->removedUserIds()),
            'device_tokens' => fn (Builder $query): Builder => $query->whereIn('user_id', $this->removedUserIds()),
            'account_deletion_requests' => fn (Builder $query): Builder => $query->whereIn('user_id', $this->removedUserIds()),
            'export_logs' => fn (Builder $query): Builder => $query->whereIn('user_id', $this->removedUserIds()),
            'farmer_crop_types' => fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                $inner->whereIn('user_id', $this->removedUserIds())
                    ->orWhereIn('crop_type_id', $this->removedCropTypeIds());
            }),
            'seller_payment_qrs' => fn (Builder $query): Builder => $query->whereIn('farmer_seller_id', $this->removedUserIds()),
            'farm_announcements' => fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                $inner->whereIn('author_id', $this->removedUserIds())
                    ->orWhereIn('farm_id', $this->removedFarmIds());
            }),
            'article_crop_type' => fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                $inner->whereIn('crop_care_article_id', $this->removedArticleIds())
                    ->orWhereIn('crop_type_id', $this->removedCropTypeIds());
            }),
            'crop_care_articles' => fn (Builder $query): Builder => $query->whereIn('id', $this->removedArticleIds()),
            'farm_crop_type_overrides' => fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                $inner->whereIn('farm_id', $this->removedFarmIds())
                    ->orWhereIn('crop_type_id', $this->removedCropTypeIds());
            }),
            'crop_types' => fn (Builder $query): Builder => $query->whereIn('id', $this->removedCropTypeIds()),
            'faq_entries' => fn (Builder $query): Builder => $query->whereIn('farm_id', $this->removedFarmIds()),
            'personal_access_tokens' => fn (Builder $query): Builder => $query
                ->where('tokenable_type', (new User)->getMorphClass())
                ->whereIn('tokenable_id', $this->removedUserIds()),
            'model_has_roles' => fn (Builder $query): Builder => $this->roleRows($query),
            'model_has_permissions' => fn (Builder $query): Builder => $this->roleRows($query),
            'sessions' => fn (Builder $query): Builder => $query->whereIn('user_id', $this->removedUserIds()),
            'password_reset_tokens' => fn (Builder $query): Builder => $query->whereIn('email', $this->removedUserEmails()),
            'users' => fn (Builder $query): Builder => $query->whereIn('id', $this->removedUserIds()),
            'farm_photos' => fn (Builder $query): Builder => $query->whereIn('farm_id', $this->removedFarmIds()),
            'farms' => fn (Builder $query): Builder => $query->whereIn('id', $this->removedFarmIds()),
        ];

        $rows = [];

        foreach ($steps as $table => $scope) {
            $query = $scope(DB::table($table));
            $rows[] = [
                'table' => $table,
                'count' => $execute ? $query->delete() : $query->count(),
            ];
        }

        return $rows;
    }

    private function reports(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->whereIn('reporter_id', $this->removedUserIds())
                ->orWhere(function (Builder $target): void {
                    $target->where('reportable_type', Listing::class)
                        ->whereIn('reportable_id', $this->removedListingIds());
                })
                ->orWhere(function (Builder $target): void {
                    $target->where('reportable_type', Review::class)
                        ->whereIn('reportable_id', $this->removedReviewIds());
                })
                ->orWhere(function (Builder $target): void {
                    $target->where('reportable_type', Order::class)
                        ->whereIn('reportable_id', $this->removedOrderIds());
                })
                ->orWhere(function (Builder $target): void {
                    $target->where('reportable_type', (new User)->getMorphClass())
                        ->whereIn('reportable_id', $this->removedUserIds());
                });
        });
    }

    private function harvestOrStock(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->whereIn('farmer_seller_id', $this->removedUserIds())
                ->orWhereIn('recorded_by', $this->removedUserIds())
                ->orWhereIn('listing_id', $this->removedListingIds())
                ->orWhereIn('farm_id', $this->removedFarmIds())
                ->orWhereIn('crop_type_id', $this->removedCropTypeIds());
        });
    }

    private function roleRows(Builder $query): Builder
    {
        return $query
            ->where('model_type', (new User)->getMorphClass())
            ->whereIn('model_id', $this->removedUserIds());
    }

    /**
     * @return list<array{disk: string, path: string}>
     */
    private function filesToDelete(): array
    {
        $listingDisk = ListingStorage::diskName();
        $files = [];
        $push = function (string $disk, array $paths) use (&$files): void {
            foreach ($paths as $path) {
                if (is_string($path) && $path !== '') {
                    $files[] = ['disk' => $disk, 'path' => $path];
                }
            }
        };

        $push('local', $this->paths('payment_proofs', 'screenshot_path', fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
            $inner->whereIn('buyer_id', $this->removedUserIds())
                ->orWhereIn('farmer_seller_id', $this->removedUserIds())
                ->orWhereIn('order_id', $this->removedOrderIds())
                ->orWhereIn('reservation_id', $this->removedReservationIds());
        })));
        $push('local', $this->paths('seller_payment_qrs', 'image_path', fn (Builder $query): Builder => $query->whereIn('farmer_seller_id', $this->removedUserIds())));
        $push('local', $this->paths('stall_messages', 'attachment_path', fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
            $inner->whereIn('user_id', $this->removedUserIds())
                ->orWhereIn('stall_conversation_id', $this->removedConversationIds());
        })));

        $push($listingDisk, $this->paths('listings', 'image_path', fn (Builder $query): Builder => $query->whereIn('id', $this->removedListingIds())));
        $push($listingDisk, $this->paths('listing_photos', 'path', fn (Builder $query): Builder => $query->whereIn('listing_id', $this->removedListingIds())));
        $push($listingDisk, $this->paths('users', 'avatar_path', fn (Builder $query): Builder => $query->whereIn('id', $this->removedUserIds())));
        $push($listingDisk, $this->paths('users', 'cover_photo_path', fn (Builder $query): Builder => $query->whereIn('id', $this->removedUserIds())));
        $push($listingDisk, $this->paths('crop_care_articles', 'image_path', fn (Builder $query): Builder => $query->whereIn('id', $this->removedArticleIds())));
        $push($listingDisk, $this->paths('farm_announcements', 'image_path', fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
            $inner->whereIn('author_id', $this->removedUserIds())
                ->orWhereIn('farm_id', $this->removedFarmIds());
        })));
        $push($listingDisk, $this->paths('farms', 'cover_photo_path', fn (Builder $query): Builder => $query->whereIn('id', $this->removedFarmIds())));
        $push($listingDisk, $this->paths('farms', 'logo_path', fn (Builder $query): Builder => $query->whereIn('id', $this->removedFarmIds())));
        $push($listingDisk, $this->paths('farm_photos', 'path', fn (Builder $query): Builder => $query->whereIn('farm_id', $this->removedFarmIds())));

        return $files;
    }

    /**
     * @param  list<array{disk: string, path: string}>  $files
     */
    private function deleteFiles(array $files): void
    {
        $kept = [];

        foreach ($this->pathColumns() as $disk => $columns) {
            $kept[$disk] = [];

            foreach ($columns as [$table, $column]) {
                foreach (DB::table($table)->whereNotNull($column)->pluck($column) as $path) {
                    if (is_string($path) && $path !== '') {
                        $kept[$disk][$path] = true;
                    }
                }
            }
        }

        $images = app(ImageVariants::class);

        foreach ($files as $file) {
            if (isset($kept[$file['disk']][$file['path']])) {
                continue;
            }

            $images->delete($file['path'], Storage::disk($file['disk']));
        }
    }

    /**
     * @return array<string, list<array{0: string, 1: string}>>
     */
    private function pathColumns(): array
    {
        $local = [
            ['payment_proofs', 'screenshot_path'],
            ['seller_payment_qrs', 'image_path'],
            ['stall_messages', 'attachment_path'],
        ];
        $listing = [
            ['listings', 'image_path'],
            ['listing_photos', 'path'],
            ['users', 'avatar_path'],
            ['users', 'cover_photo_path'],
            ['crop_care_articles', 'image_path'],
            ['farm_announcements', 'image_path'],
            ['farms', 'cover_photo_path'],
            ['farms', 'logo_path'],
            ['farm_photos', 'path'],
        ];
        $listingDisk = ListingStorage::diskName();

        if ($listingDisk === 'local') {
            return ['local' => array_merge($local, $listing)];
        }

        return [
            'local' => $local,
            $listingDisk => $listing,
        ];
    }

    /**
     * @param  Closure(Builder): Builder  $scope
     * @return list<string>
     */
    private function paths(string $table, string $column, Closure $scope): array
    {
        return $scope(DB::table($table))
            ->whereNotNull($column)
            ->pluck($column)
            ->all();
    }

    private function removedUserIds(): Builder
    {
        return DB::query()->fromSub(
            DB::table('users')
                ->select('users.id')
                ->whereNotExists(function (Builder $query): void {
                    $this->superAdminRoleMatch($query, 'users.id');
                }),
            'removed_user_ids',
        )->select('removed_user_ids.id');
    }

    private function removedUserEmails(): Builder
    {
        return DB::query()->fromSub(
            DB::table('users')
                ->select('users.email')
                ->whereIn('users.id', $this->removedUserIds()),
            'removed_user_emails',
        )->select('removed_user_emails.email');
    }

    private function removedFarmIds(): Builder
    {
        return DB::query()->fromSub(
            DB::table('farms')
                ->select('farms.id')
                ->whereNotIn('farms.id', ClientFarms::kept()->select('farms.id')),
            'removed_farm_ids',
        )->select('removed_farm_ids.id');
    }

    private function removedOrderIds(): Builder
    {
        return DB::query()->fromSub(
            DB::table('orders')->select('orders.id')->where(function (Builder $query): void {
                $query->whereIn('orders.buyer_id', $this->removedUserIds())
                    ->orWhereIn('orders.farmer_seller_id', $this->removedUserIds())
                    ->orWhereIn('orders.farm_id', $this->removedFarmIds());
            }),
            'removed_order_ids',
        )->select('removed_order_ids.id');
    }

    private function removedReservationIds(): Builder
    {
        return DB::query()->fromSub(
            DB::table('reservations')->select('reservations.id')->where(function (Builder $query): void {
                $query->whereIn('reservations.buyer_id', $this->removedUserIds())
                    ->orWhereIn('reservations.farmer_seller_id', $this->removedUserIds());
            }),
            'removed_reservation_ids',
        )->select('removed_reservation_ids.id');
    }

    private function removedListingIds(): Builder
    {
        return DB::query()->fromSub(
            DB::table('listings')->select('listings.id')->where(function (Builder $query): void {
                $query->whereIn('listings.farmer_seller_id', $this->removedUserIds())
                    ->orWhereIn('listings.farm_id', $this->removedFarmIds())
                    ->orWhereIn('listings.crop_type_id', $this->removedCropTypeIds());
            }),
            'removed_listing_ids',
        )->select('removed_listing_ids.id');
    }

    private function removedReviewIds(): Builder
    {
        return DB::query()->fromSub(
            DB::table('reviews')->select('reviews.id')->where(function (Builder $query): void {
                $query->whereIn('reviews.buyer_id', $this->removedUserIds())
                    ->orWhereIn('reviews.farmer_seller_id', $this->removedUserIds())
                    ->orWhereIn('reviews.order_id', $this->removedOrderIds());
            }),
            'removed_review_ids',
        )->select('removed_review_ids.id');
    }

    private function removedConversationIds(): Builder
    {
        return DB::query()->fromSub(
            DB::table('stall_conversations')->select('stall_conversations.id')->where(function (Builder $query): void {
                $query->whereIn('stall_conversations.buyer_id', $this->removedUserIds())
                    ->orWhereIn('stall_conversations.farmer_seller_id', $this->removedUserIds());
            }),
            'removed_conversation_ids',
        )->select('removed_conversation_ids.id');
    }

    private function removedCropTypeIds(): Builder
    {
        return DB::query()->fromSub(
            DB::table('crop_types')->select('crop_types.id')->whereIn('crop_types.farm_id', $this->removedFarmIds()),
            'removed_crop_type_ids',
        )->select('removed_crop_type_ids.id');
    }

    private function removedArticleIds(): Builder
    {
        return DB::query()->fromSub(
            DB::table('crop_care_articles')->select('crop_care_articles.id')->where(function (Builder $query): void {
                $query->whereIn('crop_care_articles.created_by', $this->removedUserIds())
                    ->orWhereIn('crop_care_articles.farm_id', $this->removedFarmIds());
            }),
            'removed_article_ids',
        )->select('removed_article_ids.id');
    }

    private function activeSuperAdminRemains(): bool
    {
        return DB::table('users')
            ->where('status', UserStatus::Active->value)
            ->whereNull('deleted_at')
            ->whereExists(function (Builder $query): void {
                $this->superAdminRoleMatch($query, 'users.id');
            })
            ->exists();
    }

    /**
     * @return list<string>
     */
    private function superAdminEmails(): array
    {
        return DB::table('users')
            ->whereExists(function (Builder $query): void {
                $this->superAdminRoleMatch($query, 'users.id');
            })
            ->orderBy('email')
            ->pluck('email')
            ->all();
    }

    private function superAdminRoleMatch(Builder $query, string $userColumn): void
    {
        $query->selectRaw('1')
            ->from('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereColumn('model_has_roles.model_id', $userColumn)
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->where('roles.name', Role::SuperAdmin->value);
    }
}
