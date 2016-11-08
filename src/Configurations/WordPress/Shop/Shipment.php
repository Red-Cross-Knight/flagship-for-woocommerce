<?php

namespace FS\Configurations\WordPress\Shop;

class Shipment extends \FS\Components\Shop\AbstractModel
{
    public function setReceiverAddress($address)
    {
        $this['to'] = $address;

        return $this;
    }

    public function getCourier()
    {
        return strtolower($this['service']['courier_name']);
    }

    public function isFedexGround()
    {
        return $this->getCourier() == 'fedex' && (strpos($this['service']['courier_code'], 'FedexGround') !== false);
    }
}
