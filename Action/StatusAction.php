<?php

namespace VikingsSystems\PayumSipsPaypagePost\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use Payum\Core\Request\GetStatusInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Symfony\Component\HttpFoundation\Response;
use VikingsSystems\PayumSipsPaypagePost\Api;

class StatusAction implements ActionInterface, ApiAwareInterface
{
    use ApiAwareTrait;

    public function __construct ()
    {
        $this->apiClass = Api::class;
    }

    public const STATUS_OK                      = '00';
    public const STATUS_DECLINED                = '05';
    public const STATUS_FRAUD_SUSPECTED_SEAL    = '34';
    public const STATUS_MAXIMUM_RETRIES_REACHED = '75';
    public const STATUS_SERVICE_UNAVAILABLE     = '90';
    public const STATUS_SESSION_EXPIRED         = '97';
    public const STATUS_TEMPORARY_ERROR         = '99';

    /**
     * {@inheritDoc}
     *
     * @param GetStatusInterface $request
     */
    public function execute ( $request )
    {
        RequestNotSupportedException::assertSupports( $this, $request );

        $model = ArrayObject::ensureArrayObject( $request->getModel() );

        // If the request just came back from the bank, validate it
        if ( isset( $model['Data'], $model['Seal'] ) && !isset( $model['responseCode'] ) ) {
            if ( $model['Seal'] !== $this->api->computeHmacField( $model['Data'] ) ) {
                // Seal is invalid, mark as unknown and specify reason
                $model['reason'] = 'Invalid Seal';
                $request->markUnknown();

                return;
            }

            // Decode data
            $data = $this->api->decodeDataField( $model['Data'], $model['Encode'] ?? '' );
            $model->replace( $data );
        }

        if ( isset( $model['responseCode'] ) ) {
            // Parse status
            switch ($model['responseCode']) {
                case self::STATUS_OK:
                    $request->markCaptured();
                    break;

                case self::STATUS_DECLINED:
                case self::STATUS_FRAUD_SUSPECTED_SEAL:
                case self::STATUS_MAXIMUM_RETRIES_REACHED:
                    $model['reason'] = 'Declined, fraud or maximum retries exceeded.';
                    $request->markFailed();
                    break;

                case self::STATUS_SERVICE_UNAVAILABLE:
                case self::STATUS_TEMPORARY_ERROR:
                    $model['reason']       = 'Temporary error or service unavailable.';
                    $model['responseCode'] = null;
                    $request->markUnknown();
                    break;

                case self::STATUS_SESSION_EXPIRED:
                    $model['reason'] = 'Session expired, payment cancelled.';
                    $request->markCanceled();
                    break;

                default:
                    $model['reason'] = 'Unknown responseCode';
                    $request->markUnknown();
                    break;
            }

            return;
        }

        if ( !isset( $model['responseCode'], $model['Seal'], $model['Data'] ) ) {
            $request->markNew();

            return;
        }

        $request->markUnknown();
    }

    /**
     * {@inheritDoc}
     */
    public function supports ( $request )
    {
        return
            $request instanceof GetStatusInterface &&
            $request->getModel() instanceof \ArrayAccess;
    }
}
