"use client";

import { useEffect, useState } from "react";

export default function useCities() {
  const [cities, setCities] = useState([]);
  const [loadingCities, setLoadingCities] = useState(true);

  useEffect(() => {
    const fetchCities = async () => {
      try {
        const res = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/cities`);
        const data = await res.json();

        if (data?.data) {
          setCities(data.data);
        }
      } catch (error) {
        console.error("Failed to fetch cities:", error);
      } finally {
        setLoadingCities(false);
      }
    };

    fetchCities();
  }, []);

  return { cities, loadingCities };
}