/**
 * Door Estimator NextCloud App
 * Enhanced version with dark mode, import/export, and improved UI
 */

(function() {
    'use strict';
    
    let app = {
        data: {
            activeTab: 'estimator',
            darkMode: true, // Always dark mode
            showImportDialog: false,
            showExportDialog: false,
            showProductManager: false,
            importData: '',
            pricingData: {
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
            },
            quoteData: {
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
            },
            markups: {
                doors: 15,
                frames: 12,
                hardware: 18
            }
        },

        // Initialize the application
        init() {
            this.loadPricingData();
            this.bindEvents();
            this.enableDarkMode(); // Always enable dark mode
            this.render();
        },

        // Enable dark mode permanently
        enableDarkMode() {
            this.data.darkMode = true;
            document.documentElement.classList.add('dark');
            document.body.style.backgroundColor = '#111827';
            document.body.style.color = '#f3f4f6';
        },

        // Bind event listeners
        bindEvents() {
            // Tab switching
            document.addEventListener('click', (e) => {
                if (e.target.matches('[data-tab]')) {
                    this.data.activeTab = e.target.dataset.tab;
                    this.render();
                }
            });

            // Remove dark mode toggle - always dark mode

            // Import/Export buttons
            document.addEventListener('click', (e) => {
                if (e.target.matches('[data-action="show-import"]') || 
                    e.target.closest('[data-action="show-import"]')) {
                    this.data.showImportDialog = true;
                    this.renderDialogs();
                }
                if (e.target.matches('[data-action="show-export"]') || 
                    e.target.closest('[data-action="show-export"]')) {
                    this.data.showExportDialog = true;
                    this.renderDialogs();
                }
                if (e.target.matches('[data-action="show-product-manager"]') || 
                    e.target.closest('[data-action="show-product-manager"]')) {
                    this.data.showProductManager = true;
                    this.renderDialogs();
                }
            });

            // Dialog actions
            document.addEventListener('click', (e) => {
                if (e.target.matches('[data-action="close-dialog"]')) {
                    this.closeAllDialogs();
                }
                if (e.target.matches('[data-action="import-data"]')) {
                    this.handleImport();
                }
                if (e.target.matches('[data-action="export-data"]')) {
                    this.handleExport();
                }
            });

            // Input changes
            document.addEventListener('input', (e) => {
                if (e.target.matches('[data-field]')) {
                    this.updateQuoteItem(e.target);
                }
                if (e.target.matches('[data-markup]')) {
                    this.updateMarkup(e.target);
                }
                if (e.target.matches('[data-import-data]')) {
                    this.data.importData = e.target.value;
                }
            });

            document.addEventListener('change', (e) => {
                if (e.target.matches('[data-field]')) {
                    this.updateQuoteItem(e.target);
                }
            });
        },

        // Close all dialogs
        closeAllDialogs() {
            this.data.showImportDialog = false;
            this.data.showExportDialog = false;
            this.data.showProductManager = false;
            this.data.importData = '';
            // Re-enable body scrolling
            document.body.style.overflow = 'auto';
            this.renderDialogs();
        },

        // Handle data import
        handleImport() {
            try {
                const data = JSON.parse(this.data.importData);
                if (data.pricingData) {
                    this.data.pricingData = data.pricingData;
                }
                if (data.markups) {
                    this.data.markups = data.markups;
                }
                this.closeAllDialogs();
                this.render();
                alert('Data imported successfully!');
            } catch (error) {
                alert('Invalid JSON format. Please check your data.');
            }
        },

        // Handle data export
        handleExport() {
            const exportData = {
                pricingData: this.data.pricingData,
                markups: this.data.markups,
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
            this.closeAllDialogs();
        },

        // Update quote item
        updateQuoteItem(input) {
            const section = input.dataset.section;
            const index = parseInt(input.dataset.index);
            const field = input.dataset.field;
            const value = input.value;

            if (!this.data.quoteData[section] || !this.data.quoteData[section][index]) return;

            const item = this.data.quoteData[section][index];
            item[field] = value;

            if (field === 'item' || field === 'frameType') {
                // Recalculate price based on item selection
                if (section === 'frames') {
                    item.price = this.lookupPrice('frames', item.item, item.frameType);
                } else {
                    item.price = this.lookupPrice(section, item.item);
                }
            }

            if (field === 'qty' || field === 'price') {
                item.total = (parseFloat(item.qty) || 0) * (parseFloat(item.price) || 0);
            }

            this.updateSectionTotal(section);
            this.updateGrandTotal();
        },

        // Update markup
        updateMarkup(input) {
            const markupType = input.dataset.markup;
            this.data.markups[markupType] = parseFloat(input.value) || 0;
            this.updateAllSectionTotals();
            this.updateGrandTotal();
        },

        // Price lookup function
        lookupPrice(category, item, frameType = null) {
            if (!item) return 0;
            
            let data = this.data.pricingData[category];
            if (frameType && data && data[frameType]) {
                data = data[frameType];
            }
            
            if (Array.isArray(data)) {
                const found = data.find(d => d.item === item);
                return found ? found.price : 0;
            }
            return 0;
        },

        // Calculate section total with markup
        calculateSectionTotal(section) {
            const sectionData = this.data.quoteData[section] || [];
            const subtotal = sectionData.reduce((sum, item) => sum + (item.total || 0), 0);
            
            let markup = 0;
            if (['doors', 'doorOptions', 'inserts'].includes(section)) {
                markup = this.data.markups.doors;
            } else if (['frames', 'frameOptions'].includes(section)) {
                markup = this.data.markups.frames;
            } else {
                markup = this.data.markups.hardware;
            }
            
            return subtotal * (1 + markup / 100);
        },

        // Update section total display
        updateSectionTotal(section) {
            const total = this.calculateSectionTotal(section);
            const elements = document.querySelectorAll(`[data-section-total="${section}"]`);
            elements.forEach(el => {
                el.textContent = '$' + total.toFixed(2);
            });
        },

        // Update all section totals
        updateAllSectionTotals() {
            Object.keys(this.data.quoteData).forEach(section => {
                this.updateSectionTotal(section);
            });
        },

        // Calculate grand total
        calculateGrandTotal() {
            const sections = Object.keys(this.data.quoteData);
            return sections.reduce((total, section) => total + this.calculateSectionTotal(section), 0);
        },

        // Update grand total display
        updateGrandTotal() {
            const total = this.calculateGrandTotal();
            const elements = document.querySelectorAll('[data-grand-total]');
            elements.forEach(el => {
                el.textContent = '$' + total.toFixed(2);
            });
        },

        // Load pricing data from server
        loadPricingData() {
            // In a real implementation, this would fetch from the PHP backend
            // For now, we use the static data defined above
        },

        // Generate HTML for quote section
        generateQuoteSectionHTML(title, sectionKey, hasFrameType = false) {
            const items = this.data.quoteData[sectionKey] || [];
            const availableItems = hasFrameType ? 
                (this.data.pricingData[sectionKey]?.[items[0]?.frameType] || []) :
                (this.data.pricingData[sectionKey] || []);

            const darkClass = this.data.darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200';
            const textClass = this.data.darkMode ? 'text-gray-100' : 'text-gray-800';

            let html = `
                <div class="${darkClass} rounded-lg shadow-sm border p-6 mb-6 quote-section">
                    <h3 class="text-lg font-semibold ${textClass} mb-4">${title}</h3>
            `;

            items.forEach((item, index) => {
                const itemBgClass = this.data.darkMode ? 'bg-gray-700 border-gray-600 text-gray-100' : 'bg-white border-gray-300 text-gray-900';
                const labelBgClass = this.data.darkMode ? 'bg-gray-600 text-gray-300' : 'bg-gray-100 text-gray-600';
                const priceBgClass = this.data.darkMode ? 'bg-gray-600 text-gray-100' : 'bg-gray-50 text-gray-900';
                const totalBgClass = this.data.darkMode ? 'bg-blue-900 text-blue-100' : 'bg-blue-50 text-blue-900';

                html += `
                    <div class="grid grid-cols-12 gap-3 mb-4 items-center p-3 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors border border-transparent hover:border-gray-200 dark:hover:border-gray-600">
                        <div class="text-sm font-bold ${labelBgClass} text-center rounded-md px-3 py-2">${item.id}</div>
                `;

                if (hasFrameType) {
                    html += `
                        <select class="col-span-2 px-3 py-2 border rounded-md text-sm ${itemBgClass} focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                data-section="${sectionKey}" data-index="${index}" data-field="frameType">
                            <option value="HM Drywall">HM Drywall</option>
                            <option value="HM EWA">HM EWA</option>
                            <option value="HM USA">HM USA</option>
                        </select>
                    `;
                }

                const colSpan = hasFrameType ? 'col-span-4' : 'col-span-6';
                html += `
                    <select class="${colSpan} px-3 py-2 border rounded-md text-sm ${itemBgClass} focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            data-section="${sectionKey}" data-index="${index}" data-field="item">
                        <option value="">Select item...</option>
                `;

                availableItems.forEach(option => {
                    const selected = option.item === item.item ? 'selected' : '';
                    html += `<option value="${option.item}" ${selected}>${option.item}</option>`;
                });

                html += `
                    </select>
                    <input type="number" class="col-span-1 px-3 py-2 border rounded-md text-sm text-center ${itemBgClass} focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Qty" value="${item.qty || ''}"
                           data-section="${sectionKey}" data-index="${index}" data-field="qty">
                    <div class="col-span-2 px-3 py-2 ${priceBgClass} rounded-md text-sm text-right font-mono">
                        $${(item.price || 0).toFixed(2)}
                    </div>
                    <div class="col-span-2 px-3 py-2 ${totalBgClass} rounded-md text-sm font-bold text-right font-mono">
                        $${(item.total || 0).toFixed(2)}
                    </div>
                </div>
                `;
            });

            const borderClass = this.data.darkMode ? 'border-gray-600' : 'border-gray-200';
            const subtotalClass = this.data.darkMode ? 'text-gray-300' : 'text-gray-600';
            const totalClass = this.data.darkMode ? 'text-gray-100' : 'text-gray-900';

            html += `
                    <div class="border-t ${borderClass} pt-4 mt-6">
                        <div class="flex justify-between items-center text-sm">
                            <span class="${subtotalClass} font-medium">Subtotal:</span>
                            <span class="${totalClass} font-bold text-lg font-mono" data-section-total="${sectionKey}">
                                $${this.calculateSectionTotal(sectionKey).toFixed(2)}
                            </span>
                        </div>
                    </div>
                </div>
            `;

            return html;
        },

        // Generate main content HTML
        generateMainHTML() {
            const headerClass = this.data.darkMode ? 'bg-gradient-to-r from-gray-800 to-gray-900' : 'bg-gradient-to-r from-blue-600 to-blue-700';
            const headerTextClass = this.data.darkMode ? 'text-gray-300' : 'text-blue-100';
            const markupBgClass = this.data.darkMode ? 'bg-yellow-900 border-yellow-700' : 'bg-yellow-50 border-yellow-200';
            const markupTextClass = this.data.darkMode ? 'text-yellow-100' : 'text-gray-800';
            const totalBgClass = this.data.darkMode ? 'bg-green-900 border-green-700' : 'bg-green-50 border-green-200';
            const totalTextClass = this.data.darkMode ? 'text-green-100' : 'text-gray-800';
            const totalAmountClass = this.data.darkMode ? 'text-green-300' : 'text-green-600';

            if (this.data.activeTab === 'estimator') {
                return `
                    <div class="space-y-6">
                        <div class="${headerClass} text-white p-6 rounded-lg shadow-lg">
                            <h2 class="text-2xl font-bold mb-2">Door & Hardware Estimator</h2>
                            <p class="${headerTextClass}">Create professional door and hardware estimates</p>
                        </div>

                        ${this.generateQuoteSectionHTML('Doors', 'doors')}
                        ${this.generateQuoteSectionHTML('Door Options', 'doorOptions')}
                        ${this.generateQuoteSectionHTML('Inserts', 'inserts')}
                        ${this.generateQuoteSectionHTML('Frames', 'frames', true)}
                        ${this.generateQuoteSectionHTML('Frame Options', 'frameOptions')}
                        ${this.generateQuoteSectionHTML('Hinges', 'hinges')}
                        ${this.generateQuoteSectionHTML('Weatherstrip', 'weatherstrip')}
                        ${this.generateQuoteSectionHTML('Closers', 'closers')}
                        ${this.generateQuoteSectionHTML('Locksets', 'locksets')}
                        ${this.generateQuoteSectionHTML('Exit Devices', 'exitDevices')}
                        ${this.generateQuoteSectionHTML('Hardware', 'hardware')}

                        <!-- Markup Configuration -->
                        <div class="${markupBgClass} border rounded-lg p-6 shadow-sm">
                            <h3 class="text-lg font-semibold ${markupTextClass} mb-4">Markup Settings</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-medium mb-2">Doors & Inserts</label>
                                    <div class="flex items-center">
                                        <input type="number" class="w-20 px-3 py-2 border rounded-md text-sm text-center focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                               value="${this.data.markups.doors}" data-markup="doors">
                                        <span class="ml-2 text-sm font-medium">%</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-2">Frames</label>
                                    <div class="flex items-center">
                                        <input type="number" class="w-20 px-3 py-2 border rounded-md text-sm text-center focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                               value="${this.data.markups.frames}" data-markup="frames">
                                        <span class="ml-2 text-sm font-medium">%</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-2">Hardware</label>
                                    <div class="flex items-center">
                                        <input type="number" class="w-20 px-3 py-2 border rounded-md text-sm text-center focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                               value="${this.data.markups.hardware}" data-markup="hardware">
                                        <span class="ml-2 text-sm font-medium">%</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Grand Total -->
                        <div class="${totalBgClass} border rounded-lg p-8 shadow-lg">
                            <div class="flex flex-col md:flex-row justify-between items-center space-y-2 md:space-y-0">
                                <h3 class="text-xl font-bold ${totalTextClass}">Total Estimate</h3>
                                <div class="text-4xl font-bold ${totalAmountClass} font-mono" data-grand-total>
                                    $${this.calculateGrandTotal().toFixed(2)}
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                            <button class="flex items-center justify-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                Save Quote
                            </button>
                            <button class="flex items-center justify-center px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-medium">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Generate PDF
                            </button>
                            <button class="flex items-center justify-center px-4 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors font-medium">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                New Quote
                            </button>
                            <button data-action="show-import" class="flex items-center justify-center px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors font-medium">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                                </svg>
                                Import Data
                            </button>
                            <button data-action="show-export" class="flex items-center justify-center px-4 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors font-medium">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                Export Data
                            </button>
                        </div>
                    </div>
                `;
            } else {
                // Admin panel
                const adminHeaderClass = this.data.darkMode ? 'bg-gradient-to-r from-gray-800 to-gray-900' : 'bg-gradient-to-r from-purple-600 to-purple-700';
                const adminTextClass = this.data.darkMode ? 'text-gray-300' : 'text-purple-100';
                
                return `
                    <div class="space-y-6">
                        <div class="${adminHeaderClass} text-white p-6 rounded-lg shadow-lg">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h2 class="text-2xl font-bold mb-2">Admin Panel</h2>
                                    <p class="${adminTextClass}">Manage pricing data and system settings</p>
                                </div>
                                <button data-action="show-product-manager" class="flex items-center px-4 py-2 bg-white text-purple-600 rounded-lg hover:bg-gray-100 transition-colors font-medium">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    Manage Products
                                </button>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            ${this.generateProductCategoriesHTML()}
                        </div>
                    </div>
                `;
            }
        },

        // Generate product categories HTML for admin
        generateProductCategoriesHTML() {
            let html = '';
            Object.entries(this.data.pricingData).forEach(([category, items]) => {
                const cardClass = this.data.darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200';
                const titleClass = this.data.darkMode ? 'text-gray-100' : 'text-gray-800';
                const itemBgClass = this.data.darkMode ? 'bg-gray-700' : 'bg-gray-50';
                const itemTextClass = this.data.darkMode ? 'text-gray-200' : 'text-gray-700';
                const priceClass = this.data.darkMode ? 'text-green-400' : 'text-green-600';
                const buttonClass = this.data.darkMode ? 'bg-purple-800 text-purple-200 hover:bg-purple-700' : 'bg-purple-100 text-purple-700 hover:bg-purple-200';

                html += `
                    <div class="${cardClass} rounded-lg shadow-sm border p-6">
                        <h3 class="text-lg font-semibold ${titleClass} mb-4 capitalize">
                            ${category.replace(/([A-Z])/g, ' $1').trim()}
                        </h3>
                        <div class="space-y-2 max-h-64 overflow-y-auto">
                `;

                if (Array.isArray(items)) {
                    items.forEach(item => {
                        html += `
                            <div class="flex justify-between items-center p-3 ${itemBgClass} rounded-md">
                                <span class="text-sm ${itemTextClass} flex-1 mr-2">${item.item}</span>
                                <span class="text-sm font-bold ${priceClass} font-mono">$${item.price}</span>
                            </div>
                        `;
                    });
                } else {
                    const grayTextClass = this.data.darkMode ? 'text-gray-400' : 'text-gray-500';
                    html += `<div class="text-sm ${grayTextClass}">Complex category structure</div>`;
                }

                html += `
                        </div>
                        <button class="mt-4 w-full py-2 text-sm ${buttonClass} rounded-md transition-colors font-medium">
                            Edit ${category.charAt(0).toUpperCase() + category.slice(1)}
                        </button>
                    </div>
                `;
            });
            return html;
        },

        // Render dialogs
        renderDialogs() {
            const dialogContainer = document.getElementById('dialog-container') || document.createElement('div');
            dialogContainer.id = 'dialog-container';
            if (!document.getElementById('dialog-container')) {
                document.body.appendChild(dialogContainer);
            }

            // Prevent body scrolling when any dialog is open
            const hasOpenDialog = this.data.showImportDialog || this.data.showExportDialog || this.data.showProductManager;
            if (hasOpenDialog) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = 'auto';
            }

            let html = '';

            if (this.data.showImportDialog) {
                const bgClass = this.data.darkMode ? 'bg-gray-800' : 'bg-white';
                const textClass = this.data.darkMode ? 'text-gray-100' : 'text-gray-800';
                const inputClass = this.data.darkMode ? 'bg-gray-700 border-gray-600 text-gray-100' : 'bg-white border-gray-300 text-gray-900';
                const cancelClass = this.data.darkMode ? 'bg-gray-600 hover:bg-gray-700' : 'bg-gray-200 hover:bg-gray-300';

                html += `
                    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                        <div class="${bgClass} rounded-lg p-6 w-full max-w-md mx-4">
                            <h3 class="text-lg font-semibold ${textClass} mb-4">Import Data</h3>
                            <textarea class="w-full h-40 px-3 py-2 border rounded-md text-sm ${inputClass} focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                      placeholder="Paste JSON data here..." data-import-data></textarea>
                            <div class="flex justify-end space-x-3 mt-4">
                                <button data-action="close-dialog" class="px-4 py-2 ${cancelClass} rounded-md transition-colors">
                                    Cancel
                                </button>
                                <button data-action="import-data" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                    Import
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }

            if (this.data.showExportDialog) {
                const bgClass = this.data.darkMode ? 'bg-gray-800' : 'bg-white';
                const textClass = this.data.darkMode ? 'text-gray-100' : 'text-gray-800';
                const descClass = this.data.darkMode ? 'text-gray-300' : 'text-gray-600';
                const cancelClass = this.data.darkMode ? 'bg-gray-600 hover:bg-gray-700' : 'bg-gray-200 hover:bg-gray-300';

                html += `
                    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                        <div class="${bgClass} rounded-lg p-6 w-full max-w-md mx-4">
                            <h3 class="text-lg font-semibold ${textClass} mb-4">Export Data</h3>
                            <p class="text-sm ${descClass} mb-4">
                                This will download a JSON file containing all pricing data and markup settings.
                            </p>
                            <div class="flex justify-end space-x-3">
                                <button data-action="close-dialog" class="px-4 py-2 ${cancelClass} rounded-md transition-colors">
                                    Cancel
                                </button>
                                <button data-action="export-data" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                                    Download
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }

            if (this.data.showProductManager) {
                const bgClass = this.data.darkMode ? 'bg-gray-800' : 'bg-white';
                const textClass = this.data.darkMode ? 'text-gray-100' : 'text-gray-800';
                const cancelClass = this.data.darkMode ? 'bg-gray-600 hover:bg-gray-700' : 'bg-gray-200 hover:bg-gray-300';

                html += `
                    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                        <div class="${bgClass} rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-xl font-semibold ${textClass}">Product Manager</h3>
                                <button data-action="close-dialog" class="px-4 py-2 ${cancelClass} rounded-md transition-colors">
                                    Close
                                </button>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                ${this.generateProductManagerHTML()}
                            </div>
                        </div>
                    </div>
                `;
            }

            dialogContainer.innerHTML = html;
        },

        // Generate product manager HTML
        generateProductManagerHTML() {
            let html = '';
            Object.entries(this.data.pricingData).filter(([_, items]) => Array.isArray(items)).forEach(([category, items]) => {
                const bgClass = this.data.darkMode ? 'bg-gray-700' : 'bg-gray-50';
                const titleClass = this.data.darkMode ? 'text-gray-200' : 'text-gray-800';
                const itemBgClass = this.data.darkMode ? 'bg-gray-600' : 'bg-white';
                const itemTextClass = this.data.darkMode ? 'text-gray-200' : 'text-gray-700';
                const priceClass = this.data.darkMode ? 'text-green-400' : 'text-green-600';

                html += `
                    <div class="${bgClass} rounded-lg p-4">
                        <h4 class="font-semibold ${titleClass} mb-3 capitalize">
                            ${category.replace(/([A-Z])/g, ' $1').trim()}
                        </h4>
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                `;

                items.forEach((item, idx) => {
                    html += `
                        <div class="flex items-center justify-between p-2 ${itemBgClass} rounded text-xs">
                            <span class="flex-1 mr-2 ${itemTextClass}">${item.item.substring(0, 30)}...</span>
                            <span class="font-mono font-bold ${priceClass}">$${item.price}</span>
                            <button class="ml-2 text-red-500 hover:text-red-700 text-xs" onclick="app.removeProduct('${category}', ${idx})">
                                ×
                            </button>
                        </div>
                    `;
                });

                html += `
                        </div>
                    </div>
                `;
            });
            return html;
        },

        // Remove product
        removeProduct(category, index) {
            if (this.data.pricingData[category] && Array.isArray(this.data.pricingData[category])) {
                this.data.pricingData[category].splice(index, 1);
                this.renderDialogs();
            }
        },

        // Main render function
        render() {
            const container = document.getElementById('door-estimator-app');
            if (!container) return;

            const bgClass = 'bg-gray-900'; // Always dark mode
            // Always use dark mode classes
            const navBgClass = 'bg-gray-800 border-gray-700';
            const logoClass = 'text-blue-400';
            const titleClass = 'text-gray-100';

            const estimatorActiveClass = this.data.activeTab === 'estimator' ? 
                'bg-blue-900 text-blue-200' : 'text-gray-300 hover:text-gray-100 hover:bg-gray-700';

            const adminActiveClass = this.data.activeTab === 'admin' ? 
                'bg-purple-900 text-purple-200' : 'text-gray-300 hover:text-gray-100 hover:bg-gray-700';

            container.className = `min-h-screen ${bgClass} transition-colors`;
            container.innerHTML = `
                <!-- Navigation -->
                <nav class="${navBgClass} shadow-sm border-b sticky top-0 z-40">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex justify-between h-16">
                            <div class="flex items-center">
                                <!-- Temporary Door App Icon -->
                                <div class="w-8 h-8 ${logoClass} mr-3 flex items-center justify-center bg-blue-600 rounded-md">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm2 1v10h10V5H5zm6 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <h1 class="text-xl font-bold ${titleClass}">Door Estimator</h1>
                            </div>
                            <div class="flex items-center space-x-4">
                                <button data-tab="estimator" class="px-4 py-2 rounded-md text-sm font-medium transition-colors ${estimatorActiveClass}">
                                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6l1 9H8l1-9zm-4 9v2a2 2 0 002 2h10a2 2 0 002-2v-2M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2"></path>
                                    </svg>
                                    Estimator
                                </button>
                                <button data-tab="admin" class="px-4 py-2 rounded-md text-sm font-medium transition-colors ${adminActiveClass}">
                                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    Admin
                                </button>
                            </div>
                        </div>
                    </div>
                </nav>

                <!-- Main Content -->
                <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    ${this.generateMainHTML()}
                </main>
            `;

            this.renderDialogs();
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => app.init());
    } else {
        app.init();
    }

    // Expose app globally for debugging
    window.app = app;
})();