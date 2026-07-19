declare namespace App {
namespace Data {
export type ActivityChangeData = {
field: string,
old: string | null,
new: string | null,
};
export type ActivityData = {
id: number,
event: string | null,
subject_type: string,
subject: string,
causer: string | null,
changes: App.Data.ActivityChangeData[],
created_at: string,
};
export type BomItemData = {
id: number,
raw_material_id: number,
name: string,
quantity: number,
};
export type BusinessSettingsData = {
legal_name: string | null,
registration_no: string | null,
address: string | null,
tax_type: string,
tax_registration_no: string | null,
has_logo: boolean,
};
export type CategoryData = {
id: number,
name: string,
description: string | null,
created_at: string,
};
export type CustomerData = {
id: number,
name: string,
email: string | null,
phone: string | null,
address: string | null,
notes: string | null,
created_at: string,
};
export type LocationData = {
id: number,
name: string,
code: string | null,
address: string | null,
created_at: string,
};
export type OptionData = {
id: number,
name: string,
};
export type ProductData = {
id: number,
name: string,
sku: string,
barcode: string | null,
description: string | null,
image_url: string | null,
category_id: number | null,
supplier_id: number | null,
category: string | null,
supplier: string | null,
unit: string,
created_at: string,
bom: App.Data.BomItemData[],
};
export type ProductionOrderData = {
id: number,
product: string,
quantity: number,
status: string,
status_label: string,
item_count: number,
completed_at: string | null,
created_at: string,
items: App.Data.ProductionOrderItemData[],
};
export type ProductionOrderItemData = {
id: number,
raw_material_id: number | null,
name: string,
quantity_per_unit: number,
quantity_required: number,
};
export type PurchaseOrderData = {
id: number,
supplier: string | null,
supplier_id: number | null,
status: string,
status_label: string,
currency: string,
item_count: number,
total: number,
notes: string | null,
received_at: string | null,
created_at: string,
items: App.Data.PurchaseOrderItemData[],
};
export type PurchaseOrderItemData = {
id: number,
raw_material_id: number | null,
name: string,
quantity: number,
unit_cost: number,
};
export type PurchaseReturnData = {
id: number,
supplier: string | null,
supplier_id: number | null,
status: string,
status_label: string,
item_count: number,
total_quantity: number,
notes: string | null,
completed_at: string | null,
created_at: string,
items: App.Data.PurchaseReturnItemData[],
};
export type PurchaseReturnItemData = {
id: number,
raw_material_id: number | null,
name: string,
quantity: number,
};
export type RawMaterialData = {
id: number,
name: string,
sku: string,
unit: string,
created_at: string,
};
export type SalesOrderData = {
id: number,
customer: string | null,
customer_id: number | null,
status: string,
status_label: string,
currency: string,
item_count: number,
total: number,
notes: string | null,
fulfilled_at: string | null,
created_at: string,
items: App.Data.SalesOrderItemData[],
};
export type SalesOrderItemData = {
id: number,
product_id: number | null,
name: string,
quantity: number,
unit_price: number,
};
export type SalesReturnData = {
id: number,
customer: string | null,
customer_id: number | null,
status: string,
status_label: string,
item_count: number,
total_quantity: number,
notes: string | null,
completed_at: string | null,
created_at: string,
items: App.Data.SalesReturnItemData[],
};
export type SalesReturnItemData = {
id: number,
product_id: number | null,
name: string,
quantity: number,
};
export type StockMovementData = {
id: number,
warehouse: string,
item: string,
quantity: number,
reason: string,
user: string | null,
created_at: string,
};
export type StockOnHandData = {
on_hand: number,
unit: string,
reorder_level: number | null,
};
export type StockTakeData = {
id: number,
warehouse: string | null,
status: string,
status_label: string,
item_count: number,
total_variance: number,
notes: string | null,
counted_at: string | null,
created_at: string,
items: App.Data.StockTakeItemData[],
};
export type StockTakeItemData = {
id: number,
name: string,
sku: string | null,
unit: string,
system_qty: number,
counted_qty: number,
variance: number,
};
export type StockTransferData = {
id: number,
item: string,
from: string,
to: string,
quantity: number,
user: string | null,
created_at: string,
};
export type SupplierData = {
id: number,
name: string,
email: string | null,
phone: string | null,
address: string | null,
notes: string | null,
created_at: string,
};
export type WarehouseData = {
id: number,
location_id: number,
location: string | null,
name: string,
code: string | null,
address: string | null,
created_at: string,
items_in_stock: number,
low_stock: number,
out_of_stock: number,
};
export type WarehouseItemData = {
stockable_type: string,
stockable_id: number,
item: string,
sku: string | null,
type: string,
unit: string,
on_hand: number,
min_stock: number,
needs_reorder: boolean,
};
}
}
