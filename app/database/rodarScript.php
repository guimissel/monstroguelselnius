<?php

set_time_limit(0);

$pasta = __DIR__ . "/seeds";
$montadorSeed = new MontadorSeed($pasta);

$montadorSeed->run();