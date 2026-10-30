// components/cart/Cart.js
'use client';

import { useAppSelector, useAppDispatch } from '@/store/hooks';
import { removeItem, updateQuantity, clearCart } from '@/store/slices/cartSlice';
import CartItem from './CartItem';
import CartSummary from './CartSummary';

export default function Cart() {
  const dispatch = useAppDispatch();
  const { items, totalQuantity, totalPrice, loading } = useAppSelector((state) => state.cart);
  const isCartOpen = useAppSelector((state) => state.ui.isCartOpen);

  if (!isCartOpen) return null;

  if (loading) {
    return (
      <div className="cart-container">
        <p>Loading cart...</p>
      </div>
    );
  }

  const handleClearCart = () => {
    if (window.confirm('Are you sure you want to clear your cart?')) {
      dispatch(clearCart());
    }
  };

  return (
    <div className="cart-container">
      <h2 className="text-2xl font-bold mb-4">
        Shopping Cart ({totalQuantity} {totalQuantity === 1 ? 'item' : 'items'})
      </h2>
      
      {items.length === 0 ? (
        <p className="text-gray-500">Your cart is empty</p>
      ) : (
        <>
          <div className="cart-items space-y-4">
            {items.map(item => (
              <CartItem key={item.id} item={item} />
            ))}
          </div>
          
          <div className="mt-6 flex justify-between items-center">
            <button 
              onClick={handleClearCart}
              className="px-4 py-2 text-red-600 hover:text-red-800 transition-colors"
            >
              Clear Cart
            </button>
            <CartSummary totalPrice={totalPrice} />
          </div>
        </>
      )}
    </div>
  );
}