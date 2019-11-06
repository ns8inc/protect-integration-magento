<?php

namespace NS8\Protect\Helper;

use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Cache\Type\Config as CacheTypeConfig;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\State;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

/**
 * Generic Helper/Utility class with convenience methods for common ops
 */
class Config extends AbstractHelper
{
    /**
     * The URL to the Development Protect API
     */
    const NS8_DEV_URL_API = 'http://magento-v2-api.ngrok.io';
    /**
     * The URL to the Development Client API
     */
    const NS8_DEV_URL_CLIENT = 'http://magento-v2-client.ngrok.io';

    /**
     * The URL to the Production Protect API
     */
    const NS8_PRODUCTION_URL_API = 'https://protect.ns8.com';

    /**
     * The URL to the Production Client API
     */
    const NS8_PRODUCTION_URL_CLIENT = 'https://protect-client.ns8.com';

    /**
     * The Environment Variable name for development Protect API URL value
     */
    const NS8_ENV_NAME_API_URL = 'NS8_PROTECT_URL';

    /**
     * The Environment Variable name for development Client API URL value
     */
    const NS8_ENV_NAME_CLIENT_URL = 'NS8_CLIENT_URL';

    /**
     * The canonical name of the Magento Service Integration
     */
    const NS8_INTEGRATION_NAME = 'NS8 Protect';

    /**
     * The canonical name of the Magento extension/module name
     */
    const NS8_MODULE_NAME = 'NS8_Protect';

    /**
     * @var TypeListInterface
     */
    protected $typeList;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var ModuleList
     */
    protected $moduleList;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

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
     * @var UrlInterface
     */
    protected $urlInterface;

    /**
     * Default constructor
     *
     * @param Context $context
     * @param EncryptorInterface $encryptor
     * @param LoggerInterface $loggerInterface
     * @param ModuleList $moduleList
     * @param OrderRepositoryInterface $orderRepository
     * @param ProductMetadataInterface $productMetadata
     * @param RequestInterface $request
     * @param ScopeConfigInterface $scopeConfig
     * @param TypeListInterface $typeList
     * @param UrlInterface $urlInterface
     * @param WriterInterface $scopeWriter
     */
    public function __construct(
        Context $context,
        EncryptorInterface $encryptor,
        LoggerInterface $loggerInterface,
        ModuleList $moduleList,
        OrderRepositoryInterface $orderRepository,
        ProductMetadataInterface $productMetadata,
        RequestInterface $request,
        ScopeConfigInterface $scopeConfig,
        TypeListInterface $typeList,
        UrlInterface $urlInterface,
        WriterInterface $scopeWriter
    ) {
        $this->context = $context;
        $this->encryptor = $encryptor;
        $this->logger = $loggerInterface;
        $this->moduleList = $moduleList;
        $this->orderRepository = $orderRepository;
        $this->productMetadata = $productMetadata;
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
        $this->scopeWriter = $scopeWriter;
        $this->typeList = $typeList;
        $this->urlInterface = $urlInterface;
    }

    /**
     * Safely try to get an Apache environment variable.
     * @internal this is only for NS8 local developers in testing.
     * @param string $envVarName Variable name. Must be `NS8_CLIENT_URL` OR `NS8_PROTECT_URL`.
     * @return string|null In production, this should always return null.
     */
    private function getEnvironmentVariable(string $envVarName): ?string
    {
        $ret = null;
        try {
            $ret = $_SERVER[$envVarName];
        } catch (Exception $e) {
            $this->logger->log('DEBUG', 'Failed to get environment variable "'.$envVarName.'"', ['error'=>$e]);
        }
        return $ret;
    }

    /**
     * Assembles the URL using environment variables and handles parsing extra `/`
     *
     * @param string $envVarName
     * @param string $defaultUrl
     * @param string $route
     * @return string The final URL
     */
    private function getNS8Url(string $envVarName, string $defaultUrl, string $route = '') : string
    {
        $url = $this->getEnvironmentVariable($envVarName) ?: '';
        $url = trim($url);

        if (substr($url, -1) === '/') {
            $url = substr($url, 0, -1);
        }

        if (empty($url)) {
            $url = $defaultUrl;
        }
        if ($url === Config::NS8_PRODUCTION_URL_API ||
            $url === Config::NS8_PRODUCTION_URL_CLIENT) {
            throw new UnexpectedValueException('Cannot use Production URLs right now.');
        }
        if (!empty($route)) {
            $route = trim($route);
            if (substr($route, -1) === '/') {
                $route = substr($route, 0, -1);
            }
            if (substr($route, 0, 1) === '/') {
                $route = substr($route, 1);
            }
            $url = $url.'/'.$route;
        }
        return $url;
    }

    /**
     * Gets the current protect API URL based on the environment variables.
     * For now, defaults to Development.
     * @todo Revisit defaults on preparation to release to Production
     *
     * @param string $route
     * @return string The NS8 Protect URL in use for this instance.
     */
    public function getApiBaseUrl(string $route = '') : string
    {
        return $this->getNS8Url(Config::NS8_ENV_NAME_API_URL, Config::NS8_DEV_URL_API, $route);
    }

