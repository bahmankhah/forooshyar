<?php

namespace Forooshyar\WPLite\Middlewares;
use Forooshyar\WPLite\Contracts\Middleware;
use Forooshyar\WPLite\Pipeline;
use Forooshyar\WPLite\Facades\App;
class AppMiddleware implements Middleware{
    public function handle($request,Pipeline $pipeline){
        App::setRequest($request);
        return $pipeline->next($request);
    }
}
