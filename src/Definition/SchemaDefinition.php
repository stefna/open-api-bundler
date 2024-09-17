<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Definition;

use Circli\Console\Definition;
use Stefna\OpenApiBundler\Input\SchemaInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SchemaDefinition extends Definition
{
	public const NAME = 'schema:';
	public const SCHEMA = 'schema';
	public const OUTPUT = 'output';
	public const COMPRESSION = 'compress';
	public const ROOT = 'root';

	public function __construct(
		private readonly string $name,
		private readonly string|object $commandClass,
		private readonly bool $optionalSchema = false,
	) {
		parent::__construct();
	}

	protected function configure(): void
	{
		$this->setName(self::NAME . $this->name);

		$this->addArgument(
			self::SCHEMA,
			$this->optionalSchema ? InputArgument::OPTIONAL : InputArgument::REQUIRED,
			'Schema to bundle',
		);
		$this->addArgument(self::OUTPUT, InputArgument::OPTIONAL, 'Output file. If not set prints to STDOUT');

		$this->addOption(
			self::COMPRESSION,
			null,
			InputOption::VALUE_NONE,
			'Remove white space from output',
		);
		$this->addOption(
			self::ROOT,
			null,
			InputOption::VALUE_REQUIRED,
			'Root directory. If not set uses folder of schema',
		);

		$this->setCommand($this->commandClass);
	}

	public function transformInput(InputInterface $input, OutputInterface $output): InputInterface
	{
		/** @var string $root */
		$root = $input->getOption(self::ROOT) ?? '';
		$root = rtrim($root, DIRECTORY_SEPARATOR);
		/** @var string $schema */
		$schema = $input->getArgument(self::SCHEMA);
		if (!$root) {
			$root = dirname($schema);
			$schema = basename($schema);
		}
		$schema = $this->resolveSchema($schema, $root);

		$compress = null;
		if ($input->getOption(self::COMPRESSION)) {
			$compress = true;
		}

		/** @var string|null $outputFile */
		$outputFile = $input->getArgument(self::OUTPUT);
		return new SchemaInput(
			$schema,
			$outputFile,
			$compress,
			$root,
		);
	}

	protected function resolveSchema(string $schema, string $root): string
	{
		$root = rtrim($root, DIRECTORY_SEPARATOR);
		if (!file_exists($schema)) {
			$pathsToCheck = [
				realpath(getcwd() . DIRECTORY_SEPARATOR . $schema),
				$root . DIRECTORY_SEPARATOR . $schema,
			];
			$found = false;
			foreach ($pathsToCheck as $path) {
				if (file_exists((string)$path)) {
					$schema = (string)$path;
					$found = true;
					break;
				}
			}
			if (!$found) {
				throw new \RuntimeException('Can\'t read file: ' . $path);
			}
		}
		elseif (!str_starts_with($schema, '/')) {
			$schema = getcwd() . DIRECTORY_SEPARATOR . $schema;
		}

		if (!$root) {
			$root = dirname($schema);
		}
		return str_replace($root . DIRECTORY_SEPARATOR, '', $schema);
	}
}
