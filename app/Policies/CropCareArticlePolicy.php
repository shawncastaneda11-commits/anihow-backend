<?php

namespace App\Policies;

use App\Models\CropCareArticle;
use App\Models\User;

class CropCareArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isFarmerSeller() || $user->isSuperAdmin();
    }

    public function view(User $user, CropCareArticle $article): bool
    {
        if (! $user->isFarmerSeller() && ! $user->isSuperAdmin()) {
            return false;
        }

        return $article->is_active || $user->isSuperAdmin();
    }
}
