<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler;

final readonly class SchemaConfig
{
	public function __construct(
		public ?string $output,
		public bool $compress = false,
		public ?string $root = null,
		public ?string $defaultSchema = null,
	) {}
}
