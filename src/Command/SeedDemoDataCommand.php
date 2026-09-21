<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Command;

use Kommandhub\DemoData\Blueprint\DemoBlueprint;
use Kommandhub\DemoData\Exception\DemoDataException;
use Kommandhub\DemoData\Service\DemoDataSeeder;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Seeds the demo catalogue.
 *
 * Non-interactive by design. The predecessor of this plugin asked the operator
 * to pick a manufacturer, a CMS page and a handful of images from menus, which
 * made the result depend on who ran it and made the command impossible to put in
 * a provisioning script. Everything it asked for is now resolved from the
 * installation itself.
 */
#[AsCommand(
    name: 'kmh:demo-data:seed',
    description: 'Creates or completes the Kommandhub demo catalogue. Safe to run repeatedly.',
)]
class SeedDemoDataCommand extends Command
{
    public function __construct(private readonly DemoDataSeeder $seeder)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'channel',
            'c',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            \sprintf('Only seed these blueprint channels (%s).', implode(', ', array_keys(DemoBlueprint::salesChannels())))
        );

        $this->addOption(
            'per-category',
            'p',
            InputOption::VALUE_REQUIRED,
            \sprintf(
                'Products per leaf category (default %d). Raising it only adds products; it never renames or replaces existing ones.',
                DemoBlueprint::PRODUCTS_PER_CATEGORY
            )
        );

        $this->addOption(
            'skip-orders',
            null,
            InputOption::VALUE_NONE,
            'Skip customers and order history. Much faster, and what a CI seed usually wants.'
        );

        $this->addOption(
            'skip-media',
            null,
            InputOption::VALUE_NONE,
            'Skip image import. Much faster, and the only way to seed on a read-only filesystem.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var array<int, string> $channels */
        $channels = $input->getOption('channel');
        $unknown = array_diff($channels, array_keys(DemoBlueprint::salesChannels()));

        if ($unknown !== []) {
            $io->error(\sprintf('Unknown channel key(s): %s', implode(', ', $unknown)));

            return Command::INVALID;
        }

        $perCategory = $input->getOption('per-category');

        if ($perCategory !== null && (!is_numeric($perCategory) || (int)$perCategory < 1)) {
            $io->error('--per-category must be a positive whole number.');

            return Command::INVALID;
        }

        $io->title('Kommandhub demo data');

        try {
            $report = $this->seeder->seed(
                Context::createCLIContext(),
                $channels,
                !$input->getOption('skip-media'),
                $perCategory !== null ? (int)$perCategory : null
            );
        } catch (DemoDataException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->table($report->headers(), $report->toTable());

        // Notes, not warnings: nothing here failed. They are the things an
        // operator has to act on or write down — a follow-up command to run, a
        // demo login to hand over.
        if ($report->notes() !== []) {
            $io->section('Worth knowing');
            $io->listing($report->notes());
        }

        if ($report->totalCreated() === 0 && $report->totalEnriched() === 0) {
            $io->success('Nothing to do — the demo catalogue is already complete.');

            return Command::SUCCESS;
        }

        $io->success(\sprintf(
            'Demo data up to date: %d entities created, %d existing entities completed.',
            $report->totalCreated(),
            $report->totalEnriched()
        ));

        return Command::SUCCESS;
    }
}
