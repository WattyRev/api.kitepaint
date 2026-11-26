<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ColorFunctionsTest extends TestCase
{
    private static function variations(string ...$svgs): string
    {
        $variations = [];
        foreach ($svgs as $index => $svg) {
            $variations[] = ['id' => (string) ($index + 1), 'name' => 'v' . $index, 'svg' => $svg, 'primary' => false];
        }
        return json_encode($variations);
    }

    public static function normalizeCases(): array
    {
        return [
            'six digit lowercase' => ['#ff0000', '#ff0000'],
            'six digit uppercase' => ['#FF00AA', '#ff00aa'],
            'three digit expands' => ['#f0a', '#ff00aa'],
            'white is kept' => ['#ffffff', '#ffffff'],
            'black is kept' => ['#000', '#000000'],
            'surrounding space trimmed' => ['  #00ff00 ', '#00ff00'],
            'named color rejected' => ['red', null],
            'none rejected' => ['none', null],
            'rgb function rejected' => ['rgb(255,0,0)', null],
            'missing hash rejected' => ['ff0000', null],
            'four digit rejected' => ['#ff00', null],
            'bad digit rejected' => ['#ff00zz', null],
            'empty rejected' => ['', null],
            'trailing newline trimmed' => ["#ff0000\n", '#ff0000'],
        ];
    }

    #[DataProvider('normalizeCases')]
    public function testNormalizeHexColor(string $input, ?string $expected): void
    {
        $this->assertSame($expected, normalize_hex_color($input));
    }

    public function testNormalizeHexColorRejectsNonStrings(): void
    {
        $this->assertNull(normalize_hex_color(null));
        $this->assertNull(normalize_hex_color(255));
    }

    public function testExtractsFillsOfDataIdPanelsOnly(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<path data-id="1" fill="#ff0000"/>'
            . '<path data-id="2" fill="#00ff00"/>'
            . '<path fill="#0000ff"/>'
            . '</svg>';

        $this->assertEqualsCanonicalizing(['#ff0000', '#00ff00'], extract_design_colors(self::variations($svg)));
    }

    public function testFindsPanelsInNestedGroups(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><g><g><rect data-id="a" fill="#123456"/></g></g></svg>';

        $this->assertSame(['#123456'], extract_design_colors(self::variations($svg)));
    }

    public function testKeepsWhiteAsAColor(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><path data-id="1" fill="#FFFFFF"/></svg>';

        $this->assertSame(['#ffffff'], extract_design_colors(self::variations($svg)));
    }

    public function testDeduplicatesAcrossPanelsAndVariations(): void
    {
        $first = '<svg xmlns="http://www.w3.org/2000/svg"><path data-id="1" fill="#f00"/><path data-id="2" fill="#f00"/></svg>';
        $second = '<svg xmlns="http://www.w3.org/2000/svg"><path data-id="1" fill="#FF0000"/><path data-id="2" fill="#0f0"/></svg>';

        $this->assertEqualsCanonicalizing(['#ff0000', '#00ff00'], extract_design_colors(self::variations($first, $second)));
    }

    public function testIgnoresPanelsWithInvalidFills(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<path data-id="1" fill="none"/>'
            . '<path data-id="2"/>'
            . '<path data-id="3" fill="rgb(1,2,3)"/>'
            . '<path data-id="4" fill="#00ff00"/>'
            . '</svg>';

        $this->assertSame(['#00ff00'], extract_design_colors(self::variations($svg)));
    }

    public function testInvalidSvgContributesNothing(): void
    {
        $good = '<svg xmlns="http://www.w3.org/2000/svg"><path data-id="1" fill="#abcdef"/></svg>';
        $broken = '<svg><path data-id="1" fill="#ff0000"></svg>';

        $this->assertSame(['#abcdef'], extract_design_colors(self::variations($broken, $good)));
    }

    public function testEmptySvgContributesNothing(): void
    {
        $this->assertSame([], extract_svg_panel_colors(''));
        $this->assertSame([], extract_svg_panel_colors(null));
    }

    public function testInvalidVariationsJsonYieldsNoColors(): void
    {
        $this->assertSame([], extract_design_colors('not json'));
        $this->assertSame([], extract_design_colors('"just a string"'));
        $this->assertSame([], extract_design_colors(null));
        $this->assertSame([], extract_design_colors(''));
    }

    public function testVariationWithoutSvgIsSkipped(): void
    {
        $variations = json_encode([['id' => '1', 'name' => 'Standard'], 'not an object']);

        $this->assertSame([], extract_design_colors($variations));
    }

    public function testDesignWithNoPaintedPanelsHasNoColors(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><rect fill="#ff0000"/></svg>';

        $this->assertSame([], extract_design_colors(self::variations($svg)));
    }
}
