'use client';

import { useSelector } from "react-redux";


export const useReduxAuth = () => {
  const user = useSelector((state) => state.auth.user);
  const isAuthenticated = useSelector((state) => state.auth.isAuthenticated);
  const loading = useSelector((state) => state.auth.loading);

  return {
    user,
    isAuthenticated,
    loading,
    getUserForGTM: () => {
      if (!user || !isAuthenticated) {
        return { email: '', phone: '' };
      }
      return {
        email: user.email || '',
        phone: user.phone || '',
        user_id: user.id || ''
      };
    }
  };
};