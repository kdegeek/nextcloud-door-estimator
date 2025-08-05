import React from 'react';
import { lookupPrice } from './utils/priceUtils';

const QuoteSection = ({
  title,
  sectionKey,
  hasFrameType = false,
  quoteData,
  pricingData,
  darkMode,
  updateQuoteItem,
  calculateSectionTotal,
  inputError
}) => {
  const items = quoteData[sectionKey] || [];
  const availableItems = hasFrameType
    ? (pricingData[sectionKey]?.[items[0]?.frameType] || [])
    : (pricingData[sectionKey] || []);

  return (
    <div className={`${darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'} rounded-lg shadow-sm border p-6 mb-6 quote-section`}>
      <h3 className={`text-lg font-semibold ${darkMode ? 'text-gray-100' : 'text-gray-800'} mb-4`}>{title}</h3>
      {items.map((item, index) => (
        <div key={item.id} className="grid grid-cols-12 gap-3 mb-4 items-center p-3 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors border border-transparent hover:border-gray-200 dark:hover:border-gray-600">
          <div className={`text-sm font-bold ${darkMode ? 'text-gray-300' : 'text-gray-600'} text-center bg-gray-100 dark:bg-gray-600 rounded-md px-3 py-2`}>{item.id}</div>
          {hasFrameType && (
            <select
              className={`col-span-2 px-3 py-2 border rounded-md text-sm ${darkMode ? 'bg-gray-700 border-gray-600 text-gray-100' : 'bg-white border-gray-300 text-gray-900'} focus:ring-2 focus:ring-blue-500 focus:border-blue-500`}
              value={item.frameType || ''}
              onChange={(e) => updateQuoteItem(sectionKey, index, 'frameType', e.target.value)}
            >
              <option value="HM Drywall">HM Drywall</option>
              <option value="HM EWA">HM EWA</option>
              <option value="HM USA">HM USA</option>
            </select>
          )}
          <select
            className={`${hasFrameType ? 'col-span-4' : 'col-span-6'} px-3 py-2 border rounded-md text-sm ${darkMode ? 'bg-gray-700 border-gray-600 text-gray-100' : 'bg-white border-gray-300 text-gray-900'} focus:ring-2 focus:ring-blue-500 focus:border-blue-500`}
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
            min="0"
            step="1"
            className={`col-span-1 px-3 py-2 border rounded-md text-sm text-center ${darkMode ? 'bg-gray-700 border-gray-600 text-gray-100' : 'bg-white border-gray-300 text-gray-900'} focus:ring-2 focus:ring-blue-500 focus:border-blue-500`}
            placeholder="Qty"
            value={item.qty || ''}
            onChange={(e) => {
              const val = e.target.value;
              if (!/^\d*$/.test(val) || parseInt(val, 10) < 0) {
                inputError('Quantity must be a non-negative integer');
              } else {
                inputError('');
                updateQuoteItem(sectionKey, index, 'qty', val);
              }
            }}
          />
          <div className={`col-span-2 px-3 py-2 ${darkMode ? 'bg-gray-600 text-gray-100' : 'bg-gray-50 text-gray-900'} rounded-md text-sm text-right font-mono`}>
            ${(item.price || 0).toFixed(2)}
          </div>
          <div className={`col-span-2 px-3 py-2 ${darkMode ? 'bg-blue-900 text-blue-100' : 'bg-blue-50 text-blue-900'} rounded-md text-sm font-bold text-right font-mono`}>
            ${(item.total || 0).toFixed(2)}
          </div>
        </div>
      ))}
      <div className={`border-t ${darkMode ? 'border-gray-600' : 'border-gray-200'} pt-4 mt-6`}>
        <div className="flex justify-between items-center text-sm">
          <span className={`${darkMode ? 'text-gray-300' : 'text-gray-600'} font-medium`}>Subtotal:</span>
          <span className={`${darkMode ? 'text-gray-100' : 'text-gray-900'} font-bold text-lg font-mono`}>${calculateSectionTotal(sectionKey).toFixed(2)}</span>
        </div>
      </div>
    </div>
  );
};

export default QuoteSection;