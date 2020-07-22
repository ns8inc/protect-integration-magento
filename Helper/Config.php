<?php

namespace NS8\Protect\Helper;

use Throwable;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Cache\Type\Config as CacheTypeConfig;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\ObjectManager\ContextInterface;
use Magento\Store\Model\StoreManagerInterface;
use NS8\ProtectSDK\Config\Manager as SdkConfigManager;
use NS8\ProtectSDK\Security\Client as SecurityClient;
use NS8\ProtectSDK\Logging\Client as LoggingClient;
use NS8\Protect\Helper\Data\ProtectMetadata;
use Zend\Http\Client;
use Zend\Json\Decoder;
use Zend\Uri\Uri;

/**
 * Generic Helper/Utility class with convenience methods for common ops
 */
class Config extends AbstractHelper
{
    /**
     * The canonical name of the Magento extension/module name
     */
    const NS8_MODULE_NAME = 'NS8_Protect';

    /**
     * Default auth user value to utilize in configuration if no admin user is triggering the event
     */
    const DEFAULT_AUTH_USER = 'default';

    const METADATA_CONFIG_KEY = 'ns8/protect/metadata';

    const EMPTY_STORE_ID = 'NO_STORE_ID';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var LoggingClient
     */
    protected $loggingClient;

    /**
     * @var ModuleList
     */
    protected $moduleList;

    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var WriterInterface
     */
    protected $scopeWriter;

    /**
     * @var TypeListInterface
     */
    protected $typeList;

    /**
     * @var Pool
     */
    protected $cacheFrontendPool;

    /**
     * @var Uri
     */
    protected $uri;

    /**
     * @var StoreManager
     */
    protected $storeManager;

    /**
     * Default constructor
     *
     * @param Context $context
     * @param EncryptorInterface $encryptor
     * @param ModuleList $moduleList
     * @param Pool $cacheFrontendPool
     * @param ProductMetadataInterface $productMetadata
     * @param RequestInterface $request
     * @param ScopeConfigInterface $scopeConfig
     * @param TypeListInterface $typeList
     * @param Uri $uri
     * @param WriterInterface $scopeWriter
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        EncryptorInterface $encryptor,
        ModuleList $moduleList,
        Pool $cacheFrontendPool,
        ProductMetadataInterface $productMetadata,
        RequestInterface $request,
        ScopeConfigInterface $scopeConfig,
        TypeListInterface $typeList,
        Uri $uri,
        WriterInterface $scopeWriter,
        StoreManagerInterface $storeManager
    ) {
        $this->context = $context;
        $this->encryptor = $encryptor;
        $this->moduleList = $moduleList;
        $this->productMetadata = $productMetadata;
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
        $this->scopeWriter = $scopeWriter;
        $this->typeList = $typeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
        $this->uri = $uri;
        $this->storeManager = $storeManager;
        $this->loggingClient = new LoggingClient();
    }

    /**
     * Safely try to get an Apache environment variable.
     * @internal this is only for NS8 local developers in testing.
     * @param string $envVarName Variable name. Must be `NS8_CLIENT_URL` OR `NS8_PROTECT_URL`.
     * @return string|null In production, this should always return null.
     */
    public function getEnvironmentVariable(string $envVarName): ?string
    {
        $ret = $this->request->getServer($envVarName);

        if (!isset($ret)) {
            $this->loggingClient->debug('Failed to get environment variable "'.$envVarName.'"');
        }

        return $ret;
    }

    /**
     * Sets if a given store is active for NS8
     *
     * @param int|null $storeId The store ID
     * @param bool $isActive The value we want to set for the merchant's activation status
     * @return void
     */
    public function setIsMerchantActive(?int $storeId, bool $isActive): void
    {
        $token = $this->getAccessToken($storeId);
        if ($token === null) { // no need to set isActive when no token exists
            return;
        }
        $metadata = new ProtectMetadata($token, $isActive);
        $this->setStoreMetadata($storeId, $metadata);
    }

    /**
     * Returns if a given store is active for NS8
     *
     * @param int|null $storeId The store ID
     * @return bool
     */
    public function isMerchantActive(?int $storeId = null): bool
    {
        $metadata = $this->getStoreMetadata($storeId);
        return $metadata !== null ? $metadata->isActive : false;
    }

    /**
     * Retrieve the Protect access token for a given store.
     * @param int|null $storeId ID of the store to retrieve token for
     * @return string|null The NS8 Protect Access Token for the provided store.
     */
    public function getAccessToken(?int $storeId = null): ?string
    {
        $metadata = $this->getStoreMetadata($storeId);
        return $metadata !== null ? $metadata->token : null;
    }

    /**
     * Set the Protect access token for a given store.
     * @param int|null $storeId The store ID to set token for
     * @param string $token The Protect access token
     * @return void
     */
    public function setAccessToken(?int $storeId, string $token): void
    {
        $metadata = new ProtectMetadata($token, $this->isMerchantActive($storeId));
        $this->setStoreMetadata($storeId, $metadata);
    }

