<?php

namespace Joby\Smol\Context\Invoker;

use PHPUnit\Framework\TestCase;

class IncludeFileVarParserTest extends TestCase
{

    public function testParsesNonFullyQualifiedVarWithUse(): void
    {
        $source = <<<PHP
            <?php
            /**
             * @var TestClassB \$test
             */

            use Joby\\Smol\\Context\\TestClasses\\TestClassB;

            return 1;
            PHP;

        $path = tempnam(sys_get_temp_dir(), 'smolctx_');
        $this->assertNotFalse($path);
        file_put_contents($path, $source);

        try {
            $vars = IncludeFileVarParser::parse($path);
            $this->assertArrayHasKey('test', $vars);
            $this->assertInstanceOf(ObjectPlaceholder::class, $vars['test']);
            /** @var ObjectPlaceholder $placeholder */
            $placeholder = $vars['test'];
            $this->assertSame('Joby\\Smol\\Context\\TestClasses\\TestClassB', $placeholder->class);
        }
        finally {
            @unlink($path);
        }
    }

    public function testParsesConfigValueNullableTypes(): void
    {
        $source = <<<PHP
            <?php
            /**
             * #[ConfigValue("nullable_string_key")]
             * @var ?string \$nullable_string
             * #[ConfigValue("nullable_int_key")]
             * @var int|null \$nullable_int
             */
            return 1;
            PHP;

        $path = tempnam(sys_get_temp_dir(), 'smolctx_');
        $this->assertNotFalse($path);
        file_put_contents($path, $source);

        try {
            $vars = IncludeFileVarParser::parse($path);
            $this->assertArrayHasKey('nullable_string', $vars);
            $this->assertArrayHasKey('nullable_int', $vars);

            $this->assertInstanceOf(ConfigPlaceholder::class, $vars['nullable_string']);
            $this->assertInstanceOf(ConfigPlaceholder::class, $vars['nullable_int']);

            /** @var ConfigPlaceholder $s */
            $s = $vars['nullable_string'];
            $this->assertSame('nullable_string_key', $s->key);
            $this->assertSame(['string'], $s->valid_types);
            $this->assertTrue($s->allows_null);

            /** @var ConfigPlaceholder $i */
            $i = $vars['nullable_int'];
            $this->assertSame('nullable_int_key', $i->key);
            $this->assertSame(['int'], $i->valid_types);
            $this->assertTrue($i->allows_null);
        }
        finally {
            @unlink($path);
        }
    }

    public function testRejectsUnionTypesForObjects(): void
    {
        $source = <<<PHP
            <?php
            /**
             * @var TestClassA|TestClassB \$x
             */

            use Joby\\Smol\\Context\\TestClasses\\TestClassA;
            use Joby\\Smol\\Context\\TestClasses\\TestClassB;

            return 1;
            PHP;

        $path = tempnam(sys_get_temp_dir(), 'smolctx_');
        $this->assertNotFalse($path);
        file_put_contents($path, $source);

        try {
            $this->expectException(\RuntimeException::class);
            IncludeFileVarParser::parse($path);
        }
        finally {
            @unlink($path);
        }
    }

}
