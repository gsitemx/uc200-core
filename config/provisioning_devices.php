<?php

declare(strict_types=1);

return [
    'defaults' => [
        'sip_server' => env('PBX_SIP_SERVER', ''),
        'transport' => 'UDP',
        'timezone' => 'America/Mazatlan',
        'ntp_server' => 'pool.ntp.org',
        'language' => 'es',
        'provision_interval' => 1440,
        'default_codecs' => ['ulaw', 'alaw'],
        'web_rich_codecs' => ['opus', 'ulaw', 'alaw'],
    ],
    'brands' => [
        'yealink' => [
            'label' => 'Yealink',
            'instructions' => [
                'Abre la interfaz web del telefono y entra a Settings > Auto Provision.',
                'Pega la URL de provisioning generada por UC200 en el campo Server URL.',
                'Guarda cambios y ejecuta Auto Provision Now o reinicia el telefono.',
            ],
            'models' => [
                'T31P' => ['template_key' => 'yealink_t3', 'supports_blf' => true, 'supports_remote_reboot' => true, 'supports_phonebook' => true, 'supports_wallpaper' => false, 'supports_tls' => true, 'codecs' => ['ulaw', 'alaw'], 'filenames' => ['y0000000000xx.cfg', '{mac}.cfg'], 'notes' => 'Entry level SIP deskphone.'],
                'T31G' => ['template_key' => 'yealink_t3', 'supports_blf' => true, 'supports_remote_reboot' => true, 'supports_phonebook' => true, 'supports_wallpaper' => false, 'supports_tls' => true, 'codecs' => ['ulaw', 'alaw'], 'filenames' => ['y0000000000xx.cfg', '{mac}.cfg'], 'notes' => 'Gigabit variant.'],
                'T33G' => ['template_key' => 'yealink_t3', 'supports_blf' => true, 'supports_remote_reboot' => true, 'supports_phonebook' => true, 'supports_wallpaper' => false, 'supports_tls' => true, 'codecs' => ['opus', 'ulaw', 'alaw'], 'filenames' => ['y0000000000xx.cfg', '{mac}.cfg'], 'notes' => 'Color screen model.'],
                'T43U' => ['template_key' => 'yealink_t4', 'supports_blf' => true, 'supports_remote_reboot' => true, 'supports_phonebook' => true, 'supports_wallpaper' => true, 'supports_tls' => true, 'codecs' => ['opus', 'ulaw', 'alaw'], 'filenames' => ['y0000000000xx.cfg', '{mac}.cfg'], 'notes' => 'Mid range enterprise phone.'],
                'T46U' => ['template_key' => 'yealink_t4', 'supports_blf' => true, 'supports_remote_reboot' => true, 'supports_phonebook' => true, 'supports_wallpaper' => true, 'supports_tls' => true, 'codecs' => ['opus', 'ulaw', 'alaw'], 'filenames' => ['y0000000000xx.cfg', '{mac}.cfg'], 'notes' => 'Executive desk phone.'],
                'T48U' => ['template_key' => 'yealink_t4', 'supports_blf' => true, 'supports_remote_reboot' => true, 'supports_phonebook' => true, 'supports_wallpaper' => true, 'supports_tls' => true, 'codecs' => ['opus', 'ulaw', 'alaw'], 'filenames' => ['y0000000000xx.cfg', '{mac}.cfg'], 'notes' => 'Touchscreen flagship.'],
            ],
        ],
        'grandstream' => [
            'label' => 'Grandstream',
            'instructions' => [
                'Entra a la interfaz web y abre Maintenance > Upgrade and Provisioning.',
                'Coloca la URL generada por UC200 en Config Server Path.',
                'Guarda cambios y aplica un reboot manual o remoto.',
            ],
            'models' => [
                'GXP1625' => ['template_key' => 'grandstream_gxp', 'supports_blf' => true, 'supports_remote_reboot' => true, 'supports_phonebook' => true, 'supports_wallpaper' => false, 'supports_tls' => true, 'codecs' => ['ulaw', 'alaw'], 'filenames' => ['cfg{mac}.xml'], 'notes' => 'Small office phone.'],
                'GXP2130' => ['template_key' => 'grandstream_gxp', 'supports_blf' => true, 'supports_remote_reboot' => true, 'supports_phonebook' => true, 'supports_wallpaper' => false, 'supports_tls' => true, 'codecs' => ['ulaw', 'alaw'], 'filenames' => ['cfg{mac}.xml'], 'notes' => 'Professional SIP phone.'],
                'GXP2170' => ['template_key' => 'grandstream_gxp', 'supports_blf' => true, 'supports_remote_reboot' => true, 'supports_phonebook' => true, 'supports_wallpaper' => false, 'supports_tls' => true, 'codecs' => ['ulaw', 'alaw'], 'filenames' => ['cfg{mac}.xml'], 'notes' => 'High capacity line keys.'],
                'GRP2612' => ['template_key' => 'grandstream_grp', 'supports_blf' => true, 'supports_remote_reboot' => true, 'supports_phonebook' => true, 'supports_wallpaper' => false, 'supports_tls' => true, 'codecs' => ['opus', 'ulaw', 'alaw'], 'filenames' => ['cfg{mac}.xml'], 'notes' => 'Carrier-grade series.'],
                'GRP2614' => ['template_key' => 'grandstream_grp', 'supports_blf' => true, 'supports_remote_reboot' => true, 'supports_phonebook' => true, 'supports_wallpaper' => false, 'supports_tls' => true, 'codecs' => ['opus', 'ulaw', 'alaw'], 'filenames' => ['cfg{mac}.xml'], 'notes' => 'Enterprise multipurpose deskphone.'],
            ],
        ],
        'fanvil' => [
            'label' => 'Fanvil',
            'instructions' => [
                'Ingresa al panel web y abre Provisioning > Server Settings.',
                'Pega la URL de provisioning en Profile Rule o Provisioning URL.',
                'Guarda y reinicia el dispositivo para forzar descarga.',
            ],
            'models' => [
                'X3U' => ['template_key' => 'fanvil_xu', 'supports_blf' => true, 'supports_remote_reboot' => true, 'supports_phonebook' => true, 'supports_wallpaper' => false, 'supports_tls' => true, 'codecs' => ['ulaw', 'alaw'], 'filenames' => ['cfg{mac}.txt'], 'notes' => 'Compact business phone.'],
                'X4U' => ['template_key' => 'fanvil_xu', 'supports_blf' => true, 'supports_remote_reboot' => true, 'supports_phonebook' => true, 'supports_wallpaper' => true, 'supports_tls' => true, 'codecs' => ['ulaw', 'alaw'], 'filenames' => ['cfg{mac}.txt'], 'notes' => 'Color display model.'],
                'X5U' => ['template_key' => 'fanvil_xu', 'supports_blf' => true, 'supports_remote_reboot' => true, 'supports_phonebook' => true, 'supports_wallpaper' => true, 'supports_tls' => true, 'codecs' => ['ulaw', 'alaw'], 'filenames' => ['cfg{mac}.txt'], 'notes' => 'Executive class model.'],
                'X6U' => ['template_key' => 'fanvil_xu', 'supports_blf' => true, 'supports_remote_reboot' => true, 'supports_phonebook' => true, 'supports_wallpaper' => true, 'supports_tls' => true, 'codecs' => ['opus', 'ulaw', 'alaw'], 'filenames' => ['cfg{mac}.txt'], 'notes' => 'DSS enhanced model.'],
            ],
        ],
        'poly' => [
            'label' => 'Poly',
            'instructions' => [
                'Abre la interfaz del telefono y entra a Settings > Provisioning Server.',
                'Configura el tipo como HTTPS o HTTP segun tu despliegue y pega la URL UC200.',
                'Aplica y reinicia para descargar el archivo mac.cfg.',
            ],
            'models' => [
                'VVX 250' => ['template_key' => 'poly_vvx', 'supports_blf' => true, 'supports_remote_reboot' => true, 'supports_phonebook' => true, 'supports_wallpaper' => true, 'supports_tls' => true, 'codecs' => ['ulaw', 'alaw'], 'filenames' => ['{mac}.cfg'], 'notes' => 'Entry Poly VVX.'],
                'VVX 350' => ['template_key' => 'poly_vvx', 'supports_blf' => true, 'supports_remote_reboot' => true, 'supports_phonebook' => true, 'supports_wallpaper' => true, 'supports_tls' => true, 'codecs' => ['ulaw', 'alaw'], 'filenames' => ['{mac}.cfg'], 'notes' => 'Mid-size Poly VVX.'],
                'VVX 450' => ['template_key' => 'poly_vvx', 'supports_blf' => true, 'supports_remote_reboot' => true, 'supports_phonebook' => true, 'supports_wallpaper' => true, 'supports_tls' => true, 'codecs' => ['ulaw', 'alaw'], 'filenames' => ['{mac}.cfg'], 'notes' => 'High visibility line keys.'],
            ],
        ],
        'cisco' => [
            'label' => 'Cisco SPA',
            'instructions' => [
                'Usa el modo de provisioning legacy de Cisco SPA.',
                'Coloca la URL UC200 en Profile Rule o Upgrade Rule.',
                'Guarda y reinicia el telefono para descargar spa{mac}.cfg.',
            ],
            'models' => [
                'SPA 303' => ['template_key' => 'cisco_spa', 'supports_blf' => true, 'supports_remote_reboot' => false, 'supports_phonebook' => false, 'supports_wallpaper' => false, 'supports_tls' => false, 'codecs' => ['ulaw', 'alaw'], 'filenames' => ['spa{mac}.cfg'], 'notes' => 'Legacy support prepared.'],
                'SPA 504G' => ['template_key' => 'cisco_spa', 'supports_blf' => true, 'supports_remote_reboot' => false, 'supports_phonebook' => false, 'supports_wallpaper' => false, 'supports_tls' => false, 'codecs' => ['ulaw', 'alaw'], 'filenames' => ['spa{mac}.cfg'], 'notes' => 'Legacy support prepared.'],
                'SPA 508G' => ['template_key' => 'cisco_spa', 'supports_blf' => true, 'supports_remote_reboot' => false, 'supports_phonebook' => false, 'supports_wallpaper' => false, 'supports_tls' => false, 'codecs' => ['ulaw', 'alaw'], 'filenames' => ['spa{mac}.cfg'], 'notes' => 'Legacy support prepared.'],
            ],
        ],
    ],
];
