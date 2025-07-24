#!/usr/bin/env python3
"""
Excel Data Extraction Script for Door Estimator
Extracts data from all 12 sheets in Estimator 050825.xlsx using Python
"""

import pandas as pd
import json
import sys
import os
from datetime import datetime

class ExcelDataExtractor:
    def __init__(self, excel_file):
        self.excel_file = excel_file
        self.extracted_data = {}
    
    def extract_all_data(self):
        """Extract data from all sheets"""
        try:
            # Read all sheets
            excel_data = pd.read_excel(self.excel_file, sheet_name=None, engine='openpyxl')
            
            # Process each sheet
            if 'Doors' in excel_data:
                self.extract_doors_sheet(excel_data['Doors'])
            
            if 'Inserts' in excel_data:
                self.extract_simple_pricing_sheet(excel_data['Inserts'], 'inserts')
            
            if 'Frames' in excel_data:
                self.extract_frames_sheet(excel_data['Frames'])
            
            if 'Hinges' in excel_data:
                self.extract_simple_pricing_sheet(excel_data['Hinges'], 'hinges')
                
            if 'WSTRP' in excel_data:
                self.extract_simple_pricing_sheet(excel_data['WSTRP'], 'weatherstrip')
                
            if 'Locksets' in excel_data:
                self.extract_simple_pricing_sheet(excel_data['Locksets'], 'locksets')
                
            if 'Exit Devices' in excel_data:
                self.extract_simple_pricing_sheet(excel_data['Exit Devices'], 'exitDevices')
                
            if 'Closers' in excel_data:
                self.extract_simple_pricing_sheet(excel_data['Closers'], 'closers')
                
            if 'Hardware' in excel_data:
                self.extract_simple_pricing_sheet(excel_data['Hardware'], 'hardware')
                
            if 'SCwood' in excel_data:
                self.extract_wood_door_sheet(excel_data['SCwood'], 'scwood')
                
            if 'SCfire' in excel_data:
                self.extract_wood_door_sheet(excel_data['SCfire'], 'scfire')
            
            return self.extracted_data
            
        except Exception as e:
            print(f"Error reading Excel file: {e}")
            return {}
    
    def extract_doors_sheet(self, df):
        """Extract doors sheet data (starts at row 5)"""
        data = []
        
        # Skip first 4 rows (headers), process the rest
        for idx, row in df.iloc[4:].iterrows():
            if pd.isna(row.iloc[0]) or pd.isna(row.iloc[1]):
                continue
                
            item_name = str(row.iloc[0]).strip()
            price = row.iloc[1]
            
            if item_name and str(price).replace('.', '').isdigit():
                stock_status = 'stock' if 'Stock' in str(row.iloc[2] if len(row) > 2 else '') else 'special_order'
                
                data.append({
                    'category': 'doors',
                    'subcategory': None,
                    'item_name': item_name,
                    'price': float(price),
                    'stock_status': stock_status,
                    'description': ''
                })
        
        self.extracted_data['doors'] = data
        print(f"Extracted {len(data)} items from Doors sheet")
    
    def extract_simple_pricing_sheet(self, df, category):
        """Extract simple pricing sheets (item + price format)"""
        data = []
        
        for idx, row in df.iterrows():
            if pd.isna(row.iloc[0]) or pd.isna(row.iloc[1]):
                continue
                
            item_name = str(row.iloc[0]).strip()
            price = row.iloc[1]
            
            if item_name and str(price).replace('.', '').replace('-', '').isdigit():
                stock_status = 'stock' if len(row) > 2 and 'Stock' in str(row.iloc[2]) else 'special_order'
                
                data.append({
                    'category': category,
                    'subcategory': None,
                    'item_name': item_name,
                    'price': float(price),
                    'stock_status': stock_status,
                    'description': ''
                })
        
        self.extracted_data[category] = data
        print(f"Extracted {len(data)} items from {category} sheet")
    
    def extract_frames_sheet(self, df):
        """Extract frames sheet with subcategories"""
        data = []
        
        for idx, row in df.iterrows():
            if pd.isna(row.iloc[0]) or pd.isna(row.iloc[1]):
                continue
                
            item_name = str(row.iloc[0]).strip()
            price = row.iloc[1]
            
            if item_name and str(price).replace('.', '').replace('-', '').isdigit():
                # Determine subcategory based on item name
                subcategory = 'HM Drywall'  # Default
                if 'EWA' in item_name:
                    subcategory = 'HM EWA'
                elif 'USA' in item_name:
                    subcategory = 'HM USA'
                
                data.append({
                    'category': 'frames',
                    'subcategory': subcategory,
                    'item_name': item_name,
                    'price': float(price),
                    'stock_status': 'stock',
                    'description': ''
                })
        
        self.extracted_data['frames'] = data
        print(f"Extracted {len(data)} items from Frames sheet")
    
    def extract_wood_door_sheet(self, df, category):
        """Extract wood door sheets with species-based pricing"""
        data = []
        
        # Species columns (adjust column indices based on actual spreadsheet)
        species_mapping = {
            'Lauan': 9,    # Column J (0-indexed = 9)
            'Birch': 10,   # Column K
            'Oak': 11,     # Column L  
            'Raw HB': 12,  # Column M
            'Legacy': 13   # Column N
        }
        
        # Start from row 6 (0-indexed = 6) to skip headers
        for idx, row in df.iloc[6:].iterrows():
            if pd.isna(row.iloc[8]):  # Column I (door size)
                continue
                
            door_size = str(row.iloc[8]).strip()
            
            for species, col_idx in species_mapping.items():
                if col_idx < len(row):
                    price = row.iloc[col_idx]
                    
                    if not pd.isna(price) and str(price).replace('.', '').replace('-', '').isdigit():
                        item_name = f"{door_size} Solid Core Wood Door - {species}"
                        if category == 'scfire':
                            item_name += " Fire Rated"
                        
                        data.append({
                            'category': category,
                            'subcategory': species,
                            'item_name': item_name,
                            'price': float(price),
                            'stock_status': 'special_order',
                            'description': ''
                        })
        
        self.extracted_data[category] = data
        print(f"Extracted {len(data)} items from {category} sheet")
    
    def save_to_json(self, output_file):
        """Save extracted data to JSON file"""
        with open(output_file, 'w') as f:
            json.dump(self.extracted_data, f, indent=2)
        print(f"Data saved to {output_file}")
    
    def save_to_sql(self, output_file):
        """Save extracted data as SQL INSERT statements"""
        sql = f"-- Door Estimator Pricing Data Import\n"
        sql += f"-- Generated on {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n\n"
        
        for category, items in self.extracted_data.items():
            sql += f"-- {category} items\n"
            for item in items:
                item_name = item['item_name'].replace("'", "''")  # Escape single quotes
                subcategory = f"'{item['subcategory']}'" if item['subcategory'] else 'NULL'
                stock_status = f"'{item['stock_status']}'" if item['stock_status'] else 'NULL'
                description = f"'{item['description']}'" if item['description'] else 'NULL'
                
                sql += f"INSERT INTO door_estimator_pricing (category, subcategory, item_name, price, stock_status, description, created_at, updated_at) VALUES ("
                sql += f"'{item['category']}', {subcategory}, '{item_name}', {item['price']}, {stock_status}, {description}, NOW(), NOW());\n"
            sql += "\n"
        
        with open(output_file, 'w') as f:
            f.write(sql)
        print(f"SQL script saved to {output_file}")

def main():
    excel_file = 'Estimator 050825.xlsx'
    
    if not os.path.exists(excel_file):
        print(f"Error: Excel file not found at {excel_file}")
        sys.exit(1)
    
    print(f"Extracting data from {excel_file}...")
    
    extractor = ExcelDataExtractor(excel_file)
    data = extractor.extract_all_data()
    
    # Save outputs
    extractor.save_to_json('scripts/extracted_pricing_data.json')
    extractor.save_to_sql('scripts/pricing_data_import.sql')
    
    # Print summary
    print("\nExtraction Summary:")
    total = 0
    for category, items in data.items():
        count = len(items)
        print(f"  {category}: {count} items")
        total += count
    print(f"  Total: {total} items")

if __name__ == "__main__":
    main()