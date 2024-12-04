<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

final class InlineTest extends ServiceTestCase
{
	public static function schemas(): \Generator
	{
		foreach (new \DirectoryIterator(dirname(__FILE__) . '/inlined-schemas') as $fileInfo) {
			if ($fileInfo->isDot()) continue;
			if (!file_exists($fileInfo->getPathname() . '/schema.json')) {
				$schemaFile = null;
				foreach (new \DirectoryIterator($fileInfo->getPathname()) as $schemaFileInfo) {
					if ($schemaFileInfo->isDot() || $schemaFileInfo->isDir()) {
						continue;
					}
					if (str_contains($schemaFileInfo->getFilename(), '.dist.')) {
						continue;
					}
					$schemaFile = $schemaFileInfo->getFilename();
				}
				if (!$schemaFile) {
					self::fail('No schema input file found for test: ' . $fileInfo->getBasename());
				}
				yield $fileInfo->getBasename() => [$fileInfo->getPathname(), $schemaFile];
			}
			else {
				yield $fileInfo->getBasename() => [$fileInfo->getPathname()];
			}

		}
	}

	#[DataProvider('schemas')]
	public function testInline(string $root, string $schemaFile = 'schema.json'): void
	{
		$service = $this->createInlineService($root);

		$document = $service->inline($root . '/' . $schemaFile);

		$this->assertDocumentResult($root, $document);
	}
}
