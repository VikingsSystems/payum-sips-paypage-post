<?php

namespace VikingsSystems\PayumSipsPaypagePost;

use Http\Message\MessageFactory;
use Payum\Core\Exception\Http\HttpException;
use Payum\Core\HttpClientInterface;
use Payum\Core\Reply\HttpPostRedirect;

class Api
{
    // Config constants
    public const CONFIG_ALGORITHM_HMAC_256   = 'HMAC-SHA-256';
    public const CONFIG_ALGORITHM_HMAC_512   = 'HMAC-SHA-512';
    public const CONFIG_ALGORITHM_SHA_256    = 'SHA-256';
    public const CONFIG_ENCODE_BASE64        = 'Base64';
    public const CONFIG_ENCODE_BASE64URL     = 'Base64URL';
    public const ALLOWED_REQUEST_DATA_FIELDS = [
        self::FIELD_MERCHANT_ID,
        self::FIELD_KEY_VERSION,
        self::FIELD_INTERFACE_VERSION,
        self::FIELD_CUSTOMER_ID,
        self::FIELD_CUSTOMER_EMAIL,
        self::FIELD_ORDER_ID,
        self::FIELD_RETURN_URL,
        self::FIELD_NOTIFY_URL,
        self::FIELD_AMOUNT,
        self::FIELD_CURRENCY_CODE,
    ];

    // Gateway options
    public const OPTION_NOTIFY_URL_BASE_OVERRIDE = 'notifyUrlBaseOverride';
    public const OPTION_SANDBOX                  = 'sandbox';

    // Default config
    public const DEFAULT_MERCHANT_ID    = '002001000000002';
    public const DEFAULT_SECRET_KEY     = '002001000000002_KEY1';
    public const DEFAULT_KEY_VERSION    = 1;
    public const DEFAULT_URL            = 'https://payment-webinit.simu.sogenactif.com';
    public const DEFAULT_DATA_ENCODE    = '';
    public const DEFAULT_HMAC_ALGORITHM = self::CONFIG_ALGORITHM_HMAC_256;

    // Gateway config
    public const INTERFACE_VERSION = 'HP_2.33';

    // Data fields
    public const FIELD_MERCHANT_ID       = 'merchantId';
    public const FIELD_SECRET_KEY        = 'secretKey';
    public const FIELD_KEY_VERSION       = 'keyVersion';
    public const FIELD_INTERFACE_VERSION = 'interfaceVersion';
    public const FIELD_CUSTOMER_ID       = 'customerId';
    public const FIELD_CUSTOMER_EMAIL    = 'customerEmail';
    public const FIELD_ORDER_ID          = 'orderId';
    public const FIELD_RETURN_URL        = 'normalReturnUrl';
    public const FIELD_NOTIFY_URL        = 'automaticResponseUrl';
    public const FIELD_AMOUNT            = 'amount';
    public const FIELD_CURRENCY_CODE     = 'currencyCode';
    public const FIELD_RESPONSE_CODE     = 'responseCode';
    public const FIELD_CAPTURE_MODE      = 'captureMode';
    public const FIELD_CAPTURE_DAY       = 'captureDay';

    // Gateway form fields
    public const FORM_FIELD_DATA              = 'Data';
    public const FORM_FIELD_INTERFACE_VERSION = 'InterfaceVersion';
    public const FORM_FIELD_HMAC              = 'Seal';
    public const FORM_FIELD_DATA_ENCODE       = 'Encode';
    public const FORM_FIELD_HMAC_ALGORITHM    = 'SealAlgorithm';

    /**
     * @var HttpClientInterface
     */
    protected $client;

    /**
     * @var MessageFactory
     */
    protected $messageFactory;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @param array               $options
     * @param HttpClientInterface $client
     * @param MessageFactory      $messageFactory
     *
     * @throws \Payum\Core\Exception\InvalidArgumentException if an option is invalid
     */
    public function __construct ( array $options, HttpClientInterface $client, MessageFactory $messageFactory )
    {
        $this->options        = $options;
        $this->client         = $client;
        $this->messageFactory = $messageFactory;
    }

