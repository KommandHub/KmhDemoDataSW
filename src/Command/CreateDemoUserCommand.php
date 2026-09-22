<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Command;

use Kommandhub\DemoData\Service\DemoUserProvisioner;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Creates the administrator account that goes out with the demo.
 *
 * The password is shown once, here, and never again: what the database holds is
 * a hash. Run with --rotate-password to choose a new one.
 */
#[AsCommand(
    name: 'kmh:demo-data:user',
    description: 'Creates a read-only administration account for showing the demo to customers.',
)]
class CreateDemoUserCommand extends Command
{
    public function __construct(private readonly DemoUserProvisioner $provisioner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Login email', DemoUserProvisioner::DEFAULT_EMAIL)
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Login name', DemoUserProvisioner::DEFAULT_USERNAME)
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Use this password instead of a generated one')
            ->addOption('rotate-password', null, InputOption::VALUE_NONE, 'Choose a new password for an account that already exists')
            ->addOption('show', null, InputOption::VALUE_NONE, 'Report the account without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createCLIContext();

        $io->title('Demo administration account');

        if ($input->getOption('show')) {
            return $this->show($io, $context);
        }

        $account = $this->provisioner->provision(
            $context,
            $this->text($input, 'email'),
            $this->text($input, 'username'),
            $this->text($input, 'password'),
            (bool)$input->getOption('rotate-password')
        );

        if ($account->refused) {
            $io->error(sprintf(
                'An account already uses "%s" or "%s", and this plugin did not create it. '
                . 'Nothing was changed — pass --email and --username to pick names that are free.',
                $account->email,
                $account->username
            ));

            return self::FAILURE;
        }

        $io->definitionList(
            ['Account' => $account->created ? 'created' : 'already existed, privileges refreshed'],
            ['Email' => $account->email],
            ['Username' => $account->username],
            ['Privileges' => sprintf('%d, all of them read-only', $account->privileges)]
        );

        if ($account->password !== null) {
            $io->success('Password: ' . $account->password);
            $io->note('Shown once. The database keeps a hash, so a forgotten password means --rotate-password.');
        } else {
            $io->note('The password was left as it is. Use --rotate-password to choose a new one.');
        }

        return self::SUCCESS;
    }

    /**
     * An option left out arrives as null and an option passed empty arrives as
     * "", and both mean "use the default" rather than "call the account ''".
     */
    private function text(InputInterface $input, string $option): ?string
    {
        $value = $input->getOption($option);

        if (!\is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function show(SymfonyStyle $io, Context $context): int
    {
        $account = $this->provisioner->describe($context);

        if (!$account['exists']) {
            $io->warning('No demo account yet. Run this command without --show to create one.');

            return self::SUCCESS;
        }

        $io->definitionList(
            ['Email' => (string)$account['email']],
            ['Username' => (string)$account['username']],
            ['Active' => $account['active'] ? 'yes' : 'no'],
            ['Privileges' => (string)$account['privileges']]
        );

        return self::SUCCESS;
    }
}
