<?php

declare(strict_types=1);

namespace App\Command;

use Smalot\PdfParser\Parser as PdfParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Dumps the extracted text of a statement PDF.
 *
 * This is a text-inspection aid, not a parser: Phase 4 builds the per-vendor
 * parsers that turn this text into canonical transactions. The extractor choice
 * is deliberately provisional here — see docs/decisions.md D-008.
 */
#[AsCommand(
    name: 'parse-statement',
    description: 'Dump the extracted text of a statement PDF for inspection.',
)]
class ParseStatement extends Command
{
    protected function configure(): void
    {
        $this
            ->setHelp('Extracts text from a statement PDF so its structure can be inspected.')
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'Path to the statement PDF to parse (keep real statements under the gitignored data/ directory).'
            )
            ->addOption(
                'page',
                'p',
                InputOption::VALUE_REQUIRED,
                'Page number to extract. Omit to extract every page.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('path');

        if (!is_file($path) || !is_readable($path)) {
            $output->writeln(sprintf('<error>Statement not readable: %s</error>', $path));

            return Command::INVALID;
        }

        $pages = (new PdfParser())->parseFile($path)->getPages();

        $page = $input->getOption('page');

        if ($page !== null) {
            $index = (int) $page - 1;

            if (!isset($pages[$index])) {
                $output->writeln(sprintf('<error>Page %d does not exist in %s</error>', (int) $page, $path));

                return Command::INVALID;
            }

            $pages = [$pages[$index]];
        }

        foreach ($pages as $extracted) {
            $output->writeln($extracted->getText());
        }

        return Command::SUCCESS;
    }
}
