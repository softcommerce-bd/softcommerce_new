// components/cart/CartItem.js
'use client';

import { useAppDispatch } from '@/store/hooks';
import { removeItem, updateQuantity } from '@/store/slices/cartSlice';

export default function CartItem({ item }) {
  const dispatch = useAppDispatch();

  const handleQuantityChange = (newQuantity) => {
    if (newQuantity < 1) {
      dispatch(removeItem(item.id));
    } else {
      dispatch(updateQuantity({ id: item.id, quantity: newQuantity }));
    }
  };

  return (
    <div className="cart-item">
      <img src={item.image} alt={item.name} />
      <div className="item-details">
        <h3>{item.name}</h3>
        <p>Price: ${item.price}</p>
        <div className="quantity-controls">
          <button onClick={() => handleQuantityChange(item.quantity - 1)}>-</button>
          <span>{item.quantity}</span>
          <button onClick={() => handleQuantityChange(item.quantity + 1)}>+</button>
        </div>
        <button onClick={() => dispatch(removeItem(item.id))}>Remove</button>
      </div>
      <div className="item-total">
        ${(item.price * item.quantity).toFixed(2)}
      </div>
    </div>
  );
}