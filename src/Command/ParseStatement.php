<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use SetaPDF_Core_Document as PDFDocument;
use SetaPDF_Extractor as PDFExtractor;
use SetaPDF_Extractor_Strategy_ExactPlain as PDFExactTextStrategy;
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
			->setHelp('This command parses a financial statement in PDF format and extracts the data.');
	}

	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		ray()->clearScreen();

		$this->out = $output;
		$this->in = $input;
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$document = PDFDocument::loadByFilename("data/samples/statement.pdf");
		$extractor = new PDFExtractor($document);
		$extractor->setStrategy(new PDFWordGroupStrategy());

		$result = $extractor->getResultByPageNumber(1);

		/** @var PDFWordsResult $group */
		foreach($result as $group) {
			ray($group->getString());
		}

		return Command::SUCCESS;
	}
}
