<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Definition;

use Circli\Console\Definition;
use Stefna\OpenApiBundler\Command\BundleCommand;
use Stefna\OpenApiBundler\Input\BundleInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class BundleDefinition extends Definition
{
	public const NAME = 'bundle:schema';
	public const SCHEMA = 'schema';
	public const OUTPUT = 'output';
	public const COMPRESSION = 'compress';

	protected function configure(): void
	{
		$this->setName(self::NAME);

		$this->addArgument(self::SCHEMA, InputArgument::REQUIRED, 'Schema to bundle');
		$this->addArgument(self::OUTPUT, InputArgument::OPTIONAL, 'Output file. If not set prints to STDOUT');

		$this->addOption(self::COMPRESSION, null,InputOption::VALUE_NONE, 'Remove white space from output');

		$this->setCommand(new BundleCommand());
	}

	public function transformInput(InputInterface $input, OutputInterface $output): InputInterface
	{
		/** @var string $schema */
		$schema = $input->getArgument(self::SCHEMA);
		if (!file_exists($schema)) {
			$schema = realpath(getcwd() . DIRECTORY_SEPARATOR . $schema);
			if (!$schema || !file_exists($schema)) {
				throw new \RuntimeException('Can\'t read file.');
			}
		}
		elseif (!str_starts_with($schema, '/')) {
			$schema = getcwd() . DIRECTORY_SEPARATOR . $schema;
		}
		$compress = null;
		if ($input->getOption(self::COMPRESSION)) {
			$compress = true;
		}
		/** @var string|null $outputFile */
		$outputFile = $input->getArgument(self::OUTPUT);
		return new BundleInput(
			$schema,
			$outputFile,
			$compress,
		);
	}
}
