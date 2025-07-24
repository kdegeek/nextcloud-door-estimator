/**
 * Door Estimator NextCloud App
 * Main application JavaScript
 */

(function() {
    'use strict';
    
    let app = {
        data: {
            activeTab: 'estimator',
            pricingData: {},
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
            },
            savedQuotes: [],
            currentQuoteId: null,
            isLoading: false
        },
        
        init: function() {
            this.setupEventHandlers();
            this.loadPricingData();
            this.loadMarkupDefaults();
            this.loadSavedQuotes();
            this.renderApp();
        },
        
        setupEventHandlers: function() {
            const self = this;
            
            // Tab switching
            document.addEventListener('click', function(e) {
                if (e.target.matches('[data-tab]')) {
                    e.preventDefault();
                    self.switchTab(e.target.dataset.tab);
                }
            });
            
            // Quote item updates
            document.addEventListener('change', function(e) {
                if (e.target.matches('[data-quote-field]')) {
                    const section = e.target.dataset.section;
                    const index = parseInt(e.target.dataset.index);
                    const field = e.target.dataset.quoteField;
                    const value = e.target.value;
                    
                    self.updateQuoteItem(section, index, field, value);
                }
            });
            
            // Save quote
            document.addEventListener('click', function(e) {
                if (e.target.matches('[data-action="save-quote"]')) {
                    e.preventDefault();
                    self.saveQuote();
                }
            });
            
            // Generate PDF
            document.addEventListener('click', function(e) {
                if (e.target.matches('[data-action="generate-pdf"]')) {
                    e.preventDefault();
                    self.generatePDF();
                }
            });
            
            // New quote
            document.addEventListener('click', function(e) {
                if (e.target.matches('[data-action="new-quote"]')) {
                    e.preventDefault();
                    self.newQuote();
                }
            });
        },
        
        loadPricingData: function() {
            const self = this;
            this.isLoading = true;
            
            fetch(OC.generateUrl('/apps/door_estimator/api/pricing'))
                .then(response => response.json())
                .then(data => {
                    self.data.pricingData = data;
                    self.isLoading = false;
                    self.renderApp();
                })
                .catch(error => {
                    console.error('Error loading pricing data:', error);
                    OC.Notification.showTemporary('Error loading pricing data');
                    self.isLoading = false;
                });
        },
        
        loadMarkupDefaults: function() {
            const self = this;
            
            fetch(OC.generateUrl('/apps/door_estimator/api/markup-defaults'))
                .then(response => response.json())
                .then(data => {
                    self.data.markups = Object.assign(self.data.markups, data);
                    self.renderApp();
                })
                .catch(error => {
                    console.error('Error loading markup defaults:', error);
                });
        },
        
        loadSavedQuotes: function() {
            const self = this;
            
            fetch(OC.generateUrl('/apps/door_estimator/api/quotes'))
                .then(response => response.json())
                .then(data => {
                    self.data.savedQuotes = data;
                    self.renderApp();
                })
                .catch(error => {
                    console.error('Error loading saved quotes:', error);
                });
        },
        
        lookupPrice: function(category, item, frameType = null) {
            let data = this.data.pricingData[category];
            if (!data) return 0;
            
            if (frameType && data[frameType]) {
                data = data[frameType];
            }
            
            if (Array.isArray(data)) {
                const found = data.find(d => d.item === item);
                return found ? found.price : 0;
            }
            return 0;
        },
        
        updateQuoteItem: function(section, index, field, value) {
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
            
            this.renderApp();
        },
        
        calculateSectionTotal: function(section) {
            const sectionData = this.data.quoteData[section] || [];
            const subtotal = sectionData.reduce((sum, item) => sum + (item.total || 0), 0);
            
            // Apply markup based on section
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
        
        calculateGrandTotal: function() {
            const sections = Object.keys(this.data.quoteData);
            return sections.reduce((total, section) => total + this.calculateSectionTotal(section), 0);
        },
        
        saveQuote: function() {
            const self = this;
            const quoteName = prompt('Enter a name for this quote:', 'Quote ' + new Date().toLocaleDateString());
            
            if (!quoteName) return;
            
            const requestData = {
                quoteData: this.data.quoteData,
                markups: this.data.markups,
                quoteName: quoteName
            };
            
            fetch(OC.generateUrl('/apps/door_estimator/api/quotes'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'requesttoken': OC.requestToken
                },
                body: JSON.stringify(requestData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    self.data.currentQuoteId = data.quoteId;
                    OC.Notification.showTemporary('Quote saved successfully!');
                    self.loadSavedQuotes();
                } else {
                    OC.Notification.showTemporary('Error saving quote: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error saving quote:', error);
                OC.Notification.showTemporary('Error saving quote');
            });
        },
        
        generatePDF: function() {
            if (!this.data.currentQuoteId) {
                OC.Notification.showTemporary('Please save the quote first before generating PDF');
                return;
            }
            
            const url = OC.generateUrl('/apps/door_estimator/api/quotes/' + this.data.currentQuoteId + '/pdf');
            window.open(url, '_blank');
        },
        
        newQuote: function() {
            if (confirm('This will clear the current quote. Are you sure?')) {
                // Reset quote data to initial state
                this.data.quoteData = {
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
                };
                this.data.currentQuoteId = null;
                this.renderApp();
            }
        },
        
        switchTab: function(tab) {
            this.data.activeTab = tab;
            this.renderApp();
        },
        
        renderApp: function() {
            const container = document.getElementById('door-estimator-app');
            if (!container) return;
            
            container.innerHTML = this.getAppHTML();
        },
        
        getAppHTML: function() {
            if (this.isLoading) {
                return `
                    <div class="loading text-center py-20">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                        <p class="mt-2 text-gray-600">Loading pricing data...</p>
                    </div>
                `;
            }
            
            return `
                ${this.getNavigationHTML()}
                <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    ${this.data.activeTab === 'estimator' ? this.getEstimatorHTML() : this.getAdminHTML()}
                </main>
            `;
        },
        
        getNavigationHTML: function() {
            return `
                <nav class="bg-white shadow-sm border-b">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex justify-between h-16">
                            <div class="flex items-center">
                                <svg class="w-8 h-8 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                                <h1 class="text-xl font-bold text-gray-900">Door Estimator</h1>
                            </div>
                            <div class="flex space-x-4">
                                <button data-tab="estimator" class="px-4 py-2 rounded-md text-sm font-medium ${this.data.activeTab === 'estimator' ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700'}">
                                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                    Estimator
                                </button>
                                <button data-tab="admin" class="px-4 py-2 rounded-md text-sm font-medium ${this.data.activeTab === 'admin' ? 'bg-purple-100 text-purple-700' : 'text-gray-500 hover:text-gray-700'}">
                                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    Admin
                                </button>
                            </div>
                        </div>
                    </div>
                </nav>
            `;
        },
        
        getEstimatorHTML: function() {
            return `
                <div class="space-y-6">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-6 rounded-lg">
                        <h2 class="text-2xl font-bold mb-2">Door & Hardware Estimator</h2>
                        <p class="text-blue-100">Create professional door and hardware estimates</p>
                    </div>

                    ${this.renderQuoteSection('Doors', 'doors')}
                    ${this.renderQuoteSection('Door Options', 'doorOptions')}
                    ${this.renderQuoteSection('Inserts', 'inserts')}
                    ${this.renderQuoteSection('Frames', 'frames', true)}
                    ${this.renderQuoteSection('Frame Options', 'frameOptions')}
                    ${this.renderQuoteSection('Hinges', 'hinges')}
                    ${this.renderQuoteSection('Weatherstrip', 'weatherstrip')}
                    ${this.renderQuoteSection('Closers', 'closers')}
                    ${this.renderQuoteSection('Locksets', 'locksets')}
                    ${this.renderQuoteSection('Exit Devices', 'exitDevices')}
                    ${this.renderQuoteSection('Hardware', 'hardware')}

                    ${this.getMarkupConfigHTML()}
                    ${this.getGrandTotalHTML()}
                    ${this.getActionButtonsHTML()}
                </div>
            `;
        },
        
        renderQuoteSection: function(title, sectionKey, hasFrameType = false) {
            const items = this.data.quoteData[sectionKey] || [];
            const availableItems = hasFrameType ? 
                (this.data.pricingData[sectionKey] && this.data.pricingData[sectionKey][items[0]?.frameType || 'HM Drywall'] || []) :
                (this.data.pricingData[sectionKey] || []);

            return `
                <div class="bg-white rounded-lg shadow-sm border p-4 mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">${title}</h3>
                    
                    ${items.map((item, index) => `
                        <div class="grid grid-cols-12 gap-2 mb-2 items-center">
                            <div class="text-sm font-medium text-gray-600">${item.id}</div>
                            
                            ${hasFrameType ? `
                                <select class="col-span-2 px-2 py-1 border rounded text-sm" 
                                        data-section="${sectionKey}" 
                                        data-index="${index}" 
                                        data-quote-field="frameType">
                                    <option value="HM Drywall" ${item.frameType === 'HM Drywall' ? 'selected' : ''}>HM Drywall</option>
                                    <option value="HM EWA" ${item.frameType === 'HM EWA' ? 'selected' : ''}>HM EWA</option>
                                    <option value="HM USA" ${item.frameType === 'HM USA' ? 'selected' : ''}>HM USA</option>
                                </select>
                            ` : ''}
                            
                            <select class="${hasFrameType ? 'col-span-4' : 'col-span-6'} px-2 py-1 border rounded text-sm" 
                                    data-section="${sectionKey}" 
                                    data-index="${index}" 
                                    data-quote-field="item">
                                <option value="">Select item...</option>
                                ${availableItems.map(option => `
                                    <option value="${option.item}" ${item.item === option.item ? 'selected' : ''}>${option.item}</option>
                                `).join('')}
                            </select>
                            
                            <input type="number" 
                                   class="col-span-1 px-2 py-1 border rounded text-sm text-center" 
                                   placeholder="Qty" 
                                   value="${item.qty || ''}"
                                   data-section="${sectionKey}" 
                                   data-index="${index}" 
                                   data-quote-field="qty">
                            
                            <div class="col-span-2 px-2 py-1 bg-gray-50 rounded text-sm text-right">
                                $${(item.price || 0).toFixed(2)}
                            </div>
                            
                            <div class="col-span-2 px-2 py-1 bg-blue-50 rounded text-sm font-medium text-right">
                                $${(item.total || 0).toFixed(2)}
                            </div>
                        </div>
                    `).join('')}
                    
                    <div class="border-t pt-2 mt-3">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-gray-600">Subtotal:</span>
                            <span>$${this.calculateSectionTotal(sectionKey).toFixed(2)}</span>
                        </div>
                    </div>
                </div>
            `;
        },
        
        getMarkupConfigHTML: function() {
            return `
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Markup Settings</h3>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Doors & Inserts</label>
                            <div class="flex items-center">
                                <input type="number" 
                                       class="w-16 px-2 py-1 border rounded text-sm text-center" 
                                       value="${this.data.markups.doors}"
                                       onchange="app.data.markups.doors = parseFloat(this.value) || 0; app.renderApp();">
                                <span class="ml-1 text-sm text-gray-600">%</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Frames</label>
                            <div class="flex items-center">
                                <input type="number" 
                                       class="w-16 px-2 py-1 border rounded text-sm text-center" 
                                       value="${this.data.markups.frames}"
                                       onchange="app.data.markups.frames = parseFloat(this.value) || 0; app.renderApp();">
                                <span class="ml-1 text-sm text-gray-600">%</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Hardware</label>
                            <div class="flex items-center">
                                <input type="number" 
                                       class="w-16 px-2 py-1 border rounded text-sm text-center" 
                                       value="${this.data.markups.hardware}"
                                       onchange="app.data.markups.hardware = parseFloat(this.value) || 0; app.renderApp();">
                                <span class="ml-1 text-sm text-gray-600">%</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        },
        
        getGrandTotalHTML: function() {
            return `
                <div class="bg-green-50 border border-green-200 rounded-lg p-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-bold text-gray-800">Total Estimate</h3>
                        <div class="text-3xl font-bold text-green-600">
                            $${this.calculateGrandTotal().toFixed(2)}
                        </div>
                    </div>
                </div>
            `;
        },
        
        getActionButtonsHTML: function() {
            return `
                <div class="flex space-x-4">
                    <button data-action="save-quote" class="flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        Save Quote
                    </button>
                    <button data-action="generate-pdf" class="flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Generate PDF
                    </button>
                    <button data-action="new-quote" class="flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        New Quote
                    </button>
                </div>
            `;
        },
        
        getAdminHTML: function() {
            return `
                <div class="space-y-6">
                    <div class="bg-gradient-to-r from-purple-600 to-purple-700 text-white p-6 rounded-lg">
                        <h2 class="text-2xl font-bold mb-2">Admin Panel</h2>
                        <p class="text-purple-100">Manage pricing data and system settings</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        ${this.getPricingCategoriesHTML()}
                    </div>
                </div>
            `;
        },
        
        getPricingCategoriesHTML: function() {
            return Object.entries(this.data.pricingData).map(([category, items]) => `
                <div class="bg-white rounded-lg shadow-sm border p-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3 capitalize">
                        ${category.replace(/([A-Z])/g, ' $1').trim()}
                    </h3>
                    <div class="space-y-2 max-h-64 overflow-y-auto">
                        ${Array.isArray(items) ? items.slice(0, 10).map(item => `
                            <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                                <span class="text-sm text-gray-700 flex-1 mr-2">${item.item}</span>
                                <span class="text-sm font-medium text-green-600">$${item.price}</span>
                            </div>
                        `).join('') : '<div class="text-sm text-gray-500">Complex category structure</div>'}
                        ${Array.isArray(items) && items.length > 10 ? `<div class="text-sm text-gray-500 italic">... and ${items.length - 10} more items</div>` : ''}
                    </div>
                    <button class="mt-3 w-full py-2 text-sm bg-purple-100 text-purple-700 rounded hover:bg-purple-200">
                        Edit ${category.charAt(0).toUpperCase() + category.slice(1)}
                    </button>
                </div>
            `).join('');
        }
    };
    
    // Initialize the app when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        window.app = app; // Make app globally accessible for inline event handlers
        app.init();
    });
    
})();