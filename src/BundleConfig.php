<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler;

final readonly class BundleConfig
{
	public function __construct(
		public ?string $output,
		public bool $compress = false,
	) {}
}
