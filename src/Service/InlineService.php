<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Service;

use JsonPointer\Document;
use JsonPointer\DocumentFactory;
use JsonPointer\Reference;
use JsonPointer\WritableDocument;
use Stefna\OpenApiBundler\Enums\SchemaType;

final class InlineService
{
	/** @var array<string, array<string, mixed>> */
	private array $components = [];

	public function __construct(
		private readonly DocumentFactory $documentFactory,
	) {}

	public function inline(string $schemaFile): Document
	{
		return $this->processDocument(
			$this->documentFactory->createFromFile($schemaFile),
			$this->documentFactory->findRoot($schemaFile),
		);
	}

	private function processDocument(
		Document&WritableDocument $document,
		?string $referenceRoot = null,
	): Document&WritableDocument {
		foreach ($this->findReferences($document, $referenceRoot) as $ref => $documentPaths) {
			$reference = Reference::fromString($ref);
			$type = $this->resolveSchemaType($documentPaths);

			if (isset($this->components[$type->name][$reference->getName()]) &&
				$this->components[$type->name][$reference->getName()]
			) {
				$schema = $this->components[$type->name][$reference->getName()];
				if (is_string($schema)) {
					$schema = ['$ref' => $schema];
				}
				foreach ($documentPaths as $path) {
					$document->set($path, $schema);
				}
				continue;
			}

			if ($reference->isInternal()) {
				$subDocument = $document->get($reference->getPath());
				if (!is_array($subDocument)) {
					throw new \BadMethodCallException('Failed to resolve internal model properly');
				}
				$referenceDocument = $this->documentFactory->createFromArray(
					$reference->getName(),
					$subDocument,
				);
			}
			else {
				$referenceDocument = $this->documentFactory->createFromReference($reference);
			}
			// @phpstan-ignore offsetAccess.nonOffsetAccessible
			$schemaId = $referenceDocument->get()['$id'] ?? $reference->getName();
			// reserve schema to avoid infinite recursion
			$this->components[$type->name][$reference->getName()] = $schemaId;
			$this->components[$type->name][$reference->getName()] = $this->processDocument(
				$referenceDocument,
				$this->documentFactory->findRoot($reference),
			)->get();
			// @phpstan-ignore offsetAccess.nonOffsetAccessible
			$this->components[$type->name][$reference->getName()]['$id'] = $schemaId;

			// update $ref to new ref
			foreach ($documentPaths as $path) {
				$componentSchema = $this->components[$type->name][$reference->getName()];
				if (str_contains($path, '/allOf/')) {
					$path = substr($path, 0, (int)strpos($path, '/allOf/'));
					$componentSchema = $this->mergeAllOf($path . '/allOf', $document);
				}

				$document->set($path, $componentSchema);
			}
		}
		return $document;
	}

	/**
	 * @return array<string, list<string>>
	 */
	private function findReferences(WritableDocument&Document $document, ?string $referenceRoot = null): array
	{
		$invertedRefs = [];
		foreach ($document->findAllReferences() as $refPath => $ref) {
			if ($ref[0] === '#') {
				$invertedRefs[$ref] ??= [];
				$invertedRefs[$ref][] = $refPath;
				continue;
			}
			if ($referenceRoot) {
				$ref = $this->virtualPath($referenceRoot . '/' . $ref);
			}
			$invertedRefs[$ref] ??= [];
			$invertedRefs[$ref][] = $refPath;
		}

		return $invertedRefs;
	}

	/**
	 * @param list<string> $documentPaths
	 */
	public function resolveSchemaType(array $documentPaths): SchemaType
	{
		foreach ($documentPaths as $path) {
			if (str_ends_with($path, 'requestBody')) {
				return SchemaType::RequestBodies;
			}
		}
		return SchemaType::Schema;
	}

	private function virtualPath(string $path): string
	{
		$path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
		$parts = array_filter(explode(DIRECTORY_SEPARATOR, $path));
		$absolutes = [];
		foreach ($parts as $part) {
			if ('.' === $part) {
				continue;
			}
			if ('..' === $part) {
				array_pop($absolutes);
				continue;
			}
			$absolutes[] = $part;
		}
		return (str_starts_with($path, DIRECTORY_SEPARATOR) ? DIRECTORY_SEPARATOR : '') . implode(DIRECTORY_SEPARATOR, $absolutes);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function mergeAllOf(string $allOfPath, WritableDocument&Document $document): array
	{
		/** @var list<array{"$ref"?: string, ...}> $allOfDocument */
		$allOfDocument = $document->get($allOfPath);
		var_dump($allOfDocument);
		$mergedSchema = [];
		foreach ($allOfDocument as $part) {
			if (isset($part['$ref'])) {
				$reference = Reference::fromString($part['$ref']);
				foreach ($this->components as $components) {
					if (isset($components[$reference->getName()]) && is_array($components[$reference->getName()])) {
						$mergedSchema = array_merge($mergedSchema, $components[$reference->getName()]);
					}
				}
			}
			else {
				$mergedSchema = array_merge($mergedSchema, $part);
			}
		}
		// remove original id
		unset($mergedSchema['$id']);
		return $mergedSchema;
	}
}
