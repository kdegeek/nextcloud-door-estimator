/* EstimatorApi: typed client for backend endpoints with caching, cancellation, and optimistic updates */
import type { PricingItem, QuoteDataMap, MarkupsMap } from '../types'
import { z } from 'zod'
import { PricingListResponseSchema, LookupPriceResponseSchema, SaveQuoteResponseSchema, QuoteSchema, QuotesListSchema, UpdatePricingItemInputSchema } from '../types/api'

export type ApiClientOptions = {
  baseUrl?: string
  timeoutMs?: number
}

type CacheKey = string

type GetCacheEntry<T> = {
  data: T
  ts: number
}

// Centralized configuration constants
const DEFAULT_BASE = ''
const DEFAULT_TIMEOUT = 15000
const DEFAULT_PRICING_TTL = 300000 // 5 minutes for pricing data
const DEFAULT_SEARCH_TTL = 120000 // 2 minutes for search results
const DEFAULT_QUOTES_TTL = 120000 // 2 minutes for quotes
const PRICING_ENDPOINT = '/api/pricing'
const LOOKUP_PRICE_ENDPOINT = '/api/lookup-price'
const SEARCH_ENDPOINT = '/api/pricing/search'
const IMPORT_ENDPOINT = '/api/import'
const QUOTES_ENDPOINT = '/api/quotes'
const ONBOARDING_STATUS_ENDPOINT = '/api/onboardingStatus'

// Import error handling utilities
import { ApiResponse, ApiException, ErrorHandler, ErrorUtils } from '../utils/errorHandling'

// API error with structured context (keeping for backward compatibility)
export class ApiError extends Error {
  status: number
  url: string
  details?: unknown

  constructor(message: string, status: number, url: string, details?: unknown) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.url = url
    this.details = details
  }
}

// Import pricing response schema
const ImportPricingResponseSchema = z.object({
  success: z.boolean(),
  imported: z.number().int().nonnegative(),
  errors: z.array(z.string()),
})
type ImportPricingResponse = z.infer<typeof ImportPricingResponseSchema>

export class EstimatorApi {
  private readonly base: string
  private readonly timeoutMs: number
  private cache = new Map<CacheKey, GetCacheEntry<unknown>>()
  // Track in-flight GET requests to deduplicate concurrent identical calls
  private inFlight = new Map<CacheKey, Promise<unknown>>()

  constructor(opts?: ApiClientOptions) {
    this.base = opts?.baseUrl ?? DEFAULT_BASE
    this.timeoutMs = opts?.timeoutMs ?? DEFAULT_TIMEOUT
  }

  // Build a stable cache key including base to avoid collisions
  private buildGetKey(path: string): string {
    return `${this.base}|GET:${path}`
  }

  // Invalidate cache entries for a given path prefix (e.g., '/api/pricing')
  public invalidateCache(prefix: string): void {
    const target = `${this.base}|GET:${prefix}`
    for (const key of Array.from(this.cache.keys())) {
      if (key.startsWith(target)) {
        this.cache.delete(key)
      }
    }
  }

