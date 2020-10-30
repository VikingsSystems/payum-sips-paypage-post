<?php

namespace VikingsSystems\PayumSipsPaypagePost\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\Capture;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Security\GenericTokenFactoryAwareInterface;
use Payum\Core\Security\GenericTokenFactoryAwareTrait;
use Payum\Core\Security\TokenInterface;
use Payum\ISO4217\ISO4217;
use VikingsSystems\PayumSipsPaypagePost\Api;

class CaptureAction implements ActionInterface, GatewayAwareInterface, GenericTokenFactoryAwareInterface, ApiAwareInterface
{
    use GatewayAwareTrait;
    use GenericTokenFactoryAwareTrait;
    use ApiAwareTrait;

    public function __construct ()
    {
        $this->apiClass = Api::class;
    }

    /**
     * {@inheritDoc}
     *
     * @param Capture $request
     */
    public function execute ( $request )
    {
        RequestNotSupportedException::assertSupports( $this, $request );

        $model = ArrayObject::ensureArrayObject( $request->getModel() );

        if ( null !== $model[Api::FIELD_RESPONSE_CODE] && $model[Api::FIELD_RESPONSE_CODE] === StatusAction::STATUS_OK ) {
            return;
        }

        if ( $request->getToken() instanceof TokenInterface ) {
            // Notify URL
            $notifyToken = $this->tokenFactory->createNotifyToken(
                $request->getToken()->getGatewayName(),
                $request->getToken()->getDetails()
            );

            // Use the overridden base notify URL if needed
            if ( $this->api && $this->api->getOption( Api::OPTION_SANDBOX ) && $this->api->getOption( Api::OPTION_NOTIFY_URL_BASE_OVERRIDE ) ) {
                $model[Api::FIELD_NOTIFY_URL] = $this->api->getOption( Api::OPTION_NOTIFY_URL_BASE_OVERRIDE ) . $notifyToken->getHash();
            } else {
                $model[Api::FIELD_NOTIFY_URL] = $notifyToken->getTargetUrl();
            }

            // Return URL
            $model[Api::FIELD_RETURN_URL] = $request->getToken()->getTargetUrl();
        }

        $this->gateway->execute( $httpRequest = new GetHttpRequest() );

        if ( isset( $httpRequest->request['Data'] ) && isset( $httpRequest->request['Seal'] ) ) {
            $model->replace( $httpRequest->request );
        } else {
            $this->api->doPayment( (array)$model );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports ( $request )
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof \ArrayAccess;
    }
}
