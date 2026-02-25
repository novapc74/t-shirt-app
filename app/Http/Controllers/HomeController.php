<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
class HomeController extends Controller
{
    public function index()
    {
        // Кэшируем дерево категорий на 1 час
        $categoriesTree = Cache::remember('home_categories_tree', 3600, function () {
            return Category::whereNull('parent_id') // Берем только корневые
            ->with(['childrenRecursive' => function($query) {
                // Подсчитываем количество вариантов для каждой категории в дереве
                $query->withCount(['variants as variants_count']);
            }])
                ->withCount(['variants as variants_count']) // И для корня тоже
                ->orderBy('priority')
                ->get();
        });

        return Inertia::render('Home', [
            'categories' => $categoriesTree
        ]);
    }
}
