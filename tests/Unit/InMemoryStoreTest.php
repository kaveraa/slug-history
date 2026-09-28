<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Unit;

use Kaveraa\SlugHistory\InMemoryStore;
use Kaveraa\SlugHistory\Store;
use Kaveraa\SlugHistory\Tests\Support\StoreContract;

final class InMemoryStoreTest extends StoreContract
{
    protected function store(): Store
    {
        return new InMemoryStore();
    }
}
