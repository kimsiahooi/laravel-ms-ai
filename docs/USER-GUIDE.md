# Guide: what each screen does

A plain-language tour of the app, written for the people who use it day to
day — not for developers. Each workspace (company) gets its own private space
with the screens below, reached from the menu on the left.

If a word looks unfamiliar, check **[Words you'll see](#words-youll-see)** at
the bottom.

---

## Dashboard

Your home screen — a quick health check of the business. It shows:

- **Open orders** — how many orders are still waiting to be dealt with, split
  into purchase, sales, and production.
- **Low-stock items** — how many products or materials have dropped below the
  level you said you want to keep.
- **Production in progress** — how many builds are still open, plus how much
  you've finished in the last 30 days.
- **Items in stock** — how many different products and materials you currently
  hold.

Below the numbers are simple charts — stock going in and out, orders by status,
what you've produced, and how stock is spread across your warehouses — plus two
short lists: **things to reorder** and your **most recent stock changes**.

---

## Your catalog

This is the list of everything your business deals in. Set these up first.

- **Categories** — groups you can sort your products into (for example
  "Drinks" or "Spare parts"). Purely for organising.
- **Suppliers** — the companies you buy raw materials from.
- **Customers** — the people or companies you sell to.
- **Raw materials** — the ingredients or parts you buy in and use to make
  things.
- **Products** — the finished things you sell. A product can also have a
  **recipe** (see below) that lists the raw materials needed to make one.

---

## Where you keep stock

- **Warehouses** — your buildings or sites that hold stock.
- **Locations** — the specific spots inside a warehouse (a shelf, bay, or
  bin). Stock always lives in a location, not just "the warehouse".

---

## Moving stock around

- **Stock movements** — a running history of every time stock went up or down,
  and why. You can also record a manual change here (for example, a stock
  count correction). This list is never edited or deleted, so it's a reliable
  paper trail.
- **Stock transfers** — move stock from one location to another. The amount
  leaves the first location and arrives at the second in one step.

Both screens have a search box that filters by item, location, or note.

---

## Orders

### Purchase orders

Buying raw materials from a supplier. You list what you want and how much.
When the goods arrive, you **receive** the order into a location, and the stock
is added automatically. Each order has its own printable sheet.

### Sales orders

Selling products to a customer. You list what they're buying. When you ship it,
you **fulfil** the order from a location, and the stock is taken out
automatically. If there isn't enough stock, the app stops you rather than
letting stock go negative. Each order has its own printable invoice.

### Production orders

Making products yourself. You pick a product and how many to build, and the app
copies its **recipe** onto the order. When you mark the order **done**, it uses
up the raw materials and adds the finished products to stock — all in one step.
If a material is short, nothing happens and the order stays open. Each order has
its own printable work sheet.

---

## Recipes

A product's **recipe** is the list of raw materials, and how much of each, it
takes to make one unit. You set it from the **Products** screen using the
"Recipe" action. Production orders copy the recipe at the moment
they're created, so changing a recipe later never disturbs orders you've
already started.

---

## Words you'll see

| Word | What it means in plain terms |
| --- | --- |
| **Stock / on hand** | How much of something you physically have right now. |
| **Raw material** | Something you buy in to make products from. |
| **Product** | A finished thing you sell. |
| **Recipe** | The list of materials needed to make one product. |
| **Warehouse** | A building or site that holds stock. |
| **Location** | A specific spot inside a warehouse. |
| **Purchase order** | A request to buy materials from a supplier. |
| **Sales order** | An order from a customer buying your products. |
| **Production order** | A job to make some of your own products. |
| **Receive** | Adding purchased goods into stock when they arrive. |
| **Fulfil** | Taking products out of stock to send to a customer. |
| **Reorder level (min stock)** | The amount below which an item counts as "low". |
| **Pending** | An order that hasn't been received, fulfilled, or finished yet. |

---

*This guide describes what each screen is for. It isn't a technical document —
for how the app is built, see the other files in this folder.*
