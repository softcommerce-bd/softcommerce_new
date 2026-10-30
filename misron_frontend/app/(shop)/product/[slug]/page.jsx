// // app/products/[slug]/page.jsx (Server Component)
// import { notFound } from 'next/navigation';
// import Details from "@/app/(pages)/Details";
// import ImageGallery from "@/app/(pages)/ImageGallery";
// import Description from "@/app/components/layout/Description";
// import YouMayAlsoLike from "@/app/components/layout/YouMayAlsoLike";
// import Breadcrumb from "@/app/components/layout/Breadcrumb";
// import FloatingVideoPlayer from '@/app/components/layout/FloatingVideoPlayer';
// import ProductDetailsVideoModal from '@/components/ProductDetailsVideoModal';
// import ProductViewTracker from '@/components/GTM/ProductViewTracker';
// import ScrollToTop from '@/app/components/ScrollToTop';

// // Helper to get API URL with fallback
// function getApiUrl() {
//   const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || '';
//   const apiUrl = process.env.NEXT_PUBLIC_API_URL;
  
//   if (apiUrl && apiUrl.startsWith('/')) {
//     return `${siteUrl}${apiUrl}`;
//   }
  
//   return apiUrl || `${siteUrl}/api/v2`;
// }

// // Generate metadata for this specific product page
// export async function generateMetadata({ params }) {
//   const { slug } = await params;
  
//   try {
//     const apiUrl = getApiUrl();
//     const url = `${apiUrl}/products/${slug}/`;
    
//     const response = await fetch(url, {
//       next: { revalidate: 30 }, // stock and availability come from this response; an hour-old copy could offer a sold-out product
//       headers: {
//         'Accept': 'application/json',
//       }
//     });
    
//     if (!response.ok) {
//       return {
//         title: 'Product Not Found',
//         description: 'The requested product could not be found',
//         robots: { index: false },
//       };
//     }
    
//     const result = await response.json();
    
//     if (!result.success || !result.data || !result.data.length) {
//       return {
//         title: 'Product Not Found',
//         description: 'The requested product could not be found',
//         robots: { index: false },
//       };
//     }
    
//     const product = result.data[0];
    
//     const cleanDescription = product.meta_description || 
//                            product.short_description || 
//                            product.description?.replace(/<[^>]*>/g, '').substring(0, 160);
    
//     const mainImage = product.meta_img || product.thumbnail_image;
//     const price = product.calculable_price;
//     const currency = product.currency_symbol || "BDT";
//     const inStock = product.current_stock > 0;
    
//     return {
//       title: product.meta_title || product.name,
//       description: cleanDescription,
//       keywords: product.tags?.join(', ') || product.meta_keywords,
//       openGraph: {
//         title: product.meta_title || product.name,
//         description: cleanDescription,
//         images: [mainImage],
//         type: "website",
//         siteName: process.env.NEXT_PUBLIC_APP_NAME || "Soft Commerce",
//         locale: "en_US",
//       },
//       twitter: {
//         card: "summary_large_image",
//         title: product.meta_title || product.name,
//         description: cleanDescription,
//         images: [mainImage],
//       },
//       alternates: {
//         canonical: `/products/${slug}`,
//       },
//       other: {
//         'product:price:amount': price?.toString(),
//         'product:price:currency': currency,
//         'product:availability': inStock ? "in stock" : "out of stock",
//         'product:retailer_item_id': product.id?.toString(),
//       },
//     };
//   } catch (error) {
//     console.error('Error generating metadata:', error);
//     return {
//       title: 'Product',
//       description: 'View product details',
//     };
//   }
// }

// // Fetch product data for the page
// async function getProductData(slug) {
//   try {
//     const apiUrl = getApiUrl();
//     const url = `${apiUrl}/products/${slug}/`;
    
//     const response = await fetch(url, {
//       next: { revalidate: 30 }, // stock and availability come from this response; an hour-old copy could offer a sold-out product
//       headers: {
//         'Accept': 'application/json',
//       }
//     });
    
//     if (!response.ok) return null;
    
//     const result = await response.json();
    
//     if (result.success && result.data && result.data.length > 0) {
//       return result.data[0];
//     }
    
//     return null;
//   } catch (error) {
//     console.error('Error fetching product:', error);
//     return null;
//   }
// }

// // Page component - SERVER COMPONENT (NO window, NO dataLayer here!)
// export default async function ProductPage({ params }) {
//   const { slug } = await params;
//   const productData = await getProductData(slug);
  
//   if (!productData) {
//     notFound();
//   }
  
