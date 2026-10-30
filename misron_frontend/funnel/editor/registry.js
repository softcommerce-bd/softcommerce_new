import {
  BadgeCheck, BadgeDollarSign, BadgePercent, GalleryThumbnails, Hash, PackageOpen, Receipt, ShoppingBag, SlidersHorizontal, Store,
  Clapperboard, CircleHelp, Columns2, FileText, Film, GalleryHorizontal, Handshake, Heading, Image,
  Images, LayoutGrid, LayoutList, LayoutTemplate, List, Megaphone, MessageCircle, MessageSquareQuote, Minus,
  MousePointerClick, MoveVertical, PanelBottom, ShieldCheck, Smile, Sparkles, Square, SquareSplitHorizontal, Table,
  Tag, Timer, Type, ClipboardList, CreditCard, PackageCheck, Truck,
} from "lucide-react";
import { BLOCKS } from "../blocks/index.js";

/**
 * The editor's side of each block: name, library icon, settings fields,
 * style groups and the content a new block starts with.
 *
 * A block appears in the library only when it is listed here, has a
 * renderer in BLOCKS and is allowed by the server registry.
 *
 * Sample copy is in Bengali because that is what most pages will use; every
 * text can be replaced, and English works the same way.
 */

export const CATEGORIES = [
  { id: "layout", label: "Layout" },
  { id: "basic", label: "Basic" },
  { id: "marketing", label: "Marketing" },
  { id: "ecommerce", label: "Products" },
  { id: "checkout", label: "Order form" },
];

/** Where the "offer" a free-standing product block follows is chosen. */
const FOLLOW_OFFER = { key: "offerKey", label: "Follows this offer", type: "offerKey" };
const SHOW_OPTIONS = [
  { key: "showOriginalPrice", label: "Show the crossed-out price", type: "toggle" },
  { key: "showSavings", label: "Show how much customers save", type: "toggle" },
];

const ICON_ITEM_FIELDS = [
  { key: "icon", label: "Icon", type: "icon" },
  { key: "title", label: "Title", type: "text" },
  { key: "text", label: "Detail", type: "text" },
];

const CTA_ACTIONS = ["scroll", "link"];

