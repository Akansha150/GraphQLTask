<?php
namespace Sigma\StockUpdateMutation\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;

class StockUpdate implements ResolverInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * StockUpdate constructor.
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        ProductRepositoryInterface $productRepository
    ) {
        $this->productRepository = $productRepository;
    }

    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $sku = $args['sku'];
        $quantity = $args['quantity'];
        
        try {
            $product = $this->productRepository->get($sku);
            $product->setStockData(['qty' => $quantity]);
            $this->productRepository->save($product);
            
            //Get Updated Product data
            $updatedProduct = $this->productRepository->get($sku);
            $updatedQuantity = $updatedProduct->getExtensionAttributes()->getStockItem()->getQty();

            return [
                'message' => "Stock Updated Successfully",
                'sku' => $sku,
                'updated_quantity' => $updatedQuantity
            ];
        } catch (\LocalizedException $e) {
            throw new LocalizedException(__("Error creating product: " . $e->getMessage()));
        }
    }
}
