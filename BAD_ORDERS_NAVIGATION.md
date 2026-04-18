# Bad Orders Navigation & System Map

## 🗺️ Where Everything Is Located

### Main Entry Points for Bad Orders

```
INVENTORY DASHBOARD
├─ Inventory Module (inventory-login.php)
│  ├─ Dashboard (modules/inventory/dashboard.php)
│  │  └─ [View overview of inventory operations]
│  │
│  ├─ Purchase Orders (modules/inventory/purchase_orders.php)
│  │  └─ [Create/Manage POs - shows rejected qty per PO]
│  │
│  ├─ ⭐ PO Receiving & Inspection (modules/inventory/po_receive.php)
│  │  └─ [STEP 1: Mark items as accepted/rejected]
│  │     └─ Select a PO from list
│  │     └─ Enter received, rejected, accepted quantities
│  │     └─ Automatic creation of return records
│  │
│  ├─ ⭐ Supplier Returns (modules/inventory/supplier_returns.php)
│  │  └─ [STEP 2-4: Manage bad orders & resolutions]
│  │     ├─ Statistics Dashboard (total impact overview)
│  │     ├─ Return Area Inventory (items in segregated area)
│  │     └─ Return Queue Table (all bad orders, status updates)
│  │
│  ├─ Suppliers (modules/inventory/add_supplier.php)
│  │  └─ [View/manage supplier information]
│  │
│  └─ Other inventory modules...
```

---

## 📍 File Structure Reference

```
Root Directory
│
├─ modules/inventory/
│  ├─ po_receive.php ........................ Receiving & Inspection
│  ├─ supplier_returns.php .................. Return Area & RTV Management
│  ├─ purchase_orders.php .................. PO Management (shows rejects)
│  ├─ dashboard.php ........................ Inventory Overview
│  └─ [other modules]
│
├─ core/inventory/
│  └─ po_actions.php ....................... Backend processing
│     ├─ action: receive_items ......... Create returns records
│     └─ action: update_return_status .. Update resolution
│
├─ database/
│  ├─ po_bo_tables.sql .................... Initial bad orders schema
│  └─ bad_orders_enhancement.sql ......... Enhanced tables & fields
│
├─ BAD_ORDERS_SYSTEM_GUIDE.md ............ Detailed technical guide
├─ BAD_ORDERS_QUICK_REFERENCE.md ........ Quick staff reference
└─ includes/
   ├─ inventory_header.php ............... Navigation menu
   └─ inventory_footer.php ............... Footer
```

---

## 🎯 Step-by-Step Navigation

### STEP 1: Receive Items & Mark Bad Orders

**Path**: Inventory → PO Receiving & Inspection

1. Login to inventory module (`inventory-login.php`)
2. Click menu: **"Receiving Module and Quality Inspection"** (or similar)
3. Opens: `modules/inventory/po_receive.php`
4. Select a PO from list to inspect
5. For each item:
   - Enter delivered quantity
   - Enter rejected quantity (if any)
   - Select reason from dropdown
   - System auto-calculates accepted quantity
6. Click "Save Receiving + Run Inspection Logic"
7. ✅ Creates supplier_returns record if rejected > 0

### STEP 2: Monitor Return Area Inventory

**Path**: Inventory → Supplier Returns

1. Click menu: **"Supplier Return Process (BO / RTV)"** (or similar)
2. Opens: `modules/inventory/supplier_returns.php`
3. See sections:
   - 📊 **Statistics Dashboard** (top): Pending/Sent/Resolved counts
   - 📍 **Return Area Inventory**: Items segregated, awaiting return
   - 📤 **Return to Vendor Queue**: All bad orders ever logged

### STEP 3: Update Return Status

**Path**: Supplier Returns → Return Queue → Status Column

1. In the Return Queue table, find the item
2. See current status: 
   - 🟡 Pending Return
   - 🔵 Sent to Supplier
   - 🟢 Resolved
3. Select new status from dropdown
4. Select resolution type:
   - 🔄 Replace (supplier sends new stock)
   - 📋 Credit Memo (deducted from next order)
5. Click "Update"
6. ✅ Status changes, timestamps recorded

### STEP 4: Check Impact on PO

**Path**: Inventory → Purchase Orders

1. Go to PO list
2. Click on a specific PO
3. See summary showing:
   - Items ordered
   - Items received ✅
   - Items rejected ❌
4. Rejected count links back to Supplier Returns

---

## 📋 User Permission & Roles

| Role | Read | Write | Delete |
|------|------|-------|--------|
| Inventory Staff | ✅ | ✅ (receive only) | ❌ |
| Inventory Manager | ✅ | ✅ (full) | ❌ |
| Admin | ✅ | ✅ | ✅ |
| Cashier | ❌ | ❌ | ❌ |
| Customer | ❌ | ❌ | ❌ |

