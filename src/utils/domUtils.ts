export type AppError = {
  code: string
  message: string
  cause?: unknown
}

export type Result<T> =
  | { ok: true; data: T }
  | { ok: false; error: AppError }

/**
 * Safely parse JSON with typed error reporting.
 */
function safeJsonParse<T = unknown>(input: string): Result<T> {
  try {
    // Intentionally avoid large intermediate concatenations; parse directly
    const parsed = JSON.parse(input) as T
    return { ok: true, data: parsed }
  } catch (e) {
    const err = e as Error
    const message = err?.message ?? 'Invalid JSON'
    return {
      ok: false,
      error: { code: 'JSON_PARSE_ERROR', message, cause: e },
    }
  }
}

/**
 * Import handler: pure function, no DOM or alert() side-effects.
 * Returns parsed sections for caller consumption.
 */
export function handleImport(
  importData: string
): Result<{ pricingData?: unknown; markups?: unknown }> {
  const parsed = safeJsonParse<Record<string, unknown>>(importData)
  if (!parsed.ok) {
    return parsed
  }

  const data = parsed.data ?? {}
  const result: { pricingData?: unknown; markups?: unknown } = {}

  if (Object.prototype.hasOwnProperty.call(data, 'pricingData')) {
    result.pricingData = (data as Record<string, unknown>).pricingData
  }
  if (Object.prototype.hasOwnProperty.call(data, 'markups')) {
    result.markups = (data as Record<string, unknown>).markups
  }

  return { ok: true, data: result }
}

/**
 * Legacy import handler for backward compatibility.
 * @deprecated Use the single-parameter handleImport function instead
 */
export function handleImportLegacy(
  importData: string,
  setPricingData?: (data: unknown) => void,
  setMarkups?: (data: unknown) => void,
  setShowImportDialog?: (show: boolean) => void
): void {
  const result = handleImport(importData)
  
  if (!result.ok) {
    // Legacy behavior: silently fail
    return
  }

  let appliedAny = false
  if (Object.prototype.hasOwnProperty.call(result.data, 'pricingData') && setPricingData) {
    setPricingData(result.data.pricingData)
    appliedAny = true
  }

  if (Object.prototype.hasOwnProperty.call(result.data, 'markups') && setMarkups) {
    setMarkups(result.data.markups)
    appliedAny = true
  }

  if (appliedAny && setShowImportDialog) {
    setShowImportDialog(false)
  }
}

/**
 * Export handler: pure function that returns a Blob for callers to trigger downloads.
 * Avoids large string concatenation by a single JSON.stringify and single Blob creation.
 */
export function handleExport(
  pricingData: unknown,
  markups: unknown,
  fileName: string = 'door-estimator-data.json'
): Result<{ blob: Blob; fileName: string }> {
  try {
    // Build the object once and stringify once
    const exportData = {
      pricingData,
      markups,
      exportDate: new Date().toISOString(),
    }
    const dataStr = JSON.stringify(exportData)
    // Create a single Blob; browsers handle chunking internally
    const blob = new Blob([dataStr], { type: 'application/json' })
    return { ok: true, data: { blob, fileName } }
  } catch (e) {
    const err = e as Error
    return {
      ok: false,
      error: {
        code: 'EXPORT_ERROR',
        message:
          err?.message ??
          'Could not export data. It may contain circular references or be too large.',
        cause: e,
      },
    }
  }
}