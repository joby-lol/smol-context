<?php
/**
 * This file should get a ConfigValue and echo it. When included, the buffered output should be returned.
 *
 * #[ConfigValue("test/test_value")]
 * @var string $test_value
 */

echo "test_value: $test_value";

// This file returns 1, which should be the same as returning nothing for detecting whether to use output buffer content
return 1;