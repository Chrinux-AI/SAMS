<?php
// Adds include for sams-head-bootstrap.php after professional-ui.css link in frontend PHP files
$root = realpath(__DIR__ . '/../../');
$frontend = $root . DIRECTORY_SEPARATOR . 'frontend';
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($frontend));
foreach ($it as $f) {
  if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
    $files[] = $f->getPathname();
  }
}
$modified = [];
foreach ($files as $file) {
  $contents = file_get_contents($file);
  if ($contents === false) continue;
  if (strpos($contents, 'professional-ui.css') === false) continue;
  if (strpos($contents, 'sams-head-bootstrap.php') !== false) continue; // already present

  // Find the first occurrence of the link tag containing professional-ui.css
  $pattern = '/<link[^>]*professional-ui\.css[^>]*>/i';
  if (preg_match($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
    $match = $matches[0][0];
    $offset = $matches[0][1];
    // Determine directory depth after 'frontend' to compute relative include path
    $relPath = str_replace('\\', '/', substr($file, strlen($frontend) + 1)); // path after frontend/
    $dir = dirname($relPath);
    $depth = 0;
    if ($dir !== '.') {
      $depth = substr_count($dir, '/') + 1; // segments count
    }
    // If file directly under frontend (no extra segment), depth = 0 -> prefix '../'
    // We want include path relative to file: number of ../ = depth (if depth==0 -> '../')
    $up = '';
    if ($depth <= 0) {
      $up = '../';
    } else {
      $up = str_repeat('../', $depth + 0); // +0 to be explicit
    }
    $includePath = $up . 'includes/sams-head-bootstrap.php';

    // Insert include after the matched link tag, keeping newline formatting
    // Find end of line of the matched tag
    $afterPos = $offset + strlen($match);
    // If next character is not newline, insert a newline
    $insert = "\n    <?php include '" . $includePath . "'; ?>\n";
    $newContents = substr($contents, 0, $afterPos) . $insert . substr($contents, $afterPos);

    // Write back
    file_put_contents($file, $newContents);
    $modified[] = $file;
  }
}

echo "Modified " . count($modified) . " files.\n";
foreach ($modified as $m) echo $m . "\n";
