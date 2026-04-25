<?php

declare(strict_types=1);

namespace Moffhub\Cli\Tests\Unit;

use Moffhub\Cli\Commands\CertifyCommand;
use Moffhub\Cli\Commands\InitConnectorCommand;
use Moffhub\Cli\Commands\ValidateManifestCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class CommandsRegistrationTest extends TestCase
{
    public function testApplicationHasAllThreeCommands(): void
    {
        $app = $this->buildApplication();

        $this->assertTrue($app->has('certify'));
        $this->assertTrue($app->has('init-connector'));
        $this->assertTrue($app->has('validate'));
    }

    public function testValidateCommandFailsWhenClassDoesNotExist(): void
    {
        $tester = new CommandTester($this->buildApplication()->find('validate'));

        $exitCode = $tester->execute([
            'connector-class' => 'Nonexistent\\Connector\\Class',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('not found', $tester->getDisplay());
    }

    public function testCertifyCommandFailsWhenClassDoesNotExist(): void
    {
        $tester = new CommandTester($this->buildApplication()->find('certify'));

        $exitCode = $tester->execute([
            'connector-class' => 'Nonexistent\\Connector\\Class',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('not found', $tester->getDisplay());
    }

    private function buildApplication(): Application
    {
        $app = new Application('moffhub', '0.1.0');
        $app->add(new CertifyCommand);
        $app->add(new InitConnectorCommand);
        $app->add(new ValidateManifestCommand);

        return $app;
    }
}
