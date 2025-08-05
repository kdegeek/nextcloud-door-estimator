import pandas as pd
import json
import logging
import os

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s"
)

# Load mappings from JSON config
def load_mappings(config_path="scripts/extractor_mappings.json"):
    if not os.path.exists(config_path):
        logging.error(f"Mapping config file not found: {config_path}")
        return {}
    try:
        with open(config_path, "r") as f:
            return json.load(f)
    except Exception as e:
        logging.error(f"Failed to load mapping config: {e}")
        return {}

MAPPINGS = load_mappings()

def extract_doors_sheet(df, row_transform=None):
    """Pure function: Extract doors sheet data (starts at row 5), with optional row_transform callable"""
    data = []
    for idx, row in df.iloc[4:].iterrows():
        try:
            if pd.isna(row.iloc[0]) or pd.isna(row.iloc[1]):
                continue
            item_name = str(row.iloc[0]).strip()
            price = row.iloc[1]
            if item_name and str(price).replace('.', '').isdigit():
                stock_status = 'stock' if 'Stock' in str(row.iloc[2] if len(row) > 2 else '') else 'special_order'
                item = {
                    'category': 'doors',
                    'subcategory': None,
                    'item_name': item_name,
                    'price': float(price),
                    'stock_status': stock_status,
                    'description': ''
                }
                if row_transform:
                    item = row_transform(item, row)
                data.append(item)
        except Exception as e:
            logging.error(f"Error extracting row {idx} in doors sheet: {e}")
    return data

def extract_simple_pricing_sheet(df, category, row_transform=None):
    """Pure function: Extract simple pricing sheets (item + price format), with optional row_transform callable"""
    data = []
    for idx, row in df.iterrows():
        try:
            if pd.isna(row.iloc[0]) or pd.isna(row.iloc[1]):
                continue
            item_name = str(row.iloc[0]).strip()
            price = row.iloc[1]
            if item_name and str(price).replace('.', '').replace('-', '').isdigit():
                stock_status = 'stock' if len(row) > 2 and 'Stock' in str(row.iloc[2]) else 'special_order'
                item = {
                    'category': category,
                    'subcategory': None,
                    'item_name': item_name,
                    'price': float(price),
                    'stock_status': stock_status,
                    'description': ''
                }
                if row_transform:
                    item = row_transform(item, row)
                data.append(item)
        except Exception as e:
            logging.error(f"Error extracting row {idx} in {category} sheet: {e}")
    return data

def extract_frames_sheet(df, row_transform=None):
    """Pure function: Extract frames sheet with subcategories, with optional row_transform callable"""
    data = []
    frame_subcategories = MAPPINGS.get("frame_subcategories", [
        {"match": "EWA", "subcategory": "HM EWA"},
        {"match": "USA", "subcategory": "HM USA"},
        {"match": "default", "subcategory": "HM Drywall"}
    ])
    for idx, row in df.iterrows():
        try:
            if pd.isna(row.iloc[0]) or pd.isna(row.iloc[1]):
                continue
            item_name = str(row.iloc[0]).strip()
            price = row.iloc[1]
            if item_name and str(price).replace('.', '').replace('-', '').isdigit():
                subcategory = None
                for entry in frame_subcategories:
                    if entry["match"] != "default" and entry["match"] in item_name:
                        subcategory = entry["subcategory"]
                        break
                if not subcategory:
                    for entry in frame_subcategories:
                        if entry["match"] == "default":
                            subcategory = entry["subcategory"]
                            break
                item = {
                    'category': 'frames',
                    'subcategory': subcategory,
                    'item_name': item_name,
                    'price': float(price),
                    'stock_status': 'stock',
                    'description': ''
                }
                if row_transform:
                    item = row_transform(item, row)
                data.append(item)
        except Exception as e:
            logging.error(f"Error extracting row {idx} in frames sheet: {e}")
    return data

def extract_wood_door_sheet(df, category, row_transform=None):
    """Pure function: Extract wood door sheets with species-based pricing, with optional row_transform callable"""
    data = []
    species_mapping = MAPPINGS.get("species_mapping", {
        'Lauan': 9,
        'Birch': 10,
        'Oak': 11,
        'Raw HB': 12,
        'Legacy': 13
    })
    for idx, row in df.iloc[6:].iterrows():
        try:
            if pd.isna(row.iloc[8]):
                continue
            door_size = str(row.iloc[8]).strip()
            for species, col_idx in species_mapping.items():
                if col_idx < len(row):
                    price = row.iloc[col_idx]
                    if not pd.isna(price) and str(price).replace('.', '').replace('-', '').isdigit():
                        item_name = f"{door_size} Solid Core Wood Door - {species}"
                        if category == 'scfire':
                            item_name += " Fire Rated"
                        item = {
                            'category': category,
                            'subcategory': species,
                            'item_name': item_name,
                            'price': float(price),
                            'stock_status': 'special_order',
                            'description': ''
                        }
                        if row_transform:
                            item = row_transform(item, row)
                        data.append(item)
        except Exception as e:
            logging.error(f"Error extracting row {idx} in {category} wood door sheet: {e}")
    return data