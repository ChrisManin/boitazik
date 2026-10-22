<?php

namespace App\Policies;

use App\Models\CollectionItem;
use App\Models\User;

class CollectionItemPolicy
{
    /**
     * Any authenticated user can list their own collection.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CollectionItem $item): bool
    {
        return $item->user_id === $user->id;
    }

    /**
     * Any authenticated user can add items to their own collection.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CollectionItem $item): bool
    {
        return $this->view($user, $item);
    }

    public function delete(User $user, CollectionItem $item): bool
    {
        return $this->view($user, $item);
    }
}
