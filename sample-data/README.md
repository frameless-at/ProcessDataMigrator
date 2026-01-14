# Sample Data Files for Testing

This directory contains sample data files for testing the Data Migrator module with various file formats.

## Files

### 1. customers.csv
CSV file with customer records demonstrating:
- Standard CSV structure with header row
- Multiple data types (text, dates, phone numbers, addresses)
- International data (multiple countries)
- Status field for filtering

**Structure:**
- 10 customer records
- Fields: id, first_name, last_name, email, phone, address, city, postal_code, country, registration_date, status

**Usage:**
Upload this file to test CSV parsing with comma-delimited format.

---

### 2. orders.json
JSON file with order records demonstrating:
- Nested JSON structure (object with arrays)
- Foreign key relationships (customer_id references customers)
- Nested objects (shipping_address)
- Arrays within objects (items)
- Multiple data types

**Structure:**
- 8 order records
- Root object with "orders" array
- Fields: id, order_number, customer_id (FK), order_date, status, total_amount, currency, payment_method, shipping_address (nested), items (array)

**Usage:**
Upload this file to test JSON parsing. The parser will flatten nested structures using dot notation:
- `shipping_address.street`
- `shipping_address.city`
- etc.

**Foreign Key Relationship:**
The `customer_id` field references the `id` field in customers.csv. Import customers.csv first, then orders.json to establish the relationship.

---

### 3. products.xml
XML file with product catalog demonstrating:
- XML structure with repeating elements
- XML attributes (id, featured)
- Nested elements
- Multiple data types (text, numbers, dates, booleans)

**Structure:**
- 10 product records
- Root element: `<products>`
- Record elements: `<product>`
- Fields: id (attribute), name, sku, category, subcategory, price, currency, stock, weight, weight_unit, description, manufacturer, release_date, rating, reviews, featured (attribute)

**Usage:**
Upload this file to test XML parsing. The parser will:
- Extract attributes as regular fields (id, featured)
- Flatten nested elements to fields
- Auto-detect record elements (`<product>`)

---

## Testing Foreign Key Relationships

To test FK relationships, import files in this order:

1. **customers.csv** - Creates customers table
2. **orders.json** - Creates orders table with FK to customers
3. **products.xml** - Creates products table (standalone)

When importing orders.json, the module will:
- Detect `customer_id` as a potential FK field
- Allow you to map it to the customers table
- Auto-resolve FK relationships in generated templates

---

## Field Type Examples

These files demonstrate various field types that will be auto-detected:

- **Text**: names, addresses, descriptions
- **Email**: email addresses
- **Integer**: id, stock, reviews
- **Float**: price, rating
- **Date**: registration_date, order_date, release_date
- **Boolean**: featured (true/false in XML)
- **Enum/Status**: status fields

---

## Import Notes

### CSV (customers.csv)
- Delimiter: comma (auto-detected)
- Header row: yes
- Character encoding: UTF-8

### JSON (orders.json)
- Format: object with arrays
- Nested structures will be flattened
- Arrays (items) will be JSON-encoded as text

### XML (products.xml)
- XPath for records: `//products/product` (auto-detected)
- Attributes extracted as fields
- Nested elements flattened

---

## Expected Results

After importing all three files, you should have:

1. **Customers** (10 records)
   - Template: `customers` (detail), `customers_list` (overview)
   - Parent page: `/migration/customers/`

2. **Orders** (8 records)
   - Template: `orders` (detail), `orders_list` (overview)
   - Parent page: `/migration/orders/`
   - FK field: `orders_customer_id` → references customers

3. **Products** (10 records)
   - Template: `products` (detail), `products_list` (overview)
   - Parent page: `/migration/products/`

Each detail template will include:
- Quick access variables for all fields
- Pre-loaded FK relationships (for orders → customers)
- Debug section showing all available data
- Clean HTML output ready for customization
