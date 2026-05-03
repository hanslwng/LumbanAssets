# 🤖 Chatbot API Documentation

## Overview

The Chatbot API provides real-time access to the LGU Inventory System database, allowing chatbot assistants to retrieve live data, statistics, and answer natural language queries.

**Base URL:** `http://localhost/lgu-inventory/api/chatbot.php`

**Response Format:** JSON

---

## 🔐 Security Features

- ✅ CORS enabled for external chatbot access
- ✅ Input sanitization on all parameters
- ✅ Prepared statements (SQL injection prevention)
- ✅ Rate limiting recommended for production

---

## 📡 API Endpoints

### 1. Get System Summary
**Endpoint:** `GET /api/chatbot.php?action=summary`

**Description:** Returns overall system statistics

**Response:**
```json
{
  "success": true,
  "data": {
    "total_items": 150,
    "by_category": {
      "SE": 80,
      "PPE": 70
    },
    "by_status": {
      "Active": 100,
      "Issued": 30,
      "Unserviceable": 15,
      "Disposed": 5
    },
    "total_value": 5000000.00,
    "total_offices": 10,
    "items_today": 5
  }
}
```

---

### 2. Get All Items
**Endpoint:** `GET /api/chatbot.php?action=items`

**Parameters:**
- `limit` (optional, default: 50) - Number of items to return
- `offset` (optional, default: 0) - Pagination offset
- `category` (optional) - Filter by SE or PPE
- `status` (optional) - Filter by status
- `office_id` (optional) - Filter by office

**Examples:**
```
GET /api/chatbot.php?action=items&limit=10
GET /api/chatbot.php?action=items&category=PPE&status=Active
GET /api/chatbot.php?action=items&office_id=1&limit=20
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "property_number": "2025-PPE-000001",
      "description": "Laptop Computer",
      "category": "PPE",
      "unit_value": 55000.00,
      "quantity": 1,
      "office_id": 1,
      "office_name": "Office of the Mayor",
      "office_code": "MAYOR",
      "status": "Active",
      "condition_status": "Good",
      "acquisition_date": "2025-01-15",
      "created_at": "2025-01-15 10:30:00"
    }
  ],
  "count": 1
}
```

---

### 3. Get All Offices
**Endpoint:** `GET /api/chatbot.php?action=offices`

**Description:** Returns all offices with item counts and values

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Office of the Mayor",
      "code": "MAYOR",
      "total_items": 25,
      "se_count": 10,
      "ppe_count": 15,
      "total_value": 1500000.00,
      "created_at": "2025-01-01 00:00:00"
    }
  ]
}
```

---

### 4. Get Issuance Records
**Endpoint:** `GET /api/chatbot.php?action=issuances`

**Parameters:**
- `limit` (optional, default: 50) - Number of records

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "form_type": "PAR",
      "form_number": "PAR-2025-001",
      "property_item_id": 1,
      "property_number": "2025-PPE-000001",
      "description": "Laptop Computer",
      "category": "PPE",
      "office_id": 1,
      "office_name": "Office of the Mayor",
      "issued_to": "Juan Dela Cruz",
      "position": "Administrative Officer",
      "issue_date": "2025-01-20",
      "created_at": "2025-01-20 14:00:00"
    }
  ]
}
```

---

### 5. Get Return Records
**Endpoint:** `GET /api/chatbot.php?action=returns`

**Parameters:**
- `limit` (optional, default: 50)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "issuance_id": 1,
      "property_item_id": 1,
      "property_number": "2025-PPE-000001",
      "description": "Laptop Computer",
      "issued_to": "Juan Dela Cruz",
      "form_number": "PAR-2025-001",
      "return_date": "2025-02-15",
      "returned_by": "Juan Dela Cruz",
      "return_action": "Returned",
      "condition_on_return": "Good",
      "created_at": "2025-02-15 10:00:00"
    }
  ]
}
```

---

### 6. Get Unserviceable Items
**Endpoint:** `GET /api/chatbot.php?action=unserviceable`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "property_item_id": 5,
      "property_number": "2025-SE-000005",
      "description": "Office Chair",
      "category": "SE",
      "office_name": "Accounting Office",
      "marked_date": "2025-03-01",
      "reason": "Broken backrest, beyond repair",
      "marked_by": "Property Officer",
      "disposal_status": "Pending",
      "created_at": "2025-03-01 09:00:00"
    }
  ]
}
```

---

### 7. Get Detailed Statistics
**Endpoint:** `GET /api/chatbot.php?action=stats`

**Description:** Returns comprehensive statistics

**Response:**
```json
{
  "success": true,
  "data": {
    "items": {
      "total_items": 150,
      "se_count": 80,
      "ppe_count": 70,
      "active_count": 100,
      "issued_count": 30,
      "unserviceable_count": 15,
      "disposed_count": 5,
      "total_value": 5000000.00,
      "avg_value": 33333.33
    },
    "issuances": {
      "PAR": {
        "total": 40,
        "form_type": "PAR",
        "unique_recipients": 25
      },
      "ICS": {
        "total": 50,
        "form_type": "ICS",
        "unique_recipients": 30
      }
    },
    "returns": {
      "Returned": 20,
      "Unserviceable": 10,
      "Transfer": 5
    },
    "top_offices": [
      {
        "name": "Office of the Mayor",
        "item_count": 25,
        "total_value": 1500000.00
      }
    ]
  }
}
```

---

### 8. Search Data
**Endpoint:** `GET /api/chatbot.php?action=search&q={query}`

**Parameters:**
- `q` (required) - Search query

**Example:**
```
GET /api/chatbot.php?action=search&q=laptop
```