    /**
     * Gets ProtectMetadata instance for a given store
     * @param int|null $storeId The store ID to get metadata for
     * @return ProtectMetadata
     */
    public function getStoreMetadata(?int $storeId): ?ProtectMetadata
    {
        $metadatas = $this->getStoreMetadatas();
        $storeId = $storeId !== null ? $storeId : self::EMPTY_STORE_ID;
        return isset($metadatas[$storeId]) ? $metadatas[$storeId] : null;
    }

    /**
     * Get all metadatas, for all stores.
     * Structured as { [storeId: string]: ProtectMetadata };
     * @return ProtectMetadata[]
     */
    public function getStoreMetadatas(): array
    {
        $rawTokensJson = $this->encryptor->decrypt($this->scopeConfig->getValue(self::METADATA_CONFIG_KEY));
        $rawMetadatas = json_decode($rawTokensJson, true);
        return array_map(function ($rawMetadata) {
            return new ProtectMetadata(
                $rawMetadata["token"],
                $rawMetadata["isActive"]
            );
        }, (array) $rawMetadatas);
    }

    /**
     * Stores a Protect metadata object for a given store.
     *
     * @param int|null $storeId ID of the store to store metadata for
     * @param ProtectMetadata $metadata Metadata object to store
     * @return void
     */
    public function setStoreMetadata(?int $storeId, ProtectMetadata $metadata): void
    {
        $storeId = $storeId !== null ? $storeId : self::EMPTY_STORE_ID;
        $accessTokens = $this->getStoreMetadatas();
        $accessTokens[$storeId] = $metadata;
        $this->scopeWriter->save(self::METADATA_CONFIG_KEY, $this->encryptor->encrypt(json_encode($accessTokens)));
        $this->flushCaches();
    }

    /**
     * Clear relevant caches after configuration change has occurred.
     *
     * @return void
     */
    public function flushCaches(): void
    {
        $cacheTypesToClear = [
            'config',
            'layout',
            'block_html',
            'config_integration',
            'config_integration_api',
            'config_webservice'
        ];

        foreach ($cacheTypesToClear as $cacheType) {
            $this->typeList->cleanType($cacheType);
        }

        foreach ($this->cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
    }

    /**
     * Get's the current Magento version
     *
     * @return string
     */
    public function getMagentoVersion(): string
    {
        return $this->productMetadata->getVersion();
    }

    /**
     * Gets the installed version of Protect
     *
     * @return string|null
     */
    public function getProtectVersion(): ?string
    {
        return $this->moduleList->getOne(self::NS8_MODULE_NAME)['setup_version'];
    }

    /**
     * Get's the authenticated user name for the admin user
     * @return string|null
     */
    public function getAuthenticatedUserName(): ?string
    {
        $username = self::DEFAULT_AUTH_USER;
        try {
            $auth = $this->context->getAuth();
            $loginUser = $auth->getUser();
            if ($loginUser && $loginUser->getUserName()) {
                $username = $loginUser->getUserName();
            }
        } catch (Throwable $e) {
            $this->loggingClient->error('Failed to get username', $e);
        }
        return $username;
    }

    /**
     * Determines if the current user is allowed to see a custom Protect UI element
     * @param ContextInterface $context A Page/Controller context
     * @return boolean
     */
    public function isAllowed(ContextInterface $context)
    {
        return $context->getAuthorization()->isAllowed(self::NS8_MODULE_NAME.'::admin');
    }

    /**
     * Init SDK Configuration class for usage
     *
     * @param bool $isAuthInfoRequired Implies if the SDK should be configured to required authorization information
     * @param string $storeId optionally passes in an authentication token to support multiple stores
     * @return void
     */
    public function initSdkConfiguration(bool $isAuthInfoRequired = true, ?string $storeId = '0') : void
    {
        SdkConfigManager::initConfiguration();
        $sdkEnv = SdkConfigManager::getEnvironment();
        SdkConfigManager::setValue('platform_version', 'Magento');
        SdkConfigManager::setValue(sprintf('%s.authorization.required', $sdkEnv), $isAuthInfoRequired);
        SdkConfigManager::setValue(sprintf('%s.authorization.auth_user', $sdkEnv), $this->getAuthenticatedUserName());
        SdkConfigManager::setValue(sprintf('%s.authorization.access_token', $sdkEnv), (string) $this->getAccessToken($storeId));
    }

    /**
     * Retrieve a list of stores that the user has access to
     *
     * @return array $stores a list of stores the user has access to
     */
    public function getStores(): array
    {
        $storeManagerDataList = $this->storeManager->getStores();
        $stores = [];

        foreach ($storeManagerDataList as $value) {
            $stores[] = [
                'name'  => $value->getName(),
                'code'  => $value->getCode(),
                'id'    => $value->getStoreId(),
                'url'   => $value->getBaseUrl(),
                'active' => $value->isActive()
            ];
        }
        return $stores;
    }
}
