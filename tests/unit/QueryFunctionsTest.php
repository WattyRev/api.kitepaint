<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QueryFunctionsTest extends TestCase
{
    private function escape(): callable
    {
        // Stand-in for mysqli_real_escape_string, which needs a live connection.
        return fn ($value) => addslashes($value);
    }

    public function testValidateColumnAcceptsAllowedColumn(): void
    {
        $this->assertSame('status', validate_column('status', ['id', 'status']));
    }

    public function testValidateColumnAcceptsAnyIdentifierWhenNoAllowlist(): void
    {
        $this->assertSame('create_time', validate_column('create_time'));
    }

    public function testValidateColumnRejectsColumnNotInAllowlist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        validate_column('password', ['username', 'loginid']);
    }

    public static function invalidColumnNames(): array
    {
        return [
            'injection' => ['id = 1 OR 1'],
            'comment' => ['id--'],
            'backtick' => ['id`'],
            'space' => ['first name'],
            'leading digit' => ['1id'],
            'integer key' => [1],
            'empty' => [''],
            'array' => [['id']],
        ];
    }

    #[DataProvider('invalidColumnNames')]
    public function testValidateColumnRejectsInvalidNames(mixed $column): void
    {
        $this->expectException(InvalidArgumentException::class);
        validate_column($column);
    }

    public function testBuildWhereConditionsQuotesAndEscapesValues(): void
    {
        $sql = build_where_conditions(['active' => '1', 'status' => 2], ['active', 'status'], $this->escape());
        $this->assertSame("`active` = '1' AND `status` = '2'", $sql);
    }

    public function testBuildWhereConditionsEscapesInjectionInValue(): void
    {
        $sql = build_where_conditions(['id' => "1' OR '1'='1"], ['id'], $this->escape());
        $this->assertSame("`id` = '1\\' OR \\'1\\'=\\'1'", $sql);
    }

    public function testBuildWhereConditionsRejectsDisallowedColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        build_where_conditions(['password' => 'x'], ['loginid'], $this->escape());
    }

    public function testBuildWhereConditionsRejectsInjectionInColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        build_where_conditions(['1=1 OR id' => '1'], null, $this->escape());
    }

    public function testBuildWhereConditionsRejectsNonArrayFilter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        build_where_conditions('id=1', null, $this->escape());
    }

    public function testBuildWhereConditionsRejectsArrayValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        build_where_conditions(['id' => ['1', '2']], null, $this->escape());
    }

    public function testBuildOrderClause(): void
    {
        $this->assertSame('ORDER BY `id` DESC', build_order_clause(['id', 'desc'], ['id']));
        $this->assertSame('ORDER BY `name` ASC', build_order_clause(['name']));
        $this->assertSame('', build_order_clause(null));
    }

    public function testBuildOrderClauseRejectsInvalidDirection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        build_order_clause(['id', 'DESC; DROP TABLE designs']);
    }

    public function testBuildOrderClauseRejectsDisallowedColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        build_order_clause(['password', 'ASC'], ['id']);
    }

    public function testBuildOrderClauseRejectsNonArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        build_order_clause('id DESC');
    }

    public function testBuildLimitClause(): void
    {
        $this->assertSame('', build_limit_clause(null));
        $this->assertSame('LIMIT 50', build_limit_clause('50'));
        $this->assertSame('LIMIT 100 OFFSET 200', build_limit_clause('100', '200'));
        $this->assertSame('LIMIT 0', build_limit_clause(0));
    }

    public function testBuildLimitClauseAcceptsOffsetCountForm(): void
    {
        // The public site paginates with limit="<offset>, <count>".
        $this->assertSame('LIMIT 0, 50', build_limit_clause('0, 50'));
        $this->assertSame('LIMIT 24, 48', build_limit_clause('24,48'));
    }

    public function testBuildLimitClauseRejectsOffsetCountFormWithSkip(): void
    {
        $this->expectException(InvalidArgumentException::class);
        build_limit_clause('0, 50', '10');
    }

    public static function invalidLimits(): array
    {
        return [
            'injection' => ['1; DROP TABLE login'],
            'injection after comma' => ['0, 50 UNION SELECT password FROM login'],
            'three numbers' => ['1, 2, 3'],
            'negative offset' => ['-1, 50'],
            'trailing comma' => ['10,'],
            'negative' => ['-1'],
            'decimal' => ['1.5'],
            'empty' => [''],
            'array' => [['1']],
        ];
    }

    #[DataProvider('invalidLimits')]
    public function testBuildLimitClauseRejectsInvalidLimit(mixed $limit): void
    {
        $this->expectException(InvalidArgumentException::class);
        build_limit_clause($limit);
    }

    public function testBuildLimitClauseRejectsInvalidSkip(): void
    {
        $this->expectException(InvalidArgumentException::class);
        build_limit_clause('10', '0 UNION SELECT 1');
    }

    public function testBuildLimitClauseRejectsSkipWithoutLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        build_limit_clause(null, '10');
    }

    public function testValidateReturnColumns(): void
    {
        $this->assertSame(['username', 'loginid'], validate_return_columns(['a' => 'username', 'b' => 'loginid'], ['username', 'loginid']));
    }

    public function testValidateReturnColumnsRejectsSensitiveColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        validate_return_columns(['username', 'password'], ['username', 'loginid']);
    }

    public function testValidateReturnColumnsRejectsNonArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        validate_return_columns('username');
    }
}
