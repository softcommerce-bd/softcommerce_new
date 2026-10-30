import { toTk } from "@/utils/currency";

// Helper functions
export const parseShippingAddress = (addressString) => {
  try {
    return JSON.parse(addressString);
  } catch {
    return {
      name: "",
      phone: "",
      address: addressString,
      email: ""
    };
  }
};

// Totals reach this both as numbers and as strings the API already formatted
// with a currency sign, so toTk() handles the two cases.
export const formatPrice = (price) => toTk(price);

export const formatDateFromTimestamp = (timestamp) => {
  if (!timestamp) return "N/A";
  const date = new Date(timestamp * 1000);
  return date.toLocaleDateString('en-US', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric' 
  });
};

export const getDeliveryStatus = (order) => {
  if (order.delivery_status === "steadfast" && order.steadfast_id) {
    return "In Transit";
  }
  return order.delivery_status || "Pending";
};

export const getStatusColor = (status) => {
  const colors = {
    pending: "bg-second",
    confirmed: "bg-blue-500",
    processing: "bg-purple-500",
    delivered: "bg-green-500",
    completed: "bg-success",
    cancelled: "bg-red-500",
    refunded: "bg-orange-500",
    steadfast: "bg-indigo-500"
  };
  return colors[status] || "bg-gray-500";
};

export const getPaymentStatusBadge = (paymentStatus) => {
  const statuses = {
    paid: { text: "Paid", color: "bg-green-600" },
    unpaid: { text: "Due", color: "bg-red-600" },
    pending: { text: "Pending", color: "bg-yellow-600" }
  };
  return statuses[paymentStatus] || { text: paymentStatus || "Unknown", color: "bg-gray-600" };
};