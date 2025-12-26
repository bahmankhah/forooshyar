<?php return array(
    'root' => array(
        'name' => 'hsm/wplite-plugin',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => 'df7610478ae65e6ef2051ee74b145ddb957f1992',
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'hsm/wplite' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => '89610c5304edd39667b67143cab88621abd20859',
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
            'reference' => 'df7610478ae65e6ef2051ee74b145ddb957f1992',
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
