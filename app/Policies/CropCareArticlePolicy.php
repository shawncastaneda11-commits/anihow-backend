<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CropCareArticle;
use App\Models\User;

/**
 * Crop-care content belongs to the Content Editor, scoped to their own farm.
 * Farmer-Sellers and Buyers read published articles and nothing more.
 *
 * This is the permission that moved off the Farmer-Seller in the rebuild.
 */
class CropCareArticlePolicy
{
    public function viewAny(User $user): bool
    {
        // Reference content is readable by every authenticated actor.
        return true;
    }

    public function view(User $user, CropCareArticle $article): bool
    {
        if ($user->can(Permission::ManageAllArticles->value)) {
            return true;
        }

        if ($this->ownsFarmOf($user, $article)) {
            return true;
        }

        return $article->isPublished();
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageAllArticles->value)
            || ($user->can(Permission::ManageOwnFarmArticles->value) && $user->farm_id !== null);
    }

    public function update(User $user, CropCareArticle $article): bool
    {
        if ($user->can(Permission::ManageAllArticles->value)) {
            return true;
        }

        return $this->ownsFarmOf($user, $article);
    }

    public function delete(User $user, CropCareArticle $article): bool
    {
        return $this->update($user, $article);
    }

    public function publish(User $user, CropCareArticle $article): bool
    {
        return $this->update($user, $article);
    }

    /**
     * Farm scope, not author scope. A farm has one Content Editor, but if that
     * editor is replaced the successor must still own the farm's back catalogue.
     */
    private function ownsFarmOf(User $user, CropCareArticle $article): bool
    {
        return $user->can(Permission::ManageOwnFarmArticles->value)
            && $user->farm_id !== null
            && $article->belongsToFarm($user->farm_id);
    }
}
