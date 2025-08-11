export const handleImport = (importData: string, setPricingData?: Function, setMarkups?: Function, setShowImportDialog?: Function) => {
  try {
    const data = JSON.parse(importData);
    if (data) {
        if (data.pricingData && setPricingData) {
          setPricingData(data.pricingData);
        }
        if (data.markups && setMarkups) {
          setMarkups(data.markups);
        }
    }
    setShowImportDialog?.(false);
    alert('Data imported successfully!');
  } catch (error) {
    alert('Invalid JSON format. Please check your data.');
  }
};

export const handleExport = (pricingData: any, markups: any, setShowExportDialog?: Function) => {
  const exportData = {
    pricingData,
    markups,
    exportDate: new Date().toISOString()
  };
  try {
    const dataStr = JSON.stringify(exportData, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'door-estimator-data.json';
    link.click();
    URL.revokeObjectURL(url);
  } catch (error) {
    console.error('Export failed:', error);
    alert('Could not export data. It may contain circular references or be too large.');
  } finally {
    setShowExportDialog?.(false);
  }
};
