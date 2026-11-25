<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../api/php/db_config.functions.inc.php';

class DbConfigTest extends TestCase
{
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
    }

    // Writes a PHP config file that returns $data, the same way the real server-only file is written.
    private function writeConfig(array $data): string
    {
        $file = tempnam(sys_get_temp_dir(), 'kpdb');
        $this->tempFiles[] = $file;
        file_put_contents($file, '<?php return ' . var_export($data, true) . ';');
        return $file;
    }

    private function validConfig(): array
    {
        return [
            'prod' => ['host' => 'localhost', 'user' => 'prod_user', 'pass' => 'prod_pass', 'db' => 'prod_db'],
            'beta' => ['host' => 'localhost', 'user' => 'beta_user', 'pass' => 'beta_pass', 'db' => 'beta_db'],
        ];
    }

    public function testReturnsProdSectionWhenProduction(): void
    {
        $config = kp_load_db_config($this->writeConfig($this->validConfig()), true);
        $this->assertSame(
            ['host' => 'localhost', 'user' => 'prod_user', 'pass' => 'prod_pass', 'db' => 'prod_db'],
            $config
        );
    }

    public function testReturnsBetaSectionWhenNotProduction(): void
    {
        $config = kp_load_db_config($this->writeConfig($this->validConfig()), false);
        $this->assertSame('beta_user', $config['user']);
        $this->assertSame('beta_db', $config['db']);
    }

    public function testThrowsWhenFileMissing(): void
    {
        $this->expectException(RuntimeException::class);
        kp_load_db_config(sys_get_temp_dir() . '/does-not-exist-' . uniqid() . '.php', true);
    }

    public function testThrowsWhenEnvironmentSectionMissing(): void
    {
        $data = $this->validConfig();
        unset($data['beta']);
        $this->expectException(RuntimeException::class);
        kp_load_db_config($this->writeConfig($data), false);
    }

    public function testThrowsWhenAFieldIsMissing(): void
    {
        $data = $this->validConfig();
        unset($data['prod']['pass']);
        $this->expectException(RuntimeException::class);
        kp_load_db_config($this->writeConfig($data), true);
    }

    public function testThrowsWhenAFieldIsEmptyOrNotAString(): void
    {
        foreach ([['db' => ''], ['db' => 123], ['db' => null]] as $override) {
            $data = $this->validConfig();
            $data['prod'] = array_merge($data['prod'], $override);
            try {
                kp_load_db_config($this->writeConfig($data), true);
                $this->fail('Expected RuntimeException for ' . json_encode($override));
            } catch (RuntimeException $e) {
                $this->assertStringNotContainsString('prod_pass', $e->getMessage());
            }
        }
    }

    public function testErrorMessagesDoNotIncludeTheFilePath(): void
    {
        $path = sys_get_temp_dir() . '/does-not-exist-' . uniqid() . '.php';
        try {
            kp_load_db_config($path, true);
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString($path, $e->getMessage());
        }
    }
}
