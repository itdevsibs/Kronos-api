<?php

declare(strict_types=1);

$app = require __DIR__ . '/../src/bootstrap.php';

$routes = require __DIR__ . '/../routes/api.php';

$routes($app);

$app->run();