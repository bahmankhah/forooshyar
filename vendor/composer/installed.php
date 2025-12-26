<?php return array(
    'root' => array(
        'name' => 'hsm/wplite-plugin',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => '487343a6ebf7e6316c41b3968a012694b8bda193',
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'hsm/wplite' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => 'e5cb0312ec1b42eaab411a97576d70f615136c46',
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
            'reference' => '487343a6ebf7e6316c41b3968a012694b8bda193',
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
