// store/store.js
import { configureStore } from '@reduxjs/toolkit';
import cartReducer from './slices/cartSlice';  // Import directly
import userReducer from './slices/userSlice';
import authReducer from './slices/authSlice';
import cartModalReducer from './slices/cartModalSlice';
import wishlistReducer from './slices/wishlistSlice';
import videoModalReducer from "./slices/videoModalSlice";

export const store = configureStore({
  reducer: {
    cart: cartReducer,
    user: userReducer,
    auth: authReducer,
    cartModal: cartModalReducer,
    wishlist: wishlistReducer,
     videoModal: videoModalReducer,
  },
  devTools: process.env.NODE_ENV !== 'production',
});