//   // Transform gallery images
//   const galleryImages = [];
  
//   if (productData.thumbnail_image) {
//     galleryImages.push({
//       id: 1,
//       src: productData.thumbnail_image,
//       alt: productData.name
//     });
//   }
  
//   if (productData.photos && Array.isArray(productData.photos)) {
//     productData.photos.forEach((photo, index) => {
//       const photoPath = typeof photo === 'string' ? photo : photo.path;
//       if (photoPath) {
//         galleryImages.push({
//           id: galleryImages.length + 1,
//           src: photoPath,
//           alt: `${productData.name} - Image ${index + 2}`
//         });
//       }
//     });
//   }
  
//   // Clean description for JSON-LD
//   const cleanDescription = productData.meta_description || 
//                           productData.short_description || 
//                           productData.description?.replace(/<[^>]*>/g, '').substring(0, 160);
  
//   // Check if custom video exists
//   const hasCustomVideo = productData?.custom_video && productData.custom_video.trim() !== '';
  
//   // NO window or dataLayer code here - that's all in ProductViewTracker client component
//   // console.log('under product page ',productData);
//   return (
//     <>
//     <ScrollToTop />
//       {/* Client component that handles GTM tracking */}
//       <ProductViewTracker product={productData} />
      
//       {/* JSON-LD Structured Data for Product */}
//       <script
//         type="application/ld+json"
//         dangerouslySetInnerHTML={{
//           __html: JSON.stringify({
//             "@context": "https://schema.org",
//             "@type": "Product",
//             "name": productData.name,
//             "description": cleanDescription,
//             "image": productData.meta_img || productData.thumbnail_image,
//             "sku": productData.id?.toString(),
//             "brand": {
//               "@type": "Brand",
//               "name": productData.brand?.name || productData.shop_name
//             },
//             "offers": {
//               "@type": "Offer",
//               "price": productData.calculable_price,
//               "priceCurrency": productData.currency_symbol || "BDT",
//               "availability": productData.current_stock > 0 ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
//               "url": `${process.env.NEXT_PUBLIC_SITE_URL || ''}/products/${slug}`
//             },
//             ...(productData.rating > 0 && {
//               "aggregateRating": {
//                 "@type": "AggregateRating",
//                 "ratingValue": productData.rating,
//                 "reviewCount": productData.rating_count || 0
//               }
//             })
//           })
//         }}
//       />
      
//       <section className="p-2 min-h-screen container mx-auto mt-10">
//         <Breadcrumb productName={productData.name} />
        
//         <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
//           <ImageGallery galleryImages={galleryImages} />
//           <Details productData={productData} />
//         </div>
        
//         <Description productData={productData} />
        
//         <YouMayAlsoLike 
//           currentProductId={slug} 
//           category={productData.category?.id}
//         />
//       </section>
      
//       {/* Video Modal Component */}
//       <ProductDetailsVideoModal
//         videoUrl={productData.custom_video}
//         productName={productData.name}
//       />
      
//       {/* Floating Video Player */}
//       {hasCustomVideo && (
//         <FloatingVideoPlayer 
//           key={`video-${slug}`}
//           videoUrl={productData.custom_video}
//           productName={productData.name}
//         />
//       )}
//     </>
//   );
// }

// app/products/[slug]/page.jsx (Server Component)
import { notFound } from 'next/navigation';
import ProductGalleryWrapper from "@/app/(pages)/ProductGalleryWrapper";
import Description from "@/app/components/layout/Description";
import YouMayAlsoLike from "@/app/components/layout/YouMayAlsoLike";
import Breadcrumb from "@/app/components/layout/Breadcrumb";
import FloatingVideoPlayer from '@/app/components/layout/FloatingVideoPlayer';
import ProductDetailsVideoModal from '@/components/ProductDetailsVideoModal';
import ProductViewTracker from '@/components/GTM/ProductViewTracker';
import ScrollToTop from '@/app/components/ScrollToTop';

// Helper to get API URL with fallback
function getApiUrl() {
  // Use the full API URL from environment variable
  const apiUrl = process.env.NEXT_PUBLIC_API_URL;
  
  // If apiUrl is a relative path (starts with /), prepend the site URL
  if (apiUrl && apiUrl.startsWith('/')) {
    const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || '';
    return `${siteUrl}${apiUrl}`;
  }
  
  // If apiUrl is already a full URL, use it directly
  if (apiUrl && (apiUrl.startsWith('http://') || apiUrl.startsWith('https://'))) {
    return apiUrl;
  }
  
  // Fallback
  return '';
}

