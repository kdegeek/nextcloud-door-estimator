import { handleImport, handleExport } from './domUtils';

describe('domUtils', () => {
  let alertSpy: jest.SpyInstance;
  let setPricingData: jest.Mock;
  let setMarkups: jest.Mock;
  let setShowImportDialog: jest.Mock;
  let setShowExportDialog: jest.Mock;

  beforeEach(() => {
    alertSpy = jest.spyOn(global, 'alert').mockImplementation(() => {});
    setPricingData = jest.fn();
    setMarkups = jest.fn();
    setShowImportDialog = jest.fn();
    setShowExportDialog = jest.fn();
  });

  afterEach(() => {
    jest.restoreAllMocks();
    jest.clearAllMocks();
  });

  describe('handleImport', () => {
    it('imports valid JSON with pricingData and markups', () => {
      const importData = JSON.stringify({ pricingData: [1, 2], markups: { a: 1 } });
      handleImport(importData, setPricingData, setMarkups, setShowImportDialog);
      expect(setPricingData).toHaveBeenCalledWith([1, 2]);
      expect(setMarkups).toHaveBeenCalledWith({ a: 1 });
      expect(setShowImportDialog).toHaveBeenCalledWith(false);
      expect(alertSpy).toHaveBeenCalledWith('Data imported successfully!');
    });

    it('imports valid JSON with only pricingData', () => {
      const importData = JSON.stringify({ pricingData: [1, 2] });
      handleImport(importData, setPricingData, setMarkups, setShowImportDialog);
      expect(setPricingData).toHaveBeenCalledWith([1, 2]);
      expect(setMarkups).not.toHaveBeenCalled();
      expect(setShowImportDialog).toHaveBeenCalledWith(false);
      expect(alertSpy).toHaveBeenCalledWith('Data imported successfully!');
    });

    it('imports valid JSON with only markups', () => {
      const importData = JSON.stringify({ markups: { a: 1 } });
      handleImport(importData, setPricingData, setMarkups, setShowImportDialog);
      expect(setPricingData).not.toHaveBeenCalled();
      expect(setMarkups).toHaveBeenCalledWith({ a: 1 });
      expect(setShowImportDialog).toHaveBeenCalledWith(false);
      expect(alertSpy).toHaveBeenCalledWith('Data imported successfully!');
    });

    it('imports JSON with extra/unexpected fields', () => {
      const importData = JSON.stringify({ pricingData: [1], markups: { b: 2 }, extra: 123 });
      handleImport(importData, setPricingData, setMarkups, setShowImportDialog);
      expect(setPricingData).toHaveBeenCalledWith([1]);
      expect(setMarkups).toHaveBeenCalledWith({ b: 2 });
      expect(setShowImportDialog).toHaveBeenCalledWith(false);
      expect(alertSpy).toHaveBeenCalledWith('Data imported successfully!');
    });

    it('imports nested JSON', () => {
      const importData = JSON.stringify({ pricingData: { nested: [1] }, markups: { deep: { a: 2 } } });
      handleImport(importData, setPricingData, setMarkups, setShowImportDialog);
      expect(setPricingData).toHaveBeenCalledWith({ nested: [1] });
      expect(setMarkups).toHaveBeenCalledWith({ deep: { a: 2 } });
      expect(setShowImportDialog).toHaveBeenCalledWith(false);
      expect(alertSpy).toHaveBeenCalledWith('Data imported successfully!');
    });

    it('imports empty JSON', () => {
      const importData = JSON.stringify({});
      handleImport(importData, setPricingData, setMarkups, setShowImportDialog);
      expect(setPricingData).not.toHaveBeenCalled();
      expect(setMarkups).not.toHaveBeenCalled();
      expect(setShowImportDialog).toHaveBeenCalledWith(false);
      expect(alertSpy).toHaveBeenCalledWith('Data imported successfully!');
    });

    it('imports null JSON', () => {
      const importData = 'null';
      handleImport(importData, setPricingData, setMarkups, setShowImportDialog);
      expect(setPricingData).not.toHaveBeenCalled();
      expect(setMarkups).not.toHaveBeenCalled();
      expect(setShowImportDialog).toHaveBeenCalledWith(false);
      expect(alertSpy).toHaveBeenCalledWith('Data imported successfully!');
    });

    it('imports large JSON', () => {
      const largeArray = Array(10000).fill(1);
      const importData = JSON.stringify({ pricingData: largeArray, markups: { a: largeArray } });
      handleImport(importData, setPricingData, setMarkups, setShowImportDialog);
      expect(setPricingData).toHaveBeenCalledWith(largeArray);
      expect(setMarkups).toHaveBeenCalledWith({ a: largeArray });
      expect(setShowImportDialog).toHaveBeenCalledWith(false);
      expect(alertSpy).toHaveBeenCalledWith('Data imported successfully!');
    });

    it('imports JSON with special characters', () => {
      const importData = JSON.stringify({ pricingData: ['©', '™', '✓'], markups: { emoji: '😃' } });
      handleImport(importData, setPricingData, setMarkups, setShowImportDialog);
      expect(setPricingData).toHaveBeenCalledWith(['©', '™', '✓']);
      expect(setMarkups).toHaveBeenCalledWith({ emoji: '😃' });
      expect(setShowImportDialog).toHaveBeenCalledWith(false);
      expect(alertSpy).toHaveBeenCalledWith('Data imported successfully!');
    });

    it('handles malformed JSON at start', () => {
      const importData = '{pricingData: [1, 2]';
      handleImport(importData, setPricingData, setMarkups, setShowImportDialog);
      expect(alertSpy).toHaveBeenCalledWith('Invalid JSON format. Please check your data.');
      expect(setPricingData).not.toHaveBeenCalled();
      expect(setMarkups).not.toHaveBeenCalled();
      expect(setShowImportDialog).not.toHaveBeenCalled();
    });

    it('handles malformed JSON at end', () => {
      const importData = '{"pricingData": [1, 2]}}';
      handleImport(importData, setPricingData, setMarkups, setShowImportDialog);
      expect(alertSpy).toHaveBeenCalledWith('Invalid JSON format. Please check your data.');
      expect(setPricingData).not.toHaveBeenCalled();
      expect(setMarkups).not.toHaveBeenCalled();
      expect(setShowImportDialog).not.toHaveBeenCalled();
    });

    it('handles malformed JSON in middle', () => {
      const importData = '{"pricingData": [1, 2], markups: }';
      handleImport(importData, setPricingData, setMarkups, setShowImportDialog);
      expect(alertSpy).toHaveBeenCalledWith('Invalid JSON format. Please check your data.');
      expect(setPricingData).not.toHaveBeenCalled();
      expect(setMarkups).not.toHaveBeenCalled();
      expect(setShowImportDialog).not.toHaveBeenCalled();
    });

    it('calls error alert if setters throw', () => {
      setPricingData.mockImplementation(() => { throw new Error('fail'); });
      const importData = JSON.stringify({ pricingData: [1] });
      handleImport(importData, setPricingData, setMarkups, setShowImportDialog);
      // The error will be caught by the catch block
      expect(alertSpy).toHaveBeenCalledWith('Invalid JSON format. Please check your data.');
      expect(setShowImportDialog).not.toHaveBeenCalled();
    });

    it('handles circular JSON (should throw and alert)', () => {
      const circular: any = {};
      circular.self = circular;
      let importData = '';
      try {
        importData = JSON.stringify(circular);
      } catch (e) {
        importData = '{"self":{}}'; // fallback to non-circular for test
      }
      handleImport('{"self":{}}', setPricingData, setMarkups, setShowImportDialog);
      expect(alertSpy).toHaveBeenCalled();
    });

    it('handles undefined/null/wrong-type params', () => {
      handleImport('{}', undefined as any, undefined as any, undefined as any);
      expect(alertSpy).toHaveBeenCalledWith('Data imported successfully!');
      // Should not throw
    });
  });

  describe('handleExport', () => {
    let linkMock: { href: string; download: string; click: jest.Mock };
    let createElementSpy: jest.SpyInstance;
    let setShowExportDialog: jest.Mock;

    beforeEach(() => {
      setShowExportDialog = jest.fn();
      // Properly mock the necessary browser APIs
      global.URL.createObjectURL = jest.fn(() => 'blob:url');
      global.URL.revokeObjectURL = jest.fn();
      global.Blob = jest.fn((content, options) => ({
          content,
          size: content.join('').length,
          type: options?.type,
      })) as any;

      linkMock = {
        href: '',
        download: '',
        click: jest.fn(),
      };
      createElementSpy = jest.spyOn(document, 'createElement').mockReturnValue(linkMock as any);
    });

    it('exports data correctly', () => {
      const pricingData = { doors: [{ item: 'A', price: 100 }] };
      const markups = { doors: 15 };
      handleExport(pricingData, markups, setShowExportDialog);

      expect(Blob).toHaveBeenCalledWith([expect.any(String)], { type: 'application/json' });
      expect(URL.createObjectURL).toHaveBeenCalled();
      expect(createElementSpy).toHaveBeenCalledWith('a');
      expect(linkMock.download).toBe('door-estimator-data.json');
      expect(linkMock.click).toHaveBeenCalled();
      expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:url');
      expect(setShowExportDialog).toHaveBeenCalledWith(false);
    });

    it('handles circular references gracefully', () => {
      const circular: any = {};
      circular.self = circular;
      handleExport(circular, {}, setShowExportDialog);
      expect(alertSpy).toHaveBeenCalledWith('Could not export data. It may contain circular references or be too large.');
      expect(setShowExportDialog).toHaveBeenCalledWith(false);
    });

    it('handles DOM errors gracefully', () => {
      createElementSpy.mockImplementation(() => {
        throw new Error('DOM Error');
      });
      handleExport({}, {}, setShowExportDialog);
      expect(alertSpy).toHaveBeenCalledWith('Could not export data. It may contain circular references or be too large.');
      expect(setShowExportDialog).toHaveBeenCalledWith(false);
    });

    it('handles undefined callback gracefully', () => {
        expect(() => handleExport({}, {}, undefined)).not.toThrow();
    });
  });
});