<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\ReferenceResolver;

use JsonPointer\Document;
use JsonPointer\DocumentFactory;
use JsonPointer\Exceptions\DocumentParseError;
use JsonPointer\Reference;
use JsonPointer\ReferenceResolver\ReferenceResolver;
use JsonPointer\ReferenceType;

final class LocalReferenceResolver implements ReferenceResolver
{
	public function __construct(
		private readonly DocumentFactory $documentFactory,
		public ?Document $rootDocument = null,
	) {}

	/**
	 * @phpstan-assert Document $this->rootDocument
	 */
	public function supports(Reference $reference): bool
	{
		return $this->rootDocument && $reference->type === ReferenceType::ComplexExternal && $reference->getPath() === '@local';
	}

	public function resolve(Reference $reference): Document
	{
		$this->supports($reference);
		$key = ltrim($reference->getUri(), '#');

		$tryKeys = [
			$key,
			'/components' . $key,
			'/components/schemas' . $key,
			'/components/responses' . $key,
			'/components/requestBodies' . $key,
			'/components/parameters' . $key,
		];

		foreach ($tryKeys as $tryKey) {
			if ($this->rootDocument->has($tryKey)) {
				/** @var array{"$id"?: string, id?: string, ...}|string $resolvedDocument */
				$resolvedDocument = $this->rootDocument->get($tryKey);
				if (!is_array($resolvedDocument)) {
					throw DocumentParseError::invalidContent($tryKey);
				}
				return $this->documentFactory->createFromArray($tryKey, $resolvedDocument);
			}
		}
		throw new DocumentParseError('Failed to resolve reference: ' . $reference->getUri());
	}
}
