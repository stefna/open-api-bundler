<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Service;

use JsonPointer\BasicDocument;
use JsonPointer\Document;
use JsonPointer\DocumentFactory;
use JsonPointer\Reference;
use JsonPointer\ReferenceResolver\ReferenceResolver;
use JsonPointer\WritableDocument;
use Stefna\OpenApiBundler\Enums\SchemaType;
use Stefna\OpenApiBundler\Merger\AllOfMerger;

final class InlineService
{
	/** @var array<string, array<string, mixed>> */
	private array $components = [];
	/** @var list<string> */
	private array $allOfPaths = [];

	private readonly DocumentFactory $documentFactory;

	public function __construct(
		private readonly ReferenceResolver $referenceResolver,
	) {
		$this->documentFactory = new DocumentFactory();
	}

	public function inline(string $schemaFile): Document
	{
		$this->components = [];
		$this->allOfPaths = [];
		$document = $this->processDocument(
			$this->documentFactory->createFromFile($schemaFile),
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
		?Reference $rootReference = null,
	): Document&WritableDocument {
		foreach ($this->findReferences($document, $rootReference) as $ref => $documentPaths) {
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
					$subDocument, // @phpstan-ignore argument.type
				);
			}
			else {
				$referenceDocument = BasicDocument::fromDocument($this->referenceResolver->resolve($reference));
			}
			$schemaId = $referenceDocument->get()['$id'] ?? $refName;
			// reserve schema to avoid infinite recursion
			$this->components[$type->name][$refName] = $schemaId;
			$this->components[$type->name][$refName] = $this->processDocument(
				$referenceDocument,
				$reference,
			)->get();
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
	private function findReferences(WritableDocument&Document $document, ?Reference $rootReference = null): array
	{
		$rootPath = $rootReference?->getRoot();
		$invertedRefs = [];
		foreach ($document->findAllReferences() as $refPath => $ref) {
			if ($ref[0] === '#') {
				$invertedRefs[$ref] ??= [];
				$invertedRefs[$ref][] = $refPath;
				continue;
			}
			if ($ref[0] !== '@') {
				$ref = $rootPath ? $rootPath . '/' . $ref : $ref;
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
}
