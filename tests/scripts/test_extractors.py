import sys
import os
import pytest
import pandas as pd

# Ensure project root is in sys.path for scripts import
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '../../')))

from scripts.extractors import (
    extract_doors_sheet,
    extract_simple_pricing_sheet,
    extract_frames_sheet,
    extract_wood_door_sheet
)

def test_extract_doors_sheet_basic():
    df = pd.DataFrame([
        ['Header', 'Header', 'Header'],
        ['Header', 'Header', 'Header'],
        ['Header', 'Header', 'Header'],
        ['Header', 'Header', 'Header'],
        ['Door A', 100, 'Stock'],
        ['Door B', 200, 'Special'],
        [None, None, None]
    ])
    result = extract_doors_sheet(df)
    assert len(result) == 2
    assert result[0]['item_name'] == 'Door A'
    assert result[0]['price'] == 100.0
    assert result[0]['stock_status'] == 'stock'
    assert result[1]['item_name'] == 'Door B'
    assert result[1]['price'] == 200.0

def test_extract_simple_pricing_sheet_basic():
    df = pd.DataFrame([
        ['Hinge A', 10, 'Stock'],
        ['Hinge B', 20, 'Special'],
        [None, None, None]
    ])
    result = extract_simple_pricing_sheet(df, 'hinges')
    assert len(result) == 2
    assert result[0]['item_name'] == 'Hinge A'
    assert result[0]['category'] == 'hinges'
    assert result[1]['item_name'] == 'Hinge B'

def test_extract_frames_sheet_basic():
    df = pd.DataFrame([
        ['Frame A', 50],
        ['Frame EWA', 60],
        ['Frame USA', 70],
        [None, None]
    ])
    result = extract_frames_sheet(df)
    assert len(result) == 3
    assert result[0]['subcategory'] == 'HM Drywall'
    assert result[1]['subcategory'] == 'HM EWA'
    assert result[2]['subcategory'] == 'HM USA'

def test_extract_wood_door_sheet_basic():
    # Create 6 dummy rows to fill indices 0-5
    dummy_rows = [[None]*14 for _ in range(6)]
    # Real data at indices 6 and 7
    real_rows = [
        ['Size1', 'x', 'x', 'x', 'x', 'x', 'x', 'x', '36x80', 200, 210, 220, 230, 240],
        ['Size2', 'x', 'x', 'x', 'x', 'x', 'x', 'x', '32x80', 300, 310, 320, 330, 340]
    ]
    df = pd.DataFrame(dummy_rows + real_rows)
    # Only rows after index 5 (i.e., index 6+) are processed
    result = extract_wood_door_sheet(df, 'scwood')
    assert len(result) == 10
    assert result[0]['item_name'].startswith('36x80 Solid Core Wood Door - Lauan')
    assert result[5]['item_name'].startswith('32x80 Solid Core Wood Door - Lauan')
    assert result[0]['price'] == 200.0
    assert result[9]['price'] == 340.0