import { calculateSectionTotal, calculateGrandTotal, lookupPrice } from './priceUtils';

// Define interfaces locally for testing, mirroring the source file
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

describe('priceUtils', () => {
  describe('lookupPrice', () => {
    const pricingData: PricingData = {
      doors: [
        { item: 'A', price: 100 },
        { item: 'B', price: 200 }
      ],
      frames: {
        'HM Drywall': [{ item: 'F1', price: 50 }],
      },
      misc: [{ item: 'M1', price: 10 }] // Changed to be a valid structure
    };

    it('returns the correct price for a known item', () => {
      expect(lookupPrice(pricingData, 'doors', 'A')).toBe(100);
      expect(lookupPrice(pricingData, 'frames', 'F1', 'HM Drywall')).toBe(50);
    });

    it('returns 0 for unknown item', () => {
      expect(lookupPrice(pricingData, 'doors', 'X')).toBe(0);
      expect(lookupPrice(pricingData, 'unknown', 'A')).toBe(0);
    });

    it('returns 0 for undefined/null inputs', () => {
      expect(lookupPrice(undefined, 'doors', 'A')).toBe(0);
      expect(lookupPrice(null, 'doors', 'A')).toBe(0);
      expect(lookupPrice(pricingData, undefined, 'A')).toBe(0);
      expect(lookupPrice(pricingData, null, 'A')).toBe(0);
      expect(lookupPrice(pricingData, 'doors', undefined)).toBe(0);
      expect(lookupPrice(pricingData, 'doors', null)).toBe(0);
    });

    it('returns 0 for empty string item', () => {
      expect(lookupPrice(pricingData, 'doors', '')).toBe(0);
    });

    it('returns 0 for category not in pricingData', () => {
      expect(lookupPrice(pricingData, 'notfound', 'A')).toBe(0);
    });

    it('returns 0 for frameType when category data is not a nested object', () => {
        const pd: PricingData = { doors: [{ item: 'A', price: 100 }] };
        expect(lookupPrice(pd, 'doors', 'A', 'frameX')).toBe(0);
    });

    it('returns 0 for frameType not in category', () => {
      const pd: PricingData = { frames: { 'frameA': [{ item: 'A', price: 100 }] } };
      expect(lookupPrice(pd, 'frames', 'A', 'frameB')).toBe(0);
    });

    it('returns 0 for non-array data structures where an array is expected', () => {
      const pd: PricingData = { doors: { notAnArray: {} } as any };
      expect(lookupPrice(pd, 'doors', 'A')).toBe(0);
    });

    it('returns 0 for items missing price field', () => {
      const pd: PricingData = { doors: [{ item: 'A' }] };
      expect(lookupPrice(pd, 'doors', 'A')).toBe(0);
    });

    it('returns 0 for items with null/undefined price', () => {
      const pd: PricingData = { doors: [{ item: 'A', price: null }, { item: 'B', price: undefined }] };
      expect(lookupPrice(pd, 'doors', 'A')).toBe(0);
      expect(lookupPrice(pd, 'doors', 'B')).toBe(0);
    });

    it('returns price of first match for duplicate items', () => {
      const pd: PricingData = { doors: [{ item: 'A', price: 100 }, { item: 'A', price: 200 }] };
      expect(lookupPrice(pd, 'doors', 'A')).toBe(100);
    });

    it('handles numeric item names', () => {
      const pd: PricingData = { doors: [
        { item: 42, price: 42 }
      ] };
      expect(lookupPrice(pd, 'doors', 42)).toBe(42);
    });

    it('handles malformed data gracefully', () => {
      expect(lookupPrice({}, 'doors', 'A')).toBe(0);
      expect(lookupPrice({ doors: null } as any, 'doors', 'A')).toBe(0);
      expect(lookupPrice({ doors: undefined } as any, 'doors', 'A')).toBe(0);
      expect(lookupPrice({ doors: [{item: 'C', price: null}, {item: 'D', price: undefined}] }, 'doors', 'A')).toBe(0);
    });

    it('does not throw for any falsy or wrong-type input', () => {
        expect(() => lookupPrice(null, null, null)).not.toThrow();
        expect(() => lookupPrice({} as PricingData, 'doors', 'A')).not.toThrow();
    });
  });

  describe('calculateSectionTotal', () => {
    const markups: Markups = { doors: 10, frames: 5, hardware: 20 };
    const quoteData: QuoteData = {
      doors: [{ total: 100 }, { total: 200 }],
      frames: [{ total: 50 }]
    };

    it('calculates total for a section with markup', () => {
        expect(calculateSectionTotal(quoteData, markups, 'doors')).toBeCloseTo(330); // (100+200) * 1.10
        expect(calculateSectionTotal(quoteData, markups, 'frames')).toBeCloseTo(52.5); // 50 * 1.05
    });

    it('returns 0 for empty or invalid section', () => {
      expect(calculateSectionTotal({}, markups, 'doors')).toBe(0);
      expect(calculateSectionTotal(quoteData, markups, 'nonexistent')).toBe(0);
    });

    it('returns 0 for undefined/null inputs', () => {
      expect(calculateSectionTotal(undefined, markups, 'doors')).toBe(0);
      expect(calculateSectionTotal(null, markups, 'doors')).toBe(0);
      expect(calculateSectionTotal(quoteData, undefined, 'doors')).toBe(0);
      expect(calculateSectionTotal(quoteData, null, 'doors')).toBe(0);
      expect(calculateSectionTotal(quoteData, markups, undefined)).toBe(0);
      expect(calculateSectionTotal(quoteData, markups, null)).toBe(0);
    });

    it('handles items with non-numeric or missing totals', () => {
      const data: QuoteData = {
        doors: [
          { total: undefined },
          { total: null },
          { total: NaN },
          { total: -100 },
          { total: 1e12 }
        ]
      };
      // subtotal = -100 + 1e12 = 999999999900
      expect(calculateSectionTotal(data, markups, 'doors')).toBeCloseTo(999999999900 * 1.1);
    });

    it('handles invalid markup values', () => {
      const data: QuoteData = { doors: [{ total: 100 }] };
      expect(calculateSectionTotal(data, { doors: 0 } as Markups, 'doors')).toBe(100);
      expect(calculateSectionTotal(data, { doors: -10 } as Markups, 'doors')).toBe(90);
      expect(calculateSectionTotal(data, { doors: NaN } as Markups, 'doors')).toBe(100); // Should return subtotal
      expect(calculateSectionTotal(data, { doors: 'abc' } as any, 'doors')).toBe(100); // Should return subtotal
    });

    it('returns subtotal for sections not in markup mapping', () => {
      const data: QuoteData = { custom: [{ total: 100 }] };
      expect(calculateSectionTotal(data, markups, 'custom')).toBe(120); // Assumes hardware markup as default
    });

    it('returns 0 for non-array section data', () => {
      expect(calculateSectionTotal({ doors: null } as any, markups, 'doors')).toBe(0);
      expect(calculateSectionTotal({ doors: {} } as any, markups, 'doors')).toBe(0);
    });

    it('does not throw for any falsy or wrong-type input', () => {
        expect(() => calculateSectionTotal(null, null, null)).not.toThrow();
        expect(() => calculateSectionTotal({}, {}, 'doors')).not.toThrow();
    });
  });

  describe('calculateGrandTotal', () => {
    const markups: Markups = { doors: 10, frames: 5, hardware: 20 };
    const quoteData: QuoteData = {
      doors: [{ total: 100 }, { total: 200 }], // 300 * 1.1 = 330
      frames: [{ total: 50 }], // 50 * 1.05 = 52.5
      hardware: [{ total: 10 }] // 10 * 1.2 = 12
    }; // Total = 330 + 52.5 + 12 = 394.5

    it('calculates the grand total correctly', () => {
      expect(calculateGrandTotal(quoteData, markups)).toBeCloseTo(394.5);
    });

    it('returns 0 for empty or invalid quoteData', () => {
      expect(calculateGrandTotal({}, markups)).toBe(0);
      expect(calculateGrandTotal(undefined, markups)).toBe(0);
      expect(calculateGrandTotal(null, markups)).toBe(0);
    });

    it('returns 0 for invalid markups', () => {
      expect(calculateGrandTotal(quoteData, undefined)).toBe(0);
      expect(calculateGrandTotal(quoteData, null)).toBe(0);
    });

    it('handles non-standard section names', () => {
      const data: QuoteData = { foo: [{ total: 10 }], bar: [{ total: 20 }] };
      // Assumes hardware markup (20%) for unknown sections
      expect(calculateGrandTotal(data, markups)).toBeCloseTo(10 * 1.2 + 20 * 1.2);
    });

    it('handles mixed data types in quoteData', () => {
      const data: QuoteData = {
        doors: [{ total: 100 }],
        frames: [{ total: 50 }],
        misc: [{ total: NaN } as any]
      };
      // doors: 100 * 1.1 = 110
      // frames: 50 * 1.05 = 52.5
      // misc: 0 * 1.2 = 0
      // total = 162.5
      expect(calculateGrandTotal(data, markups)).toBeCloseTo(162.5);
    });

    it('handles circular references gracefully', () => {
      const data: any = {};
      data.self = data;
      expect(() => calculateGrandTotal(data, markups)).not.toThrow();
      expect(calculateGrandTotal(data, markups)).toBe(0);
    });

    it('returns 0 for empty objects/arrays', () => {
      expect(calculateGrandTotal({}, markups)).toBe(0);
      expect(calculateGrandTotal({ doors: [] }, markups)).toBe(0);
    });

    it('does not throw for any falsy or wrong-type input', () => {
      expect(() => calculateGrandTotal(null, null)).not.toThrow();
      expect(() => calculateGrandTotal({}, {})).not.toThrow();
    });
  });
});