    /**
     * Gets the current protect Client URL based on the environment variables.
     * For now, defaults to Development.
     * @todo Revisit defaults on preparation to release to Production
     *
     * @param string $route
     * @return string The NS8 Protect Client URL in use for this instance.
     */
    public function getNS8ClientUrl(string $route = '') : string
    {
        return $this->getNS8Url(Config::NS8_ENV_NAME_CLIENT_URL, Config::NS8_DEV_URL_CLIENT, $route);
    }

    /**
     * Get the NS8 client order URL. If the order_id parameter is specified in the URL, then point to that specific order.
     * Otherwise, just point to the main dashboard page.
     * @param string|null $orderIncrementId
     * @return string The URL
     */
    public function getNS8ClientOrderUrl(string $orderIncrementId = null): string
    {
        $orderIncrementId = $orderIncrementId ?: $this->getOrderIncrementId();
        return sprintf(
            '%s%s?access_token=%s',
            $this->getNS8ClientUrl(),
            isset($orderIncrementId) ? '/order-details/' . $this->base64UrlEncode($orderIncrementId) : '',
            $this->getAccessToken()
        );
    }

    /**
     * Gets the current protect Middleware URL based on the environment variables.
     * For now, defaults to Development.
     * @todo Revisit defaults on preparation to release to Production
     *
     * @param string $route
     * @return string The NS8 Protect Middleware URL in use for this instance.
     */
    public function getNS8MiddlewareUrl(string $route = '') : string
    {
        if (substr($route, 0, 1) === '/') {
            $route = substr($route, 1);
        }
        $routeSlug = 'api'.'/'.$route;
        return $this->getNS8Url(Config::NS8_ENV_NAME_CLIENT_URL, Config::NS8_DEV_URL_CLIENT, $routeSlug);
    }

    /**
     * Get the URL of the iframe that holds the NS8 Protect client.
     * @param string|null $orderId
     * @return string The URL
     */
    public function getNS8IframeUrl(string $orderId = null): string
    {
        $orderId = $orderId ?: $this->request->getParam('order_id');
        return $this->urlInterface->getUrl('ns8protectadmin/sales/dashboard', isset($orderId) ? ['order_id' => $orderId] : []);
    }

    /**
     * Gets an access token.
     *
     * @return string|null The NS8 Protect Access Token.
     */
    public function getAccessToken() : ?string
    {
        $storedToken = $this->encryptor->decrypt($this->scopeConfig->getValue('ns8/protect/token'));
        return $storedToken;
    }

    /**
     * Save an access token.
     * @param string $accessToken
     * @return void
     */
    public function setAccessToken(string $accessToken) : void
    {
        $this->scopeWriter->save('ns8/protect/token', $this->encryptor->encrypt($accessToken));
        $this->flushConfigCache();
    }

    /**
     * Clear the cache
     *
     * @return void
     */
    public function flushConfigCache() : void
    {
        $this->typeList->cleanType(CacheTypeConfig::TYPE_IDENTIFIER);
    }

    /**
     * Get's the current Magento version
     *
     * @return string
     */
    public function getMagentoVersion() : string
    {
        return $this->productMetadata->getVersion();
    }

    /**
     * Gets the installed version of Protect
     *
     * @return string|null
     */
    public function getProtectVersion() : ?string
    {
        return $this->moduleList->getOne(Config::NS8_MODULE_NAME)['setup_version'];
    }

    /**
     * Get's the authenticated user name for the admin user
     * @return string|null
     */
    public function getAuthenticatedUserName() : ?string
    {
        $username = null;
        try {
            $auth = $this->context->getAuth();
            $loginUser = $auth->getUser();
            if (isset($loginUser)) {
                $username = $loginUser->getUserName();
            }
        } catch (Exception $e) {
            $this->logger->log('ERROR', 'Failed to get username', ['error'=>$e]);
        }
        return $username;
    }

    /**
     * Encode a string using base64 in URL mode.
     *
     * @link https://en.wikipedia.org/wiki/Base64#URL_applications
     *
     * @param string $data The data to encode
     *
     * @return string The encoded string
     */
    public function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Get the Order display id from the requested order
     * @param string|null $orderId
     * @return string|null An order increment id
     */
    public function getOrderIncrementId(string $orderId = null): ?string
    {
        $ret = null;
        $order = $this->getOrder($orderId);
        if(isset($order)) {
            $ret = $order->getIncrementId();
        }
        return $ret;
    }

    /**
     * Get an Order from an order id
     * @param string|null $orderId
     * @return OrderInterface An order
     */
    public function getOrder(string $orderId = null)
    {
        $ret = null;
        try {
            if (!isset($orderId)) {
                $orderId = $this->request->getParam('order_id');
            }
            $ret = $this->orderRepository->get($orderId);
        } catch (Exception $e) {
            $this->logger->log('ERROR', 'Failed to get order '.$orderId, ['error'=>$e]);
        }
        return $ret;
    }
}
