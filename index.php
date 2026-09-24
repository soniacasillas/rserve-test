<?php
declare(strict_types=1);

require __DIR__ . '/src/PortableR.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $result = PortableR::run(__DIR__ . '/r/hello.R');

    echo "PHP ha executat R correctament.\n\n";
    echo $result['stdout'];

    if ($result['stderr'] !== '') {
        echo "\nMissatges de R (stderr):\n" . $result['stderr'];
    }
} catch (Throwable $error) {
    http_response_code(500);
    echo "No s'ha pogut executar R.\n\n";
    echo $error->getMessage() . "\n";
    echo "\nConsulta README.md i obre diagnostics.php per veure els detalls del servidor.\n";
}
