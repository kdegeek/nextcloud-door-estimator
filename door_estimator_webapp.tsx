import React, { useState, useEffect } from 'react';
import { Calculator, Plus, Minus, Save, FileText, Settings, Database } from 'lucide-react';

const DoorEstimatorApp = () => {
  const [activeTab, setActiveTab] = useState('estimator');
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

  // Price lookup function - replicates SUMPRODUCT logic
  const lookupPrice = (category, item, frameType = null) => {
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

  // Update item and recalculate price
  const updateQuoteItem = (section, index, field, value) => {
    setQuoteData(prev => {
      const newData = { ...prev };
      const item = { ...newData[section][index] };
      item[field] = value;
      
      if (field === 'item' || field === 'frameType') {
        // Recalculate price based on item selection
        if (section === 'frames') {
          item.price = lookupPrice('frames', item.item, item.frameType);
        } else {
          item.price = lookupPrice(section, item.item);
        }
      }
      
      if (field === 'qty' || field === 'price') {
        item.total = (parseFloat(item.qty) || 0) * (parseFloat(item.price) || 0);
      }
      
      newData[section][index] = item;
      return newData;
    });
  };

  // Calculate section totals with markups
  const calculateSectionTotal = (section) => {
    const sectionData = quoteData[section] || [];
    const subtotal = sectionData.reduce((sum, item) => sum + (item.total || 0), 0);
    
    // Apply markup based on section
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

  // Calculate grand total
  const calculateGrandTotal = () => {
    const sections = Object.keys(quoteData);
    return sections.reduce((total, section) => total + calculateSectionTotal(section), 0);
  };

  // Render quote section
  const renderQuoteSection = (title, sectionKey, hasFrameType = false) => {
    const items = quoteData[sectionKey] || [];
    const availableItems = hasFrameType ? 
      (pricingData[sectionKey]?.[items[0]?.frameType] || []) :
      (pricingData[sectionKey] || []);

    return (
      <div className="bg-white rounded-lg shadow-sm border p-4 mb-4">
        <h3 className="text-lg font-semibold text-gray-800 mb-3">{title}</h3>
        
        {items.map((item, index) => (
          <div key={item.id} className="grid grid-cols-12 gap-2 mb-2 items-center">
            <div className="text-sm font-medium text-gray-600">{item.id}</div>
            
            {hasFrameType && (
              <select
                className="col-span-2 px-2 py-1 border rounded text-sm"
                value={item.frameType || ''}
                onChange={(e) => updateQuoteItem(sectionKey, index, 'frameType', e.target.value)}
              >
                <option value="HM Drywall">HM Drywall</option>
                <option value="HM EWA">HM EWA</option>
                <option value="HM USA">HM USA</option>
              </select>
            )}
            
            <select
              className={`${hasFrameType ? 'col-span-4' : 'col-span-6'} px-2 py-1 border rounded text-sm`}
              value={item.item}
              onChange={(e) => updateQuoteItem(sectionKey, index, 'item', e.target.value)}
            >
              <option value="">Select item...</option>
              {availableItems.map((option, idx) => (
                <option key={idx} value={option.item}>{option.item}</option>
              ))}
            </select>
            
            <input
              type="number"
              className="col-span-1 px-2 py-1 border rounded text-sm text-center"
              placeholder="Qty"
              value={item.qty || ''}
              onChange={(e) => updateQuoteItem(sectionKey, index, 'qty', e.target.value)}
            />
            
            <div className="col-span-2 px-2 py-1 bg-gray-50 rounded text-sm text-right">
              ${(item.price || 0).toFixed(2)}
            </div>
            
            <div className="col-span-2 px-2 py-1 bg-blue-50 rounded text-sm font-medium text-right">
              ${(item.total || 0).toFixed(2)}
            </div>
          </div>
        ))}
        
        <div className="border-t pt-2 mt-3">
          <div className="flex justify-between items-center text-sm">
            <span className="text-gray-600">Subtotal:</span>
            <span>${calculateSectionTotal(sectionKey).toFixed(2)}</span>
          </div>
        </div>
      </div>
    );
  };

  // Main estimator interface
  const renderEstimator = () => (
    <div className="space-y-6">
      <div className="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-6 rounded-lg">
        <h2 className="text-2xl font-bold mb-2">Door & Hardware Estimator</h2>
        <p className="text-blue-100">Create professional door and hardware estimates</p>
      </div>

      {renderQuoteSection('Doors', 'doors')}
      {renderQuoteSection('Door Options', 'doorOptions')}
      {renderQuoteSection('Inserts', 'inserts')}
      {renderQuoteSection('Frames', 'frames', true)}
      {renderQuoteSection('Frame Options', 'frameOptions')}
      {renderQuoteSection('Hinges', 'hinges')}
      {renderQuoteSection('Weatherstrip', 'weatherstrip')}
      {renderQuoteSection('Closers', 'closers')}
      {renderQuoteSection('Locksets', 'locksets')}
      {renderQuoteSection('Exit Devices', 'exitDevices')}
      {renderQuoteSection('Hardware', 'hardware')}

      {/* Markup Configuration */}
      <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <h3 className="text-lg font-semibold text-gray-800 mb-3">Markup Settings</h3>
        <div className="grid grid-cols-3 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Doors & Inserts</label>
            <div className="flex items-center">
              <input
                type="number"
                className="w-16 px-2 py-1 border rounded text-sm text-center"
                value={markups.doors}
                onChange={(e) => setMarkups(prev => ({ ...prev, doors: parseFloat(e.target.value) || 0 }))}
              />
              <span className="ml-1 text-sm text-gray-600">%</span>
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Frames</label>
            <div className="flex items-center">
              <input
                type="number"
                className="w-16 px-2 py-1 border rounded text-sm text-center"
                value={markups.frames}
                onChange={(e) => setMarkups(prev => ({ ...prev, frames: parseFloat(e.target.value) || 0 }))}
              />
              <span className="ml-1 text-sm text-gray-600">%</span>
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Hardware</label>
            <div className="flex items-center">
              <input
                type="number"
                className="w-16 px-2 py-1 border rounded text-sm text-center"
                value={markups.hardware}
                onChange={(e) => setMarkups(prev => ({ ...prev, hardware: parseFloat(e.target.value) || 0 }))}
              />
              <span className="ml-1 text-sm text-gray-600">%</span>
            </div>
          </div>
        </div>
      </div>

      {/* Grand Total */}
      <div className="bg-green-50 border border-green-200 rounded-lg p-6">
        <div className="flex justify-between items-center">
          <h3 className="text-xl font-bold text-gray-800">Total Estimate</h3>
          <div className="text-3xl font-bold text-green-600">
            ${calculateGrandTotal().toFixed(2)}
          </div>
        </div>
      </div>

      {/* Action Buttons */}
      <div className="flex space-x-4">
        <button className="flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
          <Save className="w-4 h-4 mr-2" />
          Save Quote
        </button>
        <button className="flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
          <FileText className="w-4 h-4 mr-2" />
          Generate PDF
        </button>
        <button className="flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
          <Plus className="w-4 h-4 mr-2" />
          New Quote
        </button>
      </div>
    </div>
  );

  // Admin interface for managing pricing data
  const renderAdmin = () => (
    <div className="space-y-6">
      <div className="bg-gradient-to-r from-purple-600 to-purple-700 text-white p-6 rounded-lg">
        <h2 className="text-2xl font-bold mb-2">Admin Panel</h2>
        <p className="text-purple-100">Manage pricing data and system settings</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {Object.entries(pricingData).map(([category, items]) => (
          <div key={category} className="bg-white rounded-lg shadow-sm border p-4">
            <h3 className="text-lg font-semibold text-gray-800 mb-3 capitalize">
              {category.replace(/([A-Z])/g, ' $1').trim()}
            </h3>
            <div className="space-y-2 max-h-64 overflow-y-auto">
              {Array.isArray(items) ? items.map((item, idx) => (
                <div key={idx} className="flex justify-between items-center p-2 bg-gray-50 rounded">
                  <span className="text-sm text-gray-700 flex-1 mr-2">{item.item}</span>
                  <span className="text-sm font-medium text-green-600">${item.price}</span>
                </div>
              )) : (
                <div className="text-sm text-gray-500">Complex category structure</div>
              )}
            </div>
            <button className="mt-3 w-full py-2 text-sm bg-purple-100 text-purple-700 rounded hover:bg-purple-200">
              Edit {category.charAt(0).toUpperCase() + category.slice(1)}
            </button>
          </div>
        ))}
      </div>
    </div>
  );

  return (
    <div className="min-h-screen bg-gray-100">
      {/* Navigation */}
      <nav className="bg-white shadow-sm border-b">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between h-16">
            <div className="flex items-center">
              <Calculator className="w-8 h-8 text-blue-600 mr-3" />
              <h1 className="text-xl font-bold text-gray-900">Door Estimator</h1>
            </div>
            <div className="flex space-x-4">
              <button
                onClick={() => setActiveTab('estimator')}
                className={`px-4 py-2 rounded-md text-sm font-medium ${
                  activeTab === 'estimator' 
                    ? 'bg-blue-100 text-blue-700' 
                    : 'text-gray-500 hover:text-gray-700'
                }`}
              >
                <Calculator className="w-4 h-4 inline mr-2" />
                Estimator
              </button>
              <button
                onClick={() => setActiveTab('admin')}
                className={`px-4 py-2 rounded-md text-sm font-medium ${
                  activeTab === 'admin' 
                    ? 'bg-purple-100 text-purple-700' 
                    : 'text-gray-500 hover:text-gray-700'
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
    </div>
  );
};

export default DoorEstimatorApp;