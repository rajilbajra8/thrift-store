// ThriftVibe Backend Operations - Direct JavaScript Functions
window.ThriftVibeBackend = {
    // ==================== PRODUCT OPERATIONS ====================
    getAllProducts: function() {
        return db.getAll('products');
    },
    
    getProduct: function(id) {
        return db.getById('products', id);
    },
    
    addProduct: function(productData) {
        return db.create('products', productData);
    },
    
    updateProduct: function(id, updates) {
        return db.update('products', id, updates);
    },
    
    deleteProduct: function(id) {
        return db.delete('products', id);
    },
    
    searchProducts: function(searchTerm) {
        const products = db.getAll('products');
        return products.filter(product => 
            product.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            product.category.toLowerCase().includes(searchTerm.toLowerCase())
        );
    },
    
    getProductsByCategory: function(category) {
        return db.query('products', 'category', category);
    },

    // ==================== USER OPERATIONS ====================
    login: function(email, password) {
        const users = db.getAll('users');
        const user = users.find(u => u.email === email && u.password === password);
        if (user) {
            const { password, ...safeUser } = user;
            return { success: true, user: safeUser };
        }
        return { success: false, error: "Invalid email or password" };
    },
    
    register: function(userData) {
        const users = db.getAll('users');
        const exists = users.find(u => u.email === userData.email);
        if (exists) return { success: false, error: "User already exists" };
        
        const newUser = db.create('users', { ...userData, role: 'customer' });
        const { password, ...safeUser } = newUser;
        return { success: true, user: safeUser };
    },
    
    getAllUsers: function() {
        return db.getAll('users');
    },

    // ==================== ORDER OPERATIONS ====================
    createOrder: function(orderData) {
        const order = {
            ...orderData,
            orderDate: new Date().toLocaleString(),
            status: 'pending',
            orderId: 'ORD' + Date.now()
        };
        return db.create('orders', order);
    },
    
    getAllOrders: function() {
        return db.getAll('orders');
    },
    
    updateOrderStatus: function(orderId, status) {
        return db.update('orders', orderId, { status });
    },

    // ==================== INVENTORY OPERATIONS ====================
    updateStock: function(productId, newStock) {
        const product = db.getById('products', productId);
        if (!product) return false;
        
        let status = 'in-stock';
        if (newStock === 0) status = 'out-of-stock';
        else if (newStock <= 5) status = 'low-stock';
        
        return db.update('products', productId, { 
            stock: newStock, 
            status: status 
        });
    },

    // ==================== DASHBOARD OPERATIONS ====================
    getDashboardStats: function() {
        const products = db.getAll('products');
        const orders = db.getAll('orders');
        const users = db.getAll('users');
        
        const totalSales = orders.length;
        const totalRevenue = orders.reduce((sum, order) => sum + (order.totalAmount || 0), 0);
        const totalProducts = products.length;
        const totalCustomers = users.filter(u => u.role === 'customer').length;
        
        return {
            totalSales,
            totalRevenue,
            totalProducts,
            totalCustomers,
            lowStockItems: products.filter(p => p.status === 'low-stock').length
        };
    },

    // ==================== CATEGORY OPERATIONS ====================
    getAllCategories: function() {
        return db.getAll('categories');
    },
    
    addCategory: function(categoryData) {
        return db.create('categories', categoryData);
    }
};