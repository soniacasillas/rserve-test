<?php
declare(strict_types=1);

final class PortableR
{
    /**
     * @return array{stdout: string, stderr: string}
     */
    public static function run(string $script): array
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            throw new RuntimeException('Aquest runtime només és compatible amb Linux x86_64.');
        }

        if (php_uname('m') !== 'x86_64') {
            throw new RuntimeException('Arquitectura no compatible: ' . php_uname('m') . '. Cal Linux x86_64.');
        }

        if (!function_exists('proc_open')) {
            throw new RuntimeException('DreamHost té proc_open() desactivat per a aquest domini/PHP.');
        }

        if (!is_file($script)) {
            throw new RuntimeException('No existeix el script R: ' . $script);
        }

        $root = dirname(__DIR__);
        $runtime = $root . '/runtime';
        self::relocateOnce($runtime);

        $rBinary = $runtime . '/lib/R/bin/exec/R';
        if (!is_file($rBinary)) {
            throw new RuntimeException(
                "No s'ha trobat el runtime R. Descomprimeix runtime.tar.gz de l'artefacte de GitHub al costat d'index.php."
            );
        }

        @chmod($rBinary, 0755);

        $environment = array_merge($_ENV, [
            'HOME' => sys_get_temp_dir(),
            'R_HOME' => $runtime . '/lib/R',
            'R_LIBS_SITE' => $runtime . '/lib/R/site-library',
            'R_LIBS_USER' => $runtime . '/lib/R/library',
            'LD_LIBRARY_PATH' => implode(':', [
                $runtime . '/lib',
                $runtime . '/lib/R/lib',
                getenv('LD_LIBRARY_PATH') ?: '',
            ]),
            'PATH' => $runtime . '/bin:' . (getenv('PATH') ?: '/usr/bin:/bin'),
            'TMPDIR' => sys_get_temp_dir(),
        ]);

        return self::execute([
            $rBinary,
            '--vanilla',
            '--slave',
            '-f',
            $script,
        ], $environment, 45);
    }

    private static function relocateOnce(string $runtime): void
    {
        if (!is_dir($runtime)) {
            return;
        }

        $marker = $runtime . '/.relocated';
        if (is_file($marker)) {
            return;
        }

        $lockHandle = @fopen($runtime . '/.relocate.lock', 'c');
        if ($lockHandle === false || !flock($lockHandle, LOCK_EX)) {
            throw new RuntimeException('No es pot bloquejar el runtime per fer la reubicació inicial.');
        }

        try {
            if (is_file($marker)) {
                return;
            }

            $python = $runtime . '/bin/python';
            $unpack = $runtime . '/bin/conda-unpack';

            if (!is_file($python) || !is_file($unpack)) {
                throw new RuntimeException('El paquet runtime està incomplet (falta python o conda-unpack).');
            }

            @chmod($python, 0755);
            @chmod($unpack, 0755);

            self::execute([$python, $unpack], array_merge($_ENV, [
                'PATH' => $runtime . '/bin:' . (getenv('PATH') ?: '/usr/bin:/bin'),
                'LD_LIBRARY_PATH' => $runtime . '/lib:' . (getenv('LD_LIBRARY_PATH') ?: ''),
            ]), 120);

            if (@file_put_contents($marker, "Relocated by PHP at " . gmdate(DATE_ATOM) . "\n") === false) {
                throw new RuntimeException('R s’ha reubicat, però no s’ha pogut escriure el marcador .relocated.');
            }
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /**
     * @param list<string> $command
     * @param array<string, mixed> $environment
     * @return array{stdout: string, stderr: string}
     */
    private static function execute(array $command, array $environment, int $timeoutSeconds): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes, null, $environment, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('proc_open() no ha pogut iniciar el procés.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $startedAt = microtime(true);
        $lastStatus = null;

        do {
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            $lastStatus = proc_get_status($process);

            if (!$lastStatus['running']) {
                break;
            }

            if (microtime(true) - $startedAt > $timeoutSeconds) {
                proc_terminate($process, 9);
                throw new RuntimeException("El procés ha superat el límit de {$timeoutSeconds} segons.");
            }

            usleep(10000);
        } while (true);

        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $closeCode = proc_close($process);
        $exitCode = is_array($lastStatus) && $lastStatus['exitcode'] !== -1
            ? $lastStatus['exitcode']
            : $closeCode;

        if ($exitCode !== 0) {
            throw new RuntimeException(
                "El procés ha acabat amb codi {$exitCode}.\n" .
                ($stderr !== '' ? "stderr:\n{$stderr}" : "No hi ha missatge d'error.")
            );
        }

        return ['stdout' => $stdout, 'stderr' => $stderr];
    }
}
