<?php
// Ejemplo tomado y modificado de https://www.php.net/manual/en/class.phar.php
$pharFile = 'conversor.phar';
@unlink($pharFile);

$phar = new Phar($pharFile);
$phar->startBuffering();

$it = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $file) {
    $path = $file->getPathname();
    if (preg_match('~/(vendor|\.git|\.idea|tests|logs)/~', $path)) continue;
    if (preg_match('~/(currency\.phar|build-phar\.php)$~', $path)) continue;
    $local = str_replace(__DIR__.'/', '', $path);
    $phar->addFile($path, $local);
}

$stub = <<<PHP
#!/usr/bin/env php
<?php
Phar::mapPhar('currency.phar');
require 'phar://currency.phar/conversor.php';
__HALT_COMPILER();
PHP;

$phar->setStub($stub);
$phar->stopBuffering();
chmod($pharFile, 0755);
echo "Built $pharFile\n";