**Response:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "type": "item",
        "id": 1,
        "property_number": "2025-PPE-000001",
        "description": "Laptop Computer",
        "category": "PPE",
        "office_name": "Office of the Mayor"
      }
    ],
    "issuances": [
      {
        "type": "issuance",
        "id": 1,
        "form_number": "PAR-2025-001",
        "issued_to": "Juan Dela Cruz",
        "description": "Laptop Computer"
      }
    ]
  },
  "query": "laptop"
}
```

---

### 9. Natural Language Query (POST)
**Endpoint:** `POST /api/chatbot.php`

**Content-Type:** `application/json`

**Body:**
```json
{
  "query": "How many items are there?"
}
```

**Supported Queries:**
- "How many items are there?"
- "How many offices?"
- "What is the total value?"
- "How many unserviceable items?"
- "How many PPE items?"
- "How many SE items?"

**Response:**
```json
{
  "success": true,
  "query": "How many items are there?",
  "answer": "There are 150 property items in the system.",
  "data": {
    "count": 150
  }
}
```

---

## 💻 Usage Examples

### JavaScript (Fetch API)
```javascript
// Get summary
async function getSummary() {
  const response = await fetch('api/chatbot.php?action=summary');
  const data = await response.json();
  console.log(data);
}

// Get items with filters
async function getItems() {
  const response = await fetch('api/chatbot.php?action=items&category=PPE&limit=10');
  const data = await response.json();
  console.log(data);
}

// Search
async function search(query) {
  const response = await fetch(`api/chatbot.php?action=search&q=${encodeURIComponent(query)}`);
  const data = await response.json();
  console.log(data);
}

// Natural language query
async function askQuestion(question) {
  const response = await fetch('api/chatbot.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({query: question})
  });
  const data = await response.json();
  console.log(data.answer);
}
```

### Python
```python
import requests

# Get summary
response = requests.get('http://localhost/lgu-inventory/api/chatbot.php?action=summary')
data = response.json()
print(data)

# Natural language query
response = requests.post(
    'http://localhost/lgu-inventory/api/chatbot.php',
    json={'query': 'How many items are there?'}
)
data = response.json()
print(data['answer'])
```

### cURL
```bash
# Get summary
curl "http://localhost/lgu-inventory/api/chatbot.php?action=summary"

# Get items
curl "http://localhost/lgu-inventory/api/chatbot.php?action=items&limit=5"

# Search
curl "http://localhost/lgu-inventory/api/chatbot.php?action=search&q=laptop"

# Natural language query
curl -X POST "http://localhost/lgu-inventory/api/chatbot.php" \
     -H "Content-Type: application/json" \
     -d '{"query":"How many items are there?"}'
```

---

## 🧪 Testing

**Test Interface:** `http://localhost/lgu-inventory/test-chatbot-api.html`

The test interface provides:
- ✅ Quick test buttons for all endpoints
- ✅ Search functionality
- ✅ Natural language query testing
- ✅ Live JSON response display
- ✅ Usage examples

---

## 🔧 Integration with Chatbot

### Step 1: Configure Chatbot
Add the API base URL to your chatbot configuration:
```
API_BASE_URL = "http://localhost/lgu-inventory/api/chatbot.php"
```

### Step 2: Create Helper Functions
```javascript
class LGUInventoryAPI {
  constructor(baseUrl) {
    this.baseUrl = baseUrl;
  }

  async getSummary() {
    return await this.get('summary');
  }

  async getItems(filters = {}) {
    const params = new URLSearchParams(filters);
    return await this.get(`items&${params}`);
  }

  async search(query) {
    return await this.get(`search&q=${encodeURIComponent(query)}`);
  }

  async askQuestion(question) {
    const response = await fetch(this.baseUrl, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({query: question})
    });
    return await response.json();
  }

  async get(action) {
    const response = await fetch(`${this.baseUrl}?action=${action}`);
    return await response.json();
  }
}

// Usage
const api = new LGUInventoryAPI('http://localhost/lgu-inventory/api/chatbot.php');
const summary = await api.getSummary();
```

### Step 3: Handle User Queries
```javascript
async function handleUserQuery(userMessage) {
  const api = new LGUInventoryAPI('http://localhost/lgu-inventory/api/chatbot.php');
  
  // Try natural language query first
  const response = await api.askQuestion(userMessage);
  
  if (response.success && response.answer) {
    return response.answer;
  }
  
  // Fallback to specific queries
  if (userMessage.toLowerCase().includes('search')) {
    const searchTerm = extractSearchTerm(userMessage);
    const results = await api.search(searchTerm);
    return formatSearchResults(results);
  }
  
  return "I can help you with inventory queries. Try asking 'How many items?' or 'Search for laptop'";
}
```

---

## 🚀 Production Deployment

### Security Recommendations:
1. **Add Authentication:** Implement API key or OAuth
2. **Rate Limiting:** Prevent abuse
3. **HTTPS Only:** Use SSL certificate
4. **Input Validation:** Strict parameter validation
5. **Error Logging:** Monitor API usage

### Example with API Key:
```php
// Add to chatbot.php
$api_key = isset($_GET['api_key']) ? $_GET['api_key'] : '';
if ($api_key !== 'your-secret-key') {
    jsonResponse(['success' => false, 'message' => 'Invalid API key']);
}
```

---

## 📊 Response Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 400 | Bad Request (missing parameters) |
| 404 | Not Found |
| 500 | Server Error |

---

## ✅ Summary

The Chatbot API provides:
- ✅ Real-time database access
- ✅ Multiple data endpoints
- ✅ Natural language query support
- ✅ Search functionality
- ✅ Comprehensive statistics
- ✅ CORS enabled for external access
- ✅ Secure with prepared statements
- ✅ Easy integration

**Test Now:** `http://localhost/lgu-inventory/test-chatbot-api.html`
