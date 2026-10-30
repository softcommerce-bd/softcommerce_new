// components/cart/CartSummary.js
'use client';

export default function CartSummary({ totalPrice }) {
  // Calculate tax and shipping (example)
  const shipping = totalPrice > 50 ? 0 : 5.99;
  const tax = totalPrice * 0.1; // 10% tax
  const grandTotal = totalPrice + shipping + tax;

  return (
    <div className="cart-summary bg-gray-50 p-6 rounded-lg">
      <h3 className="text-lg font-semibold mb-4">Order Summary</h3>
      
      <div className="space-y-2">
        <div className="flex justify-between">
          <span>Subtotal</span>
          <span>${totalPrice.toFixed(2)}</span>
        </div>
        
        <div className="flex justify-between">
          <span>Shipping</span>
          <span>{shipping === 0 ? 'Free' : `$${shipping.toFixed(2)}`}</span>
        </div>
        
        <div className="flex justify-between">
          <span>Tax (10%)</span>
          <span>${tax.toFixed(2)}</span>
        </div>
        
        <div className="border-t pt-2 mt-2">
          <div className="flex justify-between font-bold">
            <span>Total</span>
            <span>${grandTotal.toFixed(2)}</span>
          </div>
        </div>
      </div>

      <button 
        className="w-full mt-6 bg-main text-white py-3 px-4 rounded-lg hover:bg-main transition-colors disabled:bg-second disabled:cursor-not-allowed"
        disabled={totalPrice === 0}
        onClick={() => {
          // Handle checkout
          // console.log('Proceeding to checkout...');
        }}
      >
        Proceed to Checkout
      </button>

      <p className="text-xs text-second mt-4 text-center">
        Shipping and taxes calculated at checkout
      </p>
    </div>
  );
}