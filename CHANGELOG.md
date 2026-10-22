# Release Notes for Meier Labs Extensions

## 1.3.0 - 2026-10-23

- `mock-assets` generates image placeholders with GD in the size (at most 2400px) and format of each asset instead of downloading one 4000x4000 jpg, so layouts keep their aspect ratios and no download is needed; without GD (Imagick only) it downloads as before. Image kinds GD can't write (heic, tiff, bmp, ...) get jpg data under their own file name, as before
- `mock-assets` downloads a dummy only when there are assets of its kind, and reports a dummy it can't write once instead of failing every asset

## 1.2.1 - 2026-10-16

- relax the Craft CMS requirement from `^5.9.0` to `^5.0.0`

## 1.2.0 - 2026-09-23

- add `mock-assets` console command to replace asset files with small dummy files, useful for staging/testing environments

## 1.1.0 - 2026-07-13

- add ungroup support for matrix cards blocks

## 1.0.2 - 2026-03-03

### Fixes

- fix ungroupMatrixButtons would always create the first block

## 1.0.1 - 2026-02-27

### Fixes

- link field compact did not work

## 1.0.0 - 2026-02-26

First Release
