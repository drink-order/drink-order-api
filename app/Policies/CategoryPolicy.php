<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CategoryPolicy
{
    public function modify(User $user, Category $category): Response
    {
        return $user->id == $category->user_id
            ? Response::allow()
            : Response::deny('You do not own this category');
    }
}
