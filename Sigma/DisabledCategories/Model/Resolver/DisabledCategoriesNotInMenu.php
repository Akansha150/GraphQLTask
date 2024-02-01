<?php

namespace Sigma\DisabledCategories\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\GraphQl\Config\Element\Field;

class DisabledCategoriesNotInMenu implements ResolverInterface
{
    /**
     * @var CollectionFactory
     */
    protected $_categoryCollection;

    /**
     * DisabledCategoriesNotInMenu constructor.
     * @param CollectionFactory $categoryCollection
     */
    public function __construct(
        CollectionFactory $categoryCollection
    ) {
        $this->_categoryCollection = $categoryCollection;
    }

    /**
     * Disabled Category Collection
     *
     * @param Field $field
     * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $categories = $this->_categoryCollection->create()
            ->addAttributeToSelect('*')
            ->addFieldToFilter('is_active', 0) //disabled categories
            ->addFieldToFilter('include_in_menu', 0); // not include_in_menu

        $categoryNames = [];
        foreach ($categories as $category) {
            $categoryNames[] = [
                'name' => $category->getName(),
                'description' => $category->getDescription(),
                'imageUrl' => $category->getImageUrl()
            ];
        }
        return $categoryNames;
    }
}
