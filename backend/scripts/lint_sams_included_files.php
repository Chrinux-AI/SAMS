<?php
$root = realpath(__DIR__ . '/../../');
$frontend = $root . DIRECTORY_SEPARATOR . 'frontend';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($frontend));
$errors = [];
foreach ($it as $f) {
  if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
    $path = $f->getPathname();
    $contents = file_get_contents($path);
    if (strpos($contents, 'sams-head-bootstrap.php') !== false) {
      // run php -l
      $cmd = 'php -l ' . escapeshellarg($path);
      $out = null;
      $rc = null;
      exec($cmd, $out, $rc);
      $output = implode("\n", $out);
      if ($rc !== 0) {
        $errors[$path] = $output;
      }
    }
  }
}
if (empty($errors)) {
  echo "All sams-included files passed php -l.\n";
} else {
  echo "Found syntax errors in the following files:\n";
  foreach ($errors as $p => $o) {
    echo "--- $p ---\n";
    echo $o . "\n";
  }
}
