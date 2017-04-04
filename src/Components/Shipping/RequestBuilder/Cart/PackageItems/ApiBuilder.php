<?php

namespace FS\Components\Shipping\RequestBuilder\Cart\PackageItems;

use FS\Components\Shipping\RequestBuilder\RequestBuilderInterface;

class ApiBuilder extends FallbackBuilder implements RequestBuilderInterface
{
    public function makePackageItems($productItems, $payload)
    {
        $options = $this->getApplicationContext()
            ->_('\\FS\\Components\\Options');
        $settings = $this->getApplicationContext()
            ->_('\\FS\\Components\\Settings');
        $client = $this->getApplicationContext()
            ->_('\\FS\\Components\\Http\\Client');
        $command = $this->getApplicationContext()
            ->_('\\FS\\Components\\Shipping\\Command');
        $notifier = $this->getApplicationContext()
            ->_('\\FS\\Components\\Notifier');
        $factory = $this->getApplicationContext()
            ->_('\\FS\\Components\\Shipping\\Factory\\ShoppingOrderPackingRequestFactory');

        $response = $command->pack(
            $client,
            $factory->setPayload(array(
                'options' => $options,
                'productItems' => $productItems,
            ))->getRequest()
        );

        // when failed, we need to use fallback
        if (!$response->isSuccessful()) {
            return parent::makePackageItems($productItems, $payload);
        }

        $body = $response->getContent();
        $items = array();

        foreach ($body['packages'] as $package) {
            $items[] = array(
                'length' => $package['length'],
                'width' => $package['width'],
                'height' => $package['height'],
                'weight' => $package['weight'],
                'description' => 'product: '.implode(', ', $package['items']),
            );
        }

        return $items;
    }
}