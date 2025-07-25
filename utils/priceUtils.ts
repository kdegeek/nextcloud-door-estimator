export const lookupPrice = (pricingData, category, item, frameType = null) => {
  if (!item) return 0;

  let data = pricingData[category];
  if (frameType && data[frameType]) {
    data = data[frameType];
  }

  if (Array.isArray(data)) {
    const found = data.find(d => d.item === item);
    return found ? found.price : 0;
  }
  return 0;
};

export const calculateSectionTotal = (quoteData, markups, section) => {
  const sectionData = quoteData[section] || [];
  const subtotal = sectionData.reduce((sum, item) => sum + (item.total || 0), 0);

  let markup = 0;
  if (['doors', 'doorOptions', 'inserts'].includes(section)) {
    markup = markups.doors;
  } else if (['frames', 'frameOptions'].includes(section)) {
    markup = markups.frames;
  } else {
    markup = markups.hardware;
  }

  return subtotal * (1 + markup / 100);
};

export const calculateGrandTotal = (quoteData, markups) => {
  const sections = Object.keys(quoteData);
  return sections.reduce((total, section) => total + calculateSectionTotal(quoteData, markups, section), 0);
};