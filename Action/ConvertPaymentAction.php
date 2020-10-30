<?php

namespace VikingsSystems\PayumSipsPaypagePost\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Model\PaymentInterface;
use Payum\Core\Request\Convert;
use Payum\ISO4217\ISO4217;
use VikingsSystems\PayumSipsPaypagePost\Api;

class ConvertPaymentAction implements ActionInterface
{
    use GatewayAwareTrait;

    /**
     * {@inheritDoc}
     *
     * @param Convert $request
     */
    public function execute ( $request )
    {
        RequestNotSupportedException::assertSupports( $this, $request );

        /** @var PaymentInterface $payment */
        $payment = $request->getSource();

        $details = ArrayObject::ensureArrayObject( $payment->getDetails() );

        $currency                           = new ISO4217();
        $details[Api::FIELD_AMOUNT]         = $payment->getTotalAmount();
        $details[Api::FIELD_CURRENCY_CODE]  = $currency->findByAlpha3( $payment->getCurrencyCode() )->getNumeric();
        $details[Api::FIELD_CUSTOMER_ID]    = $payment->getClientId();
        $details[Api::FIELD_CUSTOMER_EMAIL] = $payment->getClientEmail();
        $details[Api::FIELD_ORDER_ID]       = $payment->getNumber();

        $request->setResult( (array)$details );
    }

    /**
     * {@inheritDoc}
     */
    public function supports ( $request )
    {
        return
            $request instanceof Convert &&
            $request->getSource() instanceof PaymentInterface &&
            $request->getTo() == 'array';
    }
}
