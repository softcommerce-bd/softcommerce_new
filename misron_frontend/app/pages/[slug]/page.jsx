// app/pages/[slug]/page.js


import { notFound } from 'next/navigation';

// Dynamic metadata for SEO
export async function generateMetadata({ params }) {
  const { slug } = await params;
  
  try {
    const response = await fetch(`${process.env.NEXT_PUBLIC_SITE_URL}/api/v2/seo/page/${slug}`, {
      cache: 'force-cache',
      next: { revalidate: 3600 }
    });
    // console.log('page seo ki pelam ilias',response);
    
    if (!response.ok) {
      if (response.status === 404) {
        return {
          title: 'Page Not Found',
          description: 'The requested page could not be found',
          robots: { index: false },
        };
      }
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const result = await response.json();
    
    if (!result.success || !result.data) {
      return {
        title: 'Page Not Found',
        description: 'The requested page could not be found',
        robots: { index: false },
      };
    }
    
    const pageData = result.data;
    const seoTitle = pageData.meta_title || `${pageData.title} | Soft Commerce`;
    const seoDescription = pageData.meta_description || pageData.excerpt || `${pageData.title} - Learn more about this page`;
    
    return {
      title: seoTitle,
      description: seoDescription,
      keywords: pageData.meta_keywords || pageData.tags?.join(', ') || '',
      openGraph: {
        title: seoTitle,
        description: seoDescription,
        images: [pageData.meta_image || pageData.featured_image || '/default-og-image.jpg'],
        type: 'article',
        publishedTime: pageData.created_at,
        modifiedTime: pageData.updated_at,
        authors: pageData.author ? [pageData.author] : [],
      },
      twitter: {
        card: 'summary_large_image',
        title: seoTitle,
        description: seoDescription,
        images: [pageData.meta_image || pageData.featured_image || '/default-og-image.jpg'],
      },
      alternates: {
        canonical: `/pages/${slug}`,
      },
    };
  } catch (error) {
    console.error('Error fetching page metadata:', error);
    return {
      title: 'Page',
      description: 'View page content',
    };
  }
}

// Fetch page data
async function getPageData(slug) {
  try {
    const response = await fetch(`${process.env.NEXT_PUBLIC_SITE_URL}/api/v2/seo/page/${slug}`, {
      cache: 'force-cache',
      next: { revalidate: 3600 }
    });
    // console.log('page seo ki pelam 270',response);
    
    if (!response.ok) {
      if (response.status === 404) {
        return null;
      }
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const result = await response.json();
    
    if (result.success && result.data) {
      return result.data;
    }
    
    return null;
  } catch (error) {
    console.error('Error fetching page:', error);
    return null;
  }
}

// Page component
export default async function DynamicPage({ params }) {
  const { slug } = await params;
  const pageData = await getPageData(slug);

  if (!pageData) {
    notFound();
  }

  return (
    <>
      {/* JSON-LD Schema for Article */}
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{
          __html: JSON.stringify({
            "@context": "https://schema.org",
            "@type": "Article",
            headline: pageData.title,
            description: pageData.meta_description || pageData.excerpt,
            image: pageData.meta_image || pageData.featured_image,
            datePublished: pageData.created_at,
            dateModified: pageData.updated_at,
            author: {
              "@type": "Person",
              name: pageData.author || "Soft Commerce",
            },
            publisher: {
              "@type": "Organization",
              name: "Soft Commerce",
              logo: {
                "@type": "ImageObject",
                url: `${process.env.NEXT_PUBLIC_SITE_URL}/logo.png`,
              },
            },
          }),
        }}
      />

      {/* The article is capped at a comfortable measure and centred, rather
          than running the full width of the page. */}
      <main className="container-page py-10 md:py-16">
        <div className="mx-auto max-w-[72ch]">
          <header className="mb-10">
            <h1 className="type-title-1 text-balance text-ink">{pageData.title}</h1>

            {(pageData.author || pageData.created_at) && (
              <div className="type-caption mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-ink-tertiary">
                {pageData.author && <span>By {pageData.author}</span>}

                {pageData.author && pageData.created_at && (
                  <span aria-hidden="true">·</span>
                )}

                {pageData.created_at && (
                  <time dateTime={pageData.created_at}>
                    {new Date(pageData.created_at).toLocaleDateString()}
                  </time>
                )}
              </div>
            )}

            {pageData.meta_image && (
              <div className="mt-8 overflow-hidden rounded-xl bg-canvas-sunken">
                <img
                  src={pageData.meta_image}
                  alt=""
                  className="h-64 w-full object-cover md:h-80"
                />
              </div>
            )}
          </header>

          {/* Admin-authored HTML runs through the shared prose reset so the
              CMS's own markup picks up the app's typography. */}
          <article
            className="prose-page"
            dangerouslySetInnerHTML={{ __html: pageData.content }}
          />

          {pageData.tags && pageData.tags.length > 0 && (
            <div className="mt-12 border-t border-hairline pt-6">
              <div className="flex flex-wrap gap-2">
                {pageData.tags.map((tag, index) => (
                  <span key={index} className="badge badge-neutral">
                    {tag}
                  </span>
                ))}
              </div>
            </div>
          )}

          {pageData.updated_at && (
            <footer className="type-caption mt-8 border-t border-hairline pt-6 text-ink-tertiary">
              Last updated {new Date(pageData.updated_at).toLocaleDateString()}
            </footer>
          )}
        </div>
      </main>
    </>
  );
}
