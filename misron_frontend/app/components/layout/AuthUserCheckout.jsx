// app/components/layout/AuthUserCheckout.jsx (Updated)
'use client';

import { useState, useEffect } from "react";
import { Plus, MapPin } from "lucide-react";
import CheckoutCard from "./CheckoutCard";
import DeliveryType from "./DeliveryType";
import AddressModal from "./AddressModal";
import { toast } from "react-hot-toast";
import AddressCard from "./AddressCard";
import { useCheckoutGTM } from "@/components/GTM/useCheckoutGTM";
import { useIPTracking } from "@/app/hooks/useIPTracking";
import IPBlockWarning from "@/app/components/checkout/IPBlockWarning";
import { Skeleton } from "../ui";
import { SectionHeading } from "@/app/components/checkout/CheckoutFields";

export default function AuthUserCheckout({ onClose }) {
  const [addresses, setAddresses] = useState([]);
  const [selectedAddressId, setSelectedAddressId] = useState(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingAddress, setEditingAddress] = useState(null);
  const [loading, setLoading] = useState(false);
  const [selectedAddressData, setSelectedAddressData] = useState(null);
  const [shippingCost, setShippingCost] = useState(0);
  const { ipAddress, ipLoading } = useIPTracking();
  const [ipBlocked, setIpBlocked] = useState(false);
  const [ipBlockReason, setIpBlockReason] = useState('');
  const [ipValidationComplete, setIpValidationComplete] = useState(false);

  useCheckoutGTM();

  const [deliveryInfo, setDeliveryInfo] = useState({
    deliveryType: "home_delivery",
    pickupPointId: null,
  });

  // Validate IP when it's available
  useEffect(() => {
    if (ipAddress && !ipLoading) {
      validateIPForUser();
    }
  }, [ipAddress, ipLoading]);

  const validateIPForUser = async () => {
    try {
      const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/ip-check`);
      const result = await response.json();
      
      if (result.blocked) {
        setIpBlocked(true);
        setIpBlockReason(result.message || 'Your IP has been blocked due to suspicious activity.');
      }
    } catch (error) {
      console.error('IP validation error:', error);
    } finally {
      setIpValidationComplete(true);
    }
  };

  const handleDeliveryTypeChange = (deliveryType, pickupPointId) => {
    setDeliveryInfo({
      deliveryType,
      pickupPointId,
    });

    localStorage.setItem("deliveryType", deliveryType);
    if (pickupPointId) {
      localStorage.setItem("selectedPickupPoint", pickupPointId);
    } else {
      localStorage.removeItem("selectedPickupPoint");
    }
  };

  // Fetch addresses on component mount
  useEffect(() => {
    if (!ipBlocked) {
      fetchAddresses();
    }
  }, [ipBlocked]);

  const fetchAddresses = async () => {
    try {
      setLoading(true);
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/user/shipping/address`,
        {
          headers: {
            Authorization: `Bearer ${localStorage.getItem("token")}`,
          },
        }
      );

      const data = await response.json();
      let addressList = [];

      if (Array.isArray(data)) {
        addressList = data;
      } else if (data?.data && Array.isArray(data.data)) {
        addressList = data.data;
      } else if (data?.addresses && Array.isArray(data.addresses)) {
        addressList = data.addresses;
      } else if (data?.result && data?.addresses) {
        addressList = data.addresses;
      } else {
        console.error("Unexpected response structure:", data);
        setAddresses([]);
        return;
      }

      setAddresses(addressList);

      if (addressList.length > 0) {
        const defaultAddress = addressList.find((addr) => addr.set_default === 1);
        if (defaultAddress) {
          setSelectedAddressId(defaultAddress.id);
          setSelectedAddressData(defaultAddress);
        } else {
          setSelectedAddressId(addressList[0].id);
          setSelectedAddressData(addressList[0]);
        }
      }
    } catch (error) {
      console.error("Fetch error:", error);
      toast.error("Failed to fetch addresses");
    } finally {
      setLoading(false);
    }
  };

  // Rest of the existing functions remain the same...
  const handleAddAddress = async (addressData) => {
    try {
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/user/shipping/create`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${localStorage.getItem("token")}`,
          },
          body: JSON.stringify(addressData),
        }
      );

      if (!response.ok) {
        const errorText = await response.text();
        console.error("Response not OK:", response.status, errorText);
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();

      if (data.result) {
        toast.success("Address added successfully");
        setIsModalOpen(false);
        await fetchAddresses();
      } else {
        toast.error(data.message || "Failed to add address");
      }
    } catch (error) {
      console.error("Full error:", error);
      toast.error("Failed to add address: " + error.message);
    }
  };

  const handleUpdateAddress = async (addressData) => {
    try {
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/user/shipping/update`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${localStorage.getItem("token")}`,
          },
          body: JSON.stringify({
            id: editingAddress.id,
            ...addressData,
          }),
        }
      );

      if (!response.ok) {
        const errorText = await response.text();
        console.error("Response not OK:", response.status, errorText);
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();

      if (data.result) {
        toast.success(data.message);
        setIsModalOpen(false);
        await fetchAddresses();
      } else {
        toast.error(data.message || "Failed to update address");
      }
    } catch (error) {
      toast.error("Failed to update address");
    }
  };

  const handleMakeDefault = async (addressId) => {
    try {
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/user/shipping/make_default`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${localStorage.getItem("token")}`,
          },
          body: JSON.stringify({ id: addressId }),
        }
      );

      if (!response.ok) {
        const errorText = await response.text();
        console.error("Response not OK:", response.status, errorText);
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();

      if (data.result) {
        toast.success(data.message);
        setSelectedAddressId(addressId);
        
        const updatedAddress = addresses.find(addr => addr.id === addressId);
        if (updatedAddress) {
          setSelectedAddressData(updatedAddress);
        }
        
        await fetchAddresses();
      } else {
        toast.error(data.message || "Failed to update default address");
      }
    } catch (error) {
      console.error(error);
      toast.error("Failed to update default address");
    }
  };

  const handleDeleteAddress = async (addressId) => {
    if (!confirm("Are you sure you want to delete this address?")) return;

    try {
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/user/shipping/delete/${addressId}`,
        {
          method: "GET",
          headers: {
            Authorization: `Bearer ${localStorage.getItem("token")}`,
          },
        }
      );

      const data = await response.json();

      if (data.result) {
        toast.success(data.message);
        if (selectedAddressId === addressId) {
          const remainingAddresses = addresses.filter((addr) => addr.id !== addressId);
          const newDefault = remainingAddresses.find((addr) => addr.set_default === 1);
          const newSelectedId = newDefault?.id || remainingAddresses[0]?.id || null;
          setSelectedAddressId(newSelectedId);
          
          if (newSelectedId) {
            const newSelected = remainingAddresses.find(addr => addr.id === newSelectedId);
            setSelectedAddressData(newSelected);
          }
        }
        await fetchAddresses();
      }
    } catch (error) {
      toast.error("Failed to delete address");
    }
  };

  const handleEditAddress = (address) => {
    setEditingAddress(address);
    setIsModalOpen(true);
  };

  const handleAddressSelect = (addressId) => {
    setSelectedAddressId(addressId);
    const selectedAddress = addresses.find(addr => addr.id === addressId);
    setSelectedAddressData(selectedAddress);
    toast.success("Delivery address updated");
  };

  const handleShippingCostUpdate = (cost) => {
    setShippingCost(cost);
  };

  const handleRetry = () => {
    setIpBlocked(false);
    validateIPForUser();
  };

  if (ipBlocked) {
    return (
      <div className="py-10">
        <IPBlockWarning reason={ipBlockReason} onRetry={handleRetry} />
      </div>
    );
  }

  const openAddressModal = () => {
    setEditingAddress(null);
    setIsModalOpen(true);
  };

  return (
    <>
      <CheckoutCard
        selectedAddressId={selectedAddressId}
        addresses={addresses}
        isGuest={false}
        selectedAddressData={selectedAddressData}
        onShippingCostUpdate={handleShippingCostUpdate}
        ipAddress={ipAddress}
        onClose={onClose}
      >
        <div className="flex items-center justify-between gap-3">
          <SectionHeading>Shipping &amp; Contact Details</SectionHeading>

          <button
            type="button"
            onClick={openAddressModal}
            className="flex shrink-0 items-center gap-1 text-[11px] font-semibold text-amber-800 transition-colors hover:text-amber-950"
          >
            <Plus className="h-3.5 w-3.5" aria-hidden="true" />
            Add New
          </button>
        </div>

        {loading ? (
          // Address-shaped skeletons, so the card keeps its height while saved
          // addresses load.
          <div className="space-y-2">
            {[0, 1].map((i) => (
              <Skeleton key={i} className="h-20 w-full" rounded="lg" />
            ))}
          </div>
        ) : addresses.length > 0 ? (
          <div className="space-y-2">
            {addresses.map((address, index) => (
              <AddressCard
                key={address.id}
                address={address}
                index={index + 1}
                isSelected={selectedAddressId === address.id}
                onSelect={() => handleAddressSelect(address.id)}
                onMakeDefault={() => handleMakeDefault(address.id)}
                onEdit={() => handleEditAddress(address)}
                onDelete={() => handleDeleteAddress(address.id)}
              />
            ))}
          </div>
        ) : (
          <div className="rounded-lg border border-dashed border-stone-300 bg-white p-5 text-center">
            <MapPin className="mx-auto h-5 w-5 text-stone-400" aria-hidden="true" />
            <p className="mt-2 font-medium text-stone-800">No saved addresses</p>
            <p className="mt-0.5 text-[11px] text-stone-500">
              Add a delivery address to continue with your order.
            </p>
            <button
              type="button"
              onClick={openAddressModal}
              className="mt-3 inline-flex items-center gap-1 rounded-lg border border-amber-700 px-3 py-1.5 font-semibold text-amber-900 transition-colors hover:bg-amber-50"
            >
              <Plus className="h-3.5 w-3.5" aria-hidden="true" />
              Add address
            </button>
          </div>
        )}

        <DeliveryType onDeliveryTypeChange={handleDeliveryTypeChange} />
      </CheckoutCard>

      <AddressModal
        isOpen={isModalOpen}
        onClose={() => {
          setIsModalOpen(false);
          setEditingAddress(null);
        }}
        onSave={editingAddress ? handleUpdateAddress : handleAddAddress}
        address={editingAddress}
      />
    </>
  );
}
