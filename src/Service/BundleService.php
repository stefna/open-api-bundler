<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Service;

use JsonPointer\Document;
use JsonPointer\DocumentFactory;
use JsonPointer\Reference;
use JsonPointer\WritableDocument;
use Stefna\OpenApiBundler\Enums\SchemaType;

final class BundleService
{
	/** @var array<string, array<string, mixed>> */
	private array $components = [];

	public function __construct(
		private readonly DocumentFactory $documentFactory,
	) {}

	public function bundle(string $schemaFile): Document
	{
		$rootDocument = $this->documentFactory->createFromFile($schemaFile);

		// register inline schemas to avoid recursion and errors
		// we don't fetch the schemas to allow resolving references in them
		$this->preRegisterSchemas($rootDocument);

		$this->processDocument($rootDocument);

		// update the schemas on the original document
		$this->updateSchemas($rootDocument);

		return $rootDocument;
	}

	private function processDocument(
		Document&WritableDocument $document,
		?string $referenceRoot = null,
	): Document&WritableDocument {
		foreach ($this->findReferences($document, $referenceRoot) as $ref => $documentPaths) {
			$reference = Reference::fromString($ref);
			$type = $this->resolveSchemaType($documentPaths);
			// update $ref to new ref
			foreach ($documentPaths as $path) {
				$document->set($path, ['$ref' => match ($type) {
					SchemaType::Schema => '#/components/schemas/' . $reference->getName(),
					SchemaType::RequestBodies => '#/components/requestBodies/' . $reference->getName(),
				}]);
			}

			if (isset($this->components[$type->name][$reference->getName()])) {
				continue;
			}

			// reserve schema to avoid infinite recursion
			$this->components[$type->name][$reference->getName()] = true;
			$this->components[$type->name][$reference->getName()] = $this->processDocument(
				$this->documentFactory->createFromReference($reference),
				dirname($reference->getUri()),
			)->get();
		}
		return $document;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function processInternalSchemas(SchemaType $type, WritableDocument&Document $document): array
	{
		$path = match ($type) {
			SchemaType::Schema => '/components/schemas',
			SchemaType::RequestBodies => '/components/requestBodies',
		};
		$schemas = $this->components[$type->name] ?? [];
		if ($document->has($path)) {
			/** @var string $key */
			// @phpstan-ignore-next-line
			foreach ($document->get($path) as $key => $schema) {
				$schemas[$key] = $schema;
			}
		}
		return $schemas;
	}

	private function preRegisterSchemas(WritableDocument&Document $document): void
	{
		foreach (SchemaType::cases() as $type) {
			$this->components[$type->name] = $this->processInternalSchemas($type, $document);
		}
	}

	private function updateSchemas(WritableDocument&Document $document): void
	{
		foreach (SchemaType::cases() as $type) {
			$pathKey = match ($type) {
				SchemaType::Schema => 'schemas',
				SchemaType::RequestBodies => 'requestBodies',
			};
			$schemas = $this->processInternalSchemas($type, $document);
			$path = '/components/' . $pathKey;
			if ($document->has($path)) {
				$document->set($path, $schemas);
			}
			else {
				$components = $document->get('/components');
				$components[$pathKey] = $schemas;
				$document->set('/components', $components);
			}
		}
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
