<?php

namespace NS8\Protect\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use NS8\Protect\Helper\Order;

/**
 * EQ8Score Column Class
 *
 * This class handles populating the data necessary for the
 * EQ8 Score Column in the Sales Order Grid
 */
class EQ8Score extends Column
{
    /**
     * The HTTP client helper.
     *
     * @var Order
     */
    private $order;

    /**
     * Constructor
     *
     * @param ContextInterface $context The Magento Context
     * @param UiComponentFactory $uiComponentFactory The UI Component Factory
     * @param Order $order Protect's HTTP client
     * @param array $components The components
     * @param array $data The data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        Order $order,
        array $components = [],
        array $data = []
    ) {
        $this->order = $order;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Loop through the Orders and check for an EQ8 Score
     * If none is present
     *  - Fetch it from Protect
     *  - Persist it
     *
     * @param array $dataSource The Orders we'll be looping through
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $item['eq8_score'] = $this->order->getEQ8ScoreLink($item['entity_id']);
            }
        }
        return $dataSource;
    }
}