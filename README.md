# Magento 2 — Catalog Product Import Reset Media

## Aim of the module

Surprisingly, it seems that Magento 2 product import system doesn't handle media deletion. Indeed, if you import several times the same product with new images to assign on it, Magento will add the new ones to existing ones endlessly. All of them will show up on the front end, and your filesystem will be flooded by a lot of duplicate pictures.

This module adds a `reset_images` column in the CSV file to import that will erase all previous media assigned on the product if its value is `1`.

## What you still have to do

Nothing. You just have to install the module and to put the new `reset_images` in your CSV file that contains products to import.

## Warnings

  * This module has a huge negative impact on performance. On a test I did, the imported time increased by **33** between ones that resets images and ones that don't…
This slowness happens because I use the `save` method on each imported product for handling the deletion. Issue #1 tracks the problem.

  * The purpose of this module is more to help developers to save time instead of providing ready to use tools.
## Compatibility

This module was tested on the Magento versions that follows.

| Version | State |
| ------- | ----- |
| 2.3.5-p1 | Works |

## How to install it

Using Composer for installing this module is the best way:

```
composer require spydemon/m2-catalog_product_import_reset_media
```

## Help appreciated

If you like this module and find a bug or an enhancement, don't hesitate to fill an issue, or even better: a pull request. 😀