export const EDITOR_BLOCKS = {
  // ── Products ────────────────────────────────────────────────────────────
  product_card: {
    label: "Product card",
    category: "ecommerce",
    icon: ShoppingBag,
    description: "One product from your store with its price, offer and a select button",
    keywords: ["product", "item", "buy", "single product"],
    preset: {},
    fields: [
      { key: "__bindings", label: "Product", type: "bindings" },
      { key: "layout", label: "Layout", type: "select", options: [["vertical", "Picture on top"], ["horizontal", "Picture on the side"]] },
      { key: "showDescription", label: "Show the short description", type: "toggle" },
      ...SHOW_OPTIONS,
      { key: "showStock", label: "Show stock (only when truthful)", type: "toggle" },
      { key: "ctaText", label: "Button text", type: "text", placeholder: "সিলেক্ট করুন" },
      { key: "ctaAction", label: "When the button is tapped", type: "select", options: [["select", "Select the product"], ["select_and_scroll", "Select it and scroll to the order form"]] },
      { key: "unavailableText", label: "Text when it cannot be ordered", type: "text", placeholder: "স্টক শেষ" },
    ],
    style: ["productCard", "productTitle", "productPrice", "spacing", "background"],
  },
  product_offer_selector: {
    label: "Product grid",
    category: "ecommerce",
    icon: Store,
    description: "Several products as cards; customers choose one or more",
    keywords: ["products", "grid", "selector", "offers", "catalogue", "choose"],
    preset: { props: { heading: "আপনার পছন্দের পণ্যটি বাছাই করুন", selectionMode: "single", cardLayout: "vertical" } },
    fields: [
      { key: "heading", label: "Heading", type: "text" },
      { key: "__bindings", label: "Products", type: "bindings" },
      { key: "selectionMode", label: "Customers can choose", type: "select", options: [["single", "One product (round buttons)"], ["multiple", "Several products (tick boxes)"]] },
      { key: "cardLayout", label: "Card layout", type: "select", options: [["vertical", "Picture on top"], ["horizontal", "Picture on the side"], ["compact", "Compact"]] },
      { key: "showDescription", label: "Show short descriptions", type: "toggle" },
      ...SHOW_OPTIONS,
      { key: "showStock", label: "Show stock (only when truthful)", type: "toggle" },
      { key: "allowQuantity", label: "Let customers change the quantity on the card", type: "toggle" },
      { key: "unavailableText", label: "Text when a product cannot be ordered", type: "text", placeholder: "স্টক শেষ" },
    ],
    note: "Set the number of columns for each screen size under Style → Grid. Two columns on mobile works well.",
    style: ["productGrid", "productCard", "productTitle", "productPrice", "spacing", "background"],
  },
  bundle_offer_selector: {
    label: "Bundle offers",
    category: "ecommerce",
    icon: PackageOpen,
    description: "Sets of products sold together at one price",
    keywords: ["bundle", "combo", "set", "package", "multi"],
    preset: { props: { heading: "কম্বো অফার বাছাই করুন", layout: "stack" } },
    fields: [
      { key: "heading", label: "Heading", type: "text" },
      { key: "__bindings", label: "Bundles", type: "bindings" },
      { key: "layout", label: "Layout", type: "select", options: [["stack", "One under another"], ["grid", "Two side by side"]] },
      ...SHOW_OPTIONS,
      { key: "unavailableText", label: "Text when a bundle cannot be ordered", type: "text", placeholder: "স্টক শেষ" },
    ],
    style: ["productCard", "productTitle", "productPrice", "spacing", "background"],
  },
  product_gallery: {
    label: "Product photos",
    category: "ecommerce",
    icon: GalleryThumbnails,
    description: "All photos of a product from your store",
    keywords: ["gallery", "images", "photos", "pictures", "product images"],
    preset: {},
    fields: [FOLLOW_OFFER, { key: "layout", label: "Layout", type: "select", options: [["thumbs_bottom", "Thumbnails below"], ["thumbs_left", "Thumbnails on the left"], ["carousel", "Swipe carousel"]] }],
    style: ["galleryImage", "spacing"],
  },
  product_price: {
    label: "Product price",
    category: "ecommerce",
    icon: BadgeDollarSign,
    description: "The offer price, with what customers save",
    keywords: ["price", "cost", "taka"],
    preset: {},
    fields: [FOLLOW_OFFER, { key: "showOriginal", label: "Show the crossed-out price", type: "toggle" }, { key: "showSavings", label: "Show how much customers save", type: "toggle" }],
    style: ["productPrice", "align", "spacing"],
  },
  product_variations: {
    label: "Variation picker",
    category: "ecommerce",
    icon: SlidersHorizontal,
    description: "Size, colour or other variations of a product",
    keywords: ["size", "colour", "color", "variant", "variation", "options"],
    preset: {},
    fields: [
      FOLLOW_OFFER,
      { key: "display", label: "Look", type: "select", options: [["buttons", "Buttons"], ["dropdown", "Dropdown"]] },
      { key: "label", label: "Label", type: "text", placeholder: "ভ্যারিয়েশন বাছুন" },
    ],
    style: ["align", "spacing"],
  },
  quantity_selector: {
    label: "Quantity",
    category: "ecommerce",
    icon: Hash,
    description: "A − 1 + stepper for the number to order",
    keywords: ["quantity", "amount", "how many", "stepper"],
    preset: {},
    fields: [FOLLOW_OFFER, { key: "label", label: "Label", type: "text", placeholder: "পরিমাণ" }, { key: "max", label: "Highest quantity", type: "number", min: 1, max: 99, step: 1 }],
    style: ["align", "spacing"],
  },
  discount_badge: {
    label: "Discount badge",
    category: "ecommerce",
    icon: BadgePercent,
    description: "Shows the discount of an offer, such as −10%",
    keywords: ["discount", "sale", "percent", "badge", "off"],
    preset: {},
    fields: [
      FOLLOW_OFFER,
      { key: "text", label: "Your own text (optional)", type: "text", help: "Leave empty to show the real discount automatically." },
      { key: "shape", label: "Shape", type: "select", options: [["pill", "Pill"], ["ribbon", "Ribbon"], ["circle", "Circle"]] },
    ],
    style: ["badge", "align", "spacing"],
  },
  discount_summary: {
    label: "Offer summary",
    category: "ecommerce",
    icon: Receipt,
    description: "A live summary of what the customer has chosen and will pay",
    keywords: ["summary", "total", "order", "price", "savings", "cart"],
    preset: {},
    fields: [
      { key: "title", label: "Title", type: "text", placeholder: "অর্ডারের সারাংশ" },
      { key: "showItems", label: "List the chosen products", type: "toggle" },
      { key: "showTax", label: "Show tax", type: "toggle" },
      { key: "emptyText", label: "Text before anything is chosen", type: "text" },
    ],
    note: "Prices are worked out by the server for every change. Delivery is added at checkout.",
    style: ["spacing", "background", "border"],
  },

  // ── Order form ──────────────────────────────────────────────────────────
  checkout_form: {
    label: "Order form",
    category: "checkout",
    icon: ClipboardList,
    description: "Name, phone, address, payment and the order button. Places a real order.",
    keywords: ["checkout", "order", "form", "buy", "cod", "cash on delivery", "address"],
    preset: {
      props: {
        title: "অর্ডার করতে আপনার তথ্য দিন",
        layout: "one_column",
        requireConsent: false,
        fields: [
          { key: "name", label: "আপনার নাম", placeholder: "পুরো নাম লিখুন", enabled: true, required: true, width: "full", type: "text" },
          { key: "phone", label: "মোবাইল নম্বর", placeholder: "01XXXXXXXXX", enabled: true, required: true, width: "full", type: "text" },
          { key: "address", label: "সম্পূর্ণ ঠিকানা", placeholder: "বাসা/রোড/এলাকা লিখুন", enabled: true, required: true, width: "full", type: "textarea" },
          { key: "district", label: "ডেলিভারি এলাকা", placeholder: "এলাকা বাছাই করুন", enabled: true, required: true, width: "full", type: "select" },
          { key: "notes", label: "অর্ডার নোট (ঐচ্ছিক)", placeholder: "কোনো বিশেষ নির্দেশনা থাকলে লিখুন", enabled: true, required: false, width: "full", type: "textarea" },
        ],
      },
      children: [
        { type: "order_summary", props: { title: "অর্ডারের সারাংশ", showImages: true, collapsibleOnMobile: false } },
        { type: "payment_method_selector", props: { label: "পেমেন্ট পদ্ধতি", allowed: [] } },
        { type: "purchase_button", props: { text: "অর্ডার কনফার্ম করুন", subtext: "ক্যাশ অন ডেলিভারি — পণ্য হাতে পেয়ে টাকা দিন", showTotal: true, icon: "check-circle" } },
      ],
    },
    fields: [
      { key: "title", label: "Form title", type: "text" },
      { key: "layout", label: "Fields are shown in", type: "select", options: [["one_column", "One column"], ["two_column", "Two columns on wide screens"]] },
      {
        key: "fields",
        label: "Form fields",
        type: "list",
        item: "checkout_field",
        itemLabel: (item, index) => `${item.label || item.key || `Field ${index + 1}`}${item.enabled === false ? " (hidden)" : ""}`,
        newItem: (list = []) => ({
          key: ["custom_1", "custom_2", "custom_3", "custom_4", "custom_5"].find((key) => !list.some((item) => item.key === key)) ?? "email",
          label: "", enabled: true, required: false, width: "full", type: "text",
        }),
        fields: [
          { key: "key", label: "Field", type: "select", options: [["name", "Name"], ["phone", "Mobile number"], ["email", "Email"], ["address", "Address"], ["district", "Delivery area"], ["area", "Thana / area"], ["notes", "Order note"], ["custom_1", "Extra field 1"], ["custom_2", "Extra field 2"], ["custom_3", "Extra field 3"], ["custom_4", "Extra field 4"], ["custom_5", "Extra field 5"]] },
          { key: "label", label: "Label", type: "text" },
          { key: "placeholder", label: "Hint inside the field", type: "text" },
          { key: "enabled", label: "Show this field", type: "toggle" },
          { key: "required", label: "Customer must fill it in", type: "toggle" },
          { key: "width", label: "Width (two-column form)", type: "select", options: [["full", "Full width"], ["half", "Half width"]] },
          { key: "type", label: "Kind (extra fields)", type: "select", options: [["text", "Short text"], ["textarea", "Long text"], ["select", "Choose from a list"], ["checkbox", "Tick box"]] },
          { key: "options", label: "Choices (for a list)", type: "strings", max: 20 },
        ],
      },
      { key: "requireConsent", label: "Ask customers to tick a box before ordering", type: "toggle" },
      { key: "consentText", label: "Text next to the box", type: "text", showIf: (p) => Boolean(p.requireConsent), placeholder: "আমি শর্তাবলী মেনে নিচ্ছি" },
    ],
    note: "Name, mobile number, address and delivery area are always asked and always required, so every order can be delivered. Put the summary, payment choice and order button inside the form.",
    style: ["spacing", "background", "border", "typography"],
  },
  order_summary: {
    label: "Order summary",
    category: "checkout",
    icon: Receipt,
    description: "The chosen products, discount, delivery and total to pay",
    keywords: ["summary", "total", "cart", "order", "price"],
    preset: { props: { title: "অর্ডারের সারাংশ", showImages: true } },
    fields: [
      { key: "title", label: "Title", type: "text", placeholder: "অর্ডারের সারাংশ" },
      { key: "showImages", label: "Show product photos", type: "toggle" },
      { key: "collapsibleOnMobile", label: "Let phone users fold it up", type: "toggle" },
    ],
    note: "Every amount is worked out by the server. Delivery appears once the customer chooses a delivery area.",
    style: ["spacing", "background", "border"],
  },
  delivery_charges: {
    label: "Delivery charge",
    category: "checkout",
    icon: Truck,
    description: "Shows the delivery charge for the chosen area, or free delivery",
    keywords: ["delivery", "shipping", "charge", "free delivery"],
    preset: { props: { label: "ডেলিভারি চার্জ" } },
    fields: [
      { key: "label", label: "Label", type: "text", placeholder: "ডেলিভারি চার্জ" },
      { key: "note", label: "Note under it (optional)", type: "textarea" },
    ],
    note: "The charge comes from your store's delivery settings. Offers with free delivery waive it.",
    style: ["spacing", "background", "border"],
  },
  payment_method_selector: {
    label: "Payment choice",
    category: "checkout",
    icon: CreditCard,
    description: "Lets the customer choose how to pay",
    keywords: ["payment", "pay", "cod", "cash", "online", "bkash"],
    preset: { props: { label: "পেমেন্ট পদ্ধতি", allowed: [] } },
    fields: [
      { key: "label", label: "Heading", type: "text", placeholder: "পেমেন্ট পদ্ধতি" },
      { key: "allowed", label: "Offer only these methods (cash_on_delivery, paystation). Empty = all your store has switched on.", type: "strings", max: 10, placeholder: "cash_on_delivery" },
    ],
    note: "Only payment methods that are switched on in your store and work for customers without an account can appear here: cash on delivery and the hosted online payment.",
    style: ["spacing", "background", "border"],
  },
  purchase_button: {
    label: "Order button",
    category: "checkout",
    icon: MousePointerClick,
    description: "The button that sends the order",
    keywords: ["button", "order", "buy", "submit", "confirm"],
    preset: { props: { text: "অর্ডার কনফার্ম করুন", showTotal: true, icon: "check-circle" } },
    fields: [
      { key: "text", label: "Button text", type: "text", placeholder: "অর্ডার কনফার্ম করুন" },
      { key: "subtext", label: "Text under the button", type: "text" },
      { key: "showTotal", label: "Show the total on the button", type: "toggle" },
      { key: "icon", label: "Icon (optional)", type: "icon", optional: true },
    ],
    note: "The order form needs one of these inside it before the page can be published.",
    style: ["background", "typography", "border", "spacing"],
  },
  order_confirmation: {
    label: "Order confirmation",
    category: "checkout",
    icon: PackageCheck,
    description: "Thank-you panel shown after an order is placed",
    keywords: ["thank you", "confirmation", "success", "done", "order placed"],
    preset: { props: { title: "ধন্যবাদ! আপনার অর্ডার গ্রহণ করা হয়েছে", message: "আমাদের প্রতিনিধি শীঘ্রই আপনাকে কল করে অর্ডার কনফার্ম করবেন।", showOrderCode: true, showSummary: true } },
    fields: [
      { key: "title", label: "Title", type: "text" },
      { key: "message", label: "Message", type: "textarea" },
      { key: "showOrderCode", label: "Show the order number", type: "toggle" },
      { key: "showSummary", label: "Show what was ordered", type: "toggle" },
      { key: "ctaText", label: "Button text (optional)", type: "text" },
      { key: "ctaLink", label: "Button link", type: "link", showIf: (p) => Boolean(p.ctaText) },
    ],
    note: "Hidden on the live page until an order has been placed. If you leave it out, the form shows a standard confirmation.",
    style: ["spacing", "background", "border"],
  },

  // ── Layout ──────────────────────────────────────────────────────────────
  section: {
    label: "Section",
    category: "layout",
    icon: LayoutTemplate,
    description: "A full-width band that holds other blocks",
    keywords: ["row", "band", "wrapper"],
    preset: { children: [] },
    fields: [
      { key: "contentWidth", label: "Content width", type: "select", options: [["boxed", "Boxed (centred)"], ["full", "Full width"]] },
      { key: "backgroundImage", label: "Background image", type: "image" },
      { key: "backgroundOverlay", label: "Image overlay colour", type: "color", showIf: (p) => Boolean(p.backgroundImage?.url) },
      { key: "tag", label: "HTML tag", type: "select", options: [["section", "Section"], ["header", "Header"], ["footer", "Footer"]] },
    ],
    style: ["spacing", "background", "border", "sectionLayout", "size"],
  },
  container: {
    label: "Container",
    category: "layout",
    icon: Square,
    description: "Groups blocks in a row or a column",
    keywords: ["group", "flex", "row", "box"],
    preset: { children: [] },
    fields: [
      { key: "direction", label: "Direction", type: "select", options: [["vertical", "Stacked"], ["horizontal", "Side by side"]] },
      { key: "wrap", label: "Wrap to next line", type: "toggle", showIf: (p) => p.direction === "horizontal" },
    ],
    style: ["layout", "spacing", "background", "border", "size"],
  },
  columns: {
    label: "Columns",
    category: "layout",
    icon: Columns2,
    description: "Two to six columns that stack on phones",
    keywords: ["split", "side by side", "layout"],
    preset: {
      children: [
        { type: "column", children: [] },
        { type: "column", children: [] },
      ],
    },
    fields: [
      { key: "__columns", label: "Columns", type: "columns" },
      { key: "stackOn", label: "Stack columns on", type: "select", options: [["mobile", "Phones"], ["tablet", "Tablets and phones"], ["none", "Never"]] },
      { key: "reverseOnStack", label: "Reverse order when stacked", type: "toggle" },
    ],
    style: ["layout", "spacing", "background", "border"],
  },
  column: {
    label: "Column",
    category: "layout",
    icon: Square,
    hidden: true,
    fields: [],
    note: "Drop blocks into this column. Set its width under Style.",
    style: ["columnWidth", "layout", "spacing", "background", "border"],
  },
  grid: {
    label: "Grid",
    category: "layout",
    icon: LayoutGrid,
    description: "Places blocks in equal columns",
    keywords: ["cards", "tiles"],
    preset: { children: [] },
    fields: [],
    note: "Set the number of columns for each screen size under Style.",
    style: ["grid", "spacing", "background", "border"],
  },

  // ── Basic ───────────────────────────────────────────────────────────────
  heading: {
    label: "Heading",
    category: "basic",
    icon: Heading,
    keywords: ["title", "headline", "h1", "h2"],
    preset: { props: { text: "আকর্ষণীয় একটি শিরোনাম লিখুন", level: "h2" } },
    fields: [
      { key: "text", label: "Text", type: "text" },
      { key: "level", label: "Size", type: "select", options: [["h1", "H1 — page title"], ["h2", "H2 — section"], ["h3", "H3"], ["h4", "H4"], ["h5", "H5"], ["h6", "H6"]] },
      { key: "link", label: "Link (optional)", type: "link" },
    ],
    style: ["typography", "spacing", "background", "border", "size"],
  },
  text: {
    label: "Text",
    category: "basic",
    icon: Type,
    keywords: ["paragraph", "description"],
    preset: { props: { text: "এখানে আপনার পণ্যের বিস্তারিত লিখুন। গ্রাহক কেন এটি কিনবেন, সহজ ভাষায় বলুন।" } },
    fields: [{ key: "text", label: "Text", type: "textarea" }],
    style: ["typography", "spacing", "background", "border", "size"],
  },
  rich_text: {
    label: "Formatted text",
    category: "basic",
    icon: FileText,
    keywords: ["rich text", "bold", "list", "paragraph"],
    preset: { props: { html: "<p>এখানে <strong>ফরম্যাট করা</strong> লেখা লিখুন।</p>" } },
    fields: [{ key: "html", label: "Text", type: "richtext" }],
    style: ["typography", "spacing", "background", "border", "size"],
  },
  image: {
    label: "Image",
    category: "basic",
    icon: Image,
    keywords: ["photo", "picture"],
    preset: {},
    fields: [
      { key: "image", label: "Image", type: "image" },
      { key: "caption", label: "Caption", type: "text" },
      { key: "link", label: "Link (optional)", type: "link" },
    ],
    style: ["image", "align", "spacing", "border"],
  },
  image_gallery: {
    label: "Image gallery",
    category: "basic",
    icon: Images,
    keywords: ["photos", "carousel", "slider"],
    preset: { props: { images: [], layout: "grid" } },
    fields: [
      {
        key: "images",
        label: "Images",
        type: "list",
        item: "gallery_image",
        itemLabel: (item, index) => item.caption || `Image ${index + 1}`,
        newItem: () => ({}),
        fields: [
          { key: "image", label: "Image", type: "image" },
          { key: "caption", label: "Caption", type: "text" },
        ],
      },
      { key: "layout", label: "Layout", type: "select", options: [["grid", "Grid"], ["carousel", "Swipe carousel"]] },
      { key: "lightbox", label: "Open full image on tap", type: "toggle" },
    ],
    style: ["gallery", "spacing"],
  },
  video: {
    label: "Video",
    category: "basic",
    icon: Clapperboard,
    keywords: ["youtube", "facebook", "vimeo"],
    preset: { props: { provider: "youtube" } },
    fields: [
      { key: "url", label: "Video link", type: "video", providerKey: "provider" },
      { key: "poster", label: "Cover image (optional)", type: "image" },
    ],
    style: ["spacing", "size"],
  },
  button: {
    label: "Button",
    category: "basic",
    icon: MousePointerClick,
    keywords: ["cta", "link", "order"],
    preset: { props: { text: "এখনই অর্ডার করুন", action: "scroll", variant: "solid" } },
    fields: [
      { key: "text", label: "Button text", type: "text" },
      { key: "action", label: "When tapped", type: "action", linkKey: "link", actions: ["scroll", "link", "call", "whatsapp"] },
      { key: "target", label: "Open in", type: "select", options: [["_self", "Same tab"], ["_blank", "New tab"]], showIf: (p) => p.action === "link" },
      { key: "icon", label: "Icon", type: "icon", optional: true },
      { key: "variant", label: "Look", type: "select", options: [["solid", "Filled"], ["outline", "Outline"], ["ghost", "Text only"]] },
    ],
    style: ["button", "align", "spacing", "border"],
  },
  icon: {
    label: "Icon",
    category: "basic",
    icon: Smile,
    keywords: ["symbol"],
    preset: { props: { icon: "star" } },
    fields: [
      { key: "icon", label: "Icon", type: "icon" },
      { key: "link", label: "Link (optional)", type: "link" },
    ],
    style: ["icon", "align", "spacing"],
  },
  divider: {
    label: "Divider",
    category: "basic",
    icon: Minus,
    keywords: ["line", "separator", "hr"],
    preset: {},
    fields: [{ key: "lineStyle", label: "Line", type: "select", options: [["solid", "Solid"], ["dashed", "Dashed"], ["dotted", "Dotted"]] }],
    style: ["divider", "spacing"],
  },
  spacer: {
    label: "Spacer",
    category: "basic",
    icon: MoveVertical,
    keywords: ["gap", "space", "empty"],
    preset: {},
    fields: [],
    note: "Set the height for each screen size under Style.",
    style: ["height"],
  },
  badge: {
    label: "Badge",
    category: "basic",
    icon: Tag,
    keywords: ["label", "pill", "tag"],
    preset: { props: { text: "সীমিত সময়ের অফার", variant: "soft" } },
    fields: [
      { key: "text", label: "Text", type: "text" },
      { key: "variant", label: "Look", type: "select", options: [["soft", "Soft"], ["solid", "Filled"], ["outline", "Outline"]] },
    ],
    style: ["badge", "align", "spacing"],
  },
  list: {
    label: "List",
    category: "basic",
    icon: List,
    keywords: ["bullets", "points", "features"],
    preset: {
      props: {
        items: [{ text: "উন্নত মানের উপকরণে তৈরি" }, { text: "সহজে ব্যবহার করা যায়" }, { text: "পরিবারের সবার জন্য উপযোগী" }],
        icon: "check-circle",
      },
    },
    fields: [
      {
        key: "items",
        label: "Items",
        type: "list",
        item: "list_item",
        itemLabel: (item, index) => item.text || `Item ${index + 1}`,
        newItem: () => ({ text: "" }),
        fields: [
          { key: "text", label: "Text", type: "text" },
          { key: "icon", label: "Icon (optional)", type: "icon", optional: true },
        ],
      },
      { key: "ordered", label: "Numbered list", type: "toggle" },
      { key: "icon", label: "Bullet icon", type: "icon", showIf: (p) => !p.ordered },
    ],
    style: ["typography", "spacing", "background"],
  },

  // ── Marketing ───────────────────────────────────────────────────────────
  promo_hero: {
    label: "Promotional hero",
    category: "marketing",
    icon: Sparkles,
    description: "Headline, offer text, image and an order button",
    keywords: ["banner", "header", "offer"],
    preset: {
      props: {
        eyebrow: "বিশেষ অফার",
        headline: "আপনার পণ্যের নাম ও অফার এখানে লিখুন",
        subheadline: "পণ্যটি কোন সমস্যার সমাধান করে, এক-দুই লাইনে লিখুন।",
        ctaText: "এখনই অর্ডার করুন",
        ctaAction: "scroll",
        secondaryText: "সারা বাংলাদেশে ক্যাশ অন ডেলিভারি",
        layout: "image_right",
      },
    },
    fields: [
      { key: "eyebrow", label: "Small line above", type: "text" },
      { key: "headline", label: "Headline", type: "text" },
      { key: "subheadline", label: "Sub-headline", type: "textarea" },
      { key: "ctaText", label: "Button text", type: "text" },
      { key: "ctaAction", label: "Button goes to", type: "action", linkKey: "ctaLink", actions: CTA_ACTIONS },
      { key: "secondaryText", label: "Note under the button", type: "text" },
      {
        key: "layout",
        label: "Layout",
        type: "select",
        options: [["image_right", "Image on the right"], ["image_left", "Image on the left"], ["image_top", "Image on top"], ["background", "Image behind the text"]],
      },
      { key: "image", label: "Image", type: "image", showIf: (p) => p.layout !== "background" },
      { key: "backgroundImage", label: "Background image", type: "image", showIf: (p) => p.layout === "background" },
    ],
    style: ["heroMedia", "typography", "spacing", "background", "border"],
  },
  image_banner: {
    label: "Image banner",
    category: "marketing",
    icon: GalleryHorizontal,
    description: "A wide banner, with an optional phone version",
    keywords: ["banner", "poster"],
    preset: {},
    fields: [
      { key: "image", label: "Banner image", type: "image" },
      { key: "mobileImage", label: "Phone image (optional)", type: "image" },
      { key: "link", label: "Link (optional)", type: "link" },
    ],
    style: ["bannerImage", "spacing"],
  },
  video_hero: {
    label: "Video hero",
    category: "marketing",
    icon: Film,
    description: "Headline with a video and an order button",
    keywords: ["video", "demo"],
    preset: { props: { headline: "ভিডিওতে দেখুন কীভাবে কাজ করে", ctaText: "এখনই অর্ডার করুন", ctaAction: "scroll", provider: "youtube" } },
    fields: [
      { key: "headline", label: "Headline", type: "text" },
      { key: "subheadline", label: "Sub-headline", type: "textarea" },
      { key: "url", label: "Video link", type: "video", providerKey: "provider" },
      { key: "poster", label: "Cover image (optional)", type: "image" },
      { key: "ctaText", label: "Button text", type: "text" },
      { key: "ctaAction", label: "Button goes to", type: "action", linkKey: "ctaLink", actions: CTA_ACTIONS },
    ],
    style: ["typography", "spacing", "background"],
  },
  announcement_ribbon: {
    label: "Announcement ribbon",
    category: "marketing",
    icon: Megaphone,
    description: "A thin coloured strip for a short message",
    keywords: ["notice", "bar", "marquee", "strip"],
    preset: { props: { text: "আজকের অর্ডারে বিশেষ ছাড় চলছে", icon: "zap" } },
    fields: [
      { key: "text", label: "Message", type: "text" },
      { key: "icon", label: "Icon", type: "icon", optional: true },
      { key: "link", label: "Link (optional)", type: "link" },
      { key: "scrolling", label: "Scroll the message", type: "toggle" },
    ],
    style: ["typography", "background", "spacing"],
  },
  countdown_timer: {
    label: "Countdown timer",
    category: "marketing",
    icon: Timer,
    description: "Counts down to the real end of your offer",
    keywords: ["timer", "deadline", "clock", "offer ends"],
    preset: { props: { heading: "অফার শেষ হতে বাকি" } },
    fields: [
      { key: "heading", label: "Heading", type: "text" },
      { key: "endsAt", label: "Offer ends at", type: "datetime", help: "Use the real end of your offer. The timer does not restart for each visitor." },
      { key: "expiredAction", label: "After it ends", type: "select", options: [["hide", "Hide the timer"], ["message", "Show a message"]] },
      { key: "expiredMessage", label: "Message after the end", type: "text", showIf: (p) => p.expiredAction === "message" },
      { key: "labelDays", label: "Label: days", type: "text" },
      { key: "labelHours", label: "Label: hours", type: "text" },
      { key: "labelMinutes", label: "Label: minutes", type: "text" },
      { key: "labelSeconds", label: "Label: seconds", type: "text" },
    ],
    style: ["spacing", "background", "border"],
  },
  usp_highlights: {
    label: "Highlights",
    category: "marketing",
    icon: BadgeCheck,
    description: "Icons with short selling points",
    keywords: ["usp", "features", "icons"],
    preset: {
      props: {
        items: [
          { icon: "truck", title: "দ্রুত ডেলিভারি" },
          { icon: "banknote", title: "ক্যাশ অন ডেলিভারি" },
          { icon: "shield-check", title: "নিরাপদ কেনাকাটা" },
          { icon: "headset", title: "হেল্পলাইন" },
        ],
        layout: "row",
      },
    },
    fields: [
      { key: "items", label: "Highlights", type: "list", item: "icon_item", itemLabel: (item, i) => item.title || `Highlight ${i + 1}`, newItem: () => ({ icon: "check-circle", title: "" }), fields: ICON_ITEM_FIELDS },
      { key: "layout", label: "Look", type: "select", options: [["row", "Simple"], ["grid", "Cards"]] },
    ],
    style: ["grid", "typography", "spacing", "background", "border"],
  },
  trust_icons: {
    label: "Trust icons",
    category: "marketing",
    icon: ShieldCheck,
    description: "Payment, delivery and quality promises",
    keywords: ["trust", "badges", "delivery", "payment"],
    preset: {
      props: {
        items: [
          { icon: "banknote", title: "ক্যাশ অন ডেলিভারি" },
          { icon: "truck", title: "সারা দেশে ডেলিভারি" },
          { icon: "badge-check", title: "মান নিশ্চিত" },
        ],
      },
    },
    fields: [
      {
        key: "items",
        label: "Icons",
        type: "list",
        item: "icon_item",
        itemLabel: (item, i) => item.title || `Icon ${i + 1}`,
        newItem: () => ({ icon: "check-circle", title: "" }),
        fields: [ICON_ITEM_FIELDS[0], ICON_ITEM_FIELDS[1]],
      },
    ],
    style: ["grid", "typography", "spacing", "background", "border"],
  },
  benefits_grid: {
    label: "Benefits",
    category: "marketing",
    icon: LayoutList,
    description: "Cards explaining why the product helps",
    keywords: ["benefits", "features", "why"],
    preset: {
      props: {
        heading: "কেন এই পণ্যটি আপনার জন্য?",
        items: [
          { icon: "zap", title: "প্রথম সুবিধা", text: "" },
          { icon: "leaf", title: "দ্বিতীয় সুবিধা", text: "" },
          { icon: "award", title: "তৃতীয় সুবিধা", text: "" },
        ],
      },
    },
    fields: [
      { key: "heading", label: "Heading", type: "text" },
      { key: "subheading", label: "Introduction", type: "textarea" },
      {
        key: "items",
        label: "Benefits",
        type: "list",
        item: "benefit_item",
        itemLabel: (item, i) => item.title || `Benefit ${i + 1}`,
        newItem: () => ({ icon: "check", title: "", text: "" }),
        fields: [
          { key: "icon", label: "Icon", type: "icon" },
          { key: "image", label: "Image instead of icon", type: "image" },
          { key: "title", label: "Title", type: "text" },
          { key: "text", label: "Explanation", type: "textarea" },
        ],
      },
    ],
    style: ["grid", "typography", "spacing", "background", "border"],
  },
  testimonial_cards: {
    label: "Testimonials",
    category: "marketing",
    icon: MessageSquareQuote,
    description: "Feedback from real customers",
    keywords: ["reviews", "feedback", "social proof"],
    preset: { props: { heading: "গ্রাহকদের মতামত", items: [{ name: "", text: "", rating: 5 }] } },
    notice: "Only publish feedback your customers really gave.",
    fields: [
      { key: "heading", label: "Heading", type: "text" },
      {
        key: "items",
        label: "Testimonials",
        type: "list",
        item: "testimonial_item",
        itemLabel: (item, i) => item.name || `Testimonial ${i + 1}`,
        newItem: () => ({ name: "", text: "", rating: 5 }),
        fields: [
          { key: "name", label: "Customer name", type: "text" },
          { key: "role", label: "City or detail", type: "text" },
          { key: "text", label: "What they said", type: "textarea" },
          { key: "rating", label: "Stars (1–5)", type: "number", min: 1, max: 5, step: 1 },
          { key: "avatar", label: "Photo (optional)", type: "image" },
        ],
      },
      { key: "layout", label: "Layout", type: "select", options: [["grid", "Grid"], ["carousel", "Swipe carousel"]] },
    ],
    style: ["grid", "typography", "spacing", "background"],
  },
  faq_accordion: {
    label: "FAQ",
    category: "marketing",
    icon: CircleHelp,
    description: "Questions that open to show the answer",
    keywords: ["questions", "accordion", "help"],
    preset: {
      props: {
        heading: "সচরাচর জিজ্ঞাসা",
        items: [
          { question: "ডেলিভারি পেতে কত দিন লাগে?", answer: "" },
          { question: "পণ্য হাতে পেয়ে টাকা দেওয়া যাবে?", answer: "" },
          { question: "পণ্য পছন্দ না হলে কী করব?", answer: "" },
        ],
        openFirst: true,
      },
    },
    fields: [
      { key: "heading", label: "Heading", type: "text" },
      {
        key: "items",
        label: "Questions",
        type: "list",
        item: "faq_item",
        itemLabel: (item, i) => item.question || `Question ${i + 1}`,
        newItem: () => ({ question: "", answer: "" }),
        fields: [
          { key: "question", label: "Question", type: "text" },
          { key: "answer", label: "Answer", type: "textarea" },
        ],
      },
      { key: "openFirst", label: "First answer open", type: "toggle" },
    ],
    style: ["typography", "spacing", "background"],
  },
  before_after_slider: {
    label: "Before / after",
    category: "marketing",
    icon: SquareSplitHorizontal,
    description: "Drag to compare two photos",
    keywords: ["compare", "result", "slider"],
    preset: { props: { beforeLabel: "আগে", afterLabel: "পরে", initial: 50 } },
    fields: [
      { key: "before", label: "Before image", type: "image" },
      { key: "after", label: "After image", type: "image" },
      { key: "beforeLabel", label: "Before label", type: "text" },
      { key: "afterLabel", label: "After label", type: "text" },
      { key: "initial", label: "Starting position (%)", type: "number", min: 0, max: 100, step: 1 },
    ],
    style: ["spacing"],
  },
  comparison_table: {
    label: "Comparison table",
    category: "marketing",
    icon: Table,
    description: "Your product against the alternatives",
    keywords: ["compare", "table", "vs"],
    preset: {
      props: {
        heading: "তুলনা করে দেখুন",
        headers: ["আমাদের পণ্য", "সাধারণ পণ্য"],
        rows: [{ label: "বৈশিষ্ট্য", values: ["✓", "✗"] }],
        highlightColumn: 1,
      },
    },
    fields: [
      { key: "heading", label: "Heading", type: "text" },
      { key: "headers", label: "Column names", type: "strings", max: 6, placeholder: "Column name" },
      {
        key: "rows",
        label: "Rows",
        type: "list",
        item: "comparison_row",
        itemLabel: (item, i) => item.label || `Row ${i + 1}`,
        newItem: () => ({ label: "", values: [] }),
        fields: [
          { key: "label", label: "Feature", type: "text" },
          { key: "values", label: "Values (✓ / ✗ or text), one per column", type: "strings", max: 6, placeholder: "✓" },
        ],
      },
      { key: "highlightColumn", label: "Highlight column (0 = none)", type: "number", min: 0, max: 6, step: 1 },
    ],
    style: ["typography", "spacing", "background"],
  },
  guarantee_section: {
    label: "Guarantee",
    category: "marketing",
    icon: Handshake,
    description: "Your return, warranty or quality promise",
    keywords: ["policy", "warranty", "return", "promise"],
    preset: { props: { heading: "আমাদের প্রতিশ্রুতি", icon: "shield-check", text: "" } },
    fields: [
      { key: "icon", label: "Icon", type: "icon" },
      { key: "heading", label: "Heading", type: "text" },
      { key: "text", label: "Your policy", type: "textarea" },
      {
        key: "items",
        label: "Points",
        type: "list",
        item: "icon_item",
        itemLabel: (item, i) => item.title || `Point ${i + 1}`,
        newItem: () => ({ icon: "check-circle", title: "" }),
        fields: [ICON_ITEM_FIELDS[0], ICON_ITEM_FIELDS[1]],
      },
    ],
    style: ["typography", "spacing", "background", "border"],
  },
  sticky_cta: {
    label: "Sticky order bar",
    category: "marketing",
    icon: PanelBottom,
    description: "A bar that stays on screen while customers scroll",
    keywords: ["sticky", "floating", "bar", "cta"],
    preset: { props: { text: "অর্ডার করুন", subtext: "ক্যাশ অন ডেলিভারি", action: "scroll", showOn: "mobile" } },
    fields: [
      { key: "subtext", label: "Message", type: "text" },
      { key: "text", label: "Button text", type: "text" },
      { key: "action", label: "Button goes to", type: "action", linkKey: "link", actions: ["scroll", "link", "whatsapp"] },
      { key: "showOn", label: "Show on", type: "select", options: [["mobile", "Phones only"], ["all", "All screens"], ["desktop", "Computers only"]] },
      { key: "position", label: "Position", type: "select", options: [["bottom", "Bottom"], ["top", "Top"]] },
    ],
    style: [],
  },
  floating_whatsapp: {
    label: "WhatsApp button",
    category: "marketing",
    icon: MessageCircle,
    description: "A floating button that opens a WhatsApp chat",
    keywords: ["chat", "whatsapp", "contact"],
    preset: { props: { phone: "", message: "আমি অফারটি সম্পর্কে জানতে চাই", position: "right" } },
    fields: [
      { key: "phone", label: "WhatsApp number", type: "phone", placeholder: "01XXXXXXXXX" },
      { key: "message", label: "Pre-filled message", type: "textarea" },
      { key: "label", label: "Button label (optional)", type: "text" },
      { key: "position", label: "Corner", type: "select", options: [["right", "Bottom right"], ["left", "Bottom left"]] },
    ],
    style: [],
  },
};

/**
 * Block types the merchant can add: known here, rendered by BLOCKS and
 * present in the server's registry.
 */
export function availableBlocks(serverRegistry) {
  return Object.entries(EDITOR_BLOCKS)
    .filter(([type, meta]) => !meta.hidden && BLOCKS[type] && serverRegistry?.blocks?.[type])
    .map(([type, meta]) => ({ type, ...meta }));
}

export function blockMeta(type) {
  return EDITOR_BLOCKS[type] ?? { label: type.replace(/_/g, " "), fields: [], style: [] };
}

/** Name shown in layers and breadcrumbs: the layer label, else a text snippet, else the block name. */
export function nodeTitle(node) {
  const meta = blockMeta(node.type);
  const label = node.advanced?.label;
  if (label) return label;
  const props = node.props ?? {};
  const snippet = props.text || props.headline || props.heading || props.title;
  return typeof snippet === "string" && snippet.trim() ? `${meta.label}: ${snippet.trim().slice(0, 28)}` : meta.label;
}
