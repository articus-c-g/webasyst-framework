<?php

return array(
    'name'         => 'Webasyst',
    'prefix'       => 'webasyst',
    'version'      => '2.4.2',
    'critical'     => '2.4.2',
    'vendor'       => 'webasyst',
    'csrf'         => true,
    'header_items' => array(
        'settings' => array(
            'icon'   => 'img/wa-settings/settings.svg',
            'name'   => 'Settings',  // _w('Settings')
            'link'   => 'settings',
            'rights' => 'backend'
        ),
    ),
    'ui'           => '1.3,2.0'
);
