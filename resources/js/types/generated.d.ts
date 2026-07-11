declare namespace App {
namespace Data {
export type BomItemData = {
id: number,
raw_material_id: number,
name: string,
quantity: number,
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
min_stock: number,
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
status: string,
status_label: string,
currency: string,
item_count: number,
total: number,
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
export type RawMaterialData = {
id: number,
name: string,
sku: string,
unit: string,
min_stock: string,
created_at: string,
};
export type SalesOrderData = {
id: number,
customer: string | null,
status: string,
status_label: string,
currency: string,
item_count: number,
total: number,
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
export type StockMovementData = {
id: number,
warehouse: string,
item: string,
quantity: number,
reason: string,
user: string | null,
created_at: string,
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
};
}
}
