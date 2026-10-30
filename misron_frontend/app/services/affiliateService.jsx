// // Get the correct API URL
// const getApiUrl = () => {
//   return process.env.NEXT_PUBLIC_API_URL || '/api/v2';
// };

// // Helper to get auth token
// const getAuthToken = () => {
//   if (typeof window !== 'undefined') {
//     return localStorage.getItem('auth_token') || 
//            localStorage.getItem('token') || 
//            localStorage.getItem('access_token');
//   }
//   return null;
// };

// // Helper to get auth headers
// const getAuthHeaders = () => {
//   const token = getAuthToken();
//   const headers = {
//     'Accept': 'application/json',
//   };
  
//   if (token) {
//     headers['Authorization'] = `Bearer ${token}`;
//   }
  
//   return headers;
// };

// // Helper function for making API calls
// const apiCall = async (endpoint, options = {}) => {
//   const baseUrl = getApiUrl();
//   const url = `${baseUrl}/affiliate/${endpoint}`;
  
//   console.log('Making API call to:', url); // Debug log
  
//   const config = {
//     ...options,
//     headers: {
//       ...getAuthHeaders(),
//       ...options.headers,
//     },
//   };

//   // Handle body/data
//   if (options.body instanceof FormData) {
//     delete config.headers['Content-Type'];
//   } else if (options.data) {
//     config.body = JSON.stringify(options.data);
//     delete config.data;
//     config.headers['Content-Type'] = 'application/json';
//   } else if (!config.headers['Content-Type']) {
//     config.headers['Content-Type'] = 'application/json';
//   }

//   try {
//     const response = await fetch(url, config);
//     const contentType = response.headers.get('content-type');
    
//     // Check if response is JSON
//     if (contentType && contentType.includes('application/json')) {
//       const data = await response.json();
      
//       if (!response.ok) {
//         throw data;
//       }
      
//       return data;
//     } else {
//       // Handle non-JSON response
//       const text = await response.text();
//       console.error('Non-JSON response:', text);
//       throw { result: false, message: 'Invalid server response' };
//     }
//   } catch (error) {
//     console.error('API Error:', error);
    
//     if (error instanceof TypeError && error.message === 'Failed to fetch') {
//       throw { result: false, message: 'Network error. Please check your connection.' };
//     }
//     throw error;
//   }
// };

// // Get affiliate system info (public)
// export const getAffiliateInfo = async () => {
//   return apiCall('info');
// };

// // Apply for affiliate program
// export const applyForAffiliate = async (formData) => {
//   return apiCall('apply', {
//     method: 'POST',
//     body: formData,
//   });
// };

// // Get affiliate dashboard (authenticated)
// export const getAffiliateDashboard = async () => {
//   return apiCall('dashboard');
// };

// // Get affiliate statistics (authenticated)
// export const getAffiliateStats = async (type = 'all') => {
//   return apiCall(`stats?type=${type}`);
// };

// // Get affiliate logs (authenticated)
// export const getAffiliateLogs = async (page = 1) => {
//   return apiCall(`logs?page=${page}`);
// };

// // Get payment history (authenticated)
// export const getPaymentHistory = async (page = 1) => {
//   return apiCall(`payment-history?page=${page}`);
// };

// // Get withdraw history (authenticated)
// export const getWithdrawHistory = async (page = 1) => {
//   return apiCall(`withdraw-history?page=${page}`);
// };

// // Get payment settings (authenticated)
// export const getPaymentSettings = async () => {
//   return apiCall('payment-settings');
// };

// // Store payment settings (authenticated)
// export const storePaymentSettings = async (data) => {
//   return apiCall('payment-settings', {
//     method: 'POST',
//     data: data,
//   });
// };

// // Create withdraw request (authenticated)
// export const createWithdrawRequest = async (amount) => {
//   return apiCall('withdraw', {
//     method: 'POST',
//     data: { amount },
//   });
// };

// // Get referral statistics (authenticated)
// export const getReferralStats = async () => {
//   return apiCall('referral-stats');
// };

// Get the correct API URL based on environment
const getApiUrl = () => {
  return process.env.NEXT_PUBLIC_API_URL || '/api/v2';
};

// Helper to get auth token
const getAuthToken = () => {
  if (typeof window !== 'undefined') {
    return localStorage.getItem('auth_token') || 
           localStorage.getItem('token') || 
           localStorage.getItem('access_token');
  }
  return null;
};

// Helper to get auth headers
const getAuthHeaders = () => {
  const token = getAuthToken();
  const headers = {
    'Accept': 'application/json',
  };
  
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }
  
  return headers;
};

// Helper function for making API calls using fetch
const apiCall = async (endpoint, options = {}) => {
  const baseUrl = getApiUrl();
  const url = `${baseUrl}/affiliate/${endpoint}`;
  
  const config = {
    ...options,
    headers: {
      ...getAuthHeaders(),
      ...options.headers,
    },
  };

  if (config.data instanceof FormData) {
    delete config.headers['Content-Type'];
    config.body = config.data;
    delete config.data;
  } else if (config.body instanceof FormData) {
    delete config.headers['Content-Type'];
  } else if (!config.headers['Content-Type']) {
    config.headers['Content-Type'] = 'application/json';
  }

  if (config.data && !(config.data instanceof FormData)) {
    config.body = JSON.stringify(config.data);
    delete config.data;
  }

  try {
    const response = await fetch(url, config);
    const data = await response.json();

    if (!response.ok) {
      throw data;
    }

    return data;
  } catch (error) {
    if (error instanceof TypeError && error.message === 'Failed to fetch') {
      throw { result: false, message: 'Network error. Please check your connection.' };
    }
    throw error;
  }
};

// Get affiliate system info (public)
export const getAffiliateInfo = async () => {
  return apiCall('info');
};

// Apply for affiliate program
export const applyForAffiliate = async (formData) => {
  return apiCall('apply', {
    method: 'POST',
    body: formData,
  });
};

// Get affiliate dashboard (authenticated)
export const getAffiliateDashboard = async () => {
  return apiCall('dashboard');
};

// Get affiliate statistics (authenticated)
export const getAffiliateStats = async (type = 'all') => {
  return apiCall(`stats?type=${type}`);
};

// Get affiliate logs (authenticated)
export const getAffiliateLogs = async (page = 1) => {
  return apiCall(`logs?page=${page}`);
};

// Get payment history (authenticated)
export const getPaymentHistory = async (page = 1) => {
  return apiCall(`payment-history?page=${page}`);
};

// Get withdraw history (authenticated)
export const getWithdrawHistory = async (page = 1) => {
  return apiCall(`withdraw-history?page=${page}`);
};

// Get payment settings (authenticated)
export const getPaymentSettings = async () => {
  return apiCall('payment-settings');
};

// Store payment settings (authenticated)
export const storePaymentSettings = async (data) => {
  return apiCall('payment-settings', {
    method: 'POST',
    data: data,
  });
};

// Create withdraw request (authenticated)
export const createWithdrawRequest = async (amount) => {
  return apiCall('withdraw', {
    method: 'POST',
    data: { amount },
  });
};

// Get referral statistics (authenticated)
export const getReferralStats = async () => {
  return apiCall('referral-stats');
};