<?php return array(
    'root' => array(
        'name' => 'hsm/wplite-plugin',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => 'ec7d166fe218329d57ae7485be63cedbce111086',
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'hsm/wplite' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => '5369e0a2119470bf6db0fe2a5ae14a7efde88dcd',
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
            'reference' => 'ec7d166fe218329d57ae7485be63cedbce111086',
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
