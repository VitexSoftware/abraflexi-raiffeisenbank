<?php
// Debian autoloader for abraflexi-raiffeisenbank
require_once '/usr/share/php/AbraFlexi/autoload.php';
require_once '/usr/share/php/Raiffeisenbank/autoload.php';
// PSR-4 autoloader for application classes
spl_autoload_register(function (string $class): void {
    if (strncmp('AbraFlexi\\RaiffeisenBank\\', $class, 26) === 0) {
        $file = '/usr/lib/abraflexi-raiffeisenbank/AbraFlexi/RaiffeisenBank/' . str_replace('\\', '/', substr($class, 26)) . '.php';
        if (file_exists($file)) { require $file; }
    }
});
