<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Confirmed-order-only stock (inventory v2)
    |--------------------------------------------------------------------------
    |
    | false (default): today's behaviour: stock is deducted when an order is created.
    | true: a new order line deducts nothing while pending. Confirming the order creates confirmed demand
    | (available to sell = on-hand - outstanding demand, may go negative); stock physically leaves, and FIFO cost
    | is consumed, only when the order is dispatched. Orders already in the system keep the old behaviour
    | (order_details.stock_mode = 'legacy'); new lines are 'v2'.
    |
    | Turn this on only after: the inventory_v2 migrations ran, purchase_products is InnoDB (see
    | sqlupdates/inventory_v2_innodb_conversion.sql) and `php artisan inventory:preflight` passes.
    */
    'confirmed_only' => (bool) env('INVENTORY_CONFIRMED_ONLY', false),

    /*
    |--------------------------------------------------------------------------
    | Do purchase batches roll back with a database transaction?
    |--------------------------------------------------------------------------
    |
    | Leave null: the stock service reads the storage engine of purchase_products.
    | InnoDB rolls back with the order; MyISAM (the original schema) does not, so the
    | service gives consumed batch units back by hand when an order fails.
    | Set INVENTORY_FIFO_TRANSACTIONAL=true/false only to force one behaviour in tests.
    |
    */
    'fifo_transactional' => env('INVENTORY_FIFO_TRANSACTIONAL'),

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | quantity_limit        largest quantity of one line the service accepts.
    | max_batch_attempts    how many times one line retries a batch that another
    |                       order emptied first, before giving up with "busy".
    |
    */
    'quantity_limit' => 10000,
    'max_batch_attempts' => 60,

];
