<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Service;

use JsonPointer\BasicDocument;
use JsonPointer\Document;
use JsonPointer\DocumentFactory;
use JsonPointer\Exceptions\DocumentParseError;
use JsonPointer\Reference;
use JsonPointer\ReferenceResolver\ReferenceResolver;
use JsonPointer\ReferenceResolver\ReferenceResolverCollection;
use JsonPointer\ReferenceType;
use JsonPointer\WritableDocument;
use Stefna\OpenApiBundler\Enums\SchemaType;
use Stefna\OpenApiBundler\Merger\AllOfMerger;
use Stefna\OpenApiBundler\ReferenceResolver\LocalReferenceResolver;

final class InlineService
{
	/** @var array<string, array<string, mixed>> */
	private array $components = [];
	/** @var list<string> */
	private array $allOfPaths = [];

	private readonly DocumentFactory $documentFactory;
	private LocalReferenceResolver $localReferenceResolver;
	private readonly ReferenceResolverCollection $referenceResolver;

	public function __construct(
		ReferenceResolver $referenceResolver,
	) {
		$this->documentFactory = new DocumentFactory();
		if (!$referenceResolver instanceof ReferenceResolverCollection) {
			$this->referenceResolver = new ReferenceResolverCollection();
			$this->referenceResolver->addResolver($referenceResolver);
		}
		else {
			$this->referenceResolver = $referenceResolver;
		}
		$this->localReferenceResolver = new LocalReferenceResolver($this->documentFactory);
		$this->referenceResolver->addResolver($this->localReferenceResolver);
	}

	public function inline(string $schemaFile): Document
	{
		try {
			$schemaDocument = $this->documentFactory->createFromFile($schemaFile);
		}
		catch (DocumentParseError) {
			$schemaDocument = $this->referenceResolver->resolve(Reference::fromString($schemaFile));
			if (!$schemaDocument instanceof WritableDocument) {
				throw new DocumentParseError('Invalid document type:  ' . \get_class($schemaDocument) . 'expected WritableDocument');
			}
		}
		$this->localReferenceResolver->rootDocument = $schemaDocument;

		$this->components = [];
		$this->allOfPaths = [];
		$document = $this->processDocument($schemaDocument);
		$allOfPaths = array_unique($this->allOfPaths);
		if ($allOfPaths) {
			$merger = new AllOfMerger($document);
			/** @var string $path */
			foreach ($allOfPaths as $path) {
				try {
					/** @var array{"$id": string} $mergedSchema */
					$mergedSchema = $merger->merge($path . '/allOf');
				}
				catch (\JsonPointer\Exceptions\Reference) {
					continue;
				}
				if ($path === '') {
					$document = $this->documentFactory->createFromArray($document->getId(), $mergedSchema);
					break;
				}
				$document->set($path, $mergedSchema);
			}
		}

		return $document;
	}

	private function getRefName(Reference $reference, SchemaType $type): string
	{
		if ($type === SchemaType::Paths) {
			$refName = $reference->getPath();
			if ($reference->type === ReferenceType::ComplexExternal) {
				$refName .= $reference->getUri();
			}

			return md5($refName);
		}

		return $reference->getName();
	}

	private function processDocument(
		Document&WritableDocument $document,
		?Reference $rootReference = null,
	): Document&WritableDocument {
		$isAllOfDocument = ($document->get()['allOf'] ?? null) !== null;
		foreach ($this->findReferences($document, $rootReference) as $ref => $documentPaths) {
			$reference = Reference::fromString($ref);
			$type = $this->resolveSchemaType($documentPaths);
			$refName = $this->getRefName($reference, $type);

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
		if ($isAllOfDocument) {
			$merger = new AllOfMerger($document);
			$mergedSchema = $merger->merge('/allOf');
			return $this->documentFactory->createFromArray($document->getId(), $mergedSchema);
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

		return array_reverse($invertedRefs);
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
