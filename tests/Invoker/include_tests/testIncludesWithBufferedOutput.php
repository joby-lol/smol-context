<?php
/**
 * This file should get a ConfigValue and echo it. When included, the buffered output should be returned.
 *
 * #[ConfigValue("test/test_value")]
 * @var string $test_value
 */

echo "test_value: $test_value";

// This file doesn't return anything.