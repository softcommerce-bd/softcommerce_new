// lib/gtm.js
export const GTM_EVENTS = {
  BEGIN_CHECKOUT: 'begin_checkout',
  ADD_TO_CART: 'add_to_cart',
  ADD_TO_CART_BUY: 'add_to_cart_buy',
  VIEW_ITEM: 'view_item',
  PURCHASE: 'purchase'
};

// Helper to hash email (SHA256)
export const hashEmail = async (email) => {
  if (!email) return '';
  const msgBuffer = new TextEncoder().encode(email);
  const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
  const hashArray = Array.from(new Uint8Array(hashBuffer));
  const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
  return hashHex;
};

// Sync hash function for immediate use (you might want to implement this server-side)
export const hashEmailSync = (email) => {
  // For now, return email as is, but in production, hash on backend
  return email;
};

// Push event to dataLayer
export const pushToDataLayer = (eventData) => {
  if (typeof window !== 'undefined' && window.dataLayer) {
    window.dataLayer.push(eventData);
    // console.log(' GTM Event pushed:', eventData.event, eventData);
  }
};