# ✅ CHATBOT API - COMPLETE SETUP

## 🎉 TAPOS NA! Chatbot Can Now Access Live Database!

### 📁 Created Files:

1. **api/chatbot.php** ⭐ MAIN API
   - Live database access
   - 9 endpoints for data retrieval
   - Natural language query support
   - CORS enabled for external chatbot

2. **test-chatbot-api.html** 🧪 TEST INTERFACE
   - Interactive API testing
   - All endpoints with buttons
   - Search functionality
   - Natural language query tester

3. **CHATBOT_API_DOCUMENTATION.md** 📖 FULL DOCS
   - Complete API reference
   - All endpoints documented
   - Usage examples (JS, Python, cURL)
   - Integration guide

4. **CHATBOT_FINAL_5000.txt** 📋 KNOWLEDGE BASE
   - System knowledge + API info
   - Under 5000 characters
   - Ready for chatbot

---

## 🚀 Quick Start

### Step 1: Test API
```
http://localhost/lgu-inventory/test-chatbot-api.html
```

### Step 2: Try Endpoints

**Get Summary:**
```
http://localhost/lgu-inventory/api/chatbot.php?action=summary
```

**Search:**
```
http://localhost/lgu-inventory/api/chatbot.php?action=search&q=laptop
```

**Get Items:**
```
http://localhost/lgu-inventory/api/chatbot.php?action=items&limit=10
```

---

## 📡 API Endpoints

| Endpoint | Description | Example |
|----------|-------------|---------|
| `?action=summary` | System stats | Total items, categories, value |
| `?action=items` | Get items | With filters: category, status, office |
| `?action=offices` | Get offices | With item counts |
| `?action=issuances` | PAR/ICS records | Recent issuances |
| `?action=returns` | Return records | Return history |
| `?action=unserviceable` | Damaged items | Unserviceable list |
| `?action=stats` | Detailed stats | Comprehensive statistics |
| `?action=search&q=query` | Search | Search items & issuances |
| POST `{query:"..."}` | Natural language | Ask questions |

---

## 💬 Natural Language Queries

Chatbot can ask:
- "How many items are there?"
- "How many offices?"
- "What is the total value?"
- "How many unserviceable items?"
- "How many PPE items?"
- "How many SE items?"

**Example:**
```javascript
fetch('api/chatbot.php', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({query: 'How many items are there?'})
})
.then(res => res.json())
.then(data => console.log(data.answer));
// Output: "There are 150 property items in the system."
```

---

## 🔌 Chatbot Integration

### JavaScript Example:
```javascript
class LGUInventoryAPI {
  constructor() {
    this.baseUrl = 'http://localhost/lgu-inventory/api/chatbot.php';
  }

  async getSummary() {
    const res = await fetch(`${this.baseUrl}?action=summary`);
    return await res.json();
  }

  async search(query) {
    const res = await fetch(`${this.baseUrl}?action=search&q=${query}`);
    return await res.json();
  }

  async ask(question) {
    const res = await fetch(this.baseUrl, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({query: question})
    });
    return await res.json();
  }
}

// Usage
const api = new LGUInventoryAPI();
const summary = await api.getSummary();
const answer = await api.ask('How many items?');
```

---

## 📊 Response Format

All responses return JSON:
```json
{
  "success": true,
  "data": { ... },
  "message": "..."
}
```

**Example Summary Response:**
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

## 🎯 Use Cases

### 1. Chatbot Answers Questions
```
User: "How many items do we have?"
Chatbot: [Calls API] "There are 150 property items in the system."
```

### 2. Search Functionality
```
User: "Find laptop"
Chatbot: [Calls search API] "Found 3 items: Laptop Computer (2025-PPE-000001)..."
```

### 3. Get Statistics
```
User: "Show me inventory stats"
Chatbot: [Calls stats API] "Total: 150 items, SE: 80, PPE: 70, Value: ₱5M"
```

### 4. Office Information
```
User: "What items does the Mayor's office have?"
Chatbot: [Calls items API with office filter] "Mayor's office has 25 items..."
```

---

## 🔐 Security Features

✅ CORS enabled for external access
✅ Input sanitization on all parameters
✅ Prepared statements (SQL injection prevention)
✅ Foreign key constraints
✅ Error handling

---

## 🧪 Testing

### Test Interface:
```
http://localhost/lgu-inventory/test-chatbot-api.html
```

### Manual Testing:
```bash
# Get summary
curl "http://localhost/lgu-inventory/api/chatbot.php?action=summary"

# Search
curl "http://localhost/lgu-inventory/api/chatbot.php?action=search&q=laptop"

# Natural language
curl -X POST "http://localhost/lgu-inventory/api/chatbot.php" \
     -H "Content-Type: application/json" \
     -d '{"query":"How many items?"}'
```

---

## 📋 Knowledge Base Files

| File | Characters | Purpose |
|------|-----------|---------|
| CHATBOT_FINAL_5000.txt | ~4,800 | ⭐ Use this for chatbot |
| CHATBOT_KNOWLEDGE_5000.txt | 4,357 | System knowledge only |
| CHATBOT_KNOWLEDGE_WITH_API.txt | 5,400 | Full with API (over limit) |

**Recommended:** Use `CHATBOT_FINAL_5000.txt`

---

## ✅ What Chatbot Can Do Now:

1. ✅ Get live data from database
2. ✅ Answer "How many..." questions
3. ✅ Search for items
4. ✅ Get office information
5. ✅ Retrieve statistics
6. ✅ Check issuances and returns
7. ✅ List unserviceable items
8. ✅ Calculate total values
9. ✅ Filter by category, status, office

---

## 🎓 Next Steps:

1. **Test API:** Open `test-chatbot-api.html`
2. **Read Docs:** Check `CHATBOT_API_DOCUMENTATION.md`
3. **Integrate:** Use API in your chatbot
4. **Give Knowledge:** Paste `CHATBOT_FINAL_5000.txt` to chatbot

---

## 📞 API Support

**Base URL:** `http://localhost/lgu-inventory/api/chatbot.php`

**Test Interface:** `http://localhost/lgu-inventory/test-chatbot-api.html`

**Documentation:** `CHATBOT_API_DOCUMENTATION.md`

**Knowledge Base:** `CHATBOT_FINAL_5000.txt`

---

**Status:** ✅ READY TO USE!

Ang chatbot mo ay pwede na kumuha ng live data from database! 🎉
