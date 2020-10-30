<?php

namespace VikingsSystems\PayumSipsPaypagePost\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\Notify;
use Symfony\Component\HttpFoundation\Response;
use VikingsSystems\PayumSipsPaypagePost\Api;

class NotifyAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    public function __construct ()
    {
        $this->apiClass = Api::class;
    }

    /**
     * {@inheritDoc}
     *
     * @param Notify $request
     */
    public function execute ( $request )
    {
        RequestNotSupportedException::assertSupports( $this, $request );

        $model = ArrayObject::ensureArrayObject( $request->getModel() );

        $this->gateway->execute( $httpRequest = new GetHttpRequest() );
        $model->replace( $httpRequest->request );

        throw new HttpResponse( 'OK', Response::HTTP_OK );
    }

    /**
     * {@inheritDoc}
     */
    public function supports ( $request )
    {
        return
            $request instanceof Notify &&
            $request->getModel() instanceof \ArrayAccess;
    }
}
