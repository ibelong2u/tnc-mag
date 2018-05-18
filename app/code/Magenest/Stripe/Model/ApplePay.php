<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 13/11/2017
 * Time: 10:32
 */

namespace Magenest\Stripe\Model;

use Magenest\Stripe\Helper\Logger;
use Magento\Payment\Model\Method\AbstractMethod;

class ApplePay extends AbstractMethod
{
    const CODE = 'magenest_stripe_applepay';
    protected $_code = self::CODE;
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canCaptureOnce = true;
    protected $_canVoid = false;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canOrder = false;

    protected $stripeLogger;
    protected $stripeCard;
    protected $_helper;

    public function __construct(
        \Magenest\Stripe\Helper\Data $dataHelper,
        ChargeFactory $chargeFactory,
        StripePaymentMethod $stripePaymentMethod,
        \Magenest\Stripe\Helper\Logger $stripeLogger,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_helper = $dataHelper;
        $this->_chargeFactory = $chargeFactory;
        $this->stripeLogger = $stripeLogger;
        $this->stripeCard = $stripePaymentMethod;
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $resource, $resourceCollection, $data);
    }

    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);
        try {
            $this->_debug("begin stripe applepay");
            $additionalData = $data->getData('additional_data');
            $stripeResponse = isset($additionalData['stripe_response'])?$additionalData['stripe_response']:"";
            $response = json_decode($stripeResponse, true);
            if ($response) {
                $infoInstance = $this->getInfoInstance();
                $infoInstance->setAdditionalInformation('stripe_response', $stripeResponse);
                $infoInstance->setAdditionalInformation('payment_token', $response['id']);
            } else {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Payment Data response Exception')
                );
            }
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Data error.')
            );
        }
    }

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        try {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $payment->getOrder();
            $orderId = $order->getIncrementId();
            $this->_debug("applepay authorize, orderid: ".$orderId);
            $token = $payment->getAdditionalInformation('payment_token');
            $request = $this->_helper->createChargeRequest($order, $amount, $token, false, false);
            $this->_debug($request);
            $url = 'https://api.stripe.com/v1/charges';
            $response = $this->_helper->sendRequest($request, $url, null);
            $this->stripeLogger->debug(var_export($response, true));
            if (isset($response['error'])) {
                $message = isset($response['error']['message'])?$response['error']['message']:"Payment Exception";
                throw new \Magento\Framework\Exception\LocalizedException(
                    __($message)
                );
            } else {
                $chargeId = $response['id'];
            }

            $payment->setStatus(\Magento\Payment\Model\Method\AbstractMethod::STATUS_SUCCESS)
                ->setShouldCloseParentTransaction(false)
                ->setIsTransactionClosed(false);

            $payment->setTransactionId($chargeId)
                ->setParentTransactionId($chargeId)
                ->setLastTransId($chargeId)
                ->setCcTransId($chargeId);
            $this->_helper->saveCharge($order, $response, "authorized");
        } catch (\Exception $e) {
            $this->stripeLogger->debug($e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Payment Exception')
            );
        }
        return parent::authorize($payment, $amount); // TODO: Change the autogenerated stub
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        try {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $payment->getOrder();
            $orderId = $order->getIncrementId();
            $this->_debug("applepay capture, orderid: ".$orderId);
            $transId = $payment->getCcTransId();
            if ($transId) {
                return $this->stripeCard->capture($payment, $amount);
            } else {
                $token = $payment->getAdditionalInformation('payment_token');
                $request = $this->_helper->createChargeRequest($order, $amount, $token, true, false);
                $this->_debug($request);
                $url = 'https://api.stripe.com/v1/charges';
                $response = $this->_helper->sendRequest($request, $url, null);
                $this->stripeLogger->debug(var_export($response, true));
                if (isset($response['error'])) {
                    $message = isset($response['error']['message']) ? $response['error']['message'] : "Payment Exception";
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __($message)
                    );
                } else {
                    $chargeId = $response['id'];
                }

                $payment->setStatus(\Magento\Payment\Model\Method\AbstractMethod::STATUS_SUCCESS)
                    ->setShouldCloseParentTransaction(false)
                    ->setIsTransactionClosed(false);

                $payment->setTransactionId($chargeId)
                    ->setParentTransactionId($chargeId)
                    ->setLastTransId($chargeId)
                    ->setCcTransId($chargeId);
                $this->_helper->saveCharge($order, $response, "captured");
            }
        } catch (\Exception $e) {
            $this->stripeLogger->debug($e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Payment Exception')
            );
        }

        return parent::capture($payment, $amount);
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface|\Magento\Sales\Model\Order\Payment $payment
     * @param float $amount
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        return $this->stripeCard->refund($payment, $amount);
    }

    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        return $this->stripeCard->void($payment);
    }

    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        return $this->stripeCard->cancel($payment);
    }

    /**
     * @param array|string $debugData
     */
    protected function _debug($debugData)
    {
        $this->stripeLogger->debug(var_export($debugData, true));
    }
}
