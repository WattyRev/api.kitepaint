<?php

use PHPUnit\Framework\TestCase;

class GenerateCodeTest extends TestCase
{
    public function testGeneratesCodeOfRequestedLength(): void
    {
        $this->assertSame(20, strlen(generate_code(20)));
        $this->assertSame(10, strlen(generate_code()));
    }

    public function testUsesOnlyUnambiguousAlphanumericCharacters(): void
    {
        $this->assertMatchesRegularExpression('/^[a-np-zA-NP-Z1-9]+$/', generate_code(500));
    }

    public function testReturnsFalseForNonPositiveLength(): void
    {
        $this->assertFalse(generate_code(0));
        $this->assertFalse(generate_code(-1));
    }

    public function testCodesAreNotRepeated(): void
    {
        $codes = array_map(fn () => generate_code(20), range(1, 100));
        $this->assertCount(100, array_unique($codes));
    }
}
