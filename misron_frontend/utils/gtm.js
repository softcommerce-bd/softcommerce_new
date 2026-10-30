// utils/gtm.js
import { sendGTMEvent } from '@next/third-parties/google';

export const GTM_EVENTS = {
  VIEW_ITEM: 'view_item',
  ADD_TO_CART: 'add_to_cart',
  BEGIN_CHECKOUT: 'begin_checkout',
  PURCHASE: 'purchase',
  USER_LOGIN: 'user_logged_in',
  USER_LOGOUT: 'user_logged_out'
};

class GTMEventDispatcher {
  static sendEvent(eventName, eventData = {}, useBackup = true) {
    const fullEventData = {
      event: eventName,
      ...eventData
    };

    // Send via sendGTMEvent
    // try {
    //   sendGTMEvent(fullEventData);
    // } catch (error) {
    //   console.error('Error sending GTM event via sendGTMEvent:', error);
    // }

    // Backup: push to dataLayer directly
    if (useBackup && typeof window !== 'undefined' && window.dataLayer) {
      window.dataLayer.push(fullEventData);
    }

    // Debug logging
    if (process.env.NODE_ENV === 'development') {
      console.log(`GTM Event: ${eventName}`, eventData);
    }
  }

  static viewItem(product, user) {
    const eventData = {
      user: {
        email: user?.email || '',
        phone: user?.phone || ''
      },
      ecommerce: {
        currency: "BDT",
        value: parseFloat(product.unit_price || product.calculable_price || 0),
        items: [{
          item_id: product.id?.toString(),
          item_name: product.name,
          sku: product.sku || product.id?.toString(),
          item_category: product.category_name || product.category?.name || '',
          quantity: 1,
          price: parseFloat(product.unit_price || product.calculable_price || 0),
          item_brand: product.brand?.name || '',
          item_list_name: "Product Detail",
          index: 1,
          product_type: "simple",
          stocklevel: product.current_stock || 0,
          stockstatus: product.current_stock > 0 ? "instock" : "outofstock"
        }]
      }
    };

    this.sendEvent(GTM_EVENTS.VIEW_ITEM, eventData);
  }

  static addToCart(product, quantity = 1, user) {
    const eventData = {
      user: {
        email: user?.email || '',
        phone: user?.phone || ''
      },
      ecommerce: {
        currency: "BDT",
        value: parseFloat(product.unit_price || product.calculable_price || 0) * quantity,
        items: [{
          item_id: product.id?.toString(),
          item_name: product.name,
          sku: product.sku || product.id?.toString(),
          item_category: product.category_name || product.category?.name || '',
          quantity: quantity,
          price: parseFloat(product.unit_price || product.calculable_price || 0),
          item_brand: product.brand?.name || '',
          stocklevel: product.current_stock || 0,
          stockstatus: product.current_stock > 0 ? "instock" : "outofstock"
        }]
      }
    };

    this.sendEvent(GTM_EVENTS.ADD_TO_CART, eventData);
  }
}

export default GTMEventDispatcher;