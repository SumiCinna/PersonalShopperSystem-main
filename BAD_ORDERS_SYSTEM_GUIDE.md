# Bad Orders (Return to Vendor) Process - System Integration Guide

## Overview

The Bad Orders process handles products that arrive from suppliers with issues (expired, damaged, wrong item, near expiry). This document explains the complete workflow and where it integrates with other system modules.

---

## 1. RECEIVING & INSPECTION Phase

### Where It Happens
- **Module**: `modules/inventory/po_receive.php`
- **User Role**: Inventory Staff
- **Entry Point**: PO receiving form

### Process
1. Staff receives items from supplier delivery
2. For each line item, they enter:
   - **Delivered Qty**: Physical count off the truck
   - **Rejected Qty**: Items with issues (if any)
   - **Reject Reason**: One of:
     - Expired
     - Near Expiry
     - Damaged Packaging
     - Wrong Item
     - Other
   - **Accepted Qty**: Automatically calculated (Delivered - Rejected)

### Database Impact
- **`po_receiving_items`** table: Records all received items
- **`supplier_returns`** table: Creates entry for each rejected item set
- **`purchase_order_items`**: Updates `received_qty` and `rejected_qty` counters
- **`products`**: Stock updated ONLY for accepted items (rejected items NOT added)
- **`inventory_logs`**: Audit trail of accepted stock additions

### Key Logic
```
Received Items Flow:
├─ Accepted Items (added to sellable inventory)
│  └─ Updates: products.stock ↑
│     Activity: inventory_logs entry created
│     Audit: activity_logs (stock change recorded)
│
└─ Rejected Items (segregated in return area)
   └─ Updates: supplier_returns table
      Status: pending_return (initial)
      Data: product, qty, reason, po_id, supplier_id
```

---

## 2. SEGREGATION Phase

### Where Items Go
- **Physical Location**: "Return Area" (designated space in warehouse)
- **System Tracking**: `return_area_inventory` table

### What Happens
- Rejected items are physically moved to a clearly marked "Return Area"
- NOT mixed with sellable inventory
- Each item has:
  - Location code (e.g., Shelf A1)
  - Batch number for tracking
  - Reason for rejection
  - Original PO reference

### Visibility Points
1. **PO Receive page**: Shows rejected items in receiving history
2. **Supplier Returns page**: "Return Area Inventory" section displays all segregated items
3. **Dashboard Integration**: Could show return area stock as separate metric (for future expansion)

---

## 3. RETURN PROCESSING Phase

### Status Workflow
```
Pending Return
    ↓
(Company contacts supplier, arranges return)
    ↓
Sent to Supplier
    ↓
(Waiting for supplier confirmation/processing)
    ↓
Resolved
    (Either replaced or credited)
```

### Where It's Managed
- **Module**: `modules/inventory/supplier_returns.php`
- **User Role**: Inventory Manager / Logistics
- **Action**: Select status + resolution type + save

### Status Updates Trigger
- **Timestamp Recording**:
  - `sent_to_supplier_at`: When moving to "Sent" status
  - `received_by_supplier_at`: When received confirmation (for future manual update)
  - `resolved_at`: When marked "Resolved"

- **User Tracking**:
  - `sent_by_user`: Who initiated the return
  - `resolved_by_user`: Who closed the return
  - Created in `supplier_return_updates` audit table

---

## 4. RESOLUTION Phase (Important Business Part)

### Two Resolution Options

#### Option A: REPLACE ❌ ➡️ ✅
- Supplier sends replacement stock
- Process:
  1. Mark resolution as "Replace"
  2. Original items stay in return area until replacement arrives
  3. When replacement arrives, receive via normal PO flow
  4. Original rejected items can then be physically discarded/recycled
  5. Status: Resolved (automatically closed)

#### Option B: CREDIT MEMO 📋
- Amount deducted from next purchase
- Process:
  1. Mark resolution as "Credit Memo"
  2. Amount calculated: rejected_qty × unit_cost
  3. Stored in `supplier_returns.credit_memo_amount`
  4. Manual process: Finance team applies credit to next PO
  5. Status: Resolved (when credit is recorded)

### Where Resolution Is Tracked
- **Table**: `supplier_returns`
  - `resolution_type`: 'replace', 'credit_memo', or 'pending'
  - `credit_memo_amount`: Decimal value for credit memos
  - `replacement_po_id`: PO ID if creating replacement PO
  - `return_notes`: Additional notes about resolution

---

## 5. SYSTEM INTEGRATION POINTS

### 5.1 Purchase Orders Module
- **File**: `modules/inventory/purchase_orders.php`
- **Shows**: Rejected quantities in PO line items summary
- **Reflection**: "Rejected: X units" displayed alongside received quantities

### 5.2 Inventory Dashboard
- **File**: `modules/inventory/dashboard.php`
- **Shows**: Could display:
  - Total units in return area
  - Value at risk (rejected items not yet resolved)
  - Supplier quality metrics (defect rate by supplier)
  - Outstanding credit memos

### 5.3 Financial Reconciliation (Future Integration)
- **Where**: Accounting/Finance module (if added)
- **Would Show**:
  - Credit memos issued per supplier
  - Outstanding credits to apply to next PO
  - Impact on supplier payment terms

### 5.4 Supplier Performance Analytics (Future)
- **Metrics**:
  - Defect rate (% of orders with rejections)
  - Common rejection reasons
  - Average days to resolution
  - Replacement vs. credit memo ratio

### 5.5 Audit Trail & Compliance
- **Files**: `activity_logs`, `supplier_return_updates`
- **Records**:
  - Who rejected items and when
  - Status changes with timestamps
  - Resolution decisions and users involved
  - Full traceability for quality investigations

---

## 6. DATABASE TABLES INVOLVED

### Core Tables
| Table | Purpose | Key Fields |
|-------|---------|-----------|
| `supplier_returns` | Main bad order records | return_id, status, resolution_type, credited_amount |
| `return_area_inventory` | Physical segregation tracking | location, quantity, status |
| `supplier_return_updates` | Audit trail | old_status, new_status, update_type, timestamp |
| `po_receiving_items` | Receipt details | rejected_qty, reject_reason |
| `purchase_order_items` | PO line tracking | rejected_qty counter |

### Related Tables That Are Affected
| Table | Impact | How |
|-------|--------|-----|
| `products` | Stock count | Only accepted items added |
| `inventory_logs` | Audit trail | Logs accepted stock additions |
| `activity_logs` | Activity tracking | Records stock changes |

---

## 7. USER WORKFLOWS & WHERE THEY SEE DATA

### Inventory Staff (Receiving)
1. **PO Receive page**: Mark items as accepted/rejected
2. **See**: Rejection form with reason dropdown
3. **Impact**: Creates supplier return record

### Inventory Manager (Returns Processing)
1. **Supplier Returns page**: View all bad orders
2. **See**: Statistics dashboard (pending, sent, resolved counts)
3. **See**: Return Area Inventory section (segregated items)
4. **Action**: Update status (Pending → Sent → Resolved)
5. **Action**: Set resolution (Replace or Credit Memo)

### Logistics (If implemented)
1. **Could track**: Return shipments to suppliers
2. **Could record**: Received confirmation by supplier
3. **Could manage**: Pickup scheduling

### Finance (Future Integration)
1. **Track**: Credit memos issued
2. **Apply**: Credits to next purchase orders
3. **Report**: Bad order impact on costs

---

## 8. REPORTING & ANALYTICS OPPORTUNITIES

### Available Data Points
- Rejection rate by supplier
- Common rejection reasons (expired, damaged, etc.)
- Resolution time (days from rejection to resolution)
- Financial impact (value of bad orders)
- Credit memo usage patterns

### Suggested Reports
1. **Supplier Quality Report**: Defect rates per supplier
2. **Bad Order Summary**: Pending vs. resolved status
3. **Return Area Inventory**: Current items awaiting return
4. **Credit Memo Reconciliation**: Outstanding credits per supplier
5. **Cost Impact**: Total value lost to bad orders (by supplier, reason, time period)

---

## 9. IMPLEMENTATION CHECKLIST

### Database Setup
- [ ] Run `database/po_bo_tables.sql` (existing tables)
- [ ] Run `database/bad_orders_enhancement.sql` (new tables & enhancements)

### Module Enhancements
- [ ] `modules/inventory/po_receive.php` - Enhanced UI with process guide
- [ ] `modules/inventory/supplier_returns.php` - Full return management
- [ ] `core/inventory/po_actions.php` - Status/resolution update logic

### Testing
- [ ] Test receiving with accepted + rejected mix
- [ ] Test return status progression (Pending → Sent → Resolved)
- [ ] Test both resolution options (Replace & Credit Memo)
- [ ] Verify stock NOT added for rejected items
- [ ] Verify inventory logs track only accepted items

### Staff Training
- [ ] Inventory staff: How to mark rejections with proper reason
- [ ] Inventory manager: How to manage return area & set resolutions
- [ ] Finance team: How to apply credit memos to next POs

---

## 10. FUTURE ENHANCEMENTS

1. **Supplier Communication**: Auto-email supplier when return initiated
2. **Mobile App**: Scan rejected items into return area
3. **Return Shipping Integration**: Track pickup/shipment back to supplier
4. **Automatic Reordering**: Create replacement PO when "Replace" is selected
5. **Quality Analytics Dashboard**: Visual trends by supplier/reason
6. **Supplier Portal**: Let suppliers confirm receipt of returns
7. **RMA System**: Generate Return Merchandise Authorization numbers
8. **Auto Credit Memos**: Automatically apply credits to next PO
9. **Insurance Claims**: Track insurable damage/loss claims
10. **Waste Tracking**: Monitor disposal/recycling of unsalvageable items

---

## 11. KEY BUSINESS RULES

✅ **DO**
- Mark all defective items as rejected during receiving
- Always record rejection reason
- Move rejected items physically to Return Area immediately
- Update resolution status when outcome is determined
- Record credit memo amounts accurately for financial reconciliation

❌ **DON'T**
- Mix rejected items with sellable inventory
- Skip rejection reason entry
- Forget to mark return as "Sent" when shipped back
- Forget to mark as "Resolved" when settled
- Sell items from return area

---

## 12. FAQ

**Q: What if items are rejected after being mixed with inventory?**
A: Current system prevents this - rejected items aren't added to `products` stock. Manual correction required through inventory adjustment if this occurs.

**Q: Can rejected items be sold after inspection?**
A: Only if moved OUT of return area status. System prevents this - items stay segregated until resolution.

**Q: How is credit memo amount calculated?**
A: Rejected Qty × Unit Cost (from original PO line item)

**Q: What happens to original items when "Replace" is chosen?**
A: They remain in return area until physically returned/destroyed. When replacement PO arrives, accept it normally and then dispose of original.

**Q: Can resolution be changed after set?**
A: Yes - manually edit via database or add UI to change. Currently allows re-update.

---

## Contact & Support

For questions about this process or system integration:
- Check `modules/inventory/po_receive.php` for receiving procedures
- Check `modules/inventory/supplier_returns.php` for return management
- Review database schema: `database/po_bo_tables.sql` and `database/bad_orders_enhancement.sql`

---

**Last Updated**: April 19, 2026  
**Version**: 1.0  
**Status**: Active
