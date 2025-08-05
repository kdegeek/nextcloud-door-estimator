import os
import requests

BASE_URL = os.environ.get("E2E_BASE_URL", "http://localhost:8080/apps/door_estimator/api")
AUTH_TOKEN = os.environ.get("E2E_AUTH_TOKEN", "testtoken")

HEADERS = {
    "Authorization": f"Bearer {AUTH_TOKEN}",
    "Content-Type": "application/json"
}

def test_quote_workflow():
    # 1. Get pricing data
    resp = requests.get(f"{BASE_URL}/pricing", headers=HEADERS)
    assert resp.status_code == 200
    pricing = resp.json()
    assert "data" in pricing

    # 2. Create a quote
    quote_payload = {
        "quoteData": [{"category": "doors", "item": "Door A", "qty": 1}],
        "markups": {"doors": 0.1},
        "quoteName": "E2E Test Quote",
        "customerInfo": "Test Customer"
    }
    resp = requests.post(f"{BASE_URL}/quote", json=quote_payload, headers=HEADERS)
    assert resp.status_code == 200
    quote_id = resp.json().get("id")
    assert quote_id is not None

    # 3. Update pricing
    update_payload = {
        "id": 1,
        "category": "doors",
        "item_name": "Door A",
        "price": 123.45,
        "description": "Updated by E2E test"
    }
    resp = requests.post(f"{BASE_URL}/pricing/update", json=update_payload, headers=HEADERS)
    assert resp.status_code == 200

    # 4. Generate PDF for quote
    resp = requests.get(f"{BASE_URL}/quote/{quote_id}/pdf", headers=HEADERS)
    assert resp.status_code == 200
    assert resp.headers.get("Content-Type", "").startswith("application/pdf")

    # 5. Validate quote data consistency
    resp = requests.get(f"{BASE_URL}/quote/{quote_id}", headers=HEADERS)
    assert resp.status_code == 200
    quote = resp.json()
    assert quote.get("quoteName") == "E2E Test Quote"
    assert quote.get("customerInfo") == "Test Customer"