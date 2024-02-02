<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types = 1);

namespace Sigma\CreateCustomer\Model\Resolver;

use Magento\Customer\Model\CustomerFactory;
use Magento\CustomerGraphQl\Model\Customer\GetCustomer;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\CustomerGraphQl\Model\Customer\ExtractCustomerData;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthenticationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use \Magento\Framework\Encryption\Encryptor;

/**
 * Customers field resolver, used for GraphQL request processing.
 */
class CreateCustomerCreate implements ResolverInterface
{
    /**
     * @var StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var CustomerFactory
     */
    public $customerFactory;

    /**
     * @var Encryptor
     */
    public $encryptor;

    /**
     * @var ExtractCustomerData
     */
    private $extractCustomerData;

    /**
     * CreateCustomerCreate constructor.
     * @param GetCustomer $getCustomer
     * @param ExtractCustomerData $extractCustomerData
     * @param StoreManagerInterface $storeManager
     * @param CustomerFactory $customerFactory
     * @param Encryptor $encryptor
     */
    public function __construct(
        GetCustomer $getCustomer,
        ExtractCustomerData $extractCustomerData,
        StoreManagerInterface $storeManager,
        CustomerFactory $customerFactory,
        Encryptor $encryptor
    ) {
        $this->storeManager = $storeManager;
        $this->customerFactory = $customerFactory;
        $this->encryptor = $encryptor;
    }

    /**
     * Create Customer account
     *
     * @param Field $field
     * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws GraphQlAuthenticationException
     * @throws GraphQlInputException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $customercreate = $this->createCustomer($args);
        return $customercreate;
    }

    /**
     * Validation
     *
     * @param array|null $args
     * @return array
     * @throws GraphQlAuthenticationException
     * @throws GraphQlInputException
     */
    public function createCustomer($args)
    {
        $email = $args['email'];
        $password = $args['password'];
        $confirmPassword = $args['confirm_password'];
        $firstname = $args['firstname'];
        $lastname = $args['lastname'];

        if (empty($firstname) || empty($lastname) || empty($email) || empty($password) || empty($confirmPassword)) {
            throw new GraphQlInputException(__("All fields are required."));
        }

        if ($password !== $confirmPassword) {
            throw new GraphQlAuthenticationException(__("Password and customer password do not match"));
        }

        // Check if the password contains a special character
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            throw new GraphQlInputException(__("Password must contain at least one special character."));
        }
        // Check if the password contains at least one digit
        if (!preg_match('/\d/', $password)) {
            throw new GraphQlInputException(__("Password must contain at least one digit."));
        }

        $store = $this->storeManager->getStore();
        $storeId = $store->getStoreId();
        $websiteId = $this->storeManager->getStore()->getWebsiteId();
        $customer = $this->customerFactory->create();
        $customer->setWebsiteId($websiteId);
        $customer->loadByEmail($email);// load customer by email address

        if ($customer->getId()) {
            throw new GraphQlAuthenticationException(__("Customer with email '.$email.' is already registered."));
        } else {
            try {
                $hashedPassword = $this->encryptor->getHash($password, true);
                //For guest customer create new cusotmer
                $customer->setWebsiteId($websiteId)
                    ->setStore($store)
                    ->setFirstname($firstname)
                    ->setLastname($lastname)
                    ->setEmail($email)
                    ->setPasswordHash($hashedPassword);
                $customer->save();
                return
                    ['CustomCustomerResolver' => [
                        'email' => $email,
                        'firstname' => $firstname,
                        'lastname' => $lastname,
                        'customer_id' => $customer->getId(),
                        'message' => 'Customer registered successfully'
                    ]];
            } catch (LocalizedException $e) {
                return
                    ['CustomCustomerResolver' => [
                        'message' => $e->getMessage()
                    ]];
            }
        }
    }
}
