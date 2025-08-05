import React, { useState, useEffect } from 'react';
import { Calculator, Plus, Minus, Save, FileText, Settings, Database, Moon, Sun, Upload, Download, Edit3 } from 'lucide-react';
import { lookupPrice, calculateSectionTotal, calculateGrandTotal } from './utils/priceUtils';
import QuoteSection from './QuoteSection';
import { handleImport, handleExport } from './utils/domUtils';

const DoorEstimatorApp = () => {
  const [activeTab, setActiveTab] = useState('estimator');
  const [darkMode, setDarkMode] = useState(false);
  const [inputError, setInputError] = useState('');
  const [userFeedback, setUserFeedback] = useState('');
  const [quoteData, setQuoteData] = useState({
    doors: [
      { id: 'A', item: '', qty: 0, price: 0, total: 0 },
      { id: 'B', item: '', qty: 0, price: 0, total: 0 }
    ],
    doorOptions: [
      { id: 'D', item: '', qty: 0, price: 0, total: 0 },
      { id: 'E', item: '', qty: 0, price: 0, total: 0 }
    ],
    inserts: [
      { id: 'F', item: '', qty: 0, price: 0, total: 0 }
    ],
    frames: [
      { id: 'H', item: '', qty: 0, price: 0, total: 0, frameType: 'HM Drywall' }
    ],
    frameOptions: [
      { id: 'I', item: '', qty: 0, price: 0, total: 0 },
      { id: 'J', item: '', qty: 0, price: 0, total: 0 },
      { id: 'K', item: '', qty: 0, price: 0, total: 0 }
    ],
    hinges: [
      { id: 'L', item: '', qty: 0, price: 0, total: 0 }
    ],
    weatherstrip: [
      { id: 'M', item: '', qty: 0, price: 0, total: 0 },
      { id: 'N', item: '', qty: 0, price: 0, total: 0 },
      { id: 'O', item: '', qty: 0, price: 0, total: 0 },
      { id: 'P', item: '', qty: 0, price: 0, total: 0 }
    ],
    closers: [
      { id: 'Q', item: '', qty: 0, price: 0, total: 0 }
    ],
    locksets: [
      { id: 'R', item: '', qty: 0, price: 0, total: 0 },
      { id: 'S', item: '', qty: 0, price: 0, total: 0 }
    ],
    exitDevices: [
      { id: 'T', item: '', qty: 0, price: 0, total: 0 },
      { id: 'U', item: '', qty: 0, price: 0, total: 0 }
    ],
    hardware: [
      { id: 'V', item: '', qty: 0, price: 0, total: 0 },
      { id: 'W', item: '', qty: 0, price: 0, total: 0 },
      { id: 'X', item: '', qty: 0, price: 0, total: 0 },
      { id: 'Y', item: '', qty: 0, price: 0, total: 0 }
    ]
  });

  const [markups, setMarkups] = useState({
    doors: 15, // 15% markup on doors
    frames: 12, // 12% markup on frames  
    hardware: 18 // 18% markup on hardware
  });

  const [savedQuotes, setSavedQuotes] = useState([]);
  const [currentQuoteId, setCurrentQuoteId] = useState(null);
  const [showImportDialog, setShowImportDialog] = useState(false);
  const [showExportDialog, setShowExportDialog] = useState(false);
  const [showProductManager, setShowProductManager] = useState(false);
  const [importData, setImportData] = useState('');

  // Sample pricing database - in real app this would come from PHP backend
  const [pricingData, setPricingData] = useState({
    doors: [
      { item: '2-0 x 6-8 Flush HM 18ga. Cyl lock prep, 2-3/4 backset', price: 493 },
      { item: '2-4 x 6-8 Flush HM 18ga. Cyl lock prep, 2-3/4 backset', price: 498 },
      { item: '2-6 x 6-8 Flush HM 18ga. Cyl lock prep, 2-3/4 backset', price: 510 },
      { item: '2-8 x 6-8 Flush HM 18ga. Cyl lock prep, 2-3/4 backset', price: 525 },
      { item: '3-0 x 6-8 Flush HM 18ga. Cyl lock prep, 2-3/4 backset', price: 541 }
    ],
    doorOptions: [
      { item: 'Deadbolt Bore', price: 52 },
      { item: 'Z-Ast w/ASA strike prep attached', price: 103 },
      { item: 'Z-Ast w/flush bolt prep attached', price: 173 }
    ],
    inserts: [
      { item: '6x27 Clear Fire Rated Glass/Mtl Frm (4x25 dlo)', price: 279.79 },
      { item: '6x27 WireShield Glass/Metal Frame (4"x25" dlo)', price: 234.90 },
      { item: '12x12 WireShield Glass/Metal Frame (10"x10" dlo)', price: 207.63 }
    ],
    frames: {
      'HM Drywall': [
        { item: '2-0 x 6-8 x 5-7/8 KD 16ga. HM Drywall Frame, UL Fire Label', price: 228 },
        { item: '2-4 x 6-8 x 5-7/8 KD 16ga. HM Drywall Frame, UL Fire Label', price: 233 },
        { item: '2-6 x 6-8 x 5-7/8 KD 16ga. HM Drywall Frame, UL Fire Label', price: 245 }
      ],
      'HM EWA': [
        { item: '2-0 x 6-8 x 4-9/16 KD 16ga. HM EWA Frame, UL Fire Label', price: 198 },
        { item: '2-4 x 6-8 x 4-9/16 KD 16ga. HM EWA Frame, UL Fire Label', price: 203 },
        { item: '2-6 x 6-8 x 4-9/16 KD 16ga. HM EWA Frame, UL Fire Label', price: 215 }
      ],
      'HM USA': [
        { item: '2-0 x 6-8 x 3-5/8 KD 16ga. HM USA Frame, UL Fire Label', price: 168 },
        { item: '2-4 x 6-8 x 3-5/8 KD 16ga. HM USA Frame, UL Fire Label', price: 173 },
        { item: '2-6 x 6-8 x 3-5/8 KD 16ga. HM USA Frame, UL Fire Label', price: 185 }
      ]
    },
    frameOptions: [
      { item: 'Face Weld & Finish', price: 32 },
      { item: 'Deadbolt Strike Prep', price: 35 },
      { item: 'Jamb reinf. for Rim exit device', price: 12 }
    ],
    hinges: [
      { item: 'BB5 4.5 x 4.5 NRP USP', price: 8.50 },
      { item: 'BB5 4.5 x 4.5 NRP US26D', price: 12.75 },
      { item: 'BB179 4.5 x 4.5 NRP US26D Heavy Duty', price: 28.50 }
    ]
  });

  // Price lookup function - use imported utility from priceUtils
  // (Removed local shadowing definition to prevent recursion bug)

  // Calculate section totals with markups
// (calculateSectionTotal removed; now using imported utility)

  // Calculate grand total
// (calculateGrandTotal removed; now using imported utility)

  // Update item and recalculate price
  const updateQuoteItem = (section, index, field, value) => {
    setQuoteData(prev => {
      // Create a new array for the section, updating only the target item immutably
      const newSectionArray = prev[section].map((item, idx) => {
        if (idx !== index) return item;
        const updatedItem = { ...item, [field]: value };
  
        let price = updatedItem.price;
        if (field === 'item' || field === 'frameType') {
          // Recalculate price based on item selection
          if (section === 'frames') {
            price = lookupPrice(pricingData, 'frames', updatedItem.item, updatedItem.frameType);
          } else {
            price = lookupPrice(pricingData, section, updatedItem.item);
          }
        }
        // Always recalculate price if item or frameType changed
        if (field === 'item' || field === 'frameType') {
          updatedItem.price = price;
        }
  
        // Always recalculate total if qty or price changed
        if (field === 'qty' || field === 'price' || field === 'item' || field === 'frameType') {
          updatedItem.total = (parseFloat(updatedItem.qty) || 0) * (parseFloat(updatedItem.price) || 0);
        }
  
        return updatedItem;
      });
  
      return {
        ...prev,
        [section]: newSectionArray
      };
    });
  };



  // Product management functions
  const addProduct = (category, product) => {
    setPricingData(prev => {
      if (Array.isArray(prev[category])) {
        return {
          ...prev,
          [category]: [...prev[category], product]
        };
      }
      return prev;
    });
  };

  const removeProduct = (category, index) => {
    setPricingData(prev => {
      if (Array.isArray(prev[category])) {
        return {
          ...prev,
          [category]: prev[category].filter((_, i) => i !== index)
        };
      }
      return prev;
    });
  };

  const updateProduct = (category, index, product) => {
    setPricingData(prev => {
      if (Array.isArray(prev[category])) {
        return {
          ...prev,
          [category]: prev[category].map((item, i) => (i === index ? product : item))
        };
      }
      return prev;
    });
  };

    // Higher-order function to map over items and render a component
    const mapItems = (items, renderFn) => items.map((item, idx) => renderFn(item, idx));
  
    // (renderQuoteSection removed; now using QuoteSection component)

  // Main estimator interface
  const renderEstimator = () => (
    <div className="space-y-6">
      <div className={`${darkMode ? 'bg-gradient-to-r from-gray-800 to-gray-900' : 'bg-gradient-to-r from-blue-600 to-blue-700'} text-white p-6 rounded-lg shadow-lg`}>
        <h2 className="text-2xl font-bold mb-2">Door & Hardware Estimator</h2>
        <p className={`${darkMode ? 'text-gray-300' : 'text-blue-100'}`}>Create professional door and hardware estimates</p>
      </div>

      <QuoteSection
        title="Doors"
        sectionKey="doors"
        quoteData={quoteData}
        pricingData={pricingData}
        darkMode={darkMode}
        updateQuoteItem={updateQuoteItem}
        calculateSectionTotal={(section) => calculateSectionTotal(quoteData, markups, section)}
        inputError={setInputError}
      />
      <QuoteSection
        title="Door Options"
        sectionKey="doorOptions"
        quoteData={quoteData}
        pricingData={pricingData}
        darkMode={darkMode}
        updateQuoteItem={updateQuoteItem}
        calculateSectionTotal={(section) => calculateSectionTotal(quoteData, markups, section)}
        inputError={setInputError}
      />
      <QuoteSection
        title="Inserts"
        sectionKey="inserts"
        quoteData={quoteData}
        pricingData={pricingData}
        darkMode={darkMode}
        updateQuoteItem={updateQuoteItem}
        calculateSectionTotal={(section) => calculateSectionTotal(quoteData, markups, section)}
        inputError={setInputError}
      />
      <QuoteSection
        title="Frames"
        sectionKey="frames"
        hasFrameType={true}
        quoteData={quoteData}
        pricingData={pricingData}
        darkMode={darkMode}
        updateQuoteItem={updateQuoteItem}
        calculateSectionTotal={(section) => calculateSectionTotal(quoteData, markups, section)}
        inputError={setInputError}
      />
      <QuoteSection
        title="Frame Options"
        sectionKey="frameOptions"
        quoteData={quoteData}
        pricingData={pricingData}
        darkMode={darkMode}
        updateQuoteItem={updateQuoteItem}
        calculateSectionTotal={(section) => calculateSectionTotal(quoteData, markups, section)}
        inputError={setInputError}
      />
      <QuoteSection
        title="Hinges"
        sectionKey="hinges"
        quoteData={quoteData}
        pricingData={pricingData}
        darkMode={darkMode}
        updateQuoteItem={updateQuoteItem}
        calculateSectionTotal={(section) => calculateSectionTotal(quoteData, markups, section)}
        inputError={setInputError}
      />
      <QuoteSection
        title="Weatherstrip"
        sectionKey="weatherstrip"
        quoteData={quoteData}
        pricingData={pricingData}
        darkMode={darkMode}
        updateQuoteItem={updateQuoteItem}
        calculateSectionTotal={(section) => calculateSectionTotal(quoteData, markups, section)}
        inputError={setInputError}
      />
      <QuoteSection
        title="Closers"
        sectionKey="closers"
        quoteData={quoteData}
        pricingData={pricingData}
        darkMode={darkMode}
        updateQuoteItem={updateQuoteItem}
        calculateSectionTotal={(section) => calculateSectionTotal(quoteData, markups, section)}
        inputError={setInputError}
      />
      <QuoteSection
        title="Locksets"
        sectionKey="locksets"
        quoteData={quoteData}
        pricingData={pricingData}
        darkMode={darkMode}
        updateQuoteItem={updateQuoteItem}
        calculateSectionTotal={(section) => calculateSectionTotal(quoteData, markups, section)}
        inputError={setInputError}
      />
      <QuoteSection
        title="Exit Devices"
        sectionKey="exitDevices"
        quoteData={quoteData}
        pricingData={pricingData}
        darkMode={darkMode}
        updateQuoteItem={updateQuoteItem}
        calculateSectionTotal={(section) => calculateSectionTotal(quoteData, markups, section)}
        inputError={setInputError}
      />
      <QuoteSection
        title="Hardware"
        sectionKey="hardware"
        quoteData={quoteData}
        pricingData={pricingData}
        darkMode={darkMode}
        updateQuoteItem={updateQuoteItem}
        calculateSectionTotal={(section) => calculateSectionTotal(quoteData, markups, section)}
        inputError={setInputError}
      />

      {/* Markup Configuration */}
      <div className={`${darkMode ? 'bg-yellow-900 border-yellow-700' : 'bg-yellow-50 border-yellow-200'} border rounded-lg p-6 shadow-sm`}>
        <h3 className={`text-lg font-semibold ${darkMode ? 'text-yellow-100' : 'text-gray-800'} mb-4`}>Markup Settings</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <label className={`block text-sm font-medium ${darkMode ? 'text-gray-200' : 'text-gray-700'} mb-2`}>Doors & Inserts</label>
            <div className="flex items-center">
              <input
                type="number"
                min="0"
                max="100"
                className={`w-20 px-3 py-2 border rounded-md text-sm text-center ${darkMode ? 'bg-gray-700 border-gray-600 text-gray-100' : 'bg-white border-gray-300 text-gray-900'} focus:ring-2 focus:ring-blue-500 focus:border-blue-500`}
                value={markups.doors}
                onChange={(e) => {
                  const val = e.target.value;
                  if (!/^\d{1,3}$/.test(val) || parseInt(val, 10) < 0 || parseInt(val, 10) > 100) {
                    setInputError('Markup must be a number between 0 and 100');
                  } else {
                    setInputError('');
                    setMarkups(prev => ({ ...prev, doors: parseFloat(val) || 0 }));
                  }
                }}
              />
              <span className={`ml-2 text-sm ${darkMode ? 'text-gray-300' : 'text-gray-600'} font-medium`}>%</span>
            </div>
          </div>
          <div>
            <label className={`block text-sm font-medium ${darkMode ? 'text-gray-200' : 'text-gray-700'} mb-2`}>Frames</label>
            <div className="flex items-center">
              <input
                type="number"
                min="0"
                max="100"
                className={`w-20 px-3 py-2 border rounded-md text-sm text-center ${darkMode ? 'bg-gray-700 border-gray-600 text-gray-100' : 'bg-white border-gray-300 text-gray-900'} focus:ring-2 focus:ring-blue-500 focus:border-blue-500`}
                value={markups.frames}
                onChange={(e) => {
                  const val = e.target.value;
                  if (!/^\d{1,3}$/.test(val) || parseInt(val, 10) < 0 || parseInt(val, 10) > 100) {
                    setInputError('Markup must be a number between 0 and 100');
                  } else {
                    setInputError('');
                    setMarkups(prev => ({ ...prev, frames: parseFloat(val) || 0 }));
                  }
                }}
              />
              <span className={`ml-2 text-sm ${darkMode ? 'text-gray-300' : 'text-gray-600'} font-medium`}>%</span>
            </div>
          </div>
          <div>
            <label className={`block text-sm font-medium ${darkMode ? 'text-gray-200' : 'text-gray-700'} mb-2`}>Hardware</label>
            <div className="flex items-center">
              <input
                type="number"
                min="0"
                max="100"
                className={`w-20 px-3 py-2 border rounded-md text-sm text-center ${darkMode ? 'bg-gray-700 border-gray-600 text-gray-100' : 'bg-white border-gray-300 text-gray-900'} focus:ring-2 focus:ring-blue-500 focus:border-blue-500`}
                value={markups.hardware}
                onChange={(e) => {
                  const val = e.target.value;
                  if (!/^\d{1,3}$/.test(val) || parseInt(val, 10) < 0 || parseInt(val, 10) > 100) {
                    setInputError('Markup must be a number between 0 and 100');
                  } else {
                    setInputError('');
                    setMarkups(prev => ({ ...prev, hardware: parseFloat(val) || 0 }));
                  }
                }}
              />
              <span className={`ml-2 text-sm ${darkMode ? 'text-gray-300' : 'text-gray-600'} font-medium`}>%</span>
            </div>
          </div>
        </div>
      </div>

      {/* Grand Total */}
      <div className={`${darkMode ? 'bg-green-900 border-green-700' : 'bg-green-50 border-green-200'} border rounded-lg p-8 shadow-lg`}>
        <div className="flex flex-col md:flex-row justify-between items-center space-y-2 md:space-y-0">
          <h3 className={`text-xl font-bold ${darkMode ? 'text-green-100' : 'text-gray-800'}`}>Total Estimate</h3>
          <div className={`text-4xl font-bold ${darkMode ? 'text-green-300' : 'text-green-600'} font-mono`}>
            ${calculateGrandTotal(quoteData, markups).toFixed(2)}
          </div>
        </div>
        {inputError && (
          <div className="mt-4 text-red-600 bg-red-100 border border-red-300 rounded p-2 text-sm">
            {inputError}
          </div>
        )}
        {userFeedback && (
          <div className="mt-4 text-green-700 bg-green-100 border border-green-300 rounded p-2 text-sm">
            {userFeedback}
          </div>
        )}
      </div>

      {/* Action Buttons */}
      <div className="grid grid-cols-1 md:grid-cols-5 gap-3">
        <button
          className="flex items-center justify-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium"
          onClick={() => {
            setUserFeedback('Quote saved successfully!');
            setTimeout(() => setUserFeedback(''), 2000);
          }}
        >
          <Save className="w-4 h-4 mr-2" />
          Save Quote
        </button>
        <button
          className="flex items-center justify-center px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-medium"
          onClick={() => {
            setUserFeedback('PDF generated!');
            setTimeout(() => setUserFeedback(''), 2000);
          }}
        >
          <FileText className="w-4 h-4 mr-2" />
          Generate PDF
        </button>
        <button
          className="flex items-center justify-center px-4 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors font-medium"
          onClick={() => {
            setUserFeedback('New quote started!');
            setTimeout(() => setUserFeedback(''), 2000);
          }}
        >
          <Plus className="w-4 h-4 mr-2" />
          New Quote
        </button>
        <button
          onClick={() => {
            setShowImportDialog(true);
            setUserFeedback('');
          }}
          className="flex items-center justify-center px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors font-medium"
        >
          <Upload className="w-4 h-4 mr-2" />
          Import Data
        </button>
        <button
          onClick={() => {
            setShowExportDialog(true);
            setUserFeedback('');
          }}
          className="flex items-center justify-center px-4 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors font-medium"
        >
          <Download className="w-4 h-4 mr-2" />
          Export Data
        </button>
      </div>
    </div>
  );

  // Admin interface for managing pricing data
  const renderAdmin = () => (
    <div className="space-y-6">
      <div className={`${darkMode ? 'bg-gradient-to-r from-gray-800 to-gray-900' : 'bg-gradient-to-r from-purple-600 to-purple-700'} text-white p-6 rounded-lg shadow-lg`}>
        <div className="flex justify-between items-center">
          <div>
            <h2 className="text-2xl font-bold mb-2">Admin Panel</h2>
            <p className={`${darkMode ? 'text-gray-300' : 'text-purple-100'}`}>Manage pricing data and system settings</p>
          </div>
          <button 
            onClick={() => setShowProductManager(true)}
            className="flex items-center px-4 py-2 bg-white text-purple-600 rounded-lg hover:bg-gray-100 transition-colors font-medium"
          >
            <Edit3 className="w-4 h-4 mr-2" />
            Manage Products
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {mapItems(Object.entries(pricingData), ([category, items]) => (
          <div key={category} className={`${darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'} rounded-lg shadow-sm border p-6`}>
            <h3 className={`text-lg font-semibold ${darkMode ? 'text-gray-100' : 'text-gray-800'} mb-4 capitalize`}>
              {category.replace(/([A-Z])/g, ' $1').trim()}
            </h3>
            <div className="space-y-2 max-h-64 overflow-y-auto">
              {Array.isArray(items) ? mapItems(items, (item, idx) => (
                <div key={idx} className={`flex justify-between items-center p-3 ${darkMode ? 'bg-gray-700' : 'bg-gray-50'} rounded-md`}>
                  <span className={`text-sm ${darkMode ? 'text-gray-200' : 'text-gray-700'} flex-1 mr-2`}>{item.item}</span>
                  <span className={`text-sm font-bold ${darkMode ? 'text-green-400' : 'text-green-600'} font-mono`}>${item.price}</span>
                </div>
              )) : (
                <div className={`text-sm ${darkMode ? 'text-gray-400' : 'text-gray-500'}`}>Complex category structure</div>
              )}
            </div>
            <button className={`mt-4 w-full py-2 text-sm ${darkMode ? 'bg-purple-800 text-purple-200 hover:bg-purple-700' : 'bg-purple-100 text-purple-700 hover:bg-purple-200'} rounded-md transition-colors font-medium`}>
              Edit {category.charAt(0).toUpperCase() + category.slice(1)}
            </button>
          </div>
        ))}
      </div>
    </div>
  );

  // Dialog components
  const renderImportDialog = () => showImportDialog && (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className={`${darkMode ? 'bg-gray-800' : 'bg-white'} rounded-lg p-6 w-full max-w-md mx-4`}>
        <h3 className={`text-lg font-semibold ${darkMode ? 'text-gray-100' : 'text-gray-800'} mb-4`}>Import Data</h3>
        <textarea
          className={`w-full h-40 px-3 py-2 border rounded-md text-sm ${darkMode ? 'bg-gray-700 border-gray-600 text-gray-100' : 'bg-white border-gray-300 text-gray-900'} focus:ring-2 focus:ring-blue-500 focus:border-blue-500`}
          placeholder="Paste JSON data here..."
          value={importData}
          onChange={(e) => setImportData(e.target.value)}
        />
        <div className="flex justify-end space-x-3 mt-4">
          <button
            onClick={() => setShowImportDialog(false)}
            className={`px-4 py-2 ${darkMode ? 'bg-gray-600 hover:bg-gray-700' : 'bg-gray-200 hover:bg-gray-300'} rounded-md transition-colors`}
          >
            Cancel
          </button>
          <button
            onClick={() => handleImport(importData, setPricingData, setMarkups, setShowImportDialog)}
            className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
          >
            Import
          </button>
        </div>
      </div>
    </div>
  );

  const renderExportDialog = () => showExportDialog && (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className={`${darkMode ? 'bg-gray-800' : 'bg-white'} rounded-lg p-6 w-full max-w-md mx-4`}>
        <h3 className={`text-lg font-semibold ${darkMode ? 'text-gray-100' : 'text-gray-800'} mb-4`}>Export Data</h3>
        <p className={`text-sm ${darkMode ? 'text-gray-300' : 'text-gray-600'} mb-4`}>
          This will download a JSON file containing all pricing data and markup settings.
        </p>
        <div className="flex justify-end space-x-3">
          <button
            onClick={() => setShowExportDialog(false)}
            className={`px-4 py-2 ${darkMode ? 'bg-gray-600 hover:bg-gray-700' : 'bg-gray-200 hover:bg-gray-300'} rounded-md transition-colors`}
          >
            Cancel
          </button>
          <button
            onClick={() => handleExport(pricingData, markups, setShowExportDialog)}
            className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors"
          >
            Download
          </button>
        </div>
      </div>
    </div>
  );

  const renderProductManager = () => showProductManager && (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className={`${darkMode ? 'bg-gray-800' : 'bg-white'} rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto`}>
        <div className="flex justify-between items-center mb-6">
          <h3 className={`text-xl font-semibold ${darkMode ? 'text-gray-100' : 'text-gray-800'}`}>Product Manager</h3>
          <button
            onClick={() => setShowProductManager(false)}
            className={`px-4 py-2 ${darkMode ? 'bg-gray-600 hover:bg-gray-700' : 'bg-gray-200 hover:bg-gray-300'} rounded-md transition-colors`}
          >
            Close
          </button>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {mapItems(Object.entries(pricingData).filter(([_, items]) => Array.isArray(items)), ([category, items]) => (
            <div key={category} className={`${darkMode ? 'bg-gray-700' : 'bg-gray-50'} rounded-lg p-4`}>
              <h4 className={`font-semibold ${darkMode ? 'text-gray-200' : 'text-gray-800'} mb-3 capitalize`}>
                {category.replace(/([A-Z])/g, ' $1').trim()}
              </h4>
              <div className="space-y-2 max-h-48 overflow-y-auto">
                {mapItems(items, (item, idx) => (
                  <div key={idx} className={`flex items-center justify-between p-2 ${darkMode ? 'bg-gray-600' : 'bg-white'} rounded text-xs`}>
                    <span className={`flex-1 mr-2 ${darkMode ? 'text-gray-200' : 'text-gray-700'}`}>{item.item.substring(0, 30)}...</span>
                    <span className={`font-mono font-bold ${darkMode ? 'text-green-400' : 'text-green-600'}`}>${item.price}</span>
                    <button
                      onClick={() => removeProduct(category, idx)}
                      className="ml-2 text-red-500 hover:text-red-700 text-xs"
                    >
                      ×
                    </button>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );

  return (
    <div className={`min-h-screen ${darkMode ? 'bg-gray-900' : 'bg-gray-100'} transition-colors`}>
      {/* Navigation */}
      <nav className={`${darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'} shadow-sm border-b sticky top-0 z-40`}>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between h-16">
            <div className="flex items-center">
              <Calculator className={`w-8 h-8 ${darkMode ? 'text-blue-400' : 'text-blue-600'} mr-3`} />
              <h1 className={`text-xl font-bold ${darkMode ? 'text-gray-100' : 'text-gray-900'}`}>Door Estimator</h1>
            </div>
            <div className="flex items-center space-x-4">
              <button
                onClick={() => setActiveTab('estimator')}
                className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                  activeTab === 'estimator' 
                    ? (darkMode ? 'bg-blue-900 text-blue-200' : 'bg-blue-100 text-blue-700')
                    : (darkMode ? 'text-gray-300 hover:text-gray-100 hover:bg-gray-700' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100')
                }`}
              >
                <Calculator className="w-4 h-4 inline mr-2" />
                Estimator
              </button>
              <button
                onClick={() => setActiveTab('admin')}
                className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                  activeTab === 'admin' 
                    ? (darkMode ? 'bg-purple-900 text-purple-200' : 'bg-purple-100 text-purple-700')
                    : (darkMode ? 'text-gray-300 hover:text-gray-100 hover:bg-gray-700' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100')
                }`}
              >
                <Settings className="w-4 h-4 inline mr-2" />
                Admin
              </button>
            </div>
          </div>
        </div>
      </nav>

      {/* Main Content */}
      <main className="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        {activeTab === 'estimator' && renderEstimator()}
        {activeTab === 'admin' && renderAdmin()}
      </main>
      
      {/* Dialogs */}
      {renderImportDialog()}
      {renderExportDialog()}
      {renderProductManager()}
    </div>
  );
};

export default DoorEstimatorApp;