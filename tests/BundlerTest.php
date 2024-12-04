<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

final class BundlerTest extends ServiceTestCase
{
	public static function schemas(): \Generator
	{
		foreach (new \DirectoryIterator(dirname(__FILE__) . '/bundled-schemas') as $fileInfo) {
			if($fileInfo->isDot()) continue;
			yield $fileInfo->getBasename() => [$fileInfo->getPathname()];
		}
	}

	#[DataProvider('schemas')]
	public function testBasic(string $root): void
	{
		$service = $this->createBundleService($root);

		$document = $service->bundle($root . '/schema.json');

		$this->assertDocumentResult($root, $document);
	}
}
