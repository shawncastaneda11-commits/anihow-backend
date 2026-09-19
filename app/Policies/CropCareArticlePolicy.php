<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CropCareArticle;
use App\Models\User;

/**
 * Authorship belongs to the Content Editor, scoped to their own farm.
 * The Super Admin moderates: they see every farm's articles and can unpublish
 * one, but they do not write or rewrite farm content.
 *
 * This mirrors listings exactly. A Super Admin takes a listing down; they do
 * not edit a farmer's price. The same line applies here.
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
        if ($user->can(Permission::ModerateArticles->value)) {
            return true;
        }

        if ($this->ownsFarmOf($user, $article)) {
            return true;
        }

        return $article->isPublished();
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageOwnFarmArticles->value)
            && $user->farm_id !== null;
    }

    public function update(User $user, CropCareArticle $article): bool
    {
        return $this->ownsFarmOf($user, $article);
    }

    public function delete(User $user, CropCareArticle $article): bool
    {
        return $this->ownsFarmOf($user, $article);
    }

    /**
     * Moderation: pull a published article out of the app without editing it.
     */
    public function moderate(User $user, CropCareArticle $article): bool
    {
        return $user->can(Permission::ModerateArticles->value);
    }

    /**
     * Farm scope, not author scope. A farm has one Content Editor, but if that
     * editor is replaced the successor still owns the farm's back catalogue.
     */
    private function ownsFarmOf(User $user, CropCareArticle $article): bool
    {
        return $user->can(Permission::ManageOwnFarmArticles->value)
            && $user->farm_id !== null
            && $article->belongsToFarm($user->farm_id);
    }
}
