import { z } from 'zod'

// Generic error schema (mirrors controller error format)
export const ApiErrorSchema = z.object({
  code: z.string(),
  message: z.string(),
})
export type ApiError = z.infer<typeof ApiErrorSchema>

// Pricing item schema (as per backend structure)
export const PricingItemSchema = z.object({
  id: z.number().optional(),
  category: z.string().optional(),
  subcategory: z.string().nullable().optional(),
  item: z.string(),
  price: z.number().nullable().optional(),
  stock_status: z.string().optional(),
})
export const PricingListResponseSchema = z.array(PricingItemSchema)

// Lookup price response: { price: number }
export const LookupPriceResponseSchema = z.object({
  price: z.number(),
})
export type LookupPriceResponse = z.infer<typeof LookupPriceResponseSchema>

// Save quote response: { success: true, quoteId: number }
export const SaveQuoteResponseSchema = z.object({
  success: z.literal(true),
  quoteId: z.number(),
})
export type SaveQuoteResponse = z.infer<typeof SaveQuoteResponseSchema>

// Quote line item schema with pricing flexibility
export const QuoteLineItemSchema = z.object({
  id: z.string(), 
  item: z.string(), 
  qty: z.number(), 
  price: z.number(), 
  total: z.number(),
  frameType: z.string().optional(),
  basePrice: z.number().optional(), // Original price from pricing data
  priceOverridden: z.boolean().optional(), // Flag indicating manual price override
})

// Quote details (from EstimatorService::getQuote) with flexible markup system
export const QuoteSchema = z.object({
  id: z.number(),
  quote_name: z.string(),
  customer_info: z.any().nullable(),
  quote_data: z.record(z.string(), z.array(QuoteLineItemSchema)),
  markups: z.object({ 
    doors: z.number(), 
    frames: z.number(), 
    hardware: z.number() 
  }),
  defaultMarkups: z.object({ 
    doors: z.number(), 
    frames: z.number(), 
    hardware: z.number() 
  }).optional(), // Default markups for comparison
  markupsOverridden: z.object({
    doors: z.boolean(),
    frames: z.boolean(),
    hardware: z.boolean()
  }).optional(), // Flags indicating which markups are custom
  total_amount: z.number(),
  created_at: z.string(),
  updated_at: z.string(),
})
export type QuoteResponse = z.infer<typeof QuoteSchema>

// Quotes list (from EstimatorService::getUserQuotes)
export const QuotesListSchema = z.array(
  z.object({
    id: z.number(),
    quote_name: z.string(),
    total_amount: z.number(),
    created_at: z.string(),
    updated_at: z.string(),
  })
)
export type QuotesListResponse = z.infer<typeof QuotesListSchema>

// Update pricing item input schema
export const UpdatePricingItemInputSchema = z.object({
  item: z.string().min(1).max(255),
  price: z.number().nonnegative().max(1_000_000),
  category: z.string().min(1).max(50),
  subcategory: z.string().max(100).optional(),
  stock_status: z.string().max(20).optional(),
  description: z.string().max(2000).optional(),
  id: z.number().int().optional(),
})
export type UpdatePricingItemInput = z.infer<typeof UpdatePricingItemInputSchema>