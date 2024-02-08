<?php
namespace Sigma\AddSimpleProduct\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\UrlInterface;
use Magento\Framework\Exception\LocalizedException;
use \Magento\Catalog\Api\Data\ProductTierPriceExtensionFactory;
use \Magento\Catalog\Api\Data\ProductTierPriceInterfaceFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Api\SpecialPriceInterface;
use Magento\Catalog\Api\Data\SpecialPriceInterfaceFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;

class AddSimpleProduct implements ResolverInterface
{
    /**
     * @var SpecialPriceInterface
     */
    private $specialPrice;

    /**
     * @var SpecialPriceInterfaceFactory
     */
    private $specialPriceFactory;
    /**
     * @var TimezoneInterface
     */
    protected $timezone;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var ProductTierPriceInterfaceFactory
     */
    protected $tierPriceInterface;

    /**
     * @var ProductTierPriceExtensionFactory
     */
    public $tierPriceExtensionAttributesFactory;

    /**
     * AddSimpleProduct constructor.
     * @param ProductFactory $productFactory
     * @param ProductRepositoryInterface $productRepository
     * @param UrlInterface $urlBuilder
     * @param ProductTierPriceExtensionFactory $tierPriceExtensionAttributesFactory
     * @param ProductTierPriceInterfaceFactory $tierPriceInterface
     * @param SpecialPriceInterface $specialPrice
     * @param SpecialPriceInterfaceFactory $specialPriceFactory
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        ProductFactory $productFactory,
        ProductRepositoryInterface $productRepository,
        UrlInterface $urlBuilder,
        ProductTierPriceExtensionFactory $tierPriceExtensionAttributesFactory,
        ProductTierPriceInterfaceFactory $tierPriceInterface,
        SpecialPriceInterface $specialPrice,
        SpecialPriceInterfaceFactory $specialPriceFactory,
        TimezoneInterface $timezone
    ) {
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->urlBuilder = $urlBuilder;
        $this->tierPriceExtensionAttributesFactory = $tierPriceExtensionAttributesFactory;
        $this->tierPriceInterface = $tierPriceInterface;
        $this->specialPrice = $specialPrice;
        $this->specialPriceFactory = $specialPriceFactory;
        $this->timezone = $timezone;
    }

    /**
     * Create new product
     *
     * @param Field $field
     * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws GraphQlInputException
     * @throws LocalizedException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $inputArgs = $args['input'];

        // Access specific input arguments
        $name = $inputArgs['name'];
        $sku = $inputArgs['sku'];
        $status = $inputArgs['status'];
        $visibility = $inputArgs['visibility'];
        $price = $inputArgs['price'];
        $stockData = $args['input']['stock_data'];

        // Check if a product with the same name already exists
        if ($this->productExistsByName($name)) {
            throw new GraphQlInputException(__("Product with name '$name' already exists."));
        }

        // Check if a product with the same SKU already exists
        if ($this->productExistsBySku($sku)) {
            throw new GraphQlInputException(__("Product with SKU '$sku' already exists."));
        }

        if ($visibility < 1 || $visibility > 4) {
            throw new GraphQlInputException(__('Visibility should be between 1 and 4.'));
        }

        try {
            // Create a simple product
            $product = $this->productFactory->create();
            $product->setName($name);
            $product->setTypeId('simple');
            $product->setAttributeSetId(4);
            $product->setSku($sku);
            $product->setWebsiteIds([1]);
            $product->setStatus($status);
            $product->setVisibility($visibility);
            $product->setPrice($price);
            $product->setStockData([
                'is_in_stock' => $stockData['is_in_stock'],
                'qty' => $stockData['qty'],
            ]);

            if (!empty($stockData['use_config_manage_stock'])) {
                $product->setStockData([
                   'use_config_manage_stock' =>  $stockData['use_config_manage_stock']
                ]);
            }
            if (!empty($stockData['manage_stock'])) {
                $product->setStockData([
                    'manage_stock' =>  $stockData['use_config_manage_stock']
                ]);
            }
            if (!empty($stockData['min_sale_qty'])) {
                $product->setStockData([
                    'min_sale_qty' =>  $stockData['use_config_manage_stock']
                ]);
            }
            if (!empty($stockData['max_sale_qty'])) {
                $product->setStockData([
                    'max_sale_qty' =>  $stockData['use_config_manage_stock']
                ]);
            }
            $product->setUrlKey($name . $sku);

            if (!empty($args['input']['special_price'])) {
                $specialprice = $args['input']['special_price'];
                $Format = 'dd/mm/yy';
                if ($specialprice['special_from_date'] > $specialprice['special_to_date']) {
                    throw new GraphQlInputException(__("To date should be greater than form date"));
                }

                try {
                    $from = \DateTime::createFromFormat($Format, $specialprice['special_from_date']);
                    $to = \DateTime::createFromFormat($Format, $specialprice['special_to_date']);
                } catch (LocalizedException $e) {
                    throw new GraphQlInputException(__("Format should be dd/mm/yy"));
                }

                $product->setCustomAttributes([
                    'special_from_date' => $from,
                    'special_to_date' => $to,
                    'special_price' => $specialprice['special_price'],
                ]);
            }

            if (!empty($inputArgs['tier_prices'])) {

                $tierPrices[] = $inputArgs['tier_prices'];

                // Get the count of tier_prices array
                $tierCount = count($tierPrices);

                $data = [];

                for ($x = 0; $x < $tierCount; $x++) {
                    $currentTierPrice = $tierPrices[$x];

                    foreach ($currentTierPrice as $tierPriceData) {
                        $tierPrice = $this->tierPriceInterface->create();
                        $tierPrice->setCustomerGroupId($tierPriceData['customer_group_id']);
                        $tierPrice->setQty($tierPriceData['qty']);
                        $tierPrice->setValue($tierPriceData['value']);
                        $data[] = $tierPrice;

                    }
                }
                $product->setTierPrices($data);
            }
            // Save the product
            $product->save();
            // Get product URL
            $url = $this->urlBuilder->getUrl($product->getUrlKey());
            // Remove the trailing slash
            $cleanedUrl = rtrim($url, '/');
            $url = $cleanedUrl . ".html";

            // Return product details
            return [
                'product_id' => $product->getId(),
                'name' => $product->getName(),
                'sku' => $product->getSku(),
                'qty' => $stockData['qty'],
                'url' => $url,
            ];
        } catch (LocalizedException $e) {
            // Handle exception, log error, etc.
            throw new LocalizedException(__("Error creating product: " . $e->getMessage()));
        }
    }

    /**
     * Check Product
     *
     * @param string $name
     * @return bool
     */
    private function productExistsByName($name)
    {
        try {
            $product = $this->productRepository->get($name);
            return true;
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return false;
        }
    }

    /**
     * Check sku
     *
     * @param string $sku
     * @return bool
     */
    private function productExistsBySku($sku)
    {
        try {
            $product = $this->productRepository->get($sku);
            return true;
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return false;
        }
    }
}
