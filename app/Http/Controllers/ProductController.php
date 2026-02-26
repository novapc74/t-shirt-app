<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\{Inertia, Response};
use App\Http\Resources\ProductResource;
use App\Services\Catalog\CatalogService;
use App\Services\Catalog\DTO\ProductFilterParams;

class ProductController extends Controller
{
    public function __construct(
        protected CatalogService $productService
    )
    {
    }

    public function index(Category $category, Request $request): Response
    {
        $params = ProductFilterParams::fromRequest($request);

        $data = $this->productService->getCategoryCatalog($category, $params);

        if (!$data) {
            return $this->emptyResponse($category);
        }

        return Inertia::render('Catalog/CategoryPage', [
            'category' => $category->name,
            'price_range' => $data['price_range'],
            'filters' => $data['filters'],
            'brands' => $data['brands'],
            'product_types' => $data['product_types'],
            'products' => ProductResource::collection($data['products']),
            'active_filters' => (object)$params->filters,
            'active_brands' => $params->brands,
            'active_types' => $params->productTypes,
            'current_sort' => $params->sort
        ]);
    }

    /**
     * Пустой ответ, если товары не найдены
     */
    private function emptyResponse(Category $category): Response
    {
        return Inertia::render('Catalog/CategoryPage', [
            'category' => $category->name,
            'price_range' => ['min' => 0, 'max' => 0],
            'filters' => [],
            'product_types' => [],
            'products' => ['data' => []],
            'active_filters' => (object)[],
            'active_types' => [],
        ]);
    }
}
