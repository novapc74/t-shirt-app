<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\{Inertia, Response};
use App\Services\Catalog\CatalogService;
use App\Services\Catalog\DTO\ProductFilterParams;

class ProductController extends Controller
{
    public function __construct(protected CatalogService $catalogService)
    {
    }

    public function index(int $categoryId, Request $request): Response
    {
        $params = ProductFilterParams::fromRequest($request);

        if (!$data = $this->catalogService->getCategoryCatalog($categoryId, $params)) {
            return $this->emptyResponse();
        }

//        dd(json_encode([
//            'category' => $data['category'],
//            'products' => $data['products'],
//            'filters' => $data['filters'],
//        ]));

        return Inertia::render('Catalog/CategoryPage', [
            'category' => $data['category'],
            'products' => $data['products'],
            'filters' => $data['filters'],
        ]);
    }

    private function emptyResponse(): Response
    {
        return Inertia::render('Catalog/CategoryPage', [
            'category' => '... breadcrumbs ...',
            'products' => [],
            'filters' => [],
        ]);
    }
}
