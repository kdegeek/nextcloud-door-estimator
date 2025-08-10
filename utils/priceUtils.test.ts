import { calculateSectionTotal, calculateGrandTotal, lookupPrice } from './priceUtils';

describe('priceUtils', () => {
  describe('lookupPrice', () => {
    const pricingData = {
      doors: [
        { item: 'A', price: 100 },
        { item: 'B', price: 200 }
      ],
      frames: [
        { item: 'F1', price: 50 }
      ],
      misc: { notArray: true }
    };

    it('returns the correct price for a known item', () => {
      expect(lookupPrice(pricingData, 'doors', 'A')).toBe(100);
      expect(lookupPrice(pricingData, 'frames', 'F1')).toBe(50);
    });

    it('returns 0 for unknown item', () => {
      expect(lookupPrice(pricingData, 'doors', 'X')).toBe(0);
      expect(lookupPrice(pricingData, 'unknown', 'A')).toBe(0);
    });

    it('returns 0 for undefined/null pricingData', () => {
      expect(lookupPrice(undefined, 'doors', 'A')).toBe(0);
      expect(lookupPrice(null, 'doors', 'A')).toBe(0);
    });

    it('returns 0 for undefined/null category', () => {
      expect(lookupPrice(pricingData, undefined, 'A')).toBe(0);
      expect(lookupPrice(pricingData, null, 'A')).toBe(0);
    });

    it('returns 0 for empty string item', () => {
      expect(lookupPrice(pricingData, 'doors', '')).toBe(0);
    });

    it('returns 0 for category not in pricingData', () => {
      expect(lookupPrice(pricingData, 'notfound', 'A')).toBe(0);
    });

    it('returns 0 for frameType when category data is not object', () => {
      expect(lookupPrice(pricingData, 'misc', 'A', 'frameX')).toBe(0);
    });

    it('returns 0 for frameType not in category', () => {
      const pd = { doors: { frameA: [{ item: 'A', price: 100 }] } };
      expect(lookupPrice(pd, 'doors', 'A', 'frameB')).toBe(0);
    });

    it('returns 0 for non-array data structures', () => {
      expect(lookupPrice({ doors: { notArray: true } }, 'doors', 'A')).toBe(0);
    });

    it('returns 0 for items missing price field', () => {
      const pd = { doors: [{ item: 'A' }] };
      expect(lookupPrice(pd, 'doors', 'A')).toBe(0);
    });

    it('returns 0 for items with null/undefined price', () => {
      const pd = { doors: [{ item: 'A', price: null }, { item: 'B', price: undefined }] };
      expect(lookupPrice(pd, 'doors', 'A')).toBe(0);
      expect(lookupPrice(pd, 'doors', 'B')).toBe(0);
    });

    it('returns price of first match for duplicate items', () => {
      const pd = { doors: [{ item: 'A', price: 100 }, { item: 'A', price: 200 }] };
      expect(lookupPrice(pd, 'doors', 'A')).toBe(100);
    });

    it('handles long/special/numeric item names', () => {
      const pd = { doors: [
        { item: 'A-very-long-item-name_123!@#$', price: 999 },
        { item: 42, price: 42 }
      ] };
      expect(lookupPrice(pd, 'doors', 'A-very-long-item-name_123!@#$')).toBe(999);
      expect(lookupPrice(pd, 'doors', 42)).toBe(42);
    });

    it('handles malformed data gracefully', () => {
      expect(lookupPrice({}, 'doors', 'A')).toBe(0);
      expect(lookupPrice({ doors: null }, 'doors', 'A')).toBe(0);
      expect(lookupPrice({ doors: undefined }, 'doors', 'A')).toBe(0);
      expect(lookupPrice({ doors: [null, undefined] }, 'doors', 'A')).toBe(0);
    });

    it('does not throw for any falsy or wrong-type input', () => {
      expect(() => lookupPrice()).not.toThrow();
      expect(() => lookupPrice(null, null, null)).not.toThrow();
      expect(() => lookupPrice({}, {}, {}, {})).not.toThrow();
      expect(() => lookupPrice([], [], [], [])).not.toThrow();
    });
  });

  describe('calculateSectionTotal', () => {
    const markups = { doors: 10, frames: 5, hardware: 0 };
    const quoteData = {
      doors: [{ total: 100 }, { total: 200 }],
      frames: [{ total: 50 }]
    };

    it('returns 0 for empty section', () => {
      expect(calculateSectionTotal({}, markups, 'doors')).toBe(0);
    });

    it('returns 0 for undefined/null quoteData', () => {
      expect(calculateSectionTotal(undefined, markups, 'doors')).toBe(0);
      expect(calculateSectionTotal(null, markups, 'doors')).toBe(0);
    });

    it('returns 0 for undefined/null markups', () => {
      expect(calculateSectionTotal(quoteData, undefined, 'doors')).toBe(0);
      expect(calculateSectionTotal(quoteData, null, 'doors')).toBe(0);
    });

    it('returns 0 for section not in quoteData', () => {
      expect(calculateSectionTotal(quoteData, markups, 'notfound')).toBe(0);
    });

    it('handles items with undefined/null/NaN/negative/large total', () => {
      const data = {
        doors: [
          { total: undefined },
          { total: null },
          { total: NaN },
          { total: -100 },
          { total: 1e12 }
        ]
      };
      const mk = { doors: 10, frames: 5, hardware: 0 };
      // subtotal = -100 + 1e12 = 999999999900
      expect(calculateSectionTotal(data, mk, 'doors')).toBeCloseTo(999999999900 * 1.1);
    });

    it('returns 0 for zero/negative/non-numeric markup values', () => {
      const data = { doors: [{ total: 100 }] };
      expect(calculateSectionTotal(data, { doors: 0, frames: 0, hardware: 0 }, 'doors')).toBe(100);
      expect(calculateSectionTotal(data, { doors: -10, frames: 0, hardware: 0 }, 'doors')).toBe(90);
      expect(calculateSectionTotal(data, { doors: NaN, frames: 0, hardware: 0 }, 'doors')).toBeNaN();
      expect(calculateSectionTotal(data, { doors: 'abc', frames: 0, hardware: 0 }, 'doors')).toBeNaN();
    });

    it('returns subtotal for sections not in markup mapping', () => {
      const data = { custom: [{ total: 100 }] };
      expect(calculateSectionTotal(data, markups, 'custom')).toBe(100);
    });

    it('returns 0 for empty/non-array section data', () => {
      expect(calculateSectionTotal({ doors: [] }, markups, 'doors')).toBe(0);
      expect(calculateSectionTotal({ doors: null }, markups, 'doors')).toBe(0);
      expect(calculateSectionTotal({ doors: undefined }, markups, 'doors')).toBe(0);
      expect(calculateSectionTotal({ doors: {} }, markups, 'doors')).toBe(0);
    });

    it('does not throw for any falsy or wrong-type input', () => {
      expect(() => calculateSectionTotal()).not.toThrow();
      expect(() => calculateSectionTotal(null, null, null)).not.toThrow();
      expect(() => calculateSectionTotal({}, {}, {})).not.toThrow();
      expect(() => calculateSectionTotal([], [], [])).not.toThrow();
    });
  });

  describe('calculateGrandTotal', () => {
    const markups = { doors: 10, frames: 5, hardware: 0 };
    const quoteData = {
      doors: [{ total: 100 }, { total: 200 }],
      frames: [{ total: 50 }]
    };

    it('returns 0 for empty quoteData', () => {
      expect(calculateGrandTotal({}, markups)).toBe(0);
    });

    it('returns 0 for undefined/null quoteData', () => {
      expect(calculateGrandTotal(undefined, markups)).toBe(0);
      expect(calculateGrandTotal(null, markups)).toBe(0);
    });

    it('returns 0 for undefined/null markups', () => {
      expect(calculateGrandTotal(quoteData, undefined)).toBe(0);
      expect(calculateGrandTotal(quoteData, null)).toBe(0);
    });

    it('handles non-standard section names', () => {
      const data = { foo: [{ total: 10 }], bar: [{ total: 20 }] };
      expect(calculateGrandTotal(data, markups)).toBe(30);
    });

    it('handles mixed data types in quoteData', () => {
      const data = { doors: [{ total: 100 }], frames: [null, { total: 50 }], misc: [undefined, { total: 'abc' }] };
      expect(calculateGrandTotal(data, markups)).toBeCloseTo(100 * 1.1 + 50 * 1.05);
    });

    it('handles very large numbers/overflow', () => {
      const data = { doors: [{ total: Number.MAX_SAFE_INTEGER }], frames: [{ total: Number.MAX_SAFE_INTEGER }] };
      const result = calculateGrandTotal(data, markups);
      expect(result).toBeGreaterThan(0);
      expect(result).toBeLessThan(Number.POSITIVE_INFINITY);
    });

    it('handles circular references gracefully', () => {
      const data = {};
      data.self = data;
      expect(() => calculateGrandTotal(data, markups)).not.toThrow();
      expect(calculateGrandTotal(data, markups)).toBe(0);
    });

    it('returns 0 for empty objects/arrays', () => {
      expect(calculateGrandTotal({}, markups)).toBe(0);
      expect(calculateGrandTotal({ doors: [] }, markups)).toBe(0);
      expect(calculateGrandTotal({ doors: null }, markups)).toBe(0);
      expect(calculateGrandTotal({ doors: undefined }, markups)).toBe(0);
    });

    it('performance with large datasets', () => {
      const large = Array.from({ length: 10000 }, (_, i) => ({ total: i }));
      const data = { doors: large, frames: large };
      const result = calculateGrandTotal(data, { doors: 10, frames: 5, hardware: 0 });
      expect(result).toBeGreaterThan(0);
    });

    it('does not throw for any falsy or wrong-type input', () => {
      expect(() => calculateGrandTotal()).not.toThrow();
      expect(() => calculateGrandTotal(null, null)).not.toThrow();
      expect(() => calculateGrandTotal({}, {})).not.toThrow();
      expect(() => calculateGrandTotal([], [])).not.toThrow();
    });
  });
});