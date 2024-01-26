<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Command;

use JsonPointer\DocumentFactory;
use Stefna\OpenApiBundler\BundleConfig;
use Stefna\OpenApiBundler\Input\BundleInput;
use Stefna\OpenApiBundler\Service\BundleService;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class BundleCommand
{
	public function __construct(
		private ?BundleConfig $config = null
	) {}

	public function __invoke(BundleInput $input, OutputInterface $output): int
	{
		$service = new BundleService(new DocumentFactory($input->root . DIRECTORY_SEPARATOR));

		$outputFile = $input->outputFile;
		if (!$outputFile && $this->config?->output) {
			$outputFile = $this->config->output;
		}

		if ($outputFile) {
			$output->writeln('Bundling: ' . $input->schema);
		}
		$content = $service->bundle($input->schema);

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
