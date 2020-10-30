<?php

namespace VikingsSystems\PayumSipsPaypagePost;

use VikingsSystems\PayumSipsPaypagePost\Action\AuthorizeAction;
use VikingsSystems\PayumSipsPaypagePost\Action\CancelAction;
use VikingsSystems\PayumSipsPaypagePost\Action\ConvertPaymentAction;
use VikingsSystems\PayumSipsPaypagePost\Action\CaptureAction;
use VikingsSystems\PayumSipsPaypagePost\Action\NotifyAction;
use VikingsSystems\PayumSipsPaypagePost\Action\RefundAction;
use VikingsSystems\PayumSipsPaypagePost\Action\StatusAction;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;

class SipsPaypagePostGatewayFactory extends GatewayFactory
{
    /**
     * {@inheritDoc}
     */
    protected function populateConfig ( ArrayObject $config )
    {
        $config->defaults( [
            'payum.factory_name'           => 'sips_paypage_post',
            'payum.factory_title'          => 'SIPS Paypage POST',
            'payum.action.capture'         => new CaptureAction(),
            'payum.action.authorize'       => new AuthorizeAction(),
            'payum.action.refund'          => new RefundAction(),
            'payum.action.cancel'          => new CancelAction(),
            'payum.action.notify'          => new NotifyAction(),
            'payum.action.status'          => new StatusAction(),
            'payum.action.convert_payment' => new ConvertPaymentAction(),
        ] );

        if ( false == $config['payum.api'] ) {
            $config['payum.default_options'] = [
                Api::OPTION_SANDBOX                  => true,
                Api::FIELD_MERCHANT_ID               => Api::DEFAULT_MERCHANT_ID,
                Api::FIELD_INTERFACE_VERSION         => Api::INTERFACE_VERSION,
                Api::FIELD_KEY_VERSION               => Api::DEFAULT_KEY_VERSION,
                Api::FIELD_SECRET_KEY                => Api::DEFAULT_SECRET_KEY,
                Api::FORM_FIELD_DATA_ENCODE          => Api::DEFAULT_DATA_ENCODE,
                Api::FORM_FIELD_HMAC_ALGORITHM       => Api::DEFAULT_HMAC_ALGORITHM,
                Api::OPTION_NOTIFY_URL_BASE_OVERRIDE => null,
                'url'                                => Api::DEFAULT_URL,
            ];
            $config->defaults( $config['payum.default_options'] );
            $config['payum.required_options'] = [
                Api::FIELD_MERCHANT_ID,
                Api::FIELD_INTERFACE_VERSION,
                Api::FIELD_KEY_VERSION,
                Api::FIELD_SECRET_KEY,
                Api::FORM_FIELD_HMAC_ALGORITHM,
                'url',
            ];

            $config['payum.api'] = function ( ArrayObject $config ) {
                $config->validateNotEmpty( $config['payum.required_options'] );

                return new Api( (array)$config, $config['payum.http_client'], $config['httplug.message_factory'] );
            };
        }
    }
}
