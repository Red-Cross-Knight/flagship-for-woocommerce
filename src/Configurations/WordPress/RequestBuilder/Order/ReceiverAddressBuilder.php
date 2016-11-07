<?php

namespace FS\Configurations\WordPress\RequestBuilder\Order;

class ReceiverAddressBuilder extends \FS\Components\AbstractComponent implements \FS\Components\Shipping\RequestBuilder\RequestBuilderInterface
{
    public function build($payload = null)
    {
        $address = $payload['order']->getReceiverAddress();

        if (empty($address['name'])) {
            $address['name'] = $address['attn'] ? $address['attn'] : 'Receiver';
        }

        $isNorthAmericanCountry = in_array($address['country'], array('CA', 'US'));

        // a friendly fix for quote, when customer does not provide state
        // provide a possibly wrong state to let address correction correct it
        if ($isNorthAmericanCountry && empty($address['state'])) {
            $address['state'] = $address['country'] == 'CA' ? 'QC' : 'NY';
        }

        return $address;
    }
}
