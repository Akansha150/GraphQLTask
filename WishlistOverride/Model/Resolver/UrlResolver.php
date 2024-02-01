<?php

namespace Sigma\WishlistOverride\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Catalog\Model\ProductFactory;

class UrlResolver implements ResolverInterface
{
    /**
     * @var $_product
     */
    protected $_product;

    /**
     * UrlResolver constructor.
     * @param ProductFactory $_productloader
     */
    public function __construct(
        ProductFactory $_productloader
    ) {
        $this->_productloader = $_productloader;
    }

    /**
     * Product Url
     *
     * @param Field $field
     * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return string
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $product = $value['model'];
        $productId =(int) $product->getId();
        $_product = $this->_productloader->create()->load($productId);
        return $_product->getProductUrl();
    }
}
