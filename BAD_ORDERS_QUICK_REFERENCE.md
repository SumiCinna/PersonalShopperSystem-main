# Bad Orders Process - Quick Reference Guide

## 🔄 The 4-Step Process

### Step 1️⃣ RECEIVING & INSPECTION
**When**: Items arrive from supplier  
**Where**: `modules/inventory/po_receive.php`  
**Who**: Inventory Staff  

📋 **What to Do**:
- Count items delivered from truck
- For each product line:
  - Enter "Delivered Qty"
  - Enter "Rejected Qty" if items have issues
  - Select rejection reason from dropdown
  - System auto-calculates "Accepted Qty"
- Only accepted items are added to stock

🎯 **Reject Reasons**:
- ❌ **Expired** - Past expiration date
- ⚠️ **Near Expiry** - Less than 1 month remaining
- 📦 **Damaged Packaging** - Box/packaging damaged
- 🏷️ **Wrong Item** - Different product received
- 🤷 **Other** - Different issue

---

### Step 2️⃣ SEGREGATION
**When**: Right after items are rejected  
**Where**: Physical "Return Area" in warehouse  
**Who**: Warehouse/Inventory Staff  

📍 **What to Do**:
- Move rejected items to designated "Return Area"
- Keep them separate from sellable products
- Assign location code (Shelf A1, Box B2, etc.)
- Keep batch labels visible

⚠️ **IMPORTANT**: Bad items must NEVER be mixed with good inventory!

---

### Step 3️⃣ RETURN PROCESSING
**When**: Company arranges return with supplier  
**Where**: `modules/inventory/supplier_returns.php`  
**Who**: Inventory Manager  

📤 **Status Progression**:

```
📋 PENDING RETURN (Just logged)
     ↓
     Company contacts supplier
     Arranges return/pickup
     ↓
🚚 SENT TO SUPPLIER (In transit back)
     ↓
     Waiting for supplier to confirm
     receipt & process
     ↓
✅ RESOLVED (Complete)
     Item replaced OR
     Credit memo issued
```

📝 **What to Do**:
1. Go to Supplier Returns page
2. Find the bad order in the queue
3. Select new status (Pending → Sent → Resolved)
4. Choose resolution option (see Step 4)
5. Click Update

---

### Step 4️⃣ RESOLUTION
**When**: Settlement with supplier is determined  
**Where**: Supplier Returns page (resolution dropdown)  
**Who**: Inventory Manager / Finance  

### Option A: 🔄 REPLACE
**Supplier sends replacement stock**

Process:
1. Select "Replace" from resolution dropdown
2. Original items stay in Return Area
3. Supplier ships replacement stock
4. When replacement arrives:
   - Receive via normal PO receiving
   - Then dispose of original rejected items
5. Mark Return as "Resolved"

✅ **Result**: You have good product in stock

---

### Option B: 📋 CREDIT MEMO
**Deducted from next purchase**

Process:
1. Select "Credit Memo" from resolution dropdown
2. System records credit amount automatically:
   - Credit = Rejected Qty × Unit Cost
3. Finance team tracks this credit
4. When ordering from supplier again:
   - Credit is applied to invoice
   - You pay less (credit deducted)
5. Mark Return as "Resolved"

✅ **Result**: Cost recovered from next order

---

## 📊 Where You'll See This Data

### 📦 Supplier Returns Page
**What It Shows**:
- **Statistics Dashboard**: 
  - Pending Returns count
  - Sent to Supplier count
  - Resolved count
  - Total impact (units & value)

- **Return Area Inventory Section**:
  - All items currently segregated
  - Location in warehouse
  - Why they were rejected
  - Date received

- **Return Queue Table**:
  - Every bad order ever logged
  - Current status
  - Resolution type
  - Action buttons to update

### 📥 PO Receive Page
**What It Shows**:
- Received items with accept/reject split
- Receiving history with all past transactions
- Visual three-column layout:
  - Delivered (blue)
  - Rejected (red)  
  - Accepted (green)

### 📈 Purchase Orders Page
**What It Shows**:
- Each PO summary includes rejected qty count
- "Already Received: X units"
- "Rejected: Y units" (if any)

---

## 🎓 Key Points to Remember

### ✅ DO THIS
✓ Mark items as rejected during receiving if defective  
✓ Always select the correct rejection reason  
✓ Keep rejected items physically segregated  
✓ Update return status when situation changes  
✓ Record resolution (Replace or Credit Memo)  
✓ Keep notes/documentation for audit trail  

### ❌ DON'T DO THIS
✗ Don't mix rejected items with good stock  
✗ Don't skip the rejection reason  
✗ Don't ignore items in return area  
✗ Don't forget to update return status  
✗ Don't sell items from return area  
✗ Don't lose track of credit memos  

---

## 🚨 Critical Business Impact

**Without This Process**:
- Bad products could reach customers ❌
- Supplier issues go untracked ❌
- Financial losses not recovered ❌
- Compliance/audit problems ❌

**With This Process**:
- Quality gate before selling ✅
- Full supplier accountability ✅
- Financial recovery (replace or credit) ✅
- Complete audit trail ✅

---

## 📞 Questions?

| Topic | Where to Find |
|-------|---|
| Detailed system info | `BAD_ORDERS_SYSTEM_GUIDE.md` |
| Database structure | `database/bad_orders_enhancement.sql` |
| Receiving procedures | `modules/inventory/po_receive.php` |
| Return management | `modules/inventory/supplier_returns.php` |

---

**Remember**: Every rejected item is a quality gate protecting customers AND a chance to recover costs!

---

## 🎯 Real-World Example

### Scenario: Expired Beverages from Supplier A

**Truck Arrives** (Step 1)
- Expected: 100 bottles
- Actually delivered: 100 bottles
- Quality check: 90 good, 10 expired

**Receiving Entry**:
- Delivered Qty: 100
- Rejected Qty: 10 (reason: Expired)
- Accepted Qty: 90 (auto-calculated)
- **Result**: Stock ↑ by 90 only, 10 NOT added

**Segregation** (Step 2)
- 10 expired bottles moved to Return Area
- Location: Shelf C3
- Clearly marked "DEFECTIVE - DO NOT SELL"

**Arrange Return** (Step 3)
- Email supplier: "10 units of product XYZ arrived expired"
- Supplier agrees to replace
- Set status to "Sent to Supplier"

**Resolution** (Step 4)
- Option A: Supplier ships 10 replacement units
  - Mark as "Replace"
  - Receive replacement when it arrives
  - Dispose of expired bottles
  - Mark return as "Resolved"

**OR**

- Option B: Supplier offers ₱500 credit
  - Amount = 10 units × ₱50/unit
  - Mark as "Credit Memo" (₱500)
  - Finance applies ₱500 discount to next invoice
  - Mark return as "Resolved"

**Outcome**: Zero bad product sold, 100% cost recovery! 🎉

---
