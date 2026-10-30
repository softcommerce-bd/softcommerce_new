import { useAppDispatch, useAppSelector } from '@/store/hooks';
import { addToCartAPI, addItemLocally, removeFromCart, removeItemLocally, updateCartQuantity, updateItemQuantity, fetchCart, getCartCount, getCartSummary, clearCart, clearError } from '@/store/slices/cartSlice';
import toast from 'react-hot-toast';
import { tkText } from '@/utils/currency';

export const useCart = () => {
  const dispatch = useAppDispatch();
  const { loading, error, totalQuantity, totalPrice, items, cartData, summary, lastAddedProduct } = useAppSelector((state) => state.cart);

  const addToCart = async (productData, showToast = true) => {
    try {
      let toastId = null;
      if (showToast) toastId = toast.loading('Adding to cart...');

      const apiPayload = {
        user_id: productData.user_id,
        id: productData.id,
        variant: productData.variant || '',
        quantity: productData.quantity || 1
      };
      
      if (productData.product_referral_code) {
        apiPayload.product_referral_code = productData.product_referral_code;
      }
      
      const result = await dispatch(addToCartAPI(apiPayload)).unwrap();
      
      if (result.result) {
        if (showToast) toast.success(result.message || 'Added to cart successfully!', { id: toastId });
        dispatch(addItemLocally({ id: productData.id, name: productData.name, price: productData.price, image: productData.image, quantity: productData.quantity || 1, variant: productData.variant }));
        await dispatch(fetchCart()).unwrap();
      } else {
        if (showToast) toast.error(result.message || 'Failed to add to cart', { id: toastId });
      }
      return result;
    } catch (error) {
      if (showToast) toast.error(error || 'Failed to add to cart');
      return false;
    }
  };

  const removeItem = async (cartId) => {
    try {
      const toastId = toast.loading('Removing item...');
      dispatch(removeItemLocally(cartId));
      const result = await dispatch(removeFromCart(cartId)).unwrap();
      if (result.result) {
        toast.success('Item removed from cart', { id: toastId });
        await dispatch(fetchCart()).unwrap();
        await dispatch(getCartCount()).unwrap();
      } else {
        toast.error(result.message || 'Failed to remove item', { id: toastId });
        await dispatch(fetchCart()).unwrap();
      }
      return result;
    } catch (error) {
      toast.error('Failed to remove item');
      await dispatch(fetchCart()).unwrap();
      return false;
    }
  };

  const updateQuantity = async (cartId, newQuantity, upperLimit) => {
    if (newQuantity < 1) { toast.error('Quantity must be at least 1'); return false; }
    if (upperLimit && newQuantity > upperLimit) { toast.error(`Maximum ${upperLimit} items available`); return false; }
    try {
      const toastId = toast.loading('Updating quantity...');
      const currentItem = items.find(item => item.id === cartId);
      if (currentItem) dispatch(updateItemQuantity({ id: cartId, quantity: newQuantity }));
      const result = await dispatch(updateCartQuantity({ id: cartId, quantity: newQuantity })).unwrap();
      if (result.result) {
        toast.success('Quantity updated', { id: toastId });
        await dispatch(fetchCart()).unwrap();
        await dispatch(getCartCount()).unwrap();
      } else {
        toast.error(result.message || 'Failed to update quantity', { id: toastId });
        await dispatch(fetchCart()).unwrap();
      }
      return result;
    } catch (error) {
      toast.error('Failed to update quantity');
      await dispatch(fetchCart()).unwrap();
      return false;
    }
  };

  const loadCart = async () => { try { return await dispatch(fetchCart()).unwrap(); } catch (error) { toast.error('Failed to load cart'); return false; } };
  const refreshCartCount = async () => { try { return await dispatch(getCartCount()).unwrap(); } catch (error) { return false; } };
  const loadCartSummary = async () => { try { return await dispatch(getCartSummary()).unwrap(); } catch (error) { return false; } };
  const emptyCart = () => { dispatch(clearCart()); toast.success('Cart cleared'); };
  const resetError = () => { dispatch(clearError()); };

  const getTotalItems = () => {
    if (cartData?.data) return cartData.data.reduce((total, shop) => total + shop.cart_items.reduce((sum, item) => sum + item.quantity, 0), 0);
    return totalQuantity;
  };

  const getGrandTotal = () =>
    cartData?.grand_total ? tkText(cartData.grand_total) : `৳ ${totalPrice.toFixed(2)}`;
  const isEmpty = () => cartData?.data ? cartData.data.length === 0 : items.length === 0;

  return {
    loading, error, totalQuantity, totalPrice, items, cartData, summary, lastAddedProduct,
    totalItems: getTotalItems(), grandTotal: getGrandTotal(), isEmpty: isEmpty(),
    addToCart, removeItem, updateQuantity, loadCart, refreshCartCount, loadCartSummary, emptyCart, resetError
  };
};