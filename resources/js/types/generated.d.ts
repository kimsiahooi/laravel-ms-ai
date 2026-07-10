declare namespace App {
namespace Data {
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
}
}
