<?php

use function timing\write_header;

require_once __dir__.'/debug.php';
require_once __dir__.'/timing.php';
$first_timer = \timing\start("my_timer");
usleep(1000 * 30);
$first_timer();
\timing\start('my_other_timer', 'foo', 'This timer also has a description!');
usleep(1000 * 100);
\timing\end('my_other_timer');
ob_start();
usleep(1000 * 50);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <h1>Helloworld</h1>
</body>
</html>
<?php
\timing\moment('output_preflush');
usleep(1000*40);
\timing\write_header();