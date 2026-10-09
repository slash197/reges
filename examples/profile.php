<?php

declare(strict_types=1);

/*
 * Prints the employer profile behind the configured API key.
 *
 *     make run f=examples/profile.php
 */

/** @var Slash197\Reges\Reges $reges */
$reges = require __DIR__ . '/bootstrap.php';

echo json_encode($reges->profile()->raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
