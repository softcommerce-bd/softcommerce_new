// No fallback value. This module runs on the server as well as the client, so
// a placeholder host would turn a missing `NEXT_PUBLIC_API_URL` into silent
// request failures against an address that does not exist; the previous value
// pointed at a local install of an unrelated project. An undefined base fails
// loudly at the first request instead, which is the behaviour that gets the
// misconfiguration noticed.
const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL;

class BlogAPI {
  constructor() {
    this.baseURL = `${API_BASE_URL}/blogs`;
  }

  async fetchWithErrorHandling(url, options = {}) {
    try {
      const response = await fetch(url, {
        ...options,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...options.headers,
        },
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'Something went wrong');
      }

      return data;
    } catch (error) {
      console.error('API Error:', error);
      throw error;
    }
  }

  async getAllBlogs({ page = 1, perPage = 12, search = '', selectedCategories = [] } = {}) {
    const params = new URLSearchParams();
    params.append('page', page);
    params.append('per_page', perPage);
    
    if (search) {
      params.append('search', search);
    }
    
    // Send selected_categories as a comma-separated string
    if (selectedCategories.length > 0) {
      params.append('selected_categories', selectedCategories.join(','));
    }
    
    const url = `${this.baseURL}?${params.toString()}`;
    // console.log('Request URL:', url);
    // console.log('Selected Categories:', selectedCategories);
    
    const response = await this.fetchWithErrorHandling(url);
    
    // Handle both response formats (with or without wrapper)
    if (response.success && response.data) {
      // If using direct response
      return {
        data: response.data,
        pagination: response.pagination,
        filters: response.filters
      };
    } else if (response.data && response.data.data) {
      // If using BlogCollection resource
      return {
        data: response.data.data,
        pagination: response.pagination,
        success: response.success
      };
    }
    
    return response;
  }

  // async getBlogBySlug(slug) {
  //   return this.fetchWithErrorHandling(`${this.baseURL}/${slug}`);
  // }

  async getBlogBySlug(slug) {
  const response = await this.fetchWithErrorHandling(`${this.baseURL}/${slug}`);
  // console.log('Raw getBlogBySlug response:', response);
  
  // Return the response in a consistent format
  return response;
}

async getRecentBlogs(limit = 9) {
  const response = await this.fetchWithErrorHandling(`${this.baseURL}/recent?limit=${limit}`);
  // console.log('Raw getRecentBlogs response:', response);
  
  // Return the response in a consistent format
  return response;
}

  async getRecentBlogs(limit = 9) {
    return this.fetchWithErrorHandling(`${this.baseURL}/recent?limit=${limit}`);
  }

  async getAllCategories() {
    const response = await this.fetchWithErrorHandling(`${this.baseURL}/categories`);
    // Handle response format
    if (response.success && response.data) {
      return response.data;
    }
    return response;
  }

  async getBlogsByCategory(categorySlug, { page = 1, perPage = 12 } = {}) {
    const params = new URLSearchParams({
      page,
      per_page: perPage,
    });
    return this.fetchWithErrorHandling(`${this.baseURL}/category/${categorySlug}?${params}`);
  }

  async searchBlogs(query, { page = 1, perPage = 12 } = {}) {
    const params = new URLSearchParams({
      query,
      page,
      per_page: perPage,
    });
    return this.fetchWithErrorHandling(`${this.baseURL}/search?${params}`);
  }
}

export default new BlogAPI();