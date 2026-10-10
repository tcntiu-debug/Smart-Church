<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Written by JavaScript (public/assets/js/theme.js) so that the layouts
        // can render the dark/light class server side without a flash of light.
        'tiu_theme',
    ];
}
