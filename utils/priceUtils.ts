interface PricingItem {
  item: string | number;
  price?: number | null;
}

interface PricingData {
  [category: string]: PricingItem[] | { [frameType: string]: PricingItem[] };
}

interface QuoteItem {
  total?: number | null;
}

interface QuoteData {
  [section: string]: QuoteItem[];
}

interface Markups {
  [category: string]: number;
}

export const lookupPrice = (
  pricingData: PricingData | null | undefined,
  category: string | null | undefined,
  item: string | number | null | undefined,
  frameType: string | null = null
): number => {
  if (!pricingData || !category || !item) {
    return 0;
  }

  let data = pricingData[category];
  if (!data) {
    return 0;
  }

  if (frameType) {
    if (typeof data === 'object' && !Array.isArray(data) && data[frameType]) {
      data = data[frameType];
    } else {
      return 0; // If frameType is specified but not applicable/found, return 0
    }
  }

  if (Array.isArray(data)) {
    const found = data.find(d => d && d.item === item);
    return found && typeof found.price === 'number' ? found.price : 0;
  }

  return 0;
};

export const calculateSectionTotal = (
  quoteData: QuoteData | null | undefined,
  markups: Markups | null | undefined,
  section: string | null | undefined
): number => {
  if (!quoteData || !markups || !section || !quoteData[section] || !Array.isArray(quoteData[section])) {
    return 0;
  }

  const sectionData = quoteData[section] || [];
  const subtotal = sectionData.reduce((sum, item) => {
    const total = item?.total;
    return sum + (typeof total === 'number' && isFinite(total) ? total : 0);
  }, 0);

  let markupPercent = 0;
  if (['doors', 'doorOptions', 'inserts'].includes(section)) {
    markupPercent = markups.doors ?? 0;
  } else if (['frames', 'frameOptions'].includes(section)) {
    markupPercent = markups.frames ?? 0;
  } else if (markups.hardware !== undefined) {
    markupPercent = markups.hardware;
  }

  if (typeof markupPercent !== 'number' || !isFinite(markupPercent)) {
    return subtotal;
  }

  return subtotal * (1 + markupPercent / 100);
};

export const calculateGrandTotal = (
  quoteData: QuoteData | null | undefined,
  markups: Markups | null | undefined
): number => {
  if (!quoteData || !markups) {
    return 0;
  }

  const sections = Object.keys(quoteData);
  return sections.reduce((total, section) => {
    // Avoid circular references if any by checking if we have already processed the section
    // This is a simple check, a more robust solution would be to track visited nodes.
    if (section === 'self') return total;
    return total + calculateSectionTotal(quoteData, markups, section);
  }, 0);
};