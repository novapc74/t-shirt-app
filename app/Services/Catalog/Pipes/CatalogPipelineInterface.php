<?php

namespace App\Services\Catalog\Pipes;

use App\Services\Catalog\DTO\ProductFilterDataDto;
use Closure;

interface CatalogPipelineInterface
{
    /**
     * @param ProductFilterDataDto $data
     * @param Closure $next
     */
    public function handle(ProductFilterDataDto $data, Closure $next): ProductFilterDataDto;
}
