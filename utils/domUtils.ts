export const handleImport = (importData: string, setPricingData: Function, setMarkups: Function, setShowImportDialog: Function) => {
  try {
    const data = JSON.parse(importData);
    if (data.pricingData) {
      setPricingData(data.pricingData);
    }
    if (data.markups) {
      setMarkups(data.markups);
    }
    setShowImportDialog(false);
    alert('Data imported successfully!');
  } catch (error) {
    alert('Invalid JSON format. Please check your data.');
  }
};

export const handleExport = (pricingData: any, markups: any, setShowExportDialog: Function) => {
  const exportData = {
    pricingData,
    markups,
    exportDate: new Date().toISOString()
  };
  const dataStr = JSON.stringify(exportData, null, 2);
  const dataBlob = new Blob([dataStr], { type: 'application/json' });
  const url = URL.createObjectURL(dataBlob);
  const link = document.createElement('a');
  link.href = url;
  link.download = 'door-estimator-data.json';
  link.click();
  URL.revokeObjectURL(url);
  setShowExportDialog(false);
};
