<?php

namespace App\Http\Controllers;

use Inertia\{Inertia, Response};
use App\Services\Catalog\CatalogService;
use App\Http\Requests\CatalogFilterRequest;

class ProductController extends Controller
{
    public function __construct(protected CatalogService $catalogService)
    {
    }

    public function index(CatalogFilterRequest $request, int $categoryId): Response
    {
        $data = $this->catalogService->getCategoryCatalog($categoryId, $request);

        return Inertia::render('Catalog/CategoryPage', $data);
    }
}
