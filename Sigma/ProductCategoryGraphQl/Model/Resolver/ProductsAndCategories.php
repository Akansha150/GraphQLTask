<?php
namespace Sigma\ProductCategoryGraphQl\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Product;

class ProductsAndCategories implements ResolverInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * ProductsAndCategories constructor.
     * @param ProductRepositoryInterface $productRepository
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        CategoryRepositoryInterface $categoryRepository
    ) {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $sku = $args['sku'];

        try {
            // Get product by SKU
            $product = $this->productRepository->get($sku);

            // Get product details
            $productDetails = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'sku' => $product->getSku(),
                'price' => $product->getPrice(),
            ];

            // Get categories associated with the product
            $categoryIds = $product->getCategoryIds();
            $categories = [];

            foreach ($categoryIds as $categoryId) {
                $category = $this->categoryRepository->get($categoryId);
                $categories[] = [
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                ];
            }

            // Prepare response
            $response = [
                'id' => $productDetails['id'],
                'name' => $productDetails['name'],
                'sku' => $productDetails['sku'],
                'price' => $productDetails['price'],
                'categories' => $categories,
            ];

            return $response;
        } catch (\Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
        }
    }
}
