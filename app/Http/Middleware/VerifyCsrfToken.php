<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'whatsapp/webhook*',
        'api/whatsapp/webhook*',
        'api/v1/whatsapp/webhook*',
        'services/api/whatsapp/webhook*',
        'services/whatsapp/webhook*',
        'v1/whatsapp/webhook*',
    ];
}
