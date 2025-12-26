<?php

namespace Forooshyar\WPLite\Contracts;

use Forooshyar\WPLite\Pipeline;

interface Middleware {
    public function handle($request, Pipeline $pipeline);
}