# Images Folder

This folder is for storing all images used in the ThriftVibe project.

## Current Image Usage

The project currently uses external image URLs from Unsplash. To use local images:

1. Download the images from the URLs below
2. Save them in this `assets/images/` folder
3. Update the image paths in the HTML files

## Image URLs Currently Used

### Product Images:
- **Vintage Denim Jacket**: `https://images.unsplash.com/photo-1552374196-1ab2a1c593e8?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=687&q=80`
- **Classic White Sneakers**: `https://images.unsplash.com/photo-1542291026-7eec264c27ff?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1170&q=80`
- **Floral Summer Dress**: `https://images.unsplash.com/photo-1591047139829-d91aecb6caea?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=736&q=80`
- **Leather Ankle Boots**: `https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=764&q=80`
- **Vintage Leather Handbag**: `https://images.unsplash.com/photo-1553062407-98eeb64c6a62?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=687&q=80`
- **Retro Band T-Shirt**: `https://images.unsplash.com/photo-1515372039744-b8f02a3ae446?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=688&q=80`

### Background/Hero Images:
- **Hero Background**: `https://images.unsplash.com/photo-1520006403909-838d6b92c22e?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1170&q=80`

## Suggested File Structure

```
assets/
└── images/
    ├── products/
    │   ├── denim-jacket.jpg
    │   ├── white-sneakers.jpg
    │   ├── summer-dress.jpg
    │   ├── ankle-boots.jpg
    │   ├── leather-handbag.jpg
    │   └── band-tshirt.jpg
    ├── backgrounds/
    │   └── hero-background.jpg
    └── README.md (this file)
```

## How to Update Image Paths

When using local images, update the paths in HTML files:

**From:**
```html
<img src="https://images.unsplash.com/...">
```

**To:**
```html
<!-- For root level (index.html) -->
<img src="assets/images/products/denim-jacket.jpg">

<!-- For pages folder (pages/*.html) -->
<img src="../assets/images/products/denim-jacket.jpg">
```

## Notes

- All images should be optimized for web use (compressed, appropriate file sizes)
- Recommended formats: JPG for photos, PNG for graphics with transparency
- Maintain aspect ratios when resizing images

