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

			$referenceDocument = $this->documentFactory->createFromReference($reference);
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
				$document->set($path, $this->components[$type->name][$reference->getName()]);
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
}
