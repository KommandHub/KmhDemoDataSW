<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Command;

use Kommandhub\DemoData\Command\CreateDemoUserCommand;
use Kommandhub\DemoData\Service\DemoUserAccount;
use Kommandhub\DemoData\Service\DemoUserProvisioner;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CreateDemoUserCommandTest extends TestCase
{
    private DemoUserProvisioner&MockObject $provisioner;
    private CreateDemoUserCommand $command;

    protected function setUp(): void
    {
        $this->provisioner = $this->createMock(DemoUserProvisioner::class);
        $this->command = new CreateDemoUserCommand($this->provisioner);
    }

    public function testExecuteCreatesAFreshAccountAndShowsThePassword(): void
    {
        $this->provisioner->expects($this->once())->method('provision')->with(
            $this->anything(),
            'demo@example.com',
            'demo',
            null,
            false
        )->willReturn(DemoUserAccount::created('user-id', 'demo@example.com', 'demo', 'generated-pw', 12));

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();

        $this->assertStringContainsString('Demo administration account', $output);
        $this->assertStringContainsString('created', $output);
        $this->assertStringContainsString('demo@example.com', $output);
        $this->assertStringContainsString('demo', $output);
        $this->assertStringContainsString('12, all of them read-only', $output);
        $this->assertStringContainsString('Password: generated-pw', $output);
        $this->assertStringContainsString('Shown once.', $output);
    }

    public function testExecutePassesTrimmedOptionsAndRotateFlagThrough(): void
    {
        $this->provisioner->expects($this->once())->method('provision')->with(
            $this->anything(),
            'custom@example.com',
            'custom-user',
            'my-password',
            true
        )->willReturn(DemoUserAccount::updated('user-id', 'custom@example.com', 'custom-user', 'my-password', 4));

        $tester = new CommandTester($this->command);
        $tester->execute([
            '--email' => '  custom@example.com  ',
            '--username' => '  custom-user  ',
            '--password' => '  my-password  ',
            '--rotate-password' => true,
        ]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Password: my-password', $tester->getDisplay());
    }

    public function testExecuteTreatsBlankOptionsAsUnset(): void
    {
        $this->provisioner->expects($this->once())->method('provision')->with(
            $this->anything(),
            null,
            null,
            null,
            false
        )->willReturn(DemoUserAccount::updated('user-id', 'demo@example.com', 'demo', null, 3));

        $tester = new CommandTester($this->command);
        $tester->execute([
            '--email' => '',
            '--username' => '   ',
            '--password' => '',
        ]);

        $tester->assertCommandIsSuccessful();
    }

    public function testExecuteReportsAnUpdatedAccountWithoutARotatedPassword(): void
    {
        $this->provisioner->method('provision')->willReturn(
            DemoUserAccount::updated('user-id', 'demo@example.com', 'demo', null, 7)
        );

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();

        $this->assertStringContainsString('already existed, privileges refreshed', $output);
        $this->assertStringContainsString('The password was left as it is.', $output);
        $this->assertStringContainsString('--rotate-password', $output);
    }

    public function testExecuteFailsWhenTheLoginBelongsToSomebodyElse(): void
    {
        $this->provisioner->method('provision')->willReturn(
            DemoUserAccount::refused('other-id', 'taken@example.com', 'taken-user')
        );

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $tester->getDisplay();

        $this->assertStringContainsString('taken@example.com', $output);
        $this->assertStringContainsString('taken-user', $output);
        $this->assertStringContainsString('Nothing was', $output);
        $this->assertStringContainsString('changed', $output);
    }

    public function testExecuteWithShowReportsAnExistingAccount(): void
    {
        $this->provisioner->expects($this->once())->method('describe')->willReturn([
            'exists' => true,
            'email' => 'demo@example.com',
            'username' => 'demo',
            'active' => true,
            'privileges' => 9,
        ]);
        $this->provisioner->expects($this->never())->method('provision');

        $tester = new CommandTester($this->command);
        $tester->execute(['--show' => true]);

        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();

        $this->assertStringContainsString('demo@example.com', $output);
        $this->assertStringContainsString('yes', $output);
        $this->assertStringContainsString('9', $output);
    }

    public function testExecuteWithShowReportsWhenNoAccountExistsYet(): void
    {
        $this->provisioner->method('describe')->willReturn([
            'exists' => false,
            'email' => null,
            'username' => null,
            'active' => null,
            'privileges' => 0,
        ]);

        $tester = new CommandTester($this->command);
        $tester->execute(['--show' => true]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('No demo account yet. Run this command without --show to create one.', $tester->getDisplay());
    }
}