// Generate metadata for this specific product page
export async function generateMetadata({ params }) {
  // Handle params as Promise (Next.js 14+) or plain object
  const resolvedParams = params instanceof Promise ? await params : params;
  const { slug } = resolvedParams;
  
  try {
    const apiUrl = getApiUrl();
    const url = `${apiUrl}/products/${slug}`;
    
    // console.log('🔍 generateMetadata - Fetching:', url);
    
    const response = await fetch(url, {
      next: { revalidate: 30 }, // stock and availability come from this response; an hour-old copy could offer a sold-out product
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      }
    });
    
    if (!response.ok) {
      console.error(`❌ generateMetadata - API returned status ${response.status} for ${url}`);
      return {
        title: 'Product Not Found',
        description: 'The requested product could not be found',
        robots: { index: false },
      };
    }
    
    // Check content type to ensure we're getting JSON
    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      console.error(`❌ generateMetadata - Unexpected content type: ${contentType} for ${url}`);
      return {
        title: 'Product Not Found',
        description: 'The requested product could not be found',
        robots: { index: false },
      };
    }
    
    // Get text first to check if it's valid JSON
    const text = await response.text();
    
    // Check if the response looks like HTML (starts with <)
    if (text.trim().startsWith('<')) {
      console.error('❌ generateMetadata - Received HTML instead of JSON');
      console.error('First 200 chars:', text.substring(0, 200));
      return {
        title: 'Product',
        description: 'View product details',
      };
    }
    
    let result;
    try {
      result = JSON.parse(text);
    } catch (parseError) {
      console.error('❌ generateMetadata - JSON parse error:', parseError.message);
      console.error('Response text (first 500 chars):', text.substring(0, 500));
      return {
        title: 'Product',
        description: 'View product details',
      };
    }
    
    if (!result.success || !result.data || !result.data.length) {
      return {
        title: 'Product Not Found',
        description: 'The requested product could not be found',
        robots: { index: false },
      };
    }
    
    const product = result.data[0];
    
    const cleanDescription = product.meta_description || 
                           product.short_description || 
                           product.description?.replace(/<[^>]*>/g, '').substring(0, 160);
    
    const mainImage = product.meta_img || product.thumbnail_image;
    const price = product.calculable_price;
    const currency = product.currency_symbol || "BDT";
    const inStock = product.current_stock > 0;
    
    return {
      title: product.meta_title || product.name,
      description: cleanDescription,
      keywords: product.tags?.join(', ') || product.meta_keywords,
      openGraph: {
        title: product.meta_title || product.name,
        description: cleanDescription,
        images: [mainImage],
        type: "website",
        siteName: process.env.NEXT_PUBLIC_APP_NAME || "Soft Commerce",
        locale: "en_US",
      },
      twitter: {
        card: "summary_large_image",
        title: product.meta_title || product.name,
        description: cleanDescription,
        images: [mainImage],
      },
      alternates: {
        canonical: `/products/${slug}`,
      },
      other: {
        'product:price:amount': price?.toString(),
        'product:price:currency': currency,
        'product:availability': inStock ? "in stock" : "out of stock",
        'product:retailer_item_id': product.id?.toString(),
      },
    };
  } catch (error) {
    console.error('❌ generateMetadata - Unexpected error:', error);
    return {
      title: 'Product',
      description: 'View product details',
    };
  }
}

// Fetch product data for the page
async function getProductData(slug) {
  try {
    const apiUrl = getApiUrl();
    const url = `${apiUrl}/products/${slug}`;
    
    // console.log(' getProductData - Fetching:', url);
    
    const response = await fetch(url, {
      next: { revalidate: 30 }, // stock and availability come from this response; an hour-old copy could offer a sold-out product
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      }
    });
    
    if (!response.ok) {
      console.error(`❌ getProductData - API returned status ${response.status} for ${url}`);
      return null;
    }
    
    // Check content type to ensure we're getting JSON
    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      console.error(`❌ getProductData - Unexpected content type: ${contentType} for ${url}`);
      return null;
    }
    
    // Get text first to check if it's valid JSON
    const text = await response.text();
    
    // Check if the response looks like HTML
    if (text.trim().startsWith('<')) {
      console.error('❌ getProductData - Received HTML instead of JSON');
      console.error('First 200 chars:', text.substring(0, 200));
      return null;
    }
    
    let result;
    try {
      result = JSON.parse(text);
    } catch (parseError) {
      console.error('❌ getProductData - JSON parse error:', parseError.message);
      console.error('Response text (first 500 chars):', text.substring(0, 500));
      return null;
    }
    
    if (result.success && result.data && result.data.length > 0) {
      return result.data[0];
    }
    
    console.warn('⚠️ getProductData - No product data found in response');
    return null;
  } catch (error) {
    console.error('❌ getProductData - Error fetching product:', error);
    return null;
  }
}

