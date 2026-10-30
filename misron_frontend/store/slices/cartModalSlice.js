// store/slices/cartModalSlice.js
import { createSlice } from '@reduxjs/toolkit';

const initialState = {
  isOpen: false,
  productId: null,
  productSlug: null,
  product: null
};

const cartModalSlice = createSlice({
  name: 'cartModal',
  initialState,
  reducers: {
    openCartModal: (state, action) => {
      state.isOpen = true;
      state.productId = action.payload.productId;
      state.productSlug = action.payload.productSlug;
      state.product = action.payload.product;
    },
    closeCartModal: (state) => {
      state.isOpen = false;
      state.productId = null;
      state.productSlug = null;
      state.product = null;
    }
  }
});

export const { openCartModal, closeCartModal } = cartModalSlice.actions;
export default cartModalSlice.reducer;