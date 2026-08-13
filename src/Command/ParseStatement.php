<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use SetaPDF_Core_Document as PDFDocument;
use SetaPDF_Extractor as PDFExtractor;
use SetaPDF_Extractor_Strategy_WordGroup as PDFWordGroupStrategy;
use SetaPDF_Extractor_Result_Words as PDFWordsResult;

class ParseStatement extends Command
{
	protected static $defaultName = 'parse-statement';

	private OutputInterface $out;
	private InputInterface $in;

	protected function configure()
	{
		$this
			->setDescription('Parse a financial statement in PDF format to access it\'s data.')
			->setHelp('This command parses a financial statement in PDF format and extracts the data.')
			->addArgument(
				'path',
				InputArgument::REQUIRED,
				'Path to the statement PDF to parse (keep real statements under the gitignored data/ directory).'
			)
			->addOption(
				'page',
				'p',
				InputOption::VALUE_REQUIRED,
				'Page number to extract.',
				1
			);
	}

	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		$this->out = $output;
		$this->in = $input;
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$path = (string) $input->getArgument('path');

		if (!is_file($path) || !is_readable($path)) {
			$output->writeln(sprintf('<error>Statement not readable: %s</error>', $path));

			return Command::INVALID;
		}

		$document = PDFDocument::loadByFilename($path);
		$extractor = new PDFExtractor($document);
		$extractor->setStrategy(new PDFWordGroupStrategy());

		$result = $extractor->getResultByPageNumber((int) $input->getOption('page'));

		/** @var PDFWordsResult $group */
		foreach ($result as $group) {
			$output->writeln($group->getString());
		}

		return Command::SUCCESS;
	}
}
