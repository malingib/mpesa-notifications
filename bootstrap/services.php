<?php

use Illuminate\Container\Container;

/**
 * Service Container Bootstrap
 * 
 * Registers service providers and binds services.
 */
return function (Container $app) {
    // Register service providers
    $providers = [
        \App\Providers\AppServiceProvider::class,
    ];

    foreach ($providers as $provider) {
        (new $provider($app))->register();
    }

    foreach ($providers as $provider) {
        (new $provider($app))->boot();
    }

    return $app;
};
