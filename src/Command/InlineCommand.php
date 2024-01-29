<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Command;

use JsonPointer\DocumentFactory;
use Stefna\OpenApiBundler\SchemaConfig;
use Stefna\OpenApiBundler\Input\SchemaInput;
use Stefna\OpenApiBundler\Service\InlineService;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class InlineCommand
{
	public function __construct(
		private ?SchemaConfig $config = null
	) {}

	public function __invoke(SchemaInput $input, OutputInterface $output): int
	{
		$service = new InlineService(new DocumentFactory($input->root . DIRECTORY_SEPARATOR));

		$outputFile = $input->outputFile;
		if (!$outputFile && $this->config?->output) {
			$outputFile = $this->config->output;
		}

		if ($outputFile) {
			$output->writeln('Inlining: ' . $input->schema);
		}
		$content = $service->inline($input->schema);

		$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
		if (!($input->compress ?? $this->config?->compress)) {
			$flags |= JSON_PRETTY_PRINT;
		}
		$json = (string)json_encode($content->get(), $flags);

		if ($outputFile) {
			if (!str_ends_with($outputFile, '.json')) {
				$outputFile = rtrim($outputFile, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $input->schema;
			}
			$output->writeln('Writing output to: ' . $outputFile);
			file_put_contents($outputFile, $json);
		}
		else {
			$output->writeln($json);
		}

		return 0;
	}
}
