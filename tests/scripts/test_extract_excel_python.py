import pytest
import sys
import os

# Ensure project root is in sys.path for scripts import
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '../../')))

import scripts.extract_excel_python as extract_excel

def test_main_handles_missing_file(monkeypatch):
    # Patch the excel_file variable to a non-existent file
    monkeypatch.setattr(extract_excel, "excel_file", "nonexistent.xlsx")
    # Patch sys.exit to prevent exiting the test runner
    monkeypatch.setattr(sys, "exit", lambda code=0: (_ for _ in ()).throw(SystemExit(code)))
    with pytest.raises(SystemExit):
        extract_excel.main()

def test_main_handles_invalid_file(monkeypatch, tmp_path):
    # Create an invalid Excel file
    invalid_file = tmp_path / "invalid.xlsx"
    invalid_file.write_text("not an excel file")
    monkeypatch.setattr(extract_excel, "excel_file", str(invalid_file))
    monkeypatch.setattr(sys, "exit", lambda code=0: (_ for _ in ()).throw(SystemExit(code)))
    with pytest.raises(SystemExit):
        extract_excel.main()