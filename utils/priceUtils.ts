interface PricingItem {
  item_name: string;
  price: number;
  subcategory?: string | null;
}

interface PricingData {
  [category: string]: PricingItem[];
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
  itemName: string | null | undefined,
  subcategory: string | null = null
): number => {
  if (!pricingData || !category || !itemName) {
    return 0;
  }

  const categoryData = pricingData[category];
  if (!Array.isArray(categoryData)) {
    return 0;
  }

  const foundItem = categoryData.find(item => {
    if (subcategory) {
      return item.item_name === itemName && item.subcategory === subcategory;
    }
    return item.item_name === itemName;
  });

  return foundItem ? foundItem.price : 0;
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