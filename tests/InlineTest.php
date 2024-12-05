<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

final class InlineTest extends ServiceTestCase
{
	public static function schemas(): \Generator
	{
		yield from SchemaFinder::schemas(dirname(__FILE__) . '/inlined-schemas');
	}

	#[DataProvider('schemas')]
	public function testInline(string $root, string $schemaFile = 'schema.json'): void
	{
		$service = $this->createInlineService($root);

		$document = $service->inline($root . '/' . $schemaFile);

		$this->assertDocumentResult($root, $document);
	}
}
