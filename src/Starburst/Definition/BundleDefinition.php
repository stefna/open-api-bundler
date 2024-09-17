<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Starburst\Definition;

use Stefna\OpenApiBundler\Command\BundleCommand;
use Stefna\OpenApiBundler\Definition\SchemaDefinition;
use Stefna\OpenApiBundler\Input\SchemaInput;
use Stefna\OpenApiBundler\SchemaConfig;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class BundleDefinition extends SchemaDefinition
{
	public function __construct(
		private readonly SchemaConfig $config,
	) {
		parent::__construct('bundle', BundleCommand::class, true);
	}

	protected function configure(): void
	{
		parent::configure();
		$this->addCompletion('schema', function (CompletionInput $input, CompletionSuggestions $suggestions, callable $default) {
			if (!$this->config->root) {
				return;
			}
			$root = rtrim($this->config->root, DIRECTORY_SEPARATOR);

			$files = glob($root . DIRECTORY_SEPARATOR . '*.json');
			if (!$files) {
				return;
			}
			$files = array_map(fn (string $v) => ltrim(str_replace($this->config->root, '', $v), DIRECTORY_SEPARATOR), $files);
			$suggestions->suggestValues($files);
		});
	}

	public function transformInput(InputInterface $input, OutputInterface $output): InputInterface
	{
		/** @var string $root */
		$root = $input->getOption(self::ROOT) ?? $this->config->root;
		$root = rtrim($root, DIRECTORY_SEPARATOR);
		$schema = $input->getArgument(self::SCHEMA) ?? $this->config->defaultSchema;
		if (!is_string($schema)) {
			throw new \InvalidArgumentException('Missing schema argument');
		}
		$schema = $this->resolveSchema($schema, $root);
		if (!$root) {
			$root = dirname($schema);
		}

		$compress = $this->config->compress;
		if ($input->getOption(self::COMPRESSION)) {
			$compress = true;
		}

		/** @var string|null $outputFile */
		$outputFile = $input->getArgument(self::OUTPUT) ?? $this->config->output;
		return new SchemaInput(
			$schema,
			$outputFile,
			$compress,
			$root,
		);
	}
}
