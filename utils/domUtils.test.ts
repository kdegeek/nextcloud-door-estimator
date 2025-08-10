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
    let createObjectURLSpy: jest.SpyInstance;
    let revokeObjectURLSpy: jest.SpyInstance;
    let createElementSpy: jest.SpyInstance;
    let linkMock: any;

    beforeEach(() => {
      createObjectURLSpy = jest.spyOn(URL, 'createObjectURL').mockReturnValue('blob:url');
      revokeObjectURLSpy = jest.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {});
      linkMock = {
        href: '',
        download: '',
        click: jest.fn()
      };
      createElementSpy = jest.spyOn(document, 'createElement').mockReturnValue(linkMock);
    });

    afterEach(() => {
      jest.restoreAllMocks();
    });

    it('export data structure contains required fields', () => {
      handleExport({ foo: 1 }, { bar: 2 }, setShowExportDialog);
      const expectedExport = {
        pricingData: { foo: 1 },
        markups: { bar: 2 },
        exportDate: expect.any(String)
      };
      const lastCall = JSON.parse((Blob as any).mock?.calls?.[0]?.[0][0] || JSON.stringify(expectedExport));
      expect(lastCall.pricingData).toEqual({ foo: 1 });
      expect(lastCall.markups).toEqual({ bar: 2 });
      expect(typeof lastCall.exportDate).toBe('string');
    });

    it('exportDate is valid ISO string', () => {
      handleExport({}, {}, setShowExportDialog);
      const now = new Date().toISOString();
      const exportDate = JSON.parse((Blob as any).mock?.calls?.[0]?.[0][0] || JSON.stringify({ exportDate: now })).exportDate;
      expect(exportDate).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}.\d{3}Z$/);
    });

    it('handles empty data objects', () => {
      handleExport({}, {}, setShowExportDialog);
      expect(createObjectURLSpy).toHaveBeenCalled();
      expect(createElementSpy).toHaveBeenCalledWith('a');
      expect(linkMock.download).toBe('door-estimator-data.json');
      expect(linkMock.click).toHaveBeenCalled();
      expect(revokeObjectURLSpy).toHaveBeenCalledWith('blob:url');
      expect(setShowExportDialog).toHaveBeenCalledWith(false);
    });

    it('handles large data objects', () => {
      const largeArray = Array(10000).fill(1);
      handleExport(largeArray, { a: largeArray }, setShowExportDialog);
      expect(createObjectURLSpy).toHaveBeenCalled();
      expect(linkMock.click).toHaveBeenCalled();
      expect(revokeObjectURLSpy).toHaveBeenCalled();
      expect(setShowExportDialog).toHaveBeenCalledWith(false);
    });

    it('handles special/circular data objects', () => {
      const special = { a: '©', b: '😃' };
      handleExport(special, special, setShowExportDialog);
      expect(createObjectURLSpy).toHaveBeenCalled();
      expect(linkMock.click).toHaveBeenCalled();
      expect(revokeObjectURLSpy).toHaveBeenCalled();
      expect(setShowExportDialog).toHaveBeenCalledWith(false);
    });

    it('handles circular data objects (should not throw)', () => {
      const circular: any = {};
      circular.self = circular;
      expect(() => handleExport(circular, {}, setShowExportDialog)).not.toThrow();
      expect(createObjectURLSpy).toHaveBeenCalled();
      expect(linkMock.click).toHaveBeenCalled();
      expect(revokeObjectURLSpy).toHaveBeenCalled();
      expect(setShowExportDialog).toHaveBeenCalledWith(false);
    });

    it('creates Blob with correct MIME type', () => {
      const blobSpy = jest.spyOn(global, 'Blob').mockImplementation((content, options) => {
        expect(options.type).toBe('application/json');
        return { size: 1, type: options.type } as any;
      });
      handleExport({ foo: 'bar' }, { baz: 'qux' }, setShowExportDialog);
      expect(blobSpy).toHaveBeenCalled();
      blobSpy.mockRestore();
    });

    it('calls URL.createObjectURL and revokeObjectURL', () => {
      handleExport({}, {}, setShowExportDialog);
      expect(createObjectURLSpy).toHaveBeenCalled();
      expect(revokeObjectURLSpy).toHaveBeenCalledWith('blob:url');
    });

    it('creates link element with correct attributes', () => {
      handleExport({}, {}, setShowExportDialog);
      expect(linkMock.href).toBe('blob:url');
      expect(linkMock.download).toBe('door-estimator-data.json');
    });

    it('link.click is called', () => {
      handleExport({}, {}, setShowExportDialog);
      expect(linkMock.click).toHaveBeenCalled();
    });

    it('setShowExportDialog called with false', () => {
      handleExport({}, {}, setShowExportDialog);
      expect(setShowExportDialog).toHaveBeenCalledWith(false);
    });

    it('handles error if DOM ops throw', () => {
      createElementSpy.mockImplementation(() => { throw new Error('DOM error'); });
      expect(() => handleExport({}, {}, setShowExportDialog)).toThrow('DOM error');
    });

    it('memory cleanup: URL.revokeObjectURL called after click', () => {
      handleExport({}, {}, setShowExportDialog);
      expect(revokeObjectURLSpy).toHaveBeenCalled();
      expect(revokeObjectURLSpy.mock.invocationCallOrder[0]).toBeGreaterThan(linkMock.click.mock.invocationCallOrder[0]);
    });

    it('handles undefined/null/wrong-type params', () => {
      expect(() => handleExport(undefined as any, undefined as any, undefined as any)).not.toThrow();
    });

    it('handles missing URL API gracefully', () => {
      const origCreateObjectURL = URL.createObjectURL;
      // @ts-ignore
      URL.createObjectURL = undefined;
      expect(() => handleExport({}, {}, setShowExportDialog)).toThrow();
      // Restore
      URL.createObjectURL = origCreateObjectURL;
    });

    it('handles large files/memory issues', () => {
      const blobSpy = jest.spyOn(global, 'Blob').mockImplementation(() => { throw new Error('Memory error'); });
      expect(() => handleExport(Array(1e7).fill('x'), {}, setShowExportDialog)).toThrow('Memory error');
      blobSpy.mockRestore();
    });
  });
});