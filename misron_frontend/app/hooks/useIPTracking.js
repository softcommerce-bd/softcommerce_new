// app/hooks/useIPTracking.js
'use client';

import { useState, useEffect } from 'react';

export function useIPTracking() {
  const [ipAddress, setIpAddress] = useState(null);
  const [ipLoading, setIpLoading] = useState(true);
  const [ipError, setIpError] = useState(null);

  useEffect(() => {
    const fetchIP = async () => {
      try {
        // Method 1: Using a free IP detection service
        const response = await fetch('https://api.ipify.org?format=json');
        const data = await response.json();
        setIpAddress(data.ip);
        setIpLoading(false);
      } catch (error) {
        try {
          // Method 2: Fallback IP detection service
          const fallbackResponse = await fetch('https://api.ip.sb/ip');
          const ip = await fallbackResponse.text();
          setIpAddress(ip.trim());
          setIpLoading(false);
        } catch (fallbackError) {
          console.error('Failed to fetch IP:', fallbackError);
          setIpError('Failed to detect IP address');
          setIpLoading(false);
        }
      }
    };

    fetchIP();
  }, []);

  return { ipAddress, ipLoading, ipError };
}