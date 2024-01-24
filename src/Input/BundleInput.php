<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Input;

use Circli\Console\AbstractInput;

final class BundleInput extends AbstractInput
{
	public function __construct(
		public string $schema,
		public ?string $outputFile,
		public ?bool $compress,
	) {}
}
