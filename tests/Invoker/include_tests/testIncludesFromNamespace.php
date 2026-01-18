<?php

namespace Joby\Smol\Context\TestClasses;

use Joby\Smol\Config\Config;

/**
 * This tests including a file that has a namespace specified, and then has var declarations from both inside and
 * outside that namespace.
 *
 * @var TestClassA $a
 * @var Config     $c
 * #[ConfigValue("test/test_int_key")]
 * @var int        $i
 */

return [
    'a' => $a,
    'c' => $c,
    'i' => $i,
];