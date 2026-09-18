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
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->isFarmerSeller()) {
            return false;
        }

        return $article->is_active || $article->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->isFarmerSeller();
    }

    public function update(User $user, CropCareArticle $article): bool
    {
        return $user->isFarmerSeller() && $article->isOwnedBy($user);
    }

    public function delete(User $user, CropCareArticle $article): bool
    {
        return $this->update($user, $article);
    }
}
