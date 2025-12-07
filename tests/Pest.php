<?php

use Tests\TestCase;

// Load WordPress function mocks
require_once __DIR__ . '/bootstrap.php';

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the amount of code duplication.
|
*/

function something()
{
    // ..
}

// Property testing generators
function configurationGenerator()
{
    return [
        'general' => [
            'show_variations' => fake()->boolean(),
            'title_template' => fake()->randomElement([
                '{{product_name}}',
                '{{product_name}} - {{variation_name}}',
                '{{product_name}}{{variation_suffix}}',
                '{{category}} - {{product_name}}'
            ]),
            'custom_suffix' => fake()->optional()->word(),
            'language' => fake()->randomElement(['fa_IR', 'en_US'])
        ],
        'fields' => array_fill_keys([
            'title', 'subtitle', 'parent_id', 'page_unique', 'current_price',
            'old_price', 'availability', 'category_name', 'image_links',
            'image_link', 'page_url', 'short_desc', 'spec', 'date',
            'registry', 'guarantee'
        ], fake()->boolean()),
        'images' => [
            'sizes' => fake()->randomElements(['thumbnail', 'medium', 'large', 'full'], fake()->numberBetween(1, 4)),
            'max_images' => fake()->numberBetween(1, 20),
            'quality' => fake()->numberBetween(50, 100)
        ],
        'cache' => [
            'enabled' => fake()->boolean(),
            'ttl' => fake()->numberBetween(300, 7200),
            'auto_invalidate' => fake()->boolean()
        ],
        'api' => [
            'max_per_page' => fake()->numberBetween(10, 200),
            'rate_limit' => fake()->numberBetween(100, 2000),
            'timeout' => fake()->numberBetween(10, 60)
        ]
    ];
}