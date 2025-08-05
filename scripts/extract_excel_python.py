#!/usr/bin/env python3
"""
Excel Data Extraction Script for Door Estimator
Extracts data from all 12 sheets in Estimator 050825.xlsx using Python
"""

import pandas as pd
import json
import sys
import os
import argparse
import logging
from datetime import datetime
from scripts.extractors import (
    extract_doors_sheet,
    extract_simple_pricing_sheet,
    extract_frames_sheet,
    extract_wood_door_sheet
)

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s"
)

def main():
    excel_file = 'Estimator 050825.xlsx'
    if not os.path.exists(excel_file):
        print(f"Error: Excel file not found at {excel_file}")
        sys.exit(1)
    print(f"Extracting data from {excel_file}...")

    # Read all sheets
    try:
        excel_data = pd.read_excel(excel_file, sheet_name=None, engine='openpyxl')
    except Exception as e:
        print(f"Error reading Excel file: {e}")
        return

    extracted_data = {}

    if 'Doors' in excel_data:
        extracted_data['doors'] = extract_doors_sheet(excel_data['Doors'])
        print(f"Extracted {len(extracted_data['doors'])} items from Doors sheet")
    if 'Inserts' in excel_data:
        extracted_data['inserts'] = extract_simple_pricing_sheet(excel_data['Inserts'], 'inserts')
        print(f"Extracted {len(extracted_data['inserts'])} items from Inserts sheet")
    if 'Frames' in excel_data:
        extracted_data['frames'] = extract_frames_sheet(excel_data['Frames'])
        print(f"Extracted {len(extracted_data['frames'])} items from Frames sheet")
    if 'Hinges' in excel_data:
        extracted_data['hinges'] = extract_simple_pricing_sheet(excel_data['Hinges'], 'hinges')
        print(f"Extracted {len(extracted_data['hinges'])} items from Hinges sheet")
    if 'WSTRP' in excel_data:
        extracted_data['weatherstrip'] = extract_simple_pricing_sheet(excel_data['WSTRP'], 'weatherstrip')
        print(f"Extracted {len(extracted_data['weatherstrip'])} items from WSTRP sheet")
    if 'Locksets' in excel_data:
        extracted_data['locksets'] = extract_simple_pricing_sheet(excel_data['Locksets'], 'locksets')
        print(f"Extracted {len(extracted_data['locksets'])} items from Locksets sheet")
    if 'Exit Devices' in excel_data:
        extracted_data['exitDevices'] = extract_simple_pricing_sheet(excel_data['Exit Devices'], 'exitDevices')
        print(f"Extracted {len(extracted_data['exitDevices'])} items from Exit Devices sheet")
    if 'Closers' in excel_data:
        extracted_data['closers'] = extract_simple_pricing_sheet(excel_data['Closers'], 'closers')
        print(f"Extracted {len(extracted_data['closers'])} items from Closers sheet")
    if 'Hardware' in excel_data:
        extracted_data['hardware'] = extract_simple_pricing_sheet(excel_data['Hardware'], 'hardware')
        print(f"Extracted {len(extracted_data['hardware'])} items from Hardware sheet")
    if 'SCwood' in excel_data:
        extracted_data['scwood'] = extract_wood_door_sheet(excel_data['SCwood'], 'scwood')
        print(f"Extracted {len(extracted_data['scwood'])} items from SCwood sheet")
    if 'SCfire' in excel_data:
        extracted_data['scfire'] = extract_wood_door_sheet(excel_data['SCfire'], 'scfire')
        print(f"Extracted {len(extracted_data['scfire'])} items from SCfire sheet")

    # Save outputs
    with open('scripts/extracted_pricing_data.json', 'w') as f:
        json.dump(extracted_data, f, indent=2)
    print(f"Data saved to scripts/extracted_pricing_data.json")

    # Save as SQL
    sql = f"-- Door Estimator Pricing Data Import\n"
    sql += f"-- Generated on {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n\n"
    for category, items in extracted_data.items():
        sql += f"-- {category} items\n"
        for item in items:
            item_name = item['item_name'].replace("'", "''")
            subcategory = f"'{item['subcategory']}'" if item['subcategory'] else 'NULL'
            stock_status = f"'{item['stock_status']}'" if item['stock_status'] else 'NULL'
            description = f"'{item['description']}'" if item['description'] else 'NULL'
            sql += (
                f"INSERT INTO door_estimator_pricing (category, subcategory, item_name, price, stock_status, description, created_at, updated_at) VALUES ("
                f"'{item['category']}', {subcategory}, '{item_name}', {item['price']}, {stock_status}, {description}, NOW(), NOW());\n"
            )
        sql += "\n"
    with open('scripts/pricing_data_import.sql', 'w') as f:
        f.write(sql)
    print(f"SQL script saved to scripts/pricing_data_import.sql")

    # Print summary
    print("\nExtraction Summary:")
    total = 0
    for category, items in extracted_data.items():
        count = len(items)
        print(f"  {category}: {count} items")
        total += count
    print(f"  Total: {total} items")

if __name__ == "__main__":
    main()