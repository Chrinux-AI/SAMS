<?php
$f = "frontend/includes/functions.php";
$c = file_get_contents($f);
if (strpos($c, "function format_currency") === false) {
  $c .= "\n\nfunction format_currency(\$amount) {\n    \$c = \$_SESSION[\"currency\"] ?? \"USD\";\n    \$sym = [\"USD\"=>\"$\", \"EUR\"=>\"€\", \"GBP\"=>\"£\", \"NGN\"=>\"?\", \"KES\"=>\"KSh\"];\n    return (\$sym[\$c] ?? \$c.\" \") . number_format((float)\$amount, 2);\n}\n";
  file_put_contents($f, $c);
  echo "Done\n";
}

