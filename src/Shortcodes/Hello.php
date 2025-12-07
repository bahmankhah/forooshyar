<?php

namespace Forooshyar\Shortcodes;

use WPLite\Shortcode;

class Hello extends Shortcode
{
    public function defaults(): array{
        return [
            'name' => 'John'
        ];
    }
    protected string $tag = 'hello';
    public function render()
    {
        return view('shortcodes.hello', [
            'name' => $this->attributes['name'],
        ]);
    }
}