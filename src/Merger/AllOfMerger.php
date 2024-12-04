<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Merger;

use JsonPointer\Document;
use JsonPointer\Reference;

final readonly class AllOfMerger
{
	public function __construct(
		private Document $document,
	) {}

	/**
	 * @return array<string, mixed>
	 */
	public function merge(string $allOfPath): array
	{
		/** @var list<array{"$ref"?: string, ...}> $allOfDocument */
		$allOfDocument = $this->document->get($allOfPath);
		$mergedSchema = [];
		foreach ($allOfDocument as $part) {
			if (isset($part['$ref'])) {
				$reference = Reference::fromString($part['$ref']);
				if (!$reference->isInternal()) {
					throw new \RuntimeException('Need to resolve external $ref before doing merge');
				}
				/** @var array<string, mixed>|null $part */
				$part = $this->document->get($reference->getPath());
				if (!is_array($part)) {
					throw new \BadMethodCallException('Failed to resolve internal reference: ' . $reference->getName());
				}
			}
			if (!$mergedSchema) {
				$mergedSchema = $part;
				continue;
			}
			/** @var array<string, mixed> $mergedSchema */
			$mergedSchema = $this->mergeArray($mergedSchema, $part);
		}
		// remove original id
		unset($mergedSchema['$id']);
		return $mergedSchema;
	}

	/**
	 * @param array<string, mixed> $root
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	private function mergeArray(array $root, array $input): array
	{
		foreach ($input as $key => $value) {
			if (!is_array($value) || !isset($root[$key])) {
				$root[$key] = $value;
				continue;
			}
			/** @var array<string, mixed> $value */

			if (!is_array($root[$key])) {
				throw new \RuntimeException('Can\'t merge array with ' . get_debug_type($root[$key]));
			}

			$rootValue = $root[$key];
			if (array_is_list($rootValue)) {
				$root[$key] = array_unique(array_merge($root[$key], $value));
				continue;
			}
			/** @var array<string, mixed> $rootValue */
			$root[$key] = $this->mergeArray($rootValue, $value);
		}

		return $root;
	}
}
