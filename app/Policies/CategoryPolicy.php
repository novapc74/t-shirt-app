<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CategoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Category $category): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Category $category): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Category $category): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Category $category): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Category $category): bool
    {
        return false;
    }

    /**
     * Можно ли создать ПОДКАТЕГОРИЮ внутри $parentCategory?
     */
    public function createSubcategory(User $user, Category $parentCategory): bool
    {
        // Разрешаем, только если в этой категории еще НЕТ товаров
        return $parentCategory->products()->count() === 0;
    }

    /**
     * Можно ли добавить ТОВАР в $category?
     */
    public function addProduct(User $user, Category $category): bool
    {
        // Разрешаем, только если у категории НЕТ дочерних категорий
        return $category->children()->count() === 0;
    }
}
