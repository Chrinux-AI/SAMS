<?php
$f = "frontend/includes/functions.php";
$c = file_get_contents($f);
if (strpos($c, "function format_currency") === false) {
  $c .= "\n\n/**\n * Format an amount to the useraaas preferred currency.\n */\nfunction format_currency(\$amount) {\n    \$curr = \$_SESSION[\x27preferred_currency\x27] ?? \x27USD\x27;\n    \$amount = (float)\$amount;\n    \$symbols = [\n        \x27USD\x27 => \x27$\x27,\n        \x27EUR\x27 => \x27€\x27,\n        \x27GBP\x27 => \x27£\x27,\n        \x27NGN\x27 => \x27?\x27,\n        \x27KES\x27 => \x27KSh\x27\n    ];\n    \$sym = \$symbols[\$curr] ?? \$curr . \x27 \x27;\n    return \$sym . number_format(\$amount, 2);\n}\n";
  file_put_contents($f, $c);
  echo "Added format_currency\n";
}

