import { CheckoutForm, DeliveryCharges, OrderConfirmation, OrderSummary, PaymentMethodSelector, PurchaseButton } from "./checkout.jsx";
import { Badge, ButtonBlock, Divider, Heading, IconBlock, ImageBlock, ListBlock, Paragraph, RichText, Spacer } from "./content.jsx";
import { Column, Columns, Container, Grid, Section } from "./layout.jsx";
import {
  AnnouncementRibbon, BeforeAfterSlider, BenefitsGrid, ComparisonTable, CountdownTimer, FaqAccordion,
  FloatingWhatsApp, GuaranteeSection, ImageBanner, PromoHero, StickyCta, TestimonialCards, TrustIcons,
  UspHighlights, VideoHero,
} from "./marketing.jsx";
import { ImageGallery, VideoBlock } from "./media.jsx";
import {
  BundleOfferSelector, DiscountBadge, DiscountSummary, ProductCard, ProductGallery, ProductOfferSelector, ProductPrice,
  ProductVariations, QuantitySelector,
} from "./products.jsx";

/**
 * Renderer for every block type the page can display, with the values used
 * when a setting was never set. Texts default to empty: sample copy is added
 * by the editor when a block is inserted, so a cleared text stays cleared.
 *
 * Block types that exist in the server registry but are not listed here
 * (the coupon box and customer reviews) are not offered in the editor: funnel
 * offers are not combined with coupons.
 */
export const BLOCKS = {
  section: { Component: Section, defaults: { contentWidth: "boxed", tag: "section" } },
  container: { Component: Container, defaults: { direction: "vertical", wrap: true } },
  grid: { Component: Grid, defaults: {} },
  columns: { Component: Columns, defaults: { stackOn: "mobile", reverseOnStack: false } },
  column: { Component: Column, defaults: {} },

  heading: { Component: Heading, defaults: { level: "h2", text: "" } },
  text: { Component: Paragraph, defaults: { text: "" } },
  rich_text: { Component: RichText, defaults: { html: "" } },
  image: { Component: ImageBlock, defaults: {} },
  image_gallery: { Component: ImageGallery, defaults: { images: [], layout: "grid", lightbox: true } },
  video: { Component: VideoBlock, defaults: { provider: "youtube", controls: true } },
  button: { Component: ButtonBlock, defaults: { text: "", action: "link", target: "_self", variant: "solid" } },
  icon: { Component: IconBlock, defaults: { icon: "star" } },
  divider: { Component: Divider, defaults: { lineStyle: "solid" } },
  spacer: { Component: Spacer, defaults: {} },
  badge: { Component: Badge, defaults: { text: "", variant: "soft" } },
  list: { Component: ListBlock, defaults: { items: [], ordered: false, icon: "check-circle" } },

  promo_hero: { Component: PromoHero, defaults: { layout: "image_right", ctaAction: "scroll" } },
  image_banner: { Component: ImageBanner, defaults: {} },
  video_hero: { Component: VideoHero, defaults: { provider: "youtube", ctaAction: "scroll" } },
  announcement_ribbon: { Component: AnnouncementRibbon, defaults: { scrolling: false } },
  countdown_timer: {
    Component: CountdownTimer,
    defaults: {
      expiredAction: "hide",
      labelDays: "দিন",
      labelHours: "ঘণ্টা",
      labelMinutes: "মিনিট",
      labelSeconds: "সেকেন্ড",
    },
  },
  usp_highlights: { Component: UspHighlights, defaults: { items: [], layout: "row" } },
  trust_icons: { Component: TrustIcons, defaults: { items: [] } },
  benefits_grid: { Component: BenefitsGrid, defaults: { items: [] } },
  testimonial_cards: { Component: TestimonialCards, defaults: { items: [], layout: "grid" } },
  faq_accordion: { Component: FaqAccordion, defaults: { items: [], openFirst: true } },
  before_after_slider: { Component: BeforeAfterSlider, defaults: { initial: 50 } },
  comparison_table: { Component: ComparisonTable, defaults: { headers: [], rows: [], highlightColumn: 1 } },
  sticky_cta: {
    Component: StickyCta,
    defaults: { action: "scroll", position: "bottom", showOn: "all", hideNearCheckout: true },
  },
  floating_whatsapp: { Component: FloatingWhatsApp, defaults: { position: "right" } },
  guarantee_section: { Component: GuaranteeSection, defaults: { icon: "shield-check", items: [] } },

  product_card: {
    Component: ProductCard,
    defaults: { layout: "vertical", showDescription: false, showStock: true, showOriginalPrice: true, showSavings: true, ctaAction: "select" },
  },
  product_offer_selector: {
    Component: ProductOfferSelector,
    defaults: {
      selectionMode: "single", cardLayout: "vertical", showDescription: false, showOriginalPrice: true, showSavings: true,
      showStock: false, allowQuantity: false,
    },
  },
  bundle_offer_selector: { Component: BundleOfferSelector, defaults: { layout: "stack", showSavings: true } },
  product_gallery: { Component: ProductGallery, defaults: { layout: "thumbs_bottom" } },
  product_price: { Component: ProductPrice, defaults: { showOriginal: true, showSavings: true } },
  product_variations: { Component: ProductVariations, defaults: { display: "buttons" } },
  quantity_selector: { Component: QuantitySelector, defaults: {} },
  discount_badge: { Component: DiscountBadge, defaults: { shape: "pill" } },
  discount_summary: { Component: DiscountSummary, defaults: { showItems: true, showTax: true } },

  checkout_form: { Component: CheckoutForm, defaults: { layout: "one_column", requireConsent: false } },
  order_summary: { Component: OrderSummary, defaults: { showImages: true, collapsibleOnMobile: false } },
  delivery_charges: { Component: DeliveryCharges, defaults: {} },
  payment_method_selector: { Component: PaymentMethodSelector, defaults: { allowed: [] } },
  purchase_button: { Component: PurchaseButton, defaults: { showTotal: true } },
  order_confirmation: { Component: OrderConfirmation, defaults: { showOrderCode: true, showSummary: true } },
};
