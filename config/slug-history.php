<?php

declare(strict_types=1);

return [
    // The table that keeps the old addresses.
    'table' => 'past_slugs',

    // The redirect status code. 301: permanent, this is what search engines
    // want. 308 if you want to keep the HTTP method.
    'status' => 301,

    // Adds the middleware by itself. Set false to add it by hand.
    'auto_redirect' => true,

    // How long to keep the old addresses, in days. null = forever.
    'keep_for_days' => null,

    // The default scope: what makes an address unique (a language, a
    // section). Empty string when your site does not need one. You can
    // also give a function that receives the request and returns the scope.
    'scope' => '',
];
