<?php if ($type == 'created'): ?>
<ul>
    <li>
        <h4>Summary</h4>
        <strong>Flagship ID:</strong> <?php echo $shipment['shipment_id']; ?>
        <br/>
        <strong>Service:</strong> <?php echo $shipment['service']['courier_name'].' - '.$shipment['service']['courier_desc'];?>
        <br/>
        <strong>Tracking Number:</strong> <?php echo $shipment['tracking_number'];?>
        <br/>
        <strong>Cost:</strong> $<?php echo $shipment['price']['total'];?>
        <hr/>
        <h4>Print labels:</h4>
        <a class="button button-primary" href="<?php echo $shipment['labels']['regular']; ?>"><?php echo __('Regular label', 'flagship-shipping');?></a> <a class="button button-primary" href="<?php echo $shipment['labels']['thermal']; ?>">Thermal label</a>
        <hr/>
    </li>
    <li>
        <h4>Request for pick-up:</h4>
        <input type="date" name="flagship_shipping_schedule_pickup" value="<?php echo date('Y-m-d');?>" min="<?php echo date('Y-m-d');?>" miax="<?php echo date('Y-m-d', strtotime('+3 days'));?>"/>
        <button class="button button-primary" type="submit"><?php echo __('Schedule'); ?></button>
    </li>
    <li>
        <input type="hidden" name="flagship_shipping_void_shipment_id" value="<?php echo $shipment['shipment_id']; ?>"/>
        <button class="button" type="submit"><?php echo __('Void Shipment', 'flagship-shipping');?></button>
    </li>
</ul> 
<?php elseif ($type == 'create'): ?>
<ul class="order_actions submitbox">
    <li class="wide">
    <?php
    woocommerce_wp_select(array(
        'id' => 'flagship-shipping-service',
        'label' => __('Choose Service', 'flagship-shipping'),
        'name' => 'flagship_shipping_service',
        'options' => array(
            $service['courier_name'].':'.$service['courier_code'] => ucfirst($service['courier_name']).' - '.$service['courier_code'].' $'.$shipping['cost'],
        ),
    ));
    ?>
    </li>
    <li class="wide">
        <button type="submit" class="button save_order button-primary">
        <?php echo __('Create shipment', 'flagship-shipping'); ?>
        </button>
    </li>
</ul>
<?php else: ?>
<?php echo __('Shipment was not quoted with Flagship Shipping.', 'flagship-shipping'); ?> 
<?php endif; ?>