<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Command;

use JsonPointer\DocumentFactory;
use JsonPointer\ReferenceResolver\FileReferenceResolver;
use JsonPointer\ReferenceResolver\ReferenceResolver;
use JsonPointer\ReferenceResolver\ReferenceResolverCollection;
use Stefna\OpenApiBundler\SchemaConfig;
use Stefna\OpenApiBundler\Input\SchemaInput;
use Stefna\OpenApiBundler\Service\BundleService;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class BundleCommand
{
	private ReferenceResolverCollection $referenceResolver;

	public function __construct(
		private ?SchemaConfig $config = null,
		?ReferenceResolver $referenceResolver = null,
	) {
		if (!$referenceResolver instanceof ReferenceResolverCollection) {
			$this->referenceResolver = new ReferenceResolverCollection();
			if ($referenceResolver) {
				$this->referenceResolver->addResolver($referenceResolver);
			}
		}
		else {
			$this->referenceResolver = $referenceResolver;
		}
	}

	public function __invoke(SchemaInput $input, OutputInterface $output): int
	{
		$this->referenceResolver->addResolver(new FileReferenceResolver($input->root . DIRECTORY_SEPARATOR));
		$service = new BundleService($this->referenceResolver);

		$outputFile = $input->outputFile;
		if (!$outputFile && $this->config?->output) {
			$outputFile = $this->config->output;
		}

		if ($outputFile) {
			$output->writeln('Bundling: ' . $input->schema);
		}
		$content = $service->bundle($input->root . DIRECTORY_SEPARATOR . $input->schema);

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
