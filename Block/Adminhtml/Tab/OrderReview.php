<?php

namespace NS8\Protect\Block\Adminhtml\Tab;

use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Backend\Block\Template;
use Magento\Framework\Registry;
use Magento\Backend\Block\Template\Context;
use NS8\Protect\Helper\Config;
use NS8\Protect\Helper\Order;
use NS8\Protect\Helper\Url;

/**
 * Logic for rendering the tab
 */
class OrderReview extends Template implements TabInterface
{
    /**
     * Name of the template. Should use a relative path
     * from the /view/adminhtml/templates folder
     *
     * @var string
     */
    protected $_template = 'order-review-tab.phtml';

    /**
     * @var Registry
     */
    protected $coreRegistry;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Url
     */
    protected $url;

    /**
     * Default constructor
     *
     * @param Context $context
     * @param Registry $registry
     * @param Config $config
     * @param Url $url
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        Config $config,
        Url $url,
        array $data = []
    ) {
        $this->context = $context;
        $this->coreRegistry = $registry;
        $this->config = $config;
        $this->url = $url;
        parent::__construct($context, $data);
    }

    /**
     * Retrieve order model instance
     *
     * @return mixed
     */
    public function getOrder()
    {
        return $this->coreRegistry->registry('current_order');
    }

    /**
     * Gets the display text for the tab
     *
     * @return string
     */
    public function getTabLabel() : string
    {
        $label =  __('NS8 Order Review');
        if ($this->coreRegistry->registry('ns8_order_review_tab_exists')) {
            $label = $label .__(' V2');
        }

        return $label;
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public function getTabTitle() : string
    {
        return __('Review Order Fraud Status');
    }

    /**
     * Determines whether the user has permission to see this tab
     * @return boolean
     */
    public function canShowTab()
    {
        return $this->config->isAllowed($this->context);
    }

    /**
     * Determines whether the tab is hidden
     *
     * @return boolean
     */
    public function isHidden()
    {
        return !$this->canShowTab();
    }
}
