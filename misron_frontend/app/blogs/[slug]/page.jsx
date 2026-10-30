'use client';

import { useState, useEffect } from 'react';
import { useParams, useRouter } from 'next/navigation';
import BlogAPI from '@/lib/api/blog';
import Link from 'next/link';
import Breadcrumb from '@/app/components/layout/Breadcrumb';
import { FileQuestion } from 'lucide-react';
import { EmptyState, Skeleton } from '@/app/components/ui';

export default function BlogDetails() {
  const params = useParams();
  const router = useRouter();
  const { slug } = params;
  
  const [blog, setBlog] = useState(null);
  const [recentBlogs, setRecentBlogs] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (slug) {
      fetchBlogDetails();
      fetchRecentBlogs();
    }
  }, [slug]);

  const fetchBlogDetails = async () => {
    setLoading(true);
    try {
      const response = await BlogAPI.getBlogBySlug(slug);
      // console.log('Blog details response:', response);
      
      // Handle different response formats
      let blogData = null;
      if (response.data && response.data.data) {
        // If using BlogResource (nested data)
        blogData = response.data.data;
      } else if (response.data) {
        // If direct response
        blogData = response.data;
      } else if (response.success && response.data) {
        blogData = response.data;
      }
      
      if (blogData) {
        setBlog(blogData);
      } else {
        console.error('No blog data found in response:', response);
        router.push('/404');
      }
    } catch (error) {
      console.error('Error fetching blog:', error);
      if (error.message.includes('404') || error.response?.status === 404) {
        router.push('/404');
      }
    } finally {
      setLoading(false);
    }
  };

  const fetchRecentBlogs = async () => {
    try {
      const response = await BlogAPI.getRecentBlogs(9);
      // console.log('Recent blogs response:', response);
      
      // Handle different response formats
      let recentData = [];
      if (response.data && Array.isArray(response.data)) {
        recentData = response.data;
      } else if (response.data && response.data.data && Array.isArray(response.data.data)) {
        recentData = response.data.data;
      } else if (Array.isArray(response)) {
        recentData = response;
      }
      
      setRecentBlogs(recentData);
    } catch (error) {
      console.error('Error fetching recent blogs:', error);
      setRecentBlogs([]);
    }
  };

  if (loading) {
    return (
      <div className="container-page py-8 md:py-12">
        <Skeleton className="h-3 w-40" />
        <div className="mt-8 grid gap-10 lg:grid-cols-3">
          <div className="space-y-4 lg:col-span-2">
            <Skeleton className="aspect-video w-full" rounded="xl" />
            <Skeleton className="h-8 w-4/5" />
            <Skeleton className="h-3 w-full" />
            <Skeleton className="h-3 w-full" />
            <Skeleton className="h-3 w-3/5" />
          </div>
          <Skeleton className="h-64" rounded="lg" />
        </div>
      </div>
    );
  }

  if (!blog) {
    return (
      <div className="container-page py-8 md:py-12">
        <EmptyState
          icon={FileQuestion}
          title="Post not found"
          description="This post may have been removed, or the link may be wrong."
          action={{ label: "Back to the journal", href: "/blogs" }}
          className="py-24"
        />
      </div>
    );
  }

  const otherPosts = recentBlogs
    .filter((recent) => recent.id !== blog.id)
    .slice(0, 5);

  return (
    <div className="container-page py-8 md:py-12">
      <Breadcrumb
        items={[
          { name: "Home", href: "/" },
          { name: "Journal", href: "/blogs" },
          { name: blog.title, href: "#" },
        ]}
      />

      <div className="mt-8 flex flex-col gap-12 lg:flex-row lg:gap-16">
        {/* ── Article ──────────────────────────────────────────────────────
            Capped at a comfortable measure rather than filling the column,
            since long lines are hard to track back from. */}
        <article className="min-w-0 flex-1">
          {blog.banner && (
            <div className="relative mb-8 aspect-video w-full overflow-hidden rounded-xl bg-canvas-sunken">
              {/* A banner that fails to load hides itself and leaves the
                  tinted box behind it, rather than swapping in another image
                  that has to load and can fail in turn. */}
              <img
                src={blog.banner}
                alt=""
                className="h-full w-full object-cover"
                onError={(e) => {
                  e.currentTarget.style.display = "none";
                }}
              />
            </div>
          )}

          <header className="mb-8 max-w-[68ch]">
            <div className="type-caption flex flex-wrap items-center gap-2 text-ink-tertiary">
              <time dateTime={blog.created_at}>
                {new Date(blog.created_at).toLocaleDateString()}
              </time>
              {blog.category && (
                <>
                  <span aria-hidden="true">·</span>
                  <Link
                    href={`/blogs?categories=${blog.category.slug}`}
                    className="font-medium text-ink-secondary transition-colors hover:text-ink"
                  >
                    {blog.category.category_name || blog.category.name}
                  </Link>
                </>
              )}
            </div>

            <h1 className="type-title-1 mt-3 text-balance text-ink">{blog.title}</h1>
          </header>

          <div
            className="prose-page max-w-[68ch]"
            dangerouslySetInnerHTML={{ __html: blog.description }}
          />
        </article>

        {/* ── Recent posts ─────────────────────────────────────────────────*/}
        {otherPosts.length > 0 && (
          <aside className="lg:w-72 lg:shrink-0">
            <div className="lg:sticky lg:top-[calc(var(--header-height,80px)+24px)]">
              <h2 className="type-eyebrow mb-4 text-ink-tertiary">More posts</h2>

              <ul className="space-y-1">
                {otherPosts.map((recent) => (
                  <li key={recent.id}>
                    <Link
                      href={`/blogs/${recent.slug}`}
                      className="flex gap-3 rounded-lg p-2 transition-colors duration-fast hover:bg-surface-hover"
                    >
                      {recent.banner && (
                        <div className="h-14 w-14 shrink-0 overflow-hidden rounded-md bg-canvas-sunken">
                          <img
                            src={recent.banner}
                            alt=""
                            className="h-full w-full object-cover"
                            onError={(e) => {
                              e.currentTarget.style.display = "none";
                            }}
                          />
                        </div>
                      )}

                      <div className="min-w-0">
                        <h3 className="type-footnote line-clamp-2 font-medium text-ink">
                          {recent.title}
                        </h3>
                        <p className="type-caption mt-1 text-ink-tertiary">
                          {new Date(recent.created_at).toLocaleDateString()}
                        </p>
                      </div>
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          </aside>
        )}
      </div>
    </div>
  );
}
