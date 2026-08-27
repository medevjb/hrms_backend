<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\PermissionServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    PermissionServiceProvider::class,
];
