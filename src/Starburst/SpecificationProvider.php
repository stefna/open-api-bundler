<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Starburst;

use JsonPointer\ReferenceResolver\PackageVendorReferenceResolver;
use JsonPointer\ReferenceResolver\ReferenceResolverCollection;

interface SpecificationProvider
{
	public function configureSpecificationResolvers(
		PackageVendorReferenceResolver $referenceResolver,
		ReferenceResolverCollection $resolverCollection,
	): void;
}
