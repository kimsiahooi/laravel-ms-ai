declare namespace App {
namespace Data {
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
warehouse_id: number,
warehouse: string | null,
code: string,
name: string | null,
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
};
export type RawMaterialData = {
id: number,
name: string,
sku: string,
unit: string,
min_stock: string,
created_at: string,
};
export type StockMovementData = {
id: number,
location: string,
item: string,
quantity: number,
reason: string,
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
name: string,
code: string | null,
address: string | null,
created_at: string,
};
}
}
