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
 * Attribute to indicate that a class should not be autowired even if it is part of a registered namespace. NoAutowire classes may still be explicitly registered, and will be used normally if they are.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class NoAutowire
{

}