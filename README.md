# ThriftVibe - Project Structure

## Folder Structure

```
thrift-store-main/
│
├── index.html
│
├── assets/
│   ├── css/
│   │   └── styles.css
│   │
│   ├── js/
│   │   └── script.js
│   │
│   ├── images/
│   │   └── (all your .jpg, .png, .svg files)
│   │
│   └── fonts/
│       └── (custom fonts if any)
│
└── pages/
    ├── about.html
    ├── contact.html
    ├── login.html
    ├── products.html
    ├── cart.html
    ├── checkout.html
    ├── admin-dashboard.html
    └── user-dashboard.html
```

## Path References

### From index.html (root)
- CSS: `assets/css/styles.css`
- JS: `assets/js/script.js`
- Pages: `pages/[page-name].html`

### From pages/*.html
- CSS: `../assets/css/styles.css`
- JS: `../assets/js/script.js`
- Other pages: `[page-name].html` or `../index.html`
- Backend: `../html/backend/[file].js`

## Notes

- All CSS has been extracted to `assets/css/styles.css`
- Common JavaScript is in `assets/js/script.js`
- Update all HTML files to use external CSS/JS instead of inline styles
- Update all navigation links to use the new folder structure