    public function doPayment ( array $details )
    : void
    {
        $formData = [];

        // Set keyVersion
        $details[self::FIELD_KEY_VERSION] = $this->options[self::FIELD_KEY_VERSION];

        // Set merchantId
        $details[self::FIELD_MERCHANT_ID] = $this->options[self::FIELD_MERCHANT_ID];

        // Set capture options
        $details[self::FIELD_CAPTURE_MODE] = 'IMMEDIATE';
        $details[self::FIELD_CAPTURE_DAY]  = 0;

        // Build data string
        $formData[self::FORM_FIELD_DATA] = $this->computeDataField( array_filter( $details, function ( $item ) {
            return in_array( $item, self::ALLOWED_REQUEST_DATA_FIELDS );
        }, ARRAY_FILTER_USE_KEY ) );
        if ( $this->options[self::FORM_FIELD_DATA_ENCODE] ) {
            $formData[self::FORM_FIELD_DATA_ENCODE] = $this->options[self::FORM_FIELD_DATA_ENCODE];
        }

        // Build HMAC
        $formData[self::FORM_FIELD_HMAC] = $this->computeHmacField( $formData[self::FORM_FIELD_DATA] );
        if ( $this->options[self::FORM_FIELD_HMAC_ALGORITHM] ) {
            $formData[self::FORM_FIELD_HMAC_ALGORITHM] = $this->options[self::FORM_FIELD_HMAC_ALGORITHM];
        }

        // Set misc fields
        $formData[self::FORM_FIELD_INTERFACE_VERSION] = self::INTERFACE_VERSION;

        throw new HttpPostRedirect( $this->getApiEndpoint(), $formData );
    }

    /**
     * @param array $fields
     *
     * @return array
     */
    protected function doRequest ( $method, array $fields )
    {
        $headers = [];

        $request = $this->messageFactory->createRequest( $method, $this->getApiEndpoint(), $headers, http_build_query( $fields ) );

        $response = $this->client->send( $request );

        if ( false == ( $response->getStatusCode() >= 200 && $response->getStatusCode() < 300 ) ) {
            throw HttpException::factory( $request, $response );
        }

        return $response;
    }

    /**
     * @return string
     */
    protected function getApiEndpoint ()
    {
        return $this->options['url'];
    }

    public function getOption ( string $key )
    {
        if ( !array_key_exists( $key, $this->options ) ) {
            return null;
        }

        return $this->options[$key];
    }

    /**
     * Formats the data array to a suitable string
     *
     * @param array $details
     *
     * @return string
     */
    public function computeDataField ( array $details )
    : string
    {
        $parameters = [];
        foreach ( $details as $key => $value ) {
            $parameters[] = $key . '=' . utf8_encode( $value );
        }

        $data = implode( '|', $parameters );

        if ( $this->options[self::FORM_FIELD_DATA_ENCODE] === self::CONFIG_ENCODE_BASE64 ) {
            return base64_encode( $data );
        }

        if ( $this->options[self::FORM_FIELD_DATA_ENCODE] === self::CONFIG_ENCODE_BASE64URL ) {
            return str_replace( [ '+', '/', '=' ], [ '-', '_', '' ], base64_encode( $data ) );
        }

        return $data;
    }

    public function decodeDataField ( string $dataString, string $encoding )
    {
        $data = [];

        foreach ( explode( '|', $dataString ) as $item ) {
            $dataField = explode( '=', $item );

            if ( $encoding === self::CONFIG_ENCODE_BASE64 ) {
                $data[$dataField[0]] = base64_decode( $dataField[1] );
                continue;
            }

            if ( $encoding === self::CONFIG_ENCODE_BASE64URL ) {
                $data[$dataField[0]] = base64_decode( str_replace( [ '-', '_' ], [ '+', '/' ], $dataField[1] ) );
                continue;
            }

            $data[$dataField[0]] = $dataField[1];
        }

        return $data;
    }

    /**
     * Computes the HMAC string from the submitted data and the secret key
     *
     * @param string $data
     *
     * @return string
     */
    public function computeHmacField ( string $data, string $algorithm = null )
    : string
    {
        if ( null === $algorithm ) {
            $algorithm = $this->options[self::FORM_FIELD_HMAC_ALGORITHM];
        }

        if ( $algorithm === self::CONFIG_ALGORITHM_HMAC_256 ) {
            return hash_hmac( 'sha256', $data, utf8_encode( $this->options[Api::FIELD_SECRET_KEY] ) );
        }

        if ( $algorithm === self::CONFIG_ALGORITHM_HMAC_512 ) {
            return hash_hmac( 'sha512', $data, utf8_encode( $this->options[Api::FIELD_SECRET_KEY] ) );
        }

        if ( $algorithm === self::CONFIG_ALGORITHM_SHA_256 ) {
            return hash( 'sha256', utf8_encode( $data . $this->options[Api::FIELD_SECRET_KEY] ) );
        }

        throw new \InvalidArgumentException( 'Invalid HMAC algorithm specified' );
    }
}
