<?php

namespace FS\Components\Order;

class MetaBox extends \FS\Components\AbstractComponent
{
    public function display(\FS\Components\Shop\OrderInterface $order)
    {
        $notifier = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Notifier');
        $factory = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Order\\Factory\\MetaBoxViewerFactory');

        $viewer = $factory->getViewer($order);

        $notifier->view();
        $viewer->render();
    }

    public function createShipment(\FS\Components\Shop\OrderInterface $order)
    {
        $options = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Options');
        $notifier = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Notifier');

        $shipment = $order->getShipment();

        if ($shipment) {
            $notifier->warning(sprintf('You have flagship shipment for this order. FlagShip ID (%s)', $this->shipment['shipment_id']));

            return $this;
        }

        $client = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Http\\Client');
        $command = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Shipping\\Command');
        $factory = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Shipping\\Factory\\ShoppingOrderConfirmationRequestFactory');

        $response = $command->confirm(
            $client,
            $factory->setPayload(array(
                'order' => $order,
                'request' => $this->getApplicationContext()->getComponent('\\FS\\Components\\Web\\RequestParam'),
                'options' => $options,
            ))->getRequest()
        );

        if (!$response->isSuccessful()) {
            return;
        }

        $confirmed = $response->getBody();

        unset($order['flagship_shipping_requote_rates']);

        $order['flagship_shipping_shipment_id'] = $confirmed['shipment_id'];
        $order['flagship_shipping_shipment_tracking_number'] = $confirmed['tracking_number'];
        $order['flagship_shipping_courier_name'] = $confirmed['service']['courier_name'];
        $order['flagship_shipping_courier_service_code'] = $confirmed['service']['courier_code'];

        $order['flagship_shipping_raw'] = $confirmed;
    }

    public function voidShipment(\FS\Components\Shop\OrderInterface $order)
    {
        $options = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Options');
        $notifier = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Notifier');

        $shipment = $order['flagship_shipping_raw'];
        $shipmentId = $order['flagship_shipping_shipment_id'];

        if (!$shipment || !$shipmentId) {
            $notifier->warning(sprintf('Unable to access shipment with FlagShip ID (%s)', $shipmentId));

            return;
        }

        $client = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Http\\Client');

        $response = $client->delete('/ship/shipments/'.$shipmentId);

        if (!$response->isSuccessful()) {
            $notifier->warning(sprintf('Unable to void shipment with FlagShip ID (%s)', $shipmentId));

            return;
        }

        if (empty($shipment['pickup'])) {
            unset($order['flagship_shipping_raw']);

            return;
        }

        $this->voidPickup($order);

        unset($order['flagship_shipping_raw']);
    }

    public function requoteShipment(\FS\Components\Shop\OrderInterface $order)
    {
        $options = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Options');
        $settings = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Settings');
        $client = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Http\\Client');
        $command = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Shipping\\Command');
        $factory = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Shipping\\Factory\\ShoppingOrderRateRequestFactory');
        $notifier = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Notifier');
        $rateProcessorFactory = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Shipping\\RateProcessor\\Factory\\RateProcessorFactory');

        $response = $command->quote(
            $client,
            $factory->setPayload(array(
                'order' => $order,
                'options' => $options,
            ))->getRequest()
        );

        if (!$response->isSuccessful()) {
            $notifier->error('Flagship Shipping has some difficulty in retrieving the rates. Please contact site administrator for assistance.<br/>');

            return;
        }

        $rates = $response->getBody();

        $rates = $rateProcessorFactory
            ->getRateProcessor('ProcessRate')
            ->getProcessedRates($rates, array(
                'factory' => $rateProcessorFactory,
                'options' => $options,
                'instanceId' => property_exists($method, 'instance_id') ? $method->instance_id : false,
                'methodId' => $settings['FLAGSHIP_SHIPPING_PLUGIN_ID'],
            ));

        $wcShippingRates = array();

        foreach ($rates as $rate) {
            $wcShippingRates[$rate['id']] = $rate['label'].' $'.$rate['cost'];
        }

        if ($wcShippingRates) {
            $order['flagship_shipping_requote_rates'] = $wcShippingRates;
        }
    }

    public function schedulePickup(\FS\Components\Shop\OrderInterface $order)
    {
        $options = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Options');
        $notifier = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Notifier');
        $client = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Http\\Client');
        $command = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Shipping\\Command');
        $request = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Web\\RequestParam');
        $factory = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Shipping\\Factory\\ShoppingOrderPickupRequestFactory');

        $shipment = $order->getShipment();

        if (!$shipment) {
            return;
        }

        $response = $command->pickup(
            $client,
            $factory->setPayload(array(
                'order' => $order,
                'options' => $options,
                'shipment' => $shipment,
                'date' => $request->request->get('flagship_shipping_pickup_schedule_date', date('Y-m-d')),
            ))->getRequest()
        );

        if (!$response->isSuccessful()) {
            $notifier->warning(sprintf('Unable to schedule pick-up with FlagShip ID (%s)', $shipment['shipment_id']));

            return;
        }

        $shipment['pickup'] = $response->getBody();

        $order['flagship_shipping_raw'] = $shipment;
    }

    public function voidPickup(\FS\Components\Shop\OrderInterface $order)
    {
        $options = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Options');
        $client = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Http\\Client');
        $notifier = $this->getApplicationContext()
            ->getComponent('\\FS\\Components\\Notifier');

        $shipment = $order->getShipment();

        $response = $client->delete('/pickups/'.$shipment['pickup']['id']);

        if (!$response->isSuccessful()) {
            $notifier->warning(sprintf('Unable to void pick-up with FlagShip Pickup ID (%s)', $shipment['pickup']['id']));

            return;
        }

        unset($shipment['pickup']);

        $order['flagship_shipping_raw'] = $shipment;
    }
}
