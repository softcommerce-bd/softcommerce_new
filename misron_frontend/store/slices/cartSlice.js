import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || '/api/v2';

const getLocalStorageItem = (key) => {
  if (typeof window !== 'undefined') return localStorage.getItem(key);
  return null;
};

const getUserId = () => {
  if (typeof window !== 'undefined') {
    const user = localStorage.getItem('user');
    if (user) {
      try { return JSON.parse(user).id; } catch (e) { return null; }
    }
  }
  return null;
};

export const addToCartAPI = createAsyncThunk(
  'cart/addToCartAPI',
  async (productData, { rejectWithValue, getState }) => {
    try {
      const { cart } = getState();
      const { tempUserId } = cart;
      const token = getLocalStorageItem('token');
      const userId = getUserId();
      
      const formData = {
        id: productData.id,
        quantity: productData.quantity || 1,
        variant: productData.variant || '',
        user_id: userId || productData.user_id || null,
        temp_user_id: tempUserId,
      };

      if (productData.product_referral_code) {
        formData.product_referral_code = productData.product_referral_code;
      }

      const response = await fetch(`${API_BASE_URL}/carts/add`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...(token && { 'Authorization': `Bearer ${token}` })
        },
        body: JSON.stringify(formData),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to add to cart');
      }

      const data = await response.json();
      return { ...data, product: productData, temp_user_id: data.temp_user_id || tempUserId };
    } catch (error) {
      return rejectWithValue(error.message || 'Failed to add item to cart');
    }
  }
);

export const fetchCart = createAsyncThunk(
  'cart/fetchCart',
  async (_, { rejectWithValue, getState }) => {
    try {
      const { cart } = getState();
      const { tempUserId } = cart;
      const token = getLocalStorageItem('token');
      const userId = getUserId();
      
      const response = await fetch(`${API_BASE_URL}/carts`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...(token && { 'Authorization': `Bearer ${token}` })
        },
        body: JSON.stringify({ user_id: userId, temp_user_id: tempUserId })
      });

      if (!response.ok) throw new Error('Failed to fetch cart');
      const data = await response.json();
      return data;
    } catch (error) {
      return rejectWithValue(error.message);
    }
  }
);

export const updateCartQuantity = createAsyncThunk(
  'cart/updateQuantity',
  async ({ id, quantity }, { rejectWithValue, getState }) => {
    try {
      const { cart } = getState();
      const { tempUserId } = cart;
      const token = getLocalStorageItem('token');
      const userId = getUserId();

      const response = await fetch(`${API_BASE_URL}/carts/change-quantity`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...(token && { 'Authorization': `Bearer ${token}` })
        },
        body: JSON.stringify({ id, quantity, user_id: userId, temp_user_id: tempUserId })
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to update quantity');
      }

      const data = await response.json();
      return { id, quantity, ...data };
    } catch (error) {
      return rejectWithValue(error.message);
    }
  }
);

export const removeFromCart = createAsyncThunk(
  'cart/removeFromCart',
  async (productId, { rejectWithValue, getState }) => {
    try {
      const { cart } = getState();
      const { tempUserId } = cart;
      const token = getLocalStorageItem('token');
      const userId = getUserId();

      const response = await fetch(`${API_BASE_URL}/carts/${productId}`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...(token && { 'Authorization': `Bearer ${token}` })
        },
        body: JSON.stringify({ user_id: userId, temp_user_id: tempUserId })
      });

      if (!response.ok) throw new Error('Failed to remove from cart');
      const data = await response.json();
      return { productId, ...data };
    } catch (error) {
      return rejectWithValue(error.message);
    }
  }
);

export const getCartCount = createAsyncThunk(
  'cart-getCartCount',
  async (_, { rejectWithValue, getState }) => {
    try {
      const { cart } = getState();
      const { tempUserId } = cart;
      const token = getLocalStorageItem('token');
      const userId = getUserId();

      const response = await fetch(`${API_BASE_URL}/cart-count`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...(token && { 'Authorization': `Bearer ${token}` })
        },
        body: JSON.stringify({ user_id: userId, temp_user_id: tempUserId })
      });

      if (!response.ok) throw new Error('Failed to get cart count');
      const data = await response.json();
      
      if (data && data.count !== undefined) return data;
      if (data && data.data) {
        let count = 0;
        data.data.forEach(shop => { shop.cart_items.forEach(item => { count += item.quantity; }); });
        return { count };
      }
      return { count: 0 };
    } catch (error) {
      return rejectWithValue(error.message);
    }
  }
);

export const getCartSummary = createAsyncThunk(
  'cart/getCartSummary',
  async (_, { rejectWithValue, getState }) => {
    try {
      const { cart } = getState();
      const { tempUserId } = cart;
      const token = getLocalStorageItem('token');
      const userId = getUserId();

      const response = await fetch(`${API_BASE_URL}/cart-summary`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...(token && { 'Authorization': `Bearer ${token}` })
        },
        body: JSON.stringify({ user_id: userId, temp_user_id: tempUserId })
      });

      if (!response.ok) throw new Error('Failed to get cart summary');
      const data = await response.json();
      return data;
    } catch (error) {
      return rejectWithValue(error.message);
    }
  }
);

const initialState = {
  items: [],
  totalQuantity: 0,
  totalPrice: 0,
  tempUserId: typeof window !== 'undefined' ? localStorage.getItem('guest_id') : null,
  loading: false,
  error: null,
  lastAddedProduct: null,
  cartData: null,
  summary: null
};

