<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Tests;

use PHPUnit\Framework\Assert;

final class SchemaFinder
{
	public static function schemas(string $folder): \Generator
	{
		foreach (new \DirectoryIterator($folder) as $fileInfo) {
			if ($fileInfo->isDot()) {
				continue;
			}
			if (file_exists($fileInfo->getPathname() . '/schema.json')) {
				yield $fileInfo->getBasename() => [$fileInfo->getPathname()];
				continue;
			}

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
				Assert::fail('No schema input file found for test: ' . $fileInfo->getBasename());
			}
			yield $fileInfo->getBasename() => [$fileInfo->getPathname(), $schemaFile];
		}
	}
}