  // Unified fetch helper: timeout, abort linking, error handling, and optional schema validation
  private async fetchAndParse<T>(
    path: string,
    options: {
      method: 'GET' | 'POST' | 'PUT' | 'DELETE'
      body?: BodyInit | null
      headers?: HeadersInit
      responseType?: 'json' | 'text' | 'blob' | 'arrayBuffer'
      schema?: z.ZodSchema<T>
      signal?: AbortSignal
    }
  ): Promise<T> {
    const { method, body, headers, responseType = 'json', schema, signal } = options
    const controller = new AbortController()
    const timeout = setTimeout(() => controller.abort(), this.timeoutMs)
    let onAbort: (() => void) | undefined
    if (signal) {
      if (signal.aborted) controller.abort()
      onAbort = () => controller.abort()
      signal.addEventListener('abort', onAbort)
    }

    // Prepare headers with security measures
    const secureHeaders = new Headers(headers)
    
    // Add CSRF protection for state-changing operations
    if (method !== 'GET') {
      secureHeaders.set('X-Requested-With', 'XMLHttpRequest')
      
      // Get CSRF token from Nextcloud's initial state
      const csrfToken = (window as any).OC?.config?.csrf_token || 
                       (window as any).OCA?.DoorEstimator?.csrf_token
      if (csrfToken) {
        secureHeaders.set('requesttoken', csrfToken)
      }
    }
    
    // Add content security headers
    if (body && typeof body === 'string') {
      secureHeaders.set('Content-Type', 'application/json; charset=utf-8')
    }

    const url = `${this.base}${path}`
    try {
      const res = await fetch(url, {
        method,
        body,
        headers: secureHeaders,
        signal: controller.signal,
        credentials: 'same-origin', // Include cookies for authentication
      })

      clearTimeout(timeout)
      if (onAbort) {
        signal?.removeEventListener('abort', onAbort)
      }

      if (!res.ok) {
        let errorResponse: ApiResponse<any> | undefined
        try {
          errorResponse = await res.json()
        } catch {
          // Fallback for non-JSON error responses
        }

        if (errorResponse && !errorResponse.success && errorResponse.error) {
          // Handle structured API error response
          throw new ApiException(errorResponse.error)
        } else {
          // Handle HTTP error without structured response
          const message = this.getHttpErrorMessage(res.status)
          throw new ApiException({
            code: `HTTP_${res.status}`,
            message,
            retryable: this.isRetryableHttpStatus(res.status)
          })
        }
      }

      let data: unknown
      switch (responseType) {
        case 'text':
          data = await res.text()
          break
        case 'blob':
          data = await res.blob()
          break
        case 'arrayBuffer':
          data = await res.arrayBuffer()
          break
        case 'json':
        default:
          data = await res.json()
          break
      }

      return schema ? schema.parse(data) : (data as T)
    } catch (err: any) {
      if (err instanceof ApiException) throw err
      if (err instanceof ApiError) throw err
      
      // Handle network and timeout errors
      if (err.name === 'AbortError') {
        throw ErrorUtils.handleTimeoutError()
      }
      
      // Handle other network errors
      throw ErrorUtils.handleNetworkError(err)
    } finally {
      clearTimeout(timeout)
      if (signal && onAbort) signal.removeEventListener('abort', onAbort)
    }
  }

  /**
   * Get user-friendly HTTP error message
   */
  private getHttpErrorMessage(status: number): string {
    switch (status) {
      case 400:
        return 'Invalid request. Please check your input and try again.'
      case 401:
        return 'You need to log in to perform this action.'
      case 403:
        return 'You don\'t have permission to perform this action.'
      case 404:
        return 'The requested resource was not found.'
      case 413:
        return 'The file you\'re trying to upload is too large.'
      case 429:
        return 'Too many requests. Please wait a moment and try again.'
      case 500:
        return 'A server error occurred. Please try again later.'
      case 503:
        return 'The service is temporarily unavailable. Please try again later.'
      default:
        return `An error occurred (${status}). Please try again.`
    }
  }

  /**
   * Check if HTTP status code indicates a retryable error
   */
  private isRetryableHttpStatus(status: number): boolean {
    return [408, 429, 500, 502, 503, 504].includes(status)
  }

  // Basic GET with cache, cancellation, and in-flight deduplication
  private async get<T>(path: string, schema: z.ZodSchema<T>, cacheTtlMs = 5000, signal?: AbortSignal): Promise<T> {
    const key = this.buildGetKey(path)
    const now = Date.now()
    const hit = this.cache.get(key)
    if (hit && now - hit.ts < cacheTtlMs) {
      return hit.data as T
    }

    if (this.inFlight.has(key)) {
      return this.inFlight.get(key) as Promise<T>
    }

    const p = this.fetchAndParse<T>(path, { method: 'GET', schema, responseType: 'json', signal })
      .then((parsed) => {
        this.cache.set(key, { data: parsed as unknown, ts: Date.now() })
        return parsed
      })
      .finally(() => {
        this.inFlight.delete(key)
      })

    this.inFlight.set(key, p as Promise<unknown>)
    return p
  }

  // Basic POST with JSON, validation, sanitization, and consistent error handling
  private async post<T>(path: string, body: unknown, schema: z.ZodSchema<T>, signal?: AbortSignal): Promise<T> {
    // Sanitize request body for security
    const sanitizedBody = this.sanitizeRequestBody(body)
    
    return this.fetchAndParse<T>(path, {
      method: 'POST',
      body: JSON.stringify(sanitizedBody ?? {}),
      headers: { 'Content-Type': 'application/json; charset=utf-8' },
      responseType: 'json',
      schema,
      signal,
    })
  }

