// app/ReduxProvider.js
'use client';

import { Provider } from 'react-redux';
import { store } from '@/store/store';  // Make sure this path is correct

export function ReduxProvider({ children }) {
  // console.log('ReduxProvider rendering', store); // Add this to verify
  return (
    <Provider store={store}>
      {children}
    </Provider>
  );
}