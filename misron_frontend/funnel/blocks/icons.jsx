import {
  ArrowRight, Award, Baby, BadgeCheck, BadgePercent, Banknote, Battery, Bike, Blend, Calendar, Camera, Car,
  Check, ChevronRight, CircleAlert, CircleCheck, CircleX, Clock, Coffee, CookingPot, CreditCard, Crown, Droplet,
  Dumbbell, Flame, Flower2, Footprints, Gem, Gift, Globe, HandCoins, Headset, Heart, Hourglass, House, Info, Laptop,
  Leaf, Lock, Mail, MapPin, Medal, MessageCircle, Package, PackageCheck, Palette, Percent, Phone, Play, Plug, Quote,
  Recycle, RefreshCcw, Rocket, Ruler, Scissors, Shield, ShieldCheck, Shirt, ShoppingBag, ShoppingCart, Smartphone,
  Smile, Snowflake, Sparkles, Star, Store, Sun, Tag, Target, ThumbsUp, Timer, TrendingUp, Truck, Tv, Undo2, Users,
  Utensils, Wallet, Watch, Wrench, Zap,
} from "lucide-react";

/**
 * Icons a page can use, by the name stored in the page document. A fixed set
 * keeps pages small and lets the server validate names with a simple pattern.
 */
export const ICONS = {
  "truck": Truck,
  "package": Package,
  "package-check": PackageCheck,
  "banknote": Banknote,
  "wallet": Wallet,
  "hand-coins": HandCoins,
  "credit-card": CreditCard,
  "shield-check": ShieldCheck,
  "shield": Shield,
  "badge-check": BadgeCheck,
  "badge-percent": BadgePercent,
  "percent": Percent,
  "tag": Tag,
  "gift": Gift,
  "refresh": RefreshCcw,
  "undo": Undo2,
  "clock": Clock,
  "timer": Timer,
  "hourglass": Hourglass,
  "calendar": Calendar,
  "phone": Phone,
  "message": MessageCircle,
  "headset": Headset,
  "mail": Mail,
  "map-pin": MapPin,
  "store": Store,
  "globe": Globe,
  "lock": Lock,
  "check": Check,
  "check-circle": CircleCheck,
  "x-circle": CircleX,
  "alert": CircleAlert,
  "info": Info,
  "star": Star,
  "heart": Heart,
  "thumbs-up": ThumbsUp,
  "smile": Smile,
  "users": Users,
  "award": Award,
  "medal": Medal,
  "crown": Crown,
  "gem": Gem,
  "sparkles": Sparkles,
  "zap": Zap,
  "flame": Flame,
  "rocket": Rocket,
  "target": Target,
  "trending-up": TrendingUp,
  "leaf": Leaf,
  "droplet": Droplet,
  "recycle": Recycle,
  "sun": Sun,
  "snowflake": Snowflake,
  "flower": Flower2,
  "home": House,
  "cooking-pot": CookingPot,
  "blender": Blend,
  "utensils": Utensils,
  "coffee": Coffee,
  "plug": Plug,
  "battery": Battery,
  "wrench": Wrench,
  "ruler": Ruler,
  "scissors": Scissors,
  "palette": Palette,
  "shirt": Shirt,
  "footprints": Footprints,
  "watch": Watch,
  "smartphone": Smartphone,
  "laptop": Laptop,
  "tv": Tv,
  "camera": Camera,
  "dumbbell": Dumbbell,
  "baby": Baby,
  "car": Car,
  "bike": Bike,
  "play": Play,
  "quote": Quote,
  "arrow-right": ArrowRight,
  "chevron-right": ChevronRight,
};

export const ICON_NAMES = Object.keys(ICONS);

/**
 * @param {{ name?: string, size?: number | string, className?: string, strokeWidth?: number }} props
 */
export function Icon({ name, size = "1em", className, strokeWidth = 2 }) {
  const Component = name ? ICONS[name] : null;
  if (!Component) return null;
  return <Component size={size} className={className} strokeWidth={strokeWidth} aria-hidden="true" />;
}

/** WhatsApp's mark; lucide has no brand icons. */
export function WhatsAppMark({ size = 24, className }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="currentColor" className={className} aria-hidden="true">
      <path d="M17.47 14.38c-.3-.15-1.75-.86-2.02-.96-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.64.07-.3-.15-1.25-.46-2.38-1.47-.88-.79-1.47-1.76-1.65-2.06-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.6-.92-2.2-.24-.58-.49-.5-.67-.5h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.21 3.08.15.2 2.1 3.2 5.08 4.48.71.31 1.26.49 1.7.63.71.23 1.36.2 1.87.12.57-.08 1.75-.71 2-1.4.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35M12.05 21.5h-.01a9.4 9.4 0 0 1-4.8-1.31l-.34-.2-3.56.93.95-3.47-.22-.36a9.4 9.4 0 0 1-1.44-5.02c0-5.2 4.23-9.43 9.43-9.43 2.52 0 4.89.98 6.67 2.77a9.37 9.37 0 0 1 2.76 6.67c0 5.2-4.23 9.42-9.44 9.42m8.03-17.45A11.32 11.32 0 0 0 12.05.72C5.79.72.69 5.82.69 12.08c0 2 .52 3.96 1.52 5.68L.6 23.3l5.67-1.49a11.33 11.33 0 0 0 5.42 1.38h.01c6.26 0 11.36-5.1 11.36-11.36 0-3.03-1.18-5.89-3.33-8.03" />
    </svg>
  );
}