  /**
   * Sanitize request body to prevent XSS and injection attacks
   */
  private sanitizeRequestBody(body: unknown): unknown {
    if (body === null || body === undefined) {
      return body
    }

    if (typeof body === 'string') {
      return this.sanitizeString(body)
    }

    if (Array.isArray(body)) {
      return body.map(item => this.sanitizeRequestBody(item))
    }

    if (typeof body === 'object') {
      const sanitized: Record<string, unknown> = {}
      for (const [key, value] of Object.entries(body)) {
        const sanitizedKey = this.sanitizeString(key)
        sanitized[sanitizedKey] = this.sanitizeRequestBody(value)
      }
      return sanitized
    }

    return body
  }

  /**
   * Sanitize string to prevent XSS
   */
  private sanitizeString(input: string): string {
    if (typeof input !== 'string') return input

    // Remove HTML tags and dangerous characters
    return input
      .replace(/<[^>]*>/g, '') // Remove HTML tags
      .replace(/[<>'"&]/g, (match) => {
        const entities: Record<string, string> = {
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#x27;',
          '&': '&amp;'
        }
        return entities[match] || match
      })
      .replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '') // Remove control characters
      .trim()
  }

  // Lookup price
  async lookupPrice(params: { category: string; item: string; frameType?: string }, signal?: AbortSignal) {
    const body = { category: params.category, item: params.item, frameType: params.frameType }
    return this.post(LOOKUP_PRICE_ENDPOINT, body, LookupPriceResponseSchema, signal)
  }

  // List all pricing (renamed for consistency)
  async listPricingByCategory(signal?: AbortSignal) {
    return this.get(PRICING_ENDPOINT, PricingListResponseSchema, DEFAULT_PRICING_TTL, signal)
  }

  // List pricing for a category
  async getPricingByCategory(category: string, signal?: AbortSignal) {
    return this.get(`${PRICING_ENDPOINT}/${encodeURIComponent(category)}`, PricingListResponseSchema, DEFAULT_PRICING_TTL, signal)
  }

  // Import pricing (file upload) with schema validation and cache invalidation
  async importPricing(file: File, signal?: AbortSignal) {
    const form = new FormData()
    form.append('file', file)

    const res = await this.fetchAndParse<ImportPricingResponse>(IMPORT_ENDPOINT, {
      method: 'POST',
      body: form,
      responseType: 'json',
      schema: ImportPricingResponseSchema,
      signal,
    })

    if (res.success) {
      // Invalidate pricing caches since data likely changed
      this.invalidateCache(PRICING_ENDPOINT)
    }
    return res
  }

  // Update pricing item with optimistic update pattern
  async updatePricingItem(
    input: z.infer<typeof UpdatePricingItemInputSchema>,
    current: PricingItem[],
    signal?: AbortSignal
  ): Promise<{ success: boolean; updated: PricingItem[] }> {
    const parsed = UpdatePricingItemInputSchema.parse(input)
    // optimistic update without mutating caller's array directly
    const backup = current.slice()
    let working = current.slice()
    try {
      // Try to apply optimistic change locally (ensure required fields)
      const idx = working.findIndex(p => p.item === parsed.item)
      if (idx >= 0) {
        const old = working[idx]!
        const next: PricingItem = { item: old.item, price: parsed.price }
        working[idx] = next
      } else {
        const created: PricingItem = { item: parsed.item, price: parsed.price }
        working = [created, ...working]
      }

      const successSchema = z.object({ success: z.boolean() })
      const res = await this.post(PRICING_ENDPOINT, parsed, successSchema, signal)
      if (!res.success) {
        throw new ApiError('Update failed', 500, `${this.base}${PRICING_ENDPOINT}`)
      }

      // Invalidate pricing caches for all and category-specific
      this.invalidateCache(PRICING_ENDPOINT)
      if (parsed.category) {
        this.invalidateCache(`${PRICING_ENDPOINT}/${encodeURIComponent(parsed.category)}`)
      }

      // return the updated optimistic list so callers can replace state immutably
      return { success: true, updated: working }
    } catch (e) {
      const err = e instanceof Error ? e : new Error('Update failed')
      ;(err as any).context = { item: parsed.item, price: parsed.price, original: backup }
      throw err
    }
  }

  // Export pricing trigger – backend may stream; for now just GET to an export route if exists
  async exportPricing(signal?: AbortSignal) {
    // Placeholder/stub: depends on backend route existence
    return { success: true }
  }

  // Create quote with flexible pricing support
  async createQuote(params: { quoteData: QuoteDataMap; markups: MarkupsMap; quoteName?: string; customerInfo?: unknown }, signal?: AbortSignal) {
    const body = {
      quoteData: params.quoteData,
      markups: params.markups,
      quoteName: params.quoteName,
      customerInfo: params.customerInfo,
    }
    const res = await this.post(QUOTES_ENDPOINT, body, SaveQuoteResponseSchema, signal)
    // Invalidate quotes list cache after creation
    this.invalidateCache(QUOTES_ENDPOINT)
    return res
  }

  // Generate quote PDF
  async generateQuotePDF(quoteId: number, signal?: AbortSignal) {
    return this.get(`/api/quotes/${quoteId}/pdf`, z.object({
      success: z.boolean(),
      pdfPath: z.string().optional(),
      downloadUrl: z.string().optional(),
    }), 0, signal)
  }

  // Get a quote
  async getQuote(quoteId: number, signal?: AbortSignal) {
    return this.get(`${QUOTES_ENDPOINT}/${quoteId}`, QuoteSchema, 0, signal)
  }

  // List quotes with pagination
  async listQuotes(limit = 50, offset = 0, signal?: AbortSignal) {
    const params = new URLSearchParams({
      limit: limit.toString(),
      offset: offset.toString()
    })
    return this.get(`${QUOTES_ENDPOINT}?${params}`, QuotesListSchema, DEFAULT_QUOTES_TTL, signal)
  }

  // Search pricing items with optimized caching
  async searchPricingItems(query: string, category?: string, limit = 25, offset = 0, signal?: AbortSignal) {
    const params = new URLSearchParams({
      q: query,
      limit: limit.toString(),
      offset: offset.toString()
    })
    if (category) {
      params.set('category', category)
    }
    
    const searchSchema = z.object({
      success: z.boolean(),
      data: z.array(z.object({
        id: z.number(),
        category: z.string(),
        subcategory: z.string().optional(),
        item: z.string(),
        price: z.number(),
        stock_status: z.string(),
        description: z.string().optional()
      })),
      total: z.number(),
      hasMore: z.boolean(),
      query: z.string(),
      executionTime: z.number().optional()
    })
    
    return this.get(`${SEARCH_ENDPOINT}?${params}`, searchSchema, DEFAULT_SEARCH_TTL, signal)
  }

  // Delete quote
  async deleteQuote(quoteId: number, signal?: AbortSignal) {
    const deleteSchema = z.object({ success: z.boolean() })
    const res = await this.fetchAndParse(`${QUOTES_ENDPOINT}/${quoteId}`, {
      method: 'DELETE',
      responseType: 'json',
      schema: deleteSchema,
      signal,
    })
    // Invalidate quotes list cache after deletion
    this.invalidateCache(QUOTES_ENDPOINT)
    return res
  }

  // Duplicate quote
  async duplicateQuote(quoteId: number, signal?: AbortSignal) {
    const res = await this.post(`${QUOTES_ENDPOINT}/${quoteId}/duplicate`, {}, z.object({
      success: z.boolean(),
      quoteId: z.number(),
      message: z.string().optional()
    }), signal)
    // Invalidate quotes list cache after duplication
    this.invalidateCache(QUOTES_ENDPOINT)
    return res
  }

  // Get onboarding status
  async getOnboardingStatus(signal?: AbortSignal) {
    return this.get(ONBOARDING_STATUS_ENDPOINT, z.object({
      hasData: z.boolean(),
      itemCount: z.number().optional()
    }), 0, signal)
  }

  // Get markup defaults (admin-only endpoint for permission checking)
  async getMarkupDefaults(signal?: AbortSignal) {
    return this.get('/api/markup-defaults', z.object({
      doors: z.number(),
      frames: z.number(),
      hardware: z.number()
    }), 0, signal)
  }

  // Update markup defaults (admin-only)
  async updateMarkupDefaults(markups: { doors?: number; frames?: number; hardware?: number }, signal?: AbortSignal) {
    return this.post('/api/markup-defaults', markups, z.object({
      success: z.boolean(),
      message: z.string().optional()
    }), signal)
  }
}