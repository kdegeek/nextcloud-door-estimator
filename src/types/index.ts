/* Shared domain types for the Door Estimator app */

// Quote sections used throughout the app
export type SectionKey =
  | 'doors'
  | 'doorOptions'
  | 'inserts'
  | 'frames'
  | 'frameOptions'
  | 'hinges'
  | 'weatherstrip'
  | 'closers'
  | 'locksets'
  | 'exitDevices'
  | 'hardware'

// Pricing item (as returned from backend or stored in pricingData)
export interface PricingItem {
  item: string
  price?: number | null
}

// Pricing map: each category is either a flat list or a frames map keyed by frameType
export type PricingDataMap = {
  [K in SectionKey]?: PricingItem[] | Record<string, PricingItem[]>
} & {
  frames?: Record<string, PricingItem[]>
}

// Quote line item edited by users with pricing flexibility
export interface QuoteLineItem {
  id: string
  item: string
  qty: number
  price: number
  total: number
  // Optional for frames
  frameType?: string
  // Pricing flexibility fields
  basePrice?: number // Original price from pricing data
  priceOverridden?: boolean // Flag indicating manual price override
}

// Reactive quote data structure keyed by section
export type QuoteDataMap = {
  [K in SectionKey]: QuoteLineItem[]
}

// Markups config for major groups with flexibility tracking
export interface MarkupsMap {
  doors: number
  frames: number
  hardware: number
}

// Enhanced markups with default comparison and override tracking
export interface FlexibleMarkupsMap extends MarkupsMap {
  defaultMarkups?: MarkupsMap // Default markups for comparison
  markupsOverridden?: {
    doors: boolean
    frames: boolean
    hardware: boolean
  } // Flags indicating which markups are custom
}

// Generic API error shape mirrored in controller responses
export interface ApiError {
  code: string
  message: string
}

// Generic API response wrapper (success or error)
export type ApiResponse<T> =
  | { success: true; data: T }
  | { success: false; error: ApiError }