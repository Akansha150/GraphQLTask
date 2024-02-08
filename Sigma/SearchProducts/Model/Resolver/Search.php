<?php

namespace Sigma\SearchProducts\Model\Resolver;

use Magento\Eav\Model\Config;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

class Search implements ResolverInterface
{
    /**
     * @var CollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var Option
     */
    private $eavOption;

    /**
     * @var Config
     */
    private $eavAttribute;

    /**
     * Search constructor.
     * @param CollectionFactory $productCollectionFactory
     * @param Option $eavOption
     * @param Config $eavAttribute
     */
    public function __construct(
        CollectionFactory $productCollectionFactory,
        Option $eavOption,
        Config $eavAttribute
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->eavOption = $eavOption;
        $this->eavAttribute = $eavAttribute;
    }

    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/urmi.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $query = $args['query'] ?? '';
        $filters = $args['filters'] ?? [];
        $brands = $filters['brandss'] ?? '';
        $logger->info($brands);
        $minPrice = $filters['price']['min'] ?? null;
        $maxPrice = $filters['price']['max'] ?? null;
        $sortDirection = $args['sort']['direction'] ?? null; // Get sort direction

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('*');

        // Apply name filter
        if (!empty($query)) {
            $collection->addAttributeToFilter('name', ['like' => '%' . $query . '%']);
        }

        if ($brands !== null) {
            $collection->addAttributeToFilter('brands', ['eq' => $brands]);
        }

        if ($minPrice !== null) {
            $collection->addFieldToFilter('price', ['gteq' => $minPrice]);
        }

        if ($maxPrice !== null) {
            $collection->addFieldToFilter('price', ['lteq' => $maxPrice]);
        }

        if ($sortDirection === 'DESC') {
            $collection->setOrder('price', 'DESC');
        } else {
            $collection->setOrder('price', 'ASC');
        }

        $products = [];
        foreach ($collection as $product) {
            $products[] = [
                'name' => $product->getName(),
                'price' => $product->getPrice(),
                'sku' => $product->getSku(),
                'brand' => $product->getCustomAttribute('brands')->getValue()
            ];
        }

        return $products;
    }
}
