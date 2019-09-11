<?php
namespace NS8\CSP2\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\Request\Http;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Sales\Api\Data\OrderInterface;

use NS8\CSP2\Helper\Logger;
use NS8\CSP2\Helper\HttpClient;

class OrderUpdate implements ObserverInterface
{
    protected $request;
    protected $customerSession;
    protected $logger;
    protected $order;
    protected $httpClient;

    /**
     * Default constructor
     *
     * @param Http $request
     * @param Session $session
     * @param Logger $logger
     * @param OrderInterface $order
     */
    public function __construct(
        Http $request,
        Session $session,
        Logger $logger,
        OrderInterface $order,
        HttpClient $httpClient
    ) {
        $this->customerSession = $session;
        $this->logger = $logger;
        $this->request = $request;
        $this->order = $order;
        $this->httpClient = $httpClient;
    }

    /**
     * Observer execute method
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            $order = $observer->getEvent()->getOrder()->getData();
            $params = array('order'=>$order);
            $response = $this->httpClient->post('protect/executor', $params, [], 'action=CREATE_ORDER_ACTION');
        } catch (\Exception $e) {
            $this->logger->error('The order update could not be processed', $e);
        }
    }
}
