/**
 * Online payment gateways the storefront can really take a payment through.
 *
 * Each one starts a hosted payment for an order that is already stored: the storefront posts the
 * order's (encrypted) id, the server answers with the gateway's page address, and the shopper is
 * sent there. The server confirms the payment with the gateway before marking anything paid, then
 * brings the shopper back to the order page.
 *
 * A method the admin has switched on but is not listed here is not offered at checkout: choosing
 * it would store an unpaid order with no way to pay. Add a gateway here when its begin endpoint
 * exists.
 */
export const GATEWAYS = {
  paystation: {
    path: "paystation/begin",
    body: (combinedOrderId) => ({ payment_type: "cart_payment", combined_order_id: combinedOrderId }),
  },
  sslcommerz: {
    path: "sslcommerz/storefront/begin",
    body: (combinedOrderId) => ({ combined_order_id: combinedOrderId }),
  },
};

export const isSupportedGateway = (name) => Object.prototype.hasOwnProperty.call(GATEWAYS, String(name).toLowerCase());
