<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Sigma\AddtoCartOverride\Model\Resolver;

use Magento\Quote\Model\ResourceModel\Quote\Item\CollectionFactory;
use \Magento\QuoteGraphQl\Model\Resolver\UpdateCartItems;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\QuoteGraphQl\Model\CartItem\DataProvider\UpdateCartItems as  UpdateCartItemsProvider;
use Magento\Framework\GraphQl\Query\Resolver\ArgumentsProcessorInterface;

class UpdateSimpleProductsToCartOverride extends UpdateCartItems
{

    /**
     * @var CollectionFactory
     */
    public $quoteItemCollectionFactory;

    /**
     * @var GetCartForUser
     */
    private $getCartForUser;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var UpdateCartItemsProvider
     */
    private $updateCartItems;

    /**
     * @var ArgumentsProcessorInterface
     */
    private $argsSelection;

    /**
     * UpdateSimpleProductsToCartOverride constructor.
     * @param GetCartForUser $getCartForUser
     * @param CartRepositoryInterface $cartRepository
     * @param UpdateCartItemsProvider $updateCartItems
     * @param ArgumentsProcessorInterface $argsSelection
     * @param CollectionFactory $quoteItemCollectionFactory
     */
    public function __construct(
        GetCartForUser $getCartForUser,
        CartRepositoryInterface $cartRepository,
        UpdateCartItemsProvider $updateCartItems,
        ArgumentsProcessorInterface $argsSelection,
        CollectionFactory $quoteItemCollectionFactory
    ) {
        $this->getCartForUser = $getCartForUser;
        $this->cartRepository = $cartRepository;
        $this->updateCartItems = $updateCartItems;
        $this->argsSelection = $argsSelection;
        $this->quoteItemCollectionFactory = $quoteItemCollectionFactory;
        parent::__construct(
            $getCartForUser,
            $cartRepository,
            $updateCartItems,
            $argsSelection,
            $quoteItemCollectionFactory
        );
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $processedArgs = $this->argsSelection->process($info->fieldName, $args);

        if (empty($processedArgs['input']['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing.'));
        }

        $maskedCartId = $processedArgs['input']['cart_id'];

        if (empty($processedArgs['input']['cart_items'])
            || !is_array($processedArgs['input']['cart_items'])
        ) {
            throw new GraphQlInputException(__('Required parameter "cart_items" is missing.'));
        }

        $cartItems = $processedArgs['input']['cart_items'];
        // load the quote item
        $quoteItemCollection = $this->quoteItemCollectionFactory->create();

        // Determine the number of subarrays
        $numSubarrays = count($cartItems);

        for ($x = 0; $x < $numSubarrays; $x++) {
            $id = $cartItems[$x]['cart_item_id'];
            $quoteItem = $quoteItemCollection
                ->addFieldToSelect('product_type')
                ->addFieldToFilter('item_id', $id)
                ->getFirstItem();
            if ($quoteItem['product_type'] == 'simple') {
                $cartItems[$x]['quantity'] *= 2;
            }
        }
        
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $cart = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);

        try {
            $this->updateCartItems->processCartItems($cart, $cartItems);
            $this->cartRepository->save($cart);
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__($e->getMessage()), $e);
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__($e->getMessage()), $e);
        }

        return [
            'cart' => [
                'model' => $cart,
            ],
        ];
    }
}
