<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types = 1);

namespace Sigma\OrderPlaceOverride\Model\Resolver;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Helper\Error\AggregateExceptionMessageFormatter;
use Magento\QuoteGraphQl\Model\Cart\GetCartForCheckout;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\QuoteGraphQl\Model\Cart\PlaceOrder as PlaceOrderModel;
use Magento\QuoteGraphQl\Model\Cart\PlaceOrderMutexInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\QuoteGraphQl\Model\Resolver\PlaceOrder;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Resolver for placing order after payment method has already been set
 */
class OrderPlaceCustom extends PlaceOrder
{

    public const XML_PATH_MAX_ALLOWED_QUANTITY = 'orderplaceoverride/general/min_allowed_quantity';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var GetCartForCheckout
     */
    private $getCartForCheckout;

    /**
     * @var PlaceOrderModel
     */
    private $placeOrder;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var AggregateExceptionMessageFormatter
     */
    private $errorMessageFormatter;

    /**
     * @var PlaceOrderMutexInterface
     */
    private $placeOrderMutex;

    /**
     * OrderPlaceCustom constructor.
     * @param GetCartForCheckout $getCartForCheckout
     * @param PlaceOrderModel $placeOrder
     * @param OrderRepositoryInterface $orderRepository
     * @param AggregateExceptionMessageFormatter $errorMessageFormatter
     * @param ScopeConfigInterface $scopeConfig
     * @param PlaceOrderMutexInterface||null $placeOrderMutex
     */
    public function __construct(
        GetCartForCheckout $getCartForCheckout,
        PlaceOrderModel $placeOrder,
        OrderRepositoryInterface $orderRepository,
        AggregateExceptionMessageFormatter $errorMessageFormatter,
        ScopeConfigInterface $scopeConfig,
        ?PlaceOrderMutexInterface $placeOrderMutex = null
    ) {
        $this->getCartForCheckout = $getCartForCheckout;
        $this->placeOrder = $placeOrder;
        $this->orderRepository = $orderRepository;
        $this->errorMessageFormatter = $errorMessageFormatter;
        $this->scopeConfig = $scopeConfig;
        $this->placeOrderMutex = $placeOrderMutex ?: ObjectManager::getInstance()->get(PlaceOrderMutexInterface::class);
    }

/**
 * @inheritdoc
 */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (empty($args['input']['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }

        return $this->placeOrderMutex->execute(
            $args['input']['cart_id'],
            \Closure::fromCallable([$this, 'run']),
            [$field, $context, $info, $args]
        );
    }

/**
 * Place Order
 *
 * @param Field $field
 * @param ContextInterface $context
 * @param ResolveInfo $info
 * @param array||null $args
 */

    private function run(Field $field, ContextInterface $context, ResolveInfo $info, array $args)
    {
            $maskedCartId = $args['input']['cart_id'];
            $userId = (int)$context->getUserId();
            $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
    
        try {
            $cart = $this->getCartForCheckout->execute($maskedCartId, $userId, $storeId);
    
            $orderId = $this->placeOrder->execute($cart, $maskedCartId, $userId);
            $order = $this->orderRepository->get($orderId);
        } catch (LocalizedException $e) {
            throw $this->errorMessageFormatter->getFormatted(
                $e,
                __('Unable to place order: A server error stopped your order from being placed. ' .
                    'Please try to place your order again'),
                'Unable to place order',
                $field,
                $context,
                $info
            );
        }
            
            //custom login to check total cart quantity
            $minvalue = $this->scopeConfig->getValue(
                self::XML_PATH_MAX_ALLOWED_QUANTITY,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
            $cartitems = $cart->getItemsQty();
        if ($cartitems < $minvalue) {
            throw new GraphQlInputException(__(
                "Quantity should be greater than 10. The order was not placed. Please try again later."
            ));
        }
    
            return [
                'order' => [
                    'order_number' => $order->getIncrementId(),
                    // @deprecated The order_id field is deprecated, use order_number instead
                    'order_id' => $order->getIncrementId(),
                ],
            ];
    }
}
