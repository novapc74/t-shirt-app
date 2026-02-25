<?php

namespace App\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;

abstract class QueryFilter
{
    public function handle(Builder $query, Closure $next)
    {
        if (!$this->shouldFilter()) {
            return $next($query);
        }

        return $next($this->apply($query));
    }

    abstract protected function apply(Builder $query): Builder;

    protected function shouldFilter(): bool
    {
        return request()->has($this->filterKey());
    }

    abstract protected function filterKey(): string;
}

