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
      ]
    };

    it('returns the correct price for a known item', () => {
      expect(lookupPrice(pricingData, 'doors', 'A')).toBe(100);
      expect(lookupPrice(pricingData, 'frames', 'F1')).toBe(50);
    });

    it('returns 0 for unknown item', () => {
      expect(lookupPrice(pricingData, 'doors', 'X')).toBe(0);
      expect(lookupPrice(pricingData, 'unknown', 'A')).toBe(0);
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

    it('calculates total with markup for doors', () => {
      // subtotal = 300, markup = 10% => 330
      expect(calculateSectionTotal(quoteData, markups, 'doors')).toBe(330);
    });

    it('calculates total with markup for frames', () => {
      // subtotal = 50, markup = 5% => 52.5
      expect(calculateSectionTotal(quoteData, markups, 'frames')).toBe(52.5);
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

    it('sums all section totals with markups', () => {
      // doors: 330, frames: 52.5 => 382.5
      expect(calculateGrandTotal(quoteData, markups)).toBe(382.5);
    });
  });
});