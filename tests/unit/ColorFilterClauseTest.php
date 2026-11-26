<?php

use PHPUnit\Framework\TestCase;

class ColorFilterClauseTest extends TestCase
{
    private function escape(): callable
    {
        // Stand-in for mysqli_real_escape_string, which needs a live connection.
        return fn ($value) => addslashes($value);
    }

    public function testNoColorsBuildsNoClause(): void
    {
        $this->assertSame('', build_color_filter_clause(null, 'any', false, $this->escape()));
        $this->assertSame('', build_color_filter_clause(array(), 'all', true, $this->escape()));
    }

    public function testIncludeAnyMatchesDesignsWithAtLeastOneColor(): void
    {
        $clause = build_color_filter_clause(array('#ff0000', '#00ff00'), 'any', false, $this->escape());

        $this->assertSame(
            "designs.id IN (SELECT design_id FROM design_colors WHERE color IN ('#ff0000', '#00ff00'))",
            $clause
        );
    }

    public function testIncludeAllRequiresEverySelectedColor(): void
    {
        $clause = build_color_filter_clause(array('#ff0000', '#00ff00'), 'all', false, $this->escape());

        $this->assertSame(
            "designs.id IN (SELECT design_id FROM design_colors WHERE color IN ('#ff0000', '#00ff00')"
            . " GROUP BY design_id HAVING COUNT(DISTINCT color) = 2)",
            $clause
        );
    }

    public function testExcludeOtherColorsWithAny(): void
    {
        $clause = build_color_filter_clause(array('#ff0000'), 'any', true, $this->escape());

        $this->assertSame(
            "designs.id IN (SELECT design_id FROM design_colors WHERE color IN ('#ff0000'))"
            . " AND designs.id NOT IN (SELECT design_id FROM design_colors WHERE color NOT IN ('#ff0000'))",
            $clause
        );
    }

    public function testExcludeOtherColorsWithAllAddsTheSameNoOtherColorsCheck(): void
    {
        $clause = build_color_filter_clause(array('#ff0000', '#00ff00'), 'all', true, $this->escape());

        $this->assertSame(
            "designs.id IN (SELECT design_id FROM design_colors WHERE color IN ('#ff0000', '#00ff00')"
            . " GROUP BY design_id HAVING COUNT(DISTINCT color) = 2)"
            . " AND designs.id NOT IN (SELECT design_id FROM design_colors WHERE color NOT IN ('#ff0000', '#00ff00'))",
            $clause
        );
    }

    public function testColorsAreNormalizedAndDeduplicated(): void
    {
        $clause = build_color_filter_clause(array('#FF0000', '#f00', '#ff0000'), 'all', false, $this->escape());

        $this->assertStringContainsString("color IN ('#ff0000')", $clause);
        $this->assertStringContainsString('HAVING COUNT(DISTINCT color) = 1', $clause);
    }

    public function testWhiteCanBeFiltered(): void
    {
        $clause = build_color_filter_clause(array('#ffffff'), 'any', false, $this->escape());

        $this->assertStringContainsString("color IN ('#ffffff')", $clause);
    }

    public function testRejectsInvalidColor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        build_color_filter_clause(array("#ff0000' OR 1=1 --"), 'any', false, $this->escape());
    }

    public function testRejectsNamedAndNonStringColors(): void
    {
        foreach (array(array('red'), array(255), array(array('#ff0000'))) as $colors) {
            try {
                build_color_filter_clause($colors, 'any', false, $this->escape());
                $this->fail('Expected an invalid color to be rejected');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame('Invalid color', $exception->getMessage());
            }
        }
    }

    public function testRejectsUnknownMatchMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        build_color_filter_clause(array('#ff0000'), 'some', false, $this->escape());
    }

    public function testRejectsMatchModeThatIsNotAString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        build_color_filter_clause(array('#ff0000'), array('all'), false, $this->escape());
    }

    public function testRejectsColorsThatAreNotAList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        build_color_filter_clause('#ff0000', 'any', false, $this->escape());
    }
}