const cartSlice = createSlice({
  name: 'cart',
  initialState,
  reducers: {
    addItemLocally: (state, action) => {
      const newItem = action.payload;
      const existingItem = state.items.find(item => item.id === newItem.id);
      if (!existingItem) {
        state.items.push({ ...newItem, quantity: newItem.quantity || 1 });
      } else {
        existingItem.quantity += (newItem.quantity || 1);
      }
      state.totalQuantity = state.items.reduce((total, item) => total + item.quantity, 0);
      state.totalPrice = state.items.reduce((total, item) => total + (item.price * item.quantity), 0);
    },
    clearError: (state) => { state.error = null; },
    resetLoading: (state) => { state.loading = false; },
    setTempUserId: (state, action) => {
      state.tempUserId = action.payload;
      if (typeof window !== 'undefined') localStorage.setItem('guest_id', action.payload);
    },
    clearCart: (state) => {
      state.items = [];
      state.totalQuantity = 0;
      state.totalPrice = 0;
      state.cartData = null;
      state.summary = null;
    },
    updateItemQuantity: (state, action) => {
      const { id, quantity } = action.payload;
      const item = state.items.find(item => item.id === id);
      if (item) {
        const oldQuantity = item.quantity;
        item.quantity = quantity;
        state.totalQuantity = state.totalQuantity - oldQuantity + quantity;
        state.totalPrice = state.totalPrice - (item.price * oldQuantity) + (item.price * quantity);
      }
    },
    removeItemLocally: (state, action) => {
      const id = action.payload;
      const item = state.items.find(item => item.id === id);
      if (item) {
        state.totalQuantity -= item.quantity;
        state.totalPrice -= item.price * item.quantity;
        state.items = state.items.filter(item => item.id !== id);
      }
    },
    updateCartData: (state, action) => { state.cartData = action.payload; }
  },
  extraReducers: (builder) => {
    builder
      .addCase(addToCartAPI.pending, (state) => { state.loading = true; state.error = null; })
      .addCase(addToCartAPI.fulfilled, (state, action) => {
        state.loading = false;
        state.lastAddedProduct = action.payload.product;
        if (action.payload.temp_user_id) {
          state.tempUserId = action.payload.temp_user_id;
          if (typeof window !== 'undefined') localStorage.setItem('guest_id', action.payload.temp_user_id);
        }
        if (action.payload.result && action.payload.product) {
          const product = action.payload.product;
          const existingItem = state.items.find(item => item.id === product.id);
          if (!existingItem) {
            state.items.push({ ...product, quantity: product.quantity || 1 });
          } else {
            existingItem.quantity += (product.quantity || 1);
          }
          state.totalQuantity = state.items.reduce((total, item) => total + item.quantity, 0);
          state.totalPrice = state.items.reduce((total, item) => total + (item.price * item.quantity), 0);
        }
      })
      .addCase(addToCartAPI.rejected, (state, action) => { state.loading = false; state.error = action.payload || 'Failed to add item to cart'; })
      .addCase(fetchCart.pending, (state) => { state.loading = true; state.error = null; })
      .addCase(fetchCart.fulfilled, (state, action) => {
        state.loading = false;
        state.cartData = action.payload;
        if (action.payload && action.payload.data) {
          const allItems = [];
          let totalQty = 0;
          action.payload.data.forEach(shop => {
            shop.cart_items.forEach(item => {
              allItems.push({
                id: item.id, product_id: item.product_id, name: item.product_name,
                image: item.product_thumbnail_image,
                price: parseFloat(item.price.replace(/[^\d.-]/g, '')),
                quantity: item.quantity, variation: item.variation,
                shop_name: shop.name, owner_id: shop.owner_id,
                upper_limit: item.upper_limit, lower_limit: item.lower_limit
              });
              totalQty += item.quantity;
            });
          });
          state.items = allItems;
          state.totalQuantity = totalQty;
          state.totalPrice = parseFloat(action.payload.grand_total.replace(/[^\d.-]/g, '')) || 0;
        }
      })
      .addCase(fetchCart.rejected, (state, action) => { state.loading = false; state.error = action.payload || 'Failed to fetch cart'; })
      .addCase(updateCartQuantity.pending, (state) => { state.loading = true; })
      .addCase(updateCartQuantity.fulfilled, (state, action) => {
        state.loading = false;
        const { productId, quantity } = action.payload;
        const item = state.items.find(item => item.id === productId);
        if (item) {
          const oldQuantity = item.quantity;
          item.quantity = quantity;
          state.totalQuantity = state.totalQuantity - oldQuantity + quantity;
          state.totalPrice = state.totalPrice - (item.price * oldQuantity) + (item.price * quantity);
        }
      })
      .addCase(updateCartQuantity.rejected, (state, action) => { state.loading = false; state.error = action.payload; })
      .addCase(removeFromCart.pending, (state) => { state.loading = true; })
      .addCase(removeFromCart.fulfilled, (state, action) => {
        state.loading = false;
        const { productId } = action.payload;
        const item = state.items.find(item => item.id === productId);
        if (item) {
          state.totalQuantity -= item.quantity;
          state.totalPrice -= item.price * item.quantity;
          state.items = state.items.filter(item => item.id !== productId);
        }
      })
      .addCase(removeFromCart.rejected, (state, action) => { state.loading = false; state.error = action.payload; })
      .addCase(getCartCount.fulfilled, (state, action) => {
        if (action.payload && action.payload.count !== undefined) state.totalQuantity = action.payload.count;
      })
      .addCase(getCartSummary.fulfilled, (state, action) => { state.summary = action.payload; });
  }
});

export const { addItemLocally, clearError, resetLoading, setTempUserId, clearCart, updateItemQuantity, removeItemLocally, updateCartData } = cartSlice.actions;
export default cartSlice.reducer;