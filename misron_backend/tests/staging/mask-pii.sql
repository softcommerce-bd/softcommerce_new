-- Staging masking. Run ONLY on a staging COPY. Changes personal data and credentials; never touches money, stock, costs, orders' amounts or statuses.
-- Deterministic (by id) so re-runs give the same result and rows stay distinguishable.
SET FOREIGN_KEY_CHECKS=0;
UPDATE users SET name = CONCAT('Staging ', user_type, ' ', id), email = CONCAT('user', id, '@staging.invalid'), phone = CONCAT('0170000', LPAD(id, 4, '0')),
    guest_phone = IF(guest_phone IS NULL OR guest_phone = '', guest_phone, CONCAT('0171000', LPAD(id, 4, '0'))), guest_email = IF(guest_email IS NULL OR guest_email = '', guest_email, CONCAT('guest', id, '@staging.invalid')),
    address = IF(address IS NULL OR address = '', address, 'Masked address'), password = '!masked', remember_token = NULL, access_token = NULL, email_verified_at = email_verified_at;
UPDATE addresses SET name = CONCAT('Masked ', id), email = CONCAT('addr', id, '@staging.invalid'), phone = CONCAT('0172000', LPAD(id, 4, '0')), address = 'Masked address';
UPDATE orders SET shipping_address = JSON_OBJECT('name', CONCAT('Masked customer ', id), 'phone', CONCAT('0173000', LPAD(id, 4, '0')), 'address', 'Masked address', 'city', 'Dhaka') WHERE shipping_address IS NOT NULL AND shipping_address <> '';
UPDATE combined_orders SET shipping_address = JSON_OBJECT('name', CONCAT('Masked customer ', id), 'phone', CONCAT('0173000', LPAD(id, 4, '0')), 'address', 'Masked address', 'city', 'Dhaka') WHERE shipping_address IS NOT NULL AND shipping_address <> '';
UPDATE suppliers SET business_name = CONCAT('Supplier ', id), contact_person_name = CONCAT('Contact ', id), mobile = CONCAT('0174000', LPAD(id, 4, '0')), email = CONCAT('supplier', id, '@staging.invalid'), address = IF(address IS NULL OR address = '', address, 'Masked address');
UPDATE drafts SET name = CONCAT('Masked ', id), phone = CONCAT('0175000', LPAD(id, 4, '0')), email = NULL, address = 'Masked address';
UPDATE subscribers SET email = CONCAT('sub', id, '@staging.invalid');
UPDATE blocked_ips SET customer_phone = NULL, customer_email = NULL;
UPDATE courier_stats SET phone = CONCAT('0176000', LPAD(id, 4, '0'));
UPDATE job_applications SET name = CONCAT('Masked ', id), email = CONCAT('job', id, '@staging.invalid'), phone_number = '01770000000';
UPDATE shops SET phone = '01780000000', address = 'Masked address', nid = NULL;
UPDATE cost_centers SET phone = NULL, email = NULL, address = NULL;
UPDATE affiliate_users SET paypal_email = NULL;
UPDATE payku_transactions SET email = NULL;
UPDATE chart_of_accounts SET mobile_number = IF(mobile_number IS NULL OR mobile_number = '', mobile_number, '01790000000'), account_number = IF(account_number IS NULL OR account_number = '', account_number, CONCAT('ACC-MASKED-', id));
UPDATE social_credentials SET access_token = NULL, email = NULL;
UPDATE pathao_courier_settings SET password = NULL;
UPDATE business_settings SET value = NULL WHERE type IN ('whatsapp_number');
-- sessions, tokens, OTPs, resets: removed
DELETE FROM checkout_otps; DELETE FROM password_resets; DELETE FROM personal_access_tokens;
-- payment gateways and couriers are switched off so staging can never contact or charge anything real
UPDATE payment_methods SET active = 0;
UPDATE business_settings SET value = '0' WHERE type REGEXP '_sandbox$' = 0 AND type REGEXP '^(paypal|stripe|sslcommerz|bkash|nagad|paystation|aamarpay|razorpay|paytm|iyzico|flutterwave|paystack|payfast|instamojo|payhere|voguepay|payumoney|authorizenet|proxypay)_(payment|activation)$';
SET FOREIGN_KEY_CHECKS=1;