---

## 🔍 Database Tables - Quick Lookup

**For Inventory Staff** (Read/Write):
- `supplier_returns` - Main bad order records
- `po_receiving_items` - Receiving details
- `po_receivings` - Receiving headers
- `return_area_inventory` - Segregated items (future)

**For Reporting** (Read-Only):
- `supplier_return_updates` - Audit trail
- `bad_orders_summary` - Statistics cache (future)

**Related Tables** (Don't directly access):
- `purchase_orders` - PO master records
- `purchase_order_items` - PO line items
- `products` - Product master (stock NOT updated for rejects)
- `suppliers` - Supplier info

---

## 🔧 Backend Action Routes

**All actions go through**: `core/inventory/po_actions.php`

| Action | Method | Input | What It Does |
|--------|--------|-------|-------------|
| `receive_items` | POST | po_id, received_qty[], rejected_qty[], etc | Creates receiving & return records |
| `update_return_status` | POST | return_id, next_status, resolution_type | Updates supplier_returns record |

**How to Send Data**:
```html
<form action="../../core/inventory/po_actions.php" method="POST">
    <input type="hidden" name="action" value="receive_items">
    <!-- or value="update_return_status" -->
    [form fields]
</form>
```

---

## 💾 Database Setup Order

1. **First**: Run `database/po_bo_tables.sql`
   - Creates: suppliers, purchase_orders, po_receivings, supplier_returns
   - Status: ✅ Already exists in system

2. **Then**: Run `database/bad_orders_enhancement.sql`
   - Alters: supplier_returns (adds resolution fields)
   - Creates: return_area_inventory, supplier_return_updates, bad_orders_summary
   - Status: 🆕 New enhancement

---

## 🔐 Security Considerations

- ✅ All inputs validated in PHP backend
- ✅ SQL injection prevention via prepared statements
- ✅ Role-based access control (inventory staff only)
- ✅ User ID tracked for all actions
- ✅ Full audit trail in supplier_return_updates
- ✅ Timestamps on all records

---

## 📞 Common Tasks & Where to Do Them

| Task | Go To | Action |
|------|-------|--------|
| Record incoming bad order | po_receive.php | Enter rejected qty + reason |
| See all rejected items | supplier_returns.php | View "Return Area Inventory" |
| Mark return sent to supplier | supplier_returns.php | Update status → "Sent" |
| Record replacement arrived | po_receive.php | New PO receiving |
| Record credit memo | supplier_returns.php | Set resolution → "Credit" |
| View supplier quality | purchase_orders.php | Check rejected qty by supplier |
| Audit trail of returns | supplier_returns.php* | Via database query |
| Financial impact report | (Future feature) | Not yet built |

*Requires direct database access or future audit UI

---

## 🆘 Troubleshooting Navigation

**Problem: Can't find rejected items page**
- Solution: Look for menu item "Supplier Return Process" or "Supplier Returns (BO / RTV)"
- Location: `modules/inventory/supplier_returns.php`

**Problem: Rejected items showing in stock**
- Solution: This shouldn't happen - rejected items excluded from stock
- Check: Verify item was marked "rejected" during receiving
- Fix: Can manually adjust stock in products table (admin only)

**Problem: Where did my return go?**
- Solution: Check supplier_returns.php - may have been marked "Resolved"
- Filter: Status column shows where it is in workflow

**Problem: Can't change resolution type**
- Solution: Dropdown available when updating status
- Note: Must select both status AND resolution, then click "Update"

**Problem: Need to undo a return status**
- Solution: Re-update to different status (creates audit trail)
- Note: Each change logged in supplier_return_updates table

---

## 📚 Documentation Files

| File | Purpose | For Whom |
|------|---------|----------|
| BAD_ORDERS_QUICK_REFERENCE.md | Staff training, procedures | Inventory Staff |
| BAD_ORDERS_SYSTEM_GUIDE.md | Technical deep-dive, integration | IT/Managers |
| This file (Navigation Map) | Where to find everything | Everyone |
| po_bo_tables.sql | Database schema | DBAs, Developers |
| bad_orders_enhancement.sql | Schema enhancements | DBAs, Developers |

---

## 🚀 Getting Started Checklist

- [ ] Read BAD_ORDERS_QUICK_REFERENCE.md
- [ ] Run bad_orders_enhancement.sql on database
- [ ] Test receiving with Accept + Reject mix
- [ ] Go to Supplier Returns page - see if data appears
- [ ] Try updating return status
- [ ] Try setting resolution (Replace or Credit)
- [ ] Share Quick Reference with inventory team
- [ ] Train staff on 4-step process
- [ ] Monitor return area regularly

---

**Last Updated**: April 19, 2026  
**Navigation Map Version**: 1.0
