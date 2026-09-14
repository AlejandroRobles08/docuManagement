<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Usuario administrador inicial
    |--------------------------------------------------------------------------
    |
    | database/seeders/AdminUserSeeder.php usa estos valores para crear (o
    | actualizar la contraseña de) el primer usuario del panel. Cámbialos en
    | tu .env antes de sembrar datos en un entorno que no sea tu máquina
    | local, y cambia la contraseña real desde la app en cuanto inicies sesión.
    |
    */

    'admin_seed' => [
        'name' => env('ADMIN_SEED_NAME', 'Administrador'),
        'email' => env('ADMIN_SEED_EMAIL', 'admin@documanagement.test'),
        'password' => env('ADMIN_SEED_PASSWORD', 'password'),
    ],

];
