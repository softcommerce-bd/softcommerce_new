<?php

return [

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
