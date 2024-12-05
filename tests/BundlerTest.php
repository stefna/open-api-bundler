<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

final class BundlerTest extends ServiceTestCase
{
	public static function schemas(): \Generator
	{
		yield from SchemaFinder::schemas(dirname(__FILE__) . '/bundled-schemas');
	}

	#[DataProvider('schemas')]
	public function testBasic(string $root, string $schemaFile = 'schema.json'): void
	{
		$service = $this->createBundleService($root);

		$document = $service->bundle($root . '/' . $schemaFile);

		$this->assertDocumentResult($root, $document);
	}
}
