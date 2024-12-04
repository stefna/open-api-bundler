<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Tests;

use JsonPointer\Document;
use JsonPointer\ReferenceResolver\FileReferenceResolver;
use JsonPointer\ReferenceResolver\PackageVendorReferenceResolver;
use JsonPointer\ReferenceResolver\ReferenceResolver;
use JsonPointer\ReferenceResolver\ReferenceResolverCollection;
use PHPUnit\Framework\TestCase;
use Stefna\OpenApiBundler\Service\BundleService;
use Stefna\OpenApiBundler\Service\InlineService;

abstract class ServiceTestCase extends TestCase
{
	protected function assertDocumentResult(string $root, Document $document): void
	{
		$resultFile = $root . '/schema.dist.json';
		if (!file_exists($resultFile)) {
			$this->fail('Result file does not exist');
		}
		$json = (string)json_encode($document->get(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		$this->assertJsonStringEqualsJsonFile($resultFile, $json);
	}

	protected function createBundleService(string $root, ReferenceResolver $resolver = null): BundleService
	{
		$collection = new ReferenceResolverCollection();
		if ($resolver) {
			$collection->addResolver($resolver);
		}
		$collection->addResolver($this->createPackageResolver());
		$collection->addResolver(new FileReferenceResolver($root . DIRECTORY_SEPARATOR));

		return new BundleService($collection);
	}

	protected function createInlineService(string $root, ReferenceResolver $resolver = null): InlineService
	{
		$collection = new ReferenceResolverCollection();
		if ($resolver) {
			$collection->addResolver($resolver);
		}
		$collection->addResolver($this->createPackageResolver());
		$collection->addResolver(new FileReferenceResolver($root . DIRECTORY_SEPARATOR));

		return new InlineService($collection);
	}

	protected function createPackageResolver(): ReferenceResolver
	{
		$root = dirname(__FILE__) . '/resolve-packages';
		$composerRoot = $root . '/vendor';
		$nodeRoot = $root . '/test_node_modules';

		$packageRefResolver = new PackageVendorReferenceResolver();
		$packageRefResolver->addVendorFolder('php', $composerRoot);
		$packageRefResolver->addVendorFolder('node', $nodeRoot);

		return $packageRefResolver;
	}
}
