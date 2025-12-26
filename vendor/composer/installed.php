<?php return array(
    'root' => array(
        'name' => 'hsm/wplite-plugin',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => 'a2009a4e282067f04ad98cbfd1c170f93f785d52',
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'hsm/wplite' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => '5d239cfbea46a9362ba25c6883b2831a14b8dae1',
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
            'reference' => 'a2009a4e282067f04ad98cbfd1c170f93f785d52',
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
