<?php

/**
 * smolContext
 * https://github.com/joby-lol/smol-context
 * (c) 2024-2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Context;

use Attribute;

/**
 * Attribute to indicate that a class should be autowired, and can be instantiated as an implicit dependency without explicitly prior registration.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Autowire
{

}