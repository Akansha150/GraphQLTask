<?php
namespace Sigma\ProductListSearch\Model\Resolver;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;

/**
 * Resolver for fetching product list based on provided filters.
 */
class ProductList implements ResolverInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var FilterBuilder
     */
    protected $filterBuilder;

    /**
     * @var FilterGroupBuilder
     */
    protected $filterGroupBuilder;

    /**
     * ProductList constructor.
     * @param ProductRepositoryInterface $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder
    ) {
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
    }

    /**
     * Resolve function to fetch product list based on provided arguments.
     *
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $category = isset($args['category']) ? $args['category'] : null;
        $minPrice = isset($args['minPrice']) ? $args['minPrice'] : null;
        $maxPrice = isset($args['maxPrice']) ? $args['maxPrice'] : null;
        $maxPrice = isset($args['maxPrice']) ? $args['maxPrice'] : null;
        $pageSize = isset($args['pageSize']) ? (int)$args['pageSize'] : 20;
        $currentPage = isset($args['currentPage']) ? (int)$args['currentPage'] : 1;

        $filterGroup = [];

        if ($category !== null) {
            $filterGroup[] = $this->filterBuilder
                ->setField('category_id')
                ->setValue($category)
                ->setConditionType('eq')
                ->create();
        }

        if ($minPrice !== null) {
            $filterGroup[] = $this->filterBuilder
                ->setField('price')
                ->setValue($minPrice)
                ->setConditionType('gteq')
                ->create();
        }

        if ($maxPrice !== null) {
            $filterGroup[] = $this->filterBuilder
                ->setField('price')
                ->setValue($maxPrice)
                ->setConditionType('lteq')
                ->create();
        }
        
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilters($filterGroup)
            ->setCurrentPage($currentPage)
            ->setPageSize($pageSize)
            ->create();

        $productList = $this->productRepository->getList($searchCriteria)->getItems();

        $products = [];
        foreach ($productList as $product) {
            $products[] = $product->getData();
        }

        $totalCount = count($productList);

        // Return results
        return [
            'items' => $products,
            'itemcount' => $totalCount,
        ];
    }
}