// Page component - SERVER COMPONENT
export default async function ProductPage({ params }) {
  // Handle params as Promise (Next.js 14+) or plain object
  const resolvedParams = params instanceof Promise ? await params : params;
  const { slug } = resolvedParams;
  
  // console.log(' ProductPage - Loading product:', slug);
  
  const productData = await getProductData(slug);
  
  if (!productData) {
    // console.log(' ProductPage - Product not found, redirecting to 404');
    notFound();
  }
  
  // console.log(' ProductPage - Product loaded successfully:', productData.name);
  
  // Transform gallery images
  const galleryImages = [];
  
  if (productData.thumbnail_image) {
    galleryImages.push({
      id: 1,
      src: productData.thumbnail_image,
      alt: productData.name
    });
  }
  
  if (productData.photos && Array.isArray(productData.photos)) {
    productData.photos.forEach((photo, index) => {
      const photoPath = typeof photo === 'string' ? photo : photo.path;
      if (photoPath) {
        galleryImages.push({
          id: galleryImages.length + 1,
          src: photoPath,
          alt: `${productData.name} - Image ${index + 2}`
        });
      }
    });
  }
  
  // Clean description for JSON-LD
  const cleanDescription = productData.meta_description || 
                          productData.short_description || 
                          productData.description?.replace(/<[^>]*>/g, '').substring(0, 160);
  
  // Check if custom video exists
  const hasCustomVideo = productData?.custom_video && productData.custom_video.trim() !== '';

  // Middle crumb of the trail above the gallery.
  const categoryName = productData.category_name || productData.category?.name;

  // Prepare JSON-LD data
  const jsonLdData = {
    "@context": "https://schema.org",
    "@type": "Product",
    "name": productData.name,
    "description": cleanDescription,
    "image": productData.meta_img || productData.thumbnail_image,
    "sku": productData.id?.toString(),
    "offers": {
      "@type": "Offer",
      "price": productData.calculable_price,
      "priceCurrency": productData.currency_symbol || "BDT",
      "availability": productData.current_stock > 0 ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
      "url": `${process.env.NEXT_PUBLIC_SITE_URL || ''}/products/${slug}`
    }
  };

  // Add brand if available
  if (productData.brand?.name || productData.shop_name) {
    jsonLdData.brand = {
      "@type": "Brand",
      "name": productData.brand?.name || productData.shop_name
    };
  }

  // Add aggregate rating if product has ratings
  if (productData.rating > 0) {
    jsonLdData.aggregateRating = {
      "@type": "AggregateRating",
      "ratingValue": productData.rating,
      "reviewCount": productData.rating_count || 0
    };
  }
  
  return (
    <>
      <ScrollToTop />
      
      {/* Client component that handles GTM tracking */}
      <ProductViewTracker product={productData} />
      
      {/* JSON-LD Structured Data for Product */}
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{
          __html: JSON.stringify(jsonLdData)
        }}
      />
      
      <div className="bg-cream">
        {/* Full-bleed trail under the header: Home > Category > Product. */}
        <div className="border-b border-stone-200/80 bg-white/50">
          <div className="container-page py-3">
            <Breadcrumb
              items={[
                { name: "Home", href: "/" },
                ...(categoryName
                  ? [
                      {
                        name: categoryName,
                        href: productData.category?.id
                          ? `/shop_page?categories=${productData.category.id}`
                          : "/shop_page",
                      },
                    ]
                  : []),
                { name: productData.name, href: "#" },
              ]}
            />
          </div>
        </div>

        <section className="container-page min-h-screen pb-16 pt-6 sm:pt-10">
          <div className="grid grid-cols-1 items-start gap-8 sm:gap-12 lg:grid-cols-12">
            <ProductGalleryWrapper productData={productData} galleryImages={galleryImages} />
          </div>

          <Description productData={productData} />

          <YouMayAlsoLike
            currentProductId={slug}
            category={productData.category?.id}
          />
        </section>
      </div>
      
      {/* Video Modal Component */}
      <ProductDetailsVideoModal
        videoUrl={productData.custom_video}
        productName={productData.name}
      />
      
      {/* Floating Video Player */}
      {hasCustomVideo && (
        <FloatingVideoPlayer 
          key={`video-${slug}`}
          videoUrl={productData.custom_video}
          productName={productData.name}
        />
      )}
    </>
  );
}