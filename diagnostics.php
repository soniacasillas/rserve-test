<?php
declare(strict_types=1);

require __DIR__ . '/src/PortableR.php';

header('Content-Type: text/plain; charset=utf-8');

$runtime = __DIR__ . '/runtime';
$rBinary = $runtime . '/lib/R/bin/exec/R';
$python = $runtime . '/bin/python';

echo "Diagnòstic PHP + R portable\n";
echo "===========================\n";
echo 'PHP: ' . PHP_VERSION . "\n";
echo 'Sistema: ' . PHP_OS_FAMILY . ' / ' . php_uname('m') . "\n";
echo 'proc_open disponible: ' . (function_exists('proc_open') ? 'sí' : 'no') . "\n";
echo 'Runtime present: ' . (is_dir($runtime) ? 'sí' : 'no') . "\n";
echo 'Binari R present: ' . (is_file($rBinary) ? 'sí' : 'no') . "\n";
echo 'Binari R executable: ' . (is_executable($rBinary) ? 'sí' : 'no') . "\n";
echo 'Python de reubicació present: ' . (is_file($python) ? 'sí' : 'no') . "\n\n";

try {
    $result = PortableR::run(__DIR__ . '/r/hello.R');
    echo "RESULTAT: correcte\n\n";
    echo $result['stdout'];
    if ($result['stderr'] !== '') {
        echo "\nstderr:\n" . $result['stderr'];
    }
} catch (Throwable $error) {
    echo "RESULTAT: error\n\n";
    echo $error->getMessage() . "\n";
}
