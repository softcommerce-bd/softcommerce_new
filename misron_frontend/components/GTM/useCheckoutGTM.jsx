// components/GTM/useCheckoutGTM.js
'use client';

import { useEffect, useRef, useState } from 'react';
import { useCart } from '@/store/hooks/useCart';
import { useSelector } from 'react-redux';
import { pushToDataLayer, hashEmailSync, GTM_EVENTS } from '@/lib/gtm';
import { trackInitiateCheckout } from '@/lib/pixel/meta';

export const useCheckoutGTM = () => {
  const user = useSelector((state) => state.auth.user);
  const isAuthenticated = useSelector((state) => state.auth.isAuthenticated);
  const { cartData, loading } = useCart();
  const hasTrackedBeginCheckout = useRef(false);
  const [cartItems, setCartItems] = useState([]);
  const [totalValue, setTotalValue] = useState(0);
  const [isInitialized, setIsInitialized] = useState(false);

  // console.log('useCheckoutGTM hook initialized', { cartData, loading, cartItemsLength: cartItems.length });

  // Helper to extract numeric value from price string
  const extractNumber = (priceString) => {
    if (!priceString) return 0;
    if (typeof priceString === 'number') return priceString;
    const match = priceString.toString().match(/[\d,]+\.?\d*/);
    return match ? parseFloat(match[0].replace(/,/g, "")) : 0;
  };

  // Get user data for GTM
  const getUserForGTM = () => {
    if (isAuthenticated && user) {
      return {
        email: user.email || '',
        phone: user.phone || '',
        user_id: user.id?.toString() || ''
      };
    }
    
    if (typeof window !== 'undefined') {
      try {
        const userStr = localStorage.getItem('user');
        if (userStr) {
          const userData = JSON.parse(userStr);
          return {
            email: userData.email || '',
            phone: userData.phone || '',
            user_id: userData.id?.toString() || ''
          };
        }
      } catch (e) {
        console.error('Error parsing user data:', e);
      }
    }
    
    return { email: '', phone: '', user_id: '' };
  };

  // Extract and format cart items from cartData
  const extractCartItems = () => {
    const items = [];
    
    // console.log('Extracting cart items from cartData:', cartData);
    
    if (cartData?.data && Array.isArray(cartData.data)) {
      cartData.data.forEach((shop, shopIndex) => {
        if (shop.cart_items && Array.isArray(shop.cart_items)) {
          shop.cart_items.forEach((item, itemIndex) => {
            let price = 0;
            if (item.price) {
              price = extractNumber(item.price);
            }
            
            const productName = item.product_name || 'Unknown Product';
            const category = item.category_name ?? '';
            const brand = shop.name || '';
            const hasStock = item.upper_limit && item.upper_limit > 0;
            const stockStatus = hasStock ? "instock" : "outofstock";
            const stockLevel = item.upper_limit || (hasStock ? 5 : 0);
            
            items.push({
              item_id: item.product_id?.toString() || '',
              item_name: productName,
              sku: item.sku || item.product_id?.toString() || '',
              // Catalogue id for Meta: the stock row id the feed publishes.
              // Falls back to the product id only when the cart row could not
              // be resolved to a stock row.
              content_id: (item.stock_id || item.product_id || '').toString(),
              price: price,
              stocklevel: stockLevel,
              stockstatus: stockStatus,
              google_business_vertical: "retail",
              item_category: category,
              quantity: item.quantity || 1,
              index: itemIndex + 1,
              item_brand: brand,
              variation: item.variation || ''
            });
          });
        }
      });
    }
    
    // console.log('Extracted items:', items);
    return items;
  };

  // Calculate total value from cart items
  const calculateTotalValue = () => {
    let total = 0;
    
    if (cartData?.data && Array.isArray(cartData.data)) {
      cartData.data.forEach(shop => {
        if (shop.cart_items && Array.isArray(shop.cart_items)) {
          shop.cart_items.forEach(item => {
            let price = extractNumber(item.price);
            total += price * (item.quantity || 1);
          });
        }
      });
    }
    
    return total;
  };

  // Get shipping cost from cart items
  const getShippingCost = () => {
    let shipping = 0;
    
    if (cartData?.data && Array.isArray(cartData.data)) {
      cartData.data.forEach(shop => {
        if (shop.cart_items && Array.isArray(shop.cart_items)) {
          shop.cart_items.forEach(item => {
            if (item.shipping_cost) {
              shipping += extractNumber(item.shipping_cost);
            }
          });
        }
      });
    }
    
    return shipping;
  };

  // Update cart items when cartData changes
  useEffect(() => {
    if (cartData && !loading) {
      const items = extractCartItems();
      const total = calculateTotalValue();
      setCartItems(items);
      setTotalValue(total);
      setIsInitialized(true);
      // console.log('Cart items updated:', { itemsCount: items.length, totalValue: total });
    }
  }, [cartData, loading]);

  // Send begin_checkout event - Automatically when cart items are available
  useEffect(() => {
    // console.log('Checking if should send begin_checkout:', {
    //   isInitialized,
    //   hasTracked: hasTrackedBeginCheckout.current,
    //   cartItemsLength: cartItems.length,
    //   loading
    // });
    
    // Only send once, when cart items are loaded and not empty
    if (isInitialized && !hasTrackedBeginCheckout.current && cartItems.length > 0 && !loading) {
      sendBeginCheckoutEvent();
      hasTrackedBeginCheckout.current = true;
    }
  }, [cartItems, loading, isInitialized]);

  // Send begin_checkout event - CART DATA ONLY, NO ADDRESS INFO
  const sendBeginCheckoutEvent = () => {
    const userData = getUserForGTM();
    
    if (cartItems.length === 0) {
      // console.log('No items in cart, skipping begin_checkout event');
      return null;
    }

    // Calculate shipping cost (basic, without address)
    const shippingCost = getShippingCost();
    
    // Calculate grand total (subtotal + shipping)
    const grandTotal = totalValue + shippingCost;
    
    const eventData = {
      event: GTM_EVENTS.BEGIN_CHECKOUT,
      
      // User info - basic only
      user: {
        email: userData.email,
        phone: userData.phone,
        user_id: userData.user_id
      },
      
      // Ecommerce data - cart items only
      ecommerce: {
        currency: "BDT",
        value: grandTotal,
        shipping: shippingCost,
        tax: 0,
        items: cartItems.map(item => ({
          item_id: item.item_id,
          item_name: item.item_name,
          sku: item.sku,
          price: item.price,
          quantity: item.quantity,
          index: item.index,
          item_brand: item.item_brand,
          item_category: item.item_category,
          stocklevel: item.stocklevel,
          stockstatus: item.stockstatus,
          google_business_vertical: item.google_business_vertical
        }))
      }
    };

    pushToDataLayer(eventData);

    // Meta's copy of the same event. Sent alongside the dataLayer push rather
    // than through GTM so the id scheme stays under this codebase's control.
    trackInitiateCheckout(
      cartItems.map((item) => ({
        id: item.content_id,
        quantity: item.quantity,
        price: item.price,
      })),
      grandTotal,
    );
    // console.log(' begin_checkout event sent - CART DATA ONLY:', {
    //   itemsCount: cartItems.length,
    //   subtotal: totalValue,
    //   shipping: shippingCost,
    //   grandTotal: grandTotal,
    //   user: userData.email || 'guest'
    // });
    
    return eventData;
  };

  return {
    cartItems,
    totalValue,
    isLoading: loading,
    getShippingCost
  };
};