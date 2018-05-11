<?php


namespace Prince\Faq\Block\Index;

use Magento\Customer\Model\Session;

class Index extends \Magento\Framework\View\Element\Template
{
    private $faqCollectionFactory;

    private $faqGroupCollectionFactory;

    private $storeManager;

    private $customerSession;

    private $templateProcessor;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Prince\Faq\Model\ResourceModel\Faq\CollectionFactory $faqCollectionFactory,
        \Prince\Faq\Model\ResourceModel\FaqGroup\CollectionFactory $faqGroupCollectionFactory,
        Session $customerSession,
        \Zend_Filter_Interface $templateProcessor
    ) {
        $this->faqCollectionFactory = $faqCollectionFactory;
        $this->faqGroupCollectionFactory = $faqGroupCollectionFactory;
        $this->storeManager = $context->getStoreManager();
        $this->customerSession = $customerSession;
        $this->templateProcessor = $templateProcessor;
        $this->scopeConfig = $context->getScopeConfig();
        parent::__construct($context);
    }

    public function getFaqCollection($group)
    {
        $faqCollection = $this->faqCollectionFactory->create();
        $faqCollection->addFieldToFilter('group', ['like' => '%'.$group.'%']);
        $faqCollection->addFieldToFilter('status', 1);
        $faqCollection->addFieldToFilter(
            'customer_group',
            [
                ['null' => true],
                ['finset' => $this->getCurrentCustomer()]
            ]
        );
        $faqCollection->addFieldToFilter(
            'storeview',
            [
                ['eq' => 0],
                ['finset' => $this->getCurrentStore()]
            ]
        );
        $faqCollection->setOrder('sortorder', 'ASC');
        return $faqCollection;
    }

    public function getFaqGroupCollection()
    {
        $faqGroupCollection = $this->faqGroupCollectionFactory->create();
        $faqGroupCollection->addFieldToFilter('status', 1);
        $faqGroupCollection->addFieldToFilter(
            'customer_group',
            [
                ['null' => true],
                ['finset' => $this->getCurrentCustomer()]
            ]
        );
        $faqGroupCollection->addFieldToFilter(
            'storeview',
            [
                ['eq' => 0],
                ['finset' => $this->getCurrentStore()]
            ]
        );
        $faqGroupCollection->setOrder('sortorder', 'DESC');
        return $faqGroupCollection;
    }

    public function filterOutputHtml($string)
    {
        return $this->templateProcessor->filter($string);
    }

    public function getImageUrl($icon)
    {
        $mediaUrl = $this->storeManager
                         ->getStore()
                         ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $imageUrl = $mediaUrl.'faq/tmp/icon/'.$icon;
        return $imageUrl;
    }

    public function getConfig($config)
    {
        return $this->scopeConfig->getValue(
            $config,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getCurrentCustomer()
    {
        return $this->customerSession->getCustomer()->getGroupId();
    }

    public function getCurrentStore()
    {
        return $this->storeManager->getStore()->getId();
    }

    /*updated on 13-03-2018
    * for retrieving the latest 5 faq */
    public function getFaqLatest(){
      $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
      $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
      $connection = $resource->getConnection();
      $tableName = $resource->getTableName('prince_faq'); //gives table name with prefix

      //Select Data from table
      $sql = "SELECT * FROM " . $tableName." WHERE status =1 ORDER BY faq_id DESC LIMIT 5";
      $faqCollection = $connection->fetchAll($sql); // gives associated array, table fields as key in array.
      return $faqCollection;

    }
}
