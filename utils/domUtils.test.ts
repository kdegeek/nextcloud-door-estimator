import { handleImport, handleExport } from './domUtils';

describe('domUtils', () => {
  describe('handleImport', () => {
    it('should call setPricingData and setMarkups with parsed data', () => {
      const setPricingData = jest.fn();
      const setMarkups = jest.fn();
      const setShowImportDialog = jest.fn();
      const importData = JSON.stringify({ pricingData: [1, 2], markups: { a: 1 } });

      handleImport(importData, setPricingData, setMarkups, setShowImportDialog);

      expect(setPricingData).toHaveBeenCalledWith([1, 2]);
      expect(setMarkups).toHaveBeenCalledWith({ a: 1 });
      expect(setShowImportDialog).toHaveBeenCalledWith(false);
    });

    it('should handle invalid JSON gracefully', () => {
      const setPricingData = jest.fn();
      const setMarkups = jest.fn();
      const setShowImportDialog = jest.fn();
      const importData = '{invalid json';

      expect(() => handleImport(importData, setPricingData, setMarkups, setShowImportDialog)).toThrow();
    });
  });

  describe('handleExport', () => {
    it('should call setShowExportDialog with true', () => {
      const setShowExportDialog = jest.fn();
      handleExport({}, {}, setShowExportDialog);
      expect(setShowExportDialog).toHaveBeenCalledWith(true);
    });
  });
});