<?php

use PHPUnit\Framework\TestCase;

class TombstoneTest extends TestCase
{
    public function testDeletedAccountPlaceholdersUseTheLoginId(): void
    {
        $this->assertSame('deleted-12', deleted_account_username(12));
        $this->assertSame('deleted-12@deleted.invalid', deleted_account_email(12));
    }

    public function testPlaceholderUsernamesAreReserved(): void
    {
        $this->assertTrue(is_reserved_username('deleted-12'));
        $this->assertTrue(is_reserved_username('Deleted-5'));
    }

    public function testOrdinaryUsernamesAreNotReserved(): void
    {
        $this->assertFalse(is_reserved_username('deleted'));
        $this->assertFalse(is_reserved_username('undeleted-1'));
        $this->assertFalse(is_reserved_username('bob'));
    }
}
