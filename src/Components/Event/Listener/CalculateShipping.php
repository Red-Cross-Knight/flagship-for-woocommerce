<?php

namespace FS\Components\Event\Listener;

use FS\Components\AbstractComponent;
use FS\Context\ApplicationListenerInterface;
use FS\Context\ConfigurableApplicationContextInterface as Context;
use FS\Context\ApplicationEventInterface as Event;
use FS\Components\Event\ApplicationEvent;

class CalculateShipping extends AbstractComponent implements ApplicationListenerInterface
{
    public function getSupportedEvent()
    {
        return ApplicationEvent::CALCULATE_SHIPPING;
    }

    public function onApplicationEvent(Event $event, Context $context)
    {
        $package = $event->getInput('package');
        $method = $event->getInput('method');

        $settings = $context->_('\\FS\\Components\\Settings');
        $options = $context
            ->_('\\FS\\Components\\Options');
        $command = $context
            ->_('\\FS\\Components\\Shipping\\Command');
        $factory = $context
            ->_('\\FS\\Components\\Shipping\\Factory\\ShoppingCartRateRequestFactory');
        $client = $context
            ->_('\\FS\\Components\\Http\\Client')
            ->setToken($options->get('token'));
        $notifier = $context
            ->_('\\FS\\Components\\Notifier')
            ->scope('cart');
        $rateProcessorFactory = $context
            ->_('\\FS\\Components\\Shipping\\RateProcessor\\Factory\\RateProcessorFactory');

        // when store owner disable front end warning for their customer
        if ($options->eq('disable_api_warning', 'yes')) {
            $notifier->enableSilentLogging();
        }

        // no shipping address, alert customer
        if (empty($package['destination']['postcode'])) {
            $notifier->notice('Add shipping address to get shipping rates! (click "Calculate Shipping")');
            $notifier->view();

            return;
        }

        $response = $command->quote(
            $client,
            $factory->setPayload(array(
                'package' => $package,
                'options' => $options,
                'notifier' => $notifier,
            ))->getRequest()
        );

        if (!$response->isSuccessful()) {
            $notifier->error('Flagship Shipping has some difficulty in retrieving the rates. Please contact site administrator for assistance.<br/>');
            $notifier->view();

            return;
        }

        $rates = $response->getContent();

        $rates = $rateProcessorFactory
            ->getRateProcessor('ProcessRate')
            ->getProcessedRates($rates, array(
                'factory' => $rateProcessorFactory,
                'options' => $options,
                'instanceId' => property_exists($method, 'instance_id') ? $method->instance_id : false,
                'methodId' => $settings['FLAGSHIP_SHIPPING_PLUGIN_ID'],
            ));

        foreach ($rates as $rate) {
            $method->add_rate($rate);
        }

        $notifier->view();
    }
}