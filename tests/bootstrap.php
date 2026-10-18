<?php

declare(strict_types=1);

/*
 * The module autoloader only maps src/. Map the test namespace too, so tests can share
 * helpers (traits under tests/Support) that PHPUnit does not load as test files.
 */
$loader = require __DIR__.'/../../../vendor/autoload.php';
$loader->addPsr4('Modules\\VenueBookings\\Tests\\', __DIR__);
