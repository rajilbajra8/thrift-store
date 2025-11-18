/* ============================================
   THRIFTVIBE - MAIN JAVASCRIPT
   ============================================ */

// Global Cart Management
let cart = [];

// Product Data
const products = {
    1: {
        id: 1,
        name: "Vintage Denim Jacket",
        category: "Clothing",
        price: 2499,
        image: "https://images.unsplash.com/photo-1552374196-1ab2a1c593e8?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=687&q=80"
    },
    2: {
        id: 2,
        name: "Classic White Sneakers",
        category: "Shoes",
        price: 2999,
        image: "https://images.unsplash.com/photo-1542291026-7eec264c27ff?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1170&q=80"
    },
    3: {
        id: 3,
        name: "Floral Summer Dress",
        category: "Clothing",
        price: 1999,
        image: "https://images.unsplash.com/photo-1591047139829-d91aecb6caea?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=736&q=80"
    },
    4: {
        id: 4,
        name: "Leather Ankle Boots",
        category: "Shoes",
        price: 3499,
        image: "https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=764&q=80"
    },
    5: {
        id: 5,
        name: "Vintage Leather Handbag",
        category: "Accessories",
        price: 899,
        image: "https://images.unsplash.com/photo-1553062407-98eeb64c6a62?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=687&q=80"
    },
    6: {
        id: 6,
        name: "Retro Band T-Shirt",
        category: "Vintage",
        price: 1299,
        image: "https://images.unsplash.com/photo-1515372039744-b8f02a3ae446?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=688&q=80"
    }
};

// ============================================
// CART FUNCTIONS
// ============================================

// Load cart from localStorage
function loadCart() {
    const savedCart = localStorage.getItem('thriftvibe_cart');
    if (savedCart) {
        cart = JSON.parse(savedCart);
    }
}

// Save cart to localStorage
function saveCart() {
    localStorage.setItem('thriftvibe_cart', JSON.stringify(cart));
}

// Update cart count in header
function updateCartCount() {
    const cartCount = document.getElementById('cartCount');
    if (cartCount) {
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        cartCount.textContent = totalItems;
    }
}

// Add item to cart
function addToCart(productId) {
    const existingItem = cart.find(item => item.id === productId);
    
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({
            id: productId,
            quantity: 1
        });
    }
    
    updateCartCount();
    saveCart();
    
    // Show success message
    showNotification('Item added to cart!', 'success');
}

// Remove item from cart
function removeFromCart(productId) {
    cart = cart.filter(item => item.id !== productId);
    updateCartCount();
    saveCart();
}

// Update item quantity in cart
function updateCartQuantity(productId, quantity) {
    const item = cart.find(item => item.id === productId);
    if (item) {
        item.quantity = quantity;
        if (item.quantity <= 0) {
            removeFromCart(productId);
        } else {
            saveCart();
            updateCartCount();
        }
    }
}

// Get cart total
function getCartTotal() {
    return cart.reduce((total, item) => {
        const product = products[item.id];
        return total + (product.price * item.quantity);
    }, 0);
}

// ============================================
// SEARCH FUNCTIONALITY
// ============================================

// Setup search functionality
function setupSearch() {
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    
    if (searchInput && searchButton) {
        // Search on button click
        searchButton.addEventListener('click', performSearch);
        
        // Search on Enter key press
        searchInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
    }
}

// Perform search
function performSearch() {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;
    
    const searchTerm = searchInput.value.toLowerCase();
    
    if (searchTerm.trim() === '') {
        showNotification('Please enter a search term', 'warning');
        return;
    }
    
    // Check if we're on index.html or pages/*.html
    const isIndexPage = window.location.pathname.endsWith('index.html') || window.location.pathname.endsWith('/');
    const productsPath = isIndexPage ? 'pages/products.html' : 'products.html';
    
    // Redirect to products page with search
    window.location.href = `${productsPath}?search=${encodeURIComponent(searchTerm)}`;
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

// Show notification
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#ffc107'};
        color: white;
        border-radius: 5px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Format currency
function formatCurrency(amount) {
    return `Rs ${amount.toLocaleString()}`;
}

// Handle responsive adjustments
function handleResize() {
    const width = window.innerWidth;
    const navLinks = document.querySelectorAll('.nav-links');
    
    navLinks.forEach(nav => {
        if (width <= 900) {
            nav.style.flexDirection = 'column';
        } else {
            nav.style.flexDirection = 'row';
        }
    });
}

// ============================================
// INITIALIZATION
// ============================================

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load cart
    loadCart();
    updateCartCount();
    
    // Setup search
    setupSearch();
    
    // Setup buy buttons
    const buyButtons = document.querySelectorAll('.buy-btn');
    buyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = parseInt(this.getAttribute('data-id'));
            addToCart(productId);
        });
    });
    
    // Responsive adjustments
    handleResize();
    window.addEventListener('resize', handleResize);
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        cart,
        products,
        addToCart,
        removeFromCart,
        updateCartQuantity,
        getCartTotal,
        loadCart,
        saveCart,
        updateCartCount
    };
}

