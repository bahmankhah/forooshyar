<?php return array(
    'root' => array(
        'name' => 'hsm/wplite-plugin',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => '19af8db5ce2e372c54f959a8b3501a91e37f2433',
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'hsm/wplite' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => '929a6ca6185aece786b20c030722288ac3c01966',
            'type' => 'library',
            'install_path' => __DIR__ . '/../hsm/wplite',
            'aliases' => array(
                0 => '9999999-dev',
            ),
            'dev_requirement' => false,
        ),
        'hsm/wplite-plugin' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => '19af8db5ce2e372c54f959a8b3501a91e37f2433',
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
