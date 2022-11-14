<?php

require_once __dir__.'/greybox/debug.php';
require_once __dir__.'/greybox/timing.php';

\timing\extend_header();

$first_timer = \timing\start("my_timer");
usleep(1000 * 30);
$first_timer();
\timing\start('my_other_timer', 'foo', 'This timer also has a description!');
usleep(1000 * 100);
\timing\end('my_other_timer');
ob_start();
usleep(1000 * 50);

header("Server-Timing: noise;dur=1999");
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
\timing\milestone('output_preflush', null, "This one has a description!");
usleep(1000*40);
\timing\write_header();