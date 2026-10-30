// components/GTM/usePurchaseGTM.js
'use client';

import { useEffect, useRef } from 'react';
import { pushToDataLayer } from '@/lib/gtm';
// Aliased: this hook already exports a `trackPurchase` of its own.
import { trackPurchase as trackMetaPurchase } from '@/lib/pixel/meta';

export const usePurchaseGTM = () => {
  // Check sessionStorage for existing purchase tracking
  const getHasTrackedFromStorage = (orderId) => {
    if (typeof window === 'undefined') return false;
    const trackedOrders = sessionStorage.getItem('tracked_purchases');
    if (trackedOrders) {
      const tracked = JSON.parse(trackedOrders);
      return tracked.includes(orderId);
    }
    return false;
  };

  // Save tracked order to sessionStorage
  const saveTrackedOrder = (orderId) => {
    if (typeof window === 'undefined') return;
    const trackedOrders = sessionStorage.getItem('tracked_purchases');
    let tracked = trackedOrders ? JSON.parse(trackedOrders) : [];
    if (!tracked.includes(orderId)) {
      tracked.push(orderId);
      sessionStorage.setItem('tracked_purchases', JSON.stringify(tracked));
    }
  };

  // Helper to extract numeric value
  const extractNumber = (value) => {
    if (!value) return 0;
    if (typeof value === 'number') return value;
    const match = value.toString().match(/[\d,]+\.?\d*/);
    return match ? parseFloat(match[0].replace(/,/g, "")) : 0;
  };

  // Send purchase event
  const sendPurchaseEvent = (orderDetails) => {
    if (!orderDetails || !orderDetails.order_details || orderDetails.order_details.length === 0) {
      console.log('No order details available, skipping purchase event');
      return null;
    }

    const orderId = orderDetails.code || orderDetails.order_code || orderDetails.id;
    
    // Double-check if already tracked in this session
    if (getHasTrackedFromStorage(orderId)) {
      console.log('Purchase event already tracked in this session for order:', orderId);
      return null;
    }

    // Calculate totals
    const subtotal = orderDetails.order_details.reduce((sum, item) => {
      return sum + (extractNumber(item.price) * (item.quantity || 1));
    }, 0);

    const totalTax = orderDetails.order_details.reduce((sum, item) => {
      return sum + (extractNumber(item.tax) || 0);
    }, 0);

    const totalShipping = orderDetails.order_details.reduce((sum, item) => {
      return sum + (extractNumber(item.shipping_cost) || 0);
    }, 0);

    // Format items for GTM
    const items = orderDetails.order_details.map((item, index) => {
      const product = item.product || {};
      const price = extractNumber(item.price) / (item.quantity || 1);
      
      return {
        item_id: product.id?.toString() || item.product_id?.toString() || '',
        item_name: product.name || item.product_name || 'Unknown Product',
        sku: item.sku || product.sku || item.product_id?.toString() || '',
        // Catalogue id for Meta: the stock row id the feed publishes.
        content_id: (item.stock_id || item.product_id || '').toString(),
        price: price,
        quantity: item.quantity || 1,
        index: index + 1
      };
    });

    // Get customer info
    const customerName = orderDetails.user?.name || 
                         orderDetails.shipping_address?.name || 
                         '';
    const customerEmail = orderDetails.user?.email || 
                          orderDetails.shipping_address?.email || 
                          '';
    const customerPhone = orderDetails.user?.phone || 
                          orderDetails.shipping_address?.phone || 
                          '';

    // Determine if new customer
    const isNewCustomer = orderDetails.user?.total_orders === 1 || 
                          (orderDetails.user && orderDetails.user?.total_orders === undefined);

    const eventData = {
      event: "purchase",
      ecommerce: {
        currency: "BDT",
        transaction_id: orderId,
        affiliation: process.env.NEXT_PUBLIC_APP_NAME || "Misron",
        value: extractNumber(orderDetails.grand_total),
        tax: totalTax,
        shipping: totalShipping,
        coupon: orderDetails.coupon_code || "",
        items: items
      },
      customer: {
        id: orderDetails.user?.id?.toString() || 'Guest Checkout',
        first_name: customerName.split(' ')[0] || '',
        last_name: customerName.split(' ').slice(1).join(' ') || '',
        email: customerEmail,
        phone: customerPhone
      },
      new_customer: isNewCustomer
    };

    // Send to dataLayer
    pushToDataLayer(eventData);

    // Meta's copy of the same purchase. Guarded by the same sessionStorage
    // check as the dataLayer push above, so a refresh of the confirmation page
    // cannot report the order twice.
    trackMetaPurchase({
      items: items.map((item) => ({
        id: item.content_id,
        quantity: item.quantity,
        price: item.price,
      })),
      value: eventData.ecommerce.value,
      orderId,
    });
    
    // Mark as tracked in sessionStorage
    saveTrackedOrder(orderId);
    
    console.log('Purchase event sent:', {
      transaction_id: eventData.ecommerce.transaction_id,
      value: eventData.ecommerce.value,
      itemsCount: items.length,
      customer: customerEmail
    });

    return eventData;
  };

  // Track purchase on order confirmation
  const trackPurchase = (orderDetails) => {
    if (!orderDetails || !orderDetails.order_details) {
      console.log('Invalid order details, skipping');
      return;
    }
    
    const orderId = orderDetails.code || orderDetails.order_code || orderDetails.id;
    
    // Check if already tracked in this session
    if (getHasTrackedFromStorage(orderId)) {
      console.log('Purchase event already sent for this order in this session, skipping');
      return;
    }
    
    // Send the event
    sendPurchaseEvent(orderDetails);
  };

  return {
    trackPurchase,
    sendPurchaseEvent,
    hasTracked: false // This will be checked per order ID now
  };
};