<?php
/*
 * This file is part of the App Search Magento module.
 *
 * (c) Elastic
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Elastic\AppSearch\Framework\AppSearch\SearchAdapter\RequestExecutor\Facet;

use Elastic\AppSearch\Framework\AppSearch\SearchAdapter\RequestExecutor\ResponseProcessorInterface;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Request\BucketInterface;
use Elastic\AppSearch\Framework\AppSearch\SearchAdapter\RequestExecutor\Facet\Dynamic\AlgorithmInterface;
use Elastic\AppSearch\Framework\AppSearch\SearchAdapter\Response\DocumentCountResolver;

/**
 * Process facet from the App Search response.
 *
 * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
 *
 * @package   Elastic\AppSearch\Framework\AppSearch\SearchAdapter\RequestExecutor\Facet
 * @copyright 2019 Elastic
 * @license   Open Software License ("OSL") v. 3.0
 */
class ResponseProcessor implements ResponseProcessorInterface
{
    /**
     * @var DocumentCountResolver
     */
    private $documentCountResolver;

    /**
     * @var AlgorithmInterface[]
     */
    private $algorithms;

    /**
     * @var string
     */
    private $facetSuffix;

    /**
     * Constructor.
     *
     * @SuppressWarnings(PHPMD.LongVariable)
     *
     * @param DocumentCountResolver $documentCountResolver
     * @param AlgorithmInterface[]  $algorithms
     */
    public function __construct(
        DocumentCountResolver $documentCountResolver,
        array $algorithms,
        string $facetSuffix = ''
    ) {
        $this->documentCountResolver = $documentCountResolver;
        $this->algorithms            = $algorithms;
        $this->facetSuffix           = $facetSuffix;
    }

    /**
     * {@inheritDoc}
     */
    public function process(RequestInterface $request, array $response): array
    {
        $response['facets'] = array_merge(
            $this->parseFacets($request, $response['facets'] ?? []),
            $this->getDocumentCountFacet($response)
        );

        return $response;
    }

    /**
     * Parse result facets and convert into the format expected by the ResponseFactory.
     *
     * @param RequestInterface $request
     * @param array            $facetData
     *
     * @return array
     */
    private function parseFacets(RequestInterface $request, array $facetData): array
    {
        $facets = [];

        foreach ($facetData as $fieldFacets) {
            foreach ($fieldFacets as $facet) {
                $facets[$facet['name']] = $this->filterFacetValues($facet['data']);
            }
        }

        foreach ($request->getAggregation() as $bucket) {
            if (!isset($facets[$bucket->getName()])) {
                $facets[$bucket->getName()] = [];
            } elseif ($bucket->getType() == BucketInterface::TYPE_DYNAMIC) {
                $facets[$bucket->getName()] = $this->getRanges($bucket, $facets[$bucket->getName()]);
            }
        }

        return array_map([$this, 'parseFacetValues'], $facets);
    }

    /**
     * Parse facet values to match response format.
     *
     * @param array $values
     *
     * @return array
     */
    private function parseFacetValues(array $values): array
    {
        return array_map([$this, 'parseFacetValue'], $values);
    }

    /**
     * Remove facet values when the value is empty or the count is 0.
     *
     * @param array $values
     *
     * @return array
     */
    private function filterFacetValues(array $values): array
    {
        return array_filter($values, function ($value) {
            return $value['count'] > 0;
        });
    }

    /**
     * Parse facet value (mosty convert range into string).
     *
     * @param array $value
     *
     * @return array
     */
    private function parseFacetValue(array $value)
    {
        if (isset($value['from']) || isset($value['to'])) {
            $value = ['value' => $this->getRangeValueString($value), 'count' => $value['count']];
        }

        return $value;
    }

    /**
     * Get string representation for range facet value.
     *
     * @param array $value
     *
     * @return string
     */
    private function getRangeValueString(array $value): string
    {
        return sprintf("%s_%s", $value['from'] ?? '', $value['to'] ?? '');
    }

    /**
     * Use algorithm for range facet values post processing.
     *
     * @param BucketInterface $bucket
     * @param array           $data
     *
     * @return array
     */
    private function getRanges(BucketInterface $bucket, array $data)
    {
        if (isset($this->algorithms[$bucket->getMethod()])) {
            $data = $this->algorithms[$bucket->getMethod()]->getRanges($data);
        }

        return $data;
    }

    /**
     * Temporary fix to allow having search results count available through aggregations.
     *
     * @deprecated
     *
     * @param array $reponse
     *
     * @return array
     */
    private function getDocumentCountFacet(array $response): array
    {
        $docCounts = $this->documentCountResolver->getDocumentCount($response);
        $facetName = '_meta' . $this->facetSuffix;

        return [$facetName => [['value' => 'docs', 'count' => $docCounts]]];
    }
}
