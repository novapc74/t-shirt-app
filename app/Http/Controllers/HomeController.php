<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function index(): Response
    {
        $categoriesTree = Cache::remember('home_categories_tree', 3600, function () {
            return Category::whereNull('parent_id')
            ->with(['childrenRecursive' => function($query) {
                $query->withCount(['variants as variants_count']);
            }])
                ->withCount(['variants as variants_count'])
                ->orderBy('priority')
                ->get();
        });

        return Inertia::render('Home', [
            'categories' => $categoriesTree
        ]);
    }
}
