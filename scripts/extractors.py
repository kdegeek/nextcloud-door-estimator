import pandas as pd

def extract_doors_sheet(df, row_transform=None):
    """Pure function: Extract doors sheet data (starts at row 5), with optional row_transform callable"""
    data = []
    for idx, row in df.iloc[4:].iterrows():
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
    return data

def extract_simple_pricing_sheet(df, category, row_transform=None):
    """Pure function: Extract simple pricing sheets (item + price format), with optional row_transform callable"""
    data = []
    for idx, row in df.iterrows():
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
    return data

def extract_frames_sheet(df, row_transform=None):
    """Pure function: Extract frames sheet with subcategories, with optional row_transform callable"""
    data = []
    for idx, row in df.iterrows():
        if pd.isna(row.iloc[0]) or pd.isna(row.iloc[1]):
            continue
        item_name = str(row.iloc[0]).strip()
        price = row.iloc[1]
        if item_name and str(price).replace('.', '').replace('-', '').isdigit():
            subcategory = 'HM Drywall'
            if 'EWA' in item_name:
                subcategory = 'HM EWA'
            elif 'USA' in item_name:
                subcategory = 'HM USA'
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
    return data

def extract_wood_door_sheet(df, category, row_transform=None):
    """Pure function: Extract wood door sheets with species-based pricing, with optional row_transform callable"""
    data = []
    species_mapping = {
        'Lauan': 9,
        'Birch': 10,
        'Oak': 11,
        'Raw HB': 12,
        'Legacy': 13
    }
    for idx, row in df.iloc[6:].iterrows():
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
    return data