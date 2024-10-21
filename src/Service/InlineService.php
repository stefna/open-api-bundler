<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Service;

use JsonPointer\Document;
use JsonPointer\DocumentFactory;
use JsonPointer\Reference;
use JsonPointer\WritableDocument;
use Stefna\OpenApiBundler\Enums\SchemaType;
use Stefna\OpenApiBundler\Merger\AllOfMerger;

final class InlineService
{
	/** @var array<string, array<string, mixed>> */
	private array $components = [];
	/** @var list<string> */
	private array $allOfPaths = [];

	public function __construct(
		private readonly DocumentFactory $documentFactory,
	) {}

	public function inline(string $schemaFile): Document
	{
		$this->components = [];
		$this->allOfPaths = [];
		$document = $this->processDocument(
			$this->documentFactory->createFromFile($schemaFile),
			$this->documentFactory->findRoot($schemaFile),
		);
		$allOfPaths = array_unique($this->allOfPaths);
		if ($allOfPaths) {
			$merger = new AllOfMerger($document);
			foreach ($allOfPaths as $path) {
				$document->set($path, $merger->merge($path . '/allOf'));
			}
		}

		return $document;
	}

	private function processDocument(
		Document&WritableDocument $document,
		?string $referenceRoot = null,
	): Document&WritableDocument {
		foreach ($this->findReferences($document, $referenceRoot) as $ref => $documentPaths) {
			$reference = Reference::fromString($ref);
			$type = $this->resolveSchemaType($documentPaths);
			$refName = $type === SchemaType::Paths ? md5($reference->getPath()) : $reference->getName();

			if (
				isset($this->components[$type->name][$refName])
				&& $this->components[$type->name][$refName]
			) {
				$schema = $this->components[$type->name][$refName];
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
					$refName,
					$subDocument,
				);
			}
			else {
				$referenceDocument = $this->documentFactory->createFromReference($reference);
			}
			// @phpstan-ignore offsetAccess.nonOffsetAccessible
			$schemaId = $referenceDocument->get()['$id'] ?? $refName;
			// reserve schema to avoid infinite recursion
			$this->components[$type->name][$refName] = $schemaId;
			$this->components[$type->name][$refName] = $this->processDocument(
				$referenceDocument,
				$this->documentFactory->findRoot($reference),
			)->get();
			// @phpstan-ignore offsetAccess.nonOffsetAccessible
			$this->components[$type->name][$refName]['$id'] = $schemaId;

			// update $ref to new ref
			foreach ($documentPaths as $path) {
				if (str_contains($path, '/allOf/')) {
					$this->allOfPaths[] = substr($path, 0, (int)strpos($path, '/allOf/'));
				}
				$document->set($path, $this->components[$type->name][$refName]);
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
			if (str_starts_with($documentPaths[0], '/paths/') && substr_count($documentPaths[0], '/') === 2) {
				return SchemaType::Paths;
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
