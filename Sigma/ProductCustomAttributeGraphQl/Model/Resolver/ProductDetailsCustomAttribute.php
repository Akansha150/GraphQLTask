<?php
namespace Sigma\ProductCustomAttributeGraphQl\Model\Resolver;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\InputException;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTimeFormatterInterface;
use Magento\Directory\Model\CurrencyFactory;

class ProductDetailsCustomAttribute implements ResolverInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Product
     */
    private $product;

    /**
     * @var CollectionFactory
     */
    private $categoryCollection;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var DateTimeFormatterInterface
     */
    private $dateTimeFormatter;

    /**
     * @var CurrencyFactory
     */
    private $currencyFactory;

    /**
     * ProductDetailsCustomAttribute constructor.
     * @param ProductRepositoryInterface $productRepository
     * @param Product $product
     * @param CollectionFactory $categoryCollection
     * @param StoreManagerInterface $storeManager
     * @param DateTimeFormatterInterface $dateTimeFormatter
     * @param CurrencyFactory $currencyFactory
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        Product $product,
        CollectionFactory $categoryCollection,
        StoreManagerInterface $storeManager,
        DateTimeFormatterInterface $dateTimeFormatter,
        CurrencyFactory $currencyFactory
    ) {
        $this->productRepository = $productRepository;
        $this->product = $product;
        $this->categoryCollection = $categoryCollection;
        $this->storeManager = $storeManager;
        $this->dateTimeFormatter = $dateTimeFormatter;
        $this->currencyFactory = $currencyFactory;
    }

    /**
     * Resolver
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws GraphQlInputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Exception
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $sku = $args['sku'];

        if (empty($sku)) {
            throw new GraphQlInputException(__("Sku is required field, Please add sku"));
        }
        try {
            $product = $this->productRepository->get($sku);
            $materials = $result = $product->getResource()->getAttribute('material')->getFrontend()->getValue($product);
            $imageUrl = $this->getImageUrl($product->getData('brandlogo'));
            $date = $this->formatDate($product->getData('launch'));
            $price = $this->formatPrice($product->getPrice());
            $weight = $product->getAttributeText('weight');
            $belt_material = $product->getResource()->getAttribute('belt_material')->getFrontend()->getValue($product);

            return [
                'sku' => $sku,
                'id' => $product->getId(),
                'name' => $product->getName(),
                'price' => $price,
                'material' => $materials,
                'brand_logo' => $imageUrl,
                'launch_date' => $date,
                'belt_material' => $belt_material,
                'weight' => $weight
            ];

        } catch (NoSuchEntityException $e) {
            throw new NoSuchEntityException(__('Product with SKU %1 does not exist.', $sku));
        } catch (LocalizedException $e) {
            throw new LocalizedException(__('Failed to fetch product details: %1', $e->getMessage()));
        } catch (\Exception $e) {
            throw new \Exception(__('Failed to fetch product details: %1', $e->getMessage()));
        }
    }

    /**
     * Format date
     *
     * @param Date $date
     * @return string
     */
    private function formatDate($date)
    {
        $dateTime = new \DateTime($date);
        $day = $dateTime->format('j');
        $month = $dateTime->format('M');
        $year = $dateTime->format('Y');
        $suffix = $this->getDaySuffix($day);
        return $day . $suffix . ' ' . $month . ' ' . $year;
    }

    /**
     * Day Suffix
     *
     * @param Date $day
     * @return string
     */
    private function getDaySuffix($day)
    {
        if ($day % 10 == 1 && $day != 11) {
            return 'st';
        } elseif ($day % 10 == 2 && $day != 12) {
            return 'nd';
        } elseif ($day % 10 == 3 && $day != 13) {
            return 'rd';
        } else {
            return 'th';
        }
    }

    /**
     * Format Price
     *
     * @param Float $price
     * @return string
     */
    private function formatPrice($price)
    {
        $currencyCode = $this->storeManager->getStore()->getCurrentCurrency()->getCode();
        $currencySymbol = $this->currencyFactory->create()->load($currencyCode)->getCurrencySymbol();
        return $currencySymbol . number_format((float)$price, 2);
    }

    /**
     * ImagePath
     *
     * @param String $relativeImagePath
     * @return string
     */
    private function getImageUrl($relativeImagePath)
    {
        return $this->storeManager->getStore()
            ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $relativeImagePath;
    }
}
