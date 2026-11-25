<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValidationFunctionsTest extends TestCase
{
    public static function usernames(): array
    {
        return [
            'letters and digits' => ['kite123', true],
            'underscore and dash' => ['kite_paint-1', true],
            'min length' => ['abc', true],
            'max length' => [str_repeat('a', 30), true],
            'too short' => ['ab', false],
            'too long' => [str_repeat('a', 31), false],
            'empty' => ['', false],
            'space' => ['kite paint', false],
            'quote' => ["kite'", false],
            'sql' => ["a' OR '1'='1", false],
        ];
    }

    #[DataProvider('usernames')]
    public function testValidUsername(string $username, bool $expected): void
    {
        $this->assertSame($expected, valid_username($username));
    }

    public static function passwords(): array
    {
        return [
            'valid' => ['secret1', true],
            'min length' => ['abcdef', true],
            'max length' => [str_repeat('a', 15), true],
            'too short' => ['abcde', false],
            'too long' => [str_repeat('a', 16), false],
            'whitespace only' => ['       ', false],
        ];
    }

    #[DataProvider('passwords')]
    public function testValidPassword(string $password, bool $expected): void
    {
        $this->assertSame($expected, valid_password($password));
    }

    public static function emails(): array
    {
        return [
            'valid' => ['someone@example.com', true],
            'subdomain' => ['a.b@mail.example.co.uk', true],
            'missing at' => ['someone.example.com', false],
            'two ats' => ['a@b@example.com', false],
            'newline' => ["a@example.com\r\nBcc: x@example.com", false],
            'empty' => ['', false],
        ];
    }

    #[DataProvider('emails')]
    public function testValidEmail(string $email, bool $expected): void
    {
        $this->assertSame($expected, valid_email($email));
    }
}
