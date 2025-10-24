// ThriftVibe Database - Pure JavaScript
class ThriftVibeDB {
    constructor() {
        this.initDatabase();
    }

    initDatabase() {
        // Initialize products
        if (!localStorage.getItem('thriftvibe_products')) {
            const products = [
                {
                    id: 1,
                    name: "Vintage Denim Jacket",
                    price: 2499,
                    category: "Clothing",
                    description: "Classic vintage denim jacket in excellent condition",
                    image: "https://images.unsplash.com/photo-1552374196-1ab2a1c593e8?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=687&q=80",
                    stock: 15,
                    status: "in-stock",
                    color: "Blue",
                    size: "M"
                },
                {
                    id: 2,
                    name: "Classic White Sneakers",
                    price: 2999,
                    category: "Shoes",
                    description: "Comfortable white sneakers perfect for casual wear",
                    image: "https://images.unsplash.com/photo-1542291026-7eec264c27ff?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1170&q=80",
                    stock: 8,
                    status: "in-stock",
                    color: "White",
                    size: "42"
                }
            ];
            this.save('products', products);
        }

        // Initialize users
        if (!localStorage.getItem('thriftvibe_users')) {
            const users = [
                {
                    id: 1,
                    email: "admin@thriftvibe.com",
                    password: "admin123",
                    name: "Admin User",
                    role: "admin"
                }
            ];
            this.save('users', users);
        }

        // Initialize empty orders and categories
        if (!localStorage.getItem('thriftvibe_orders')) {
            this.save('orders', []);
        }
        if (!localStorage.getItem('thriftvibe_categories')) {
            this.save('categories', []);
        }
    }

    // CREATE - Add new item
    create(collection, data) {
        const items = this.getAll(collection);
        const newItem = { id: Date.now(), ...data };
        items.push(newItem);
        this.save(collection, items);
        return newItem;
    }

    // READ - Get all items
    getAll(collection) {
        const data = localStorage.getItem(`thriftvibe_${collection}`);
        return data ? JSON.parse(data) : [];
    }

    // READ - Get item by ID
    getById(collection, id) {
        const items = this.getAll(collection);
        return items.find(item => item.id == id);
    }

    // UPDATE - Modify item
    update(collection, id, updates) {
        const items = this.getAll(collection);
        const index = items.findIndex(item => item.id == id);
        if (index !== -1) {
            items[index] = { ...items[index], ...updates };
            this.save(collection, items);
            return items[index];
        }
        return null;
    }

    // DELETE - Remove item
    delete(collection, id) {
        const items = this.getAll(collection);
        const filteredItems = items.filter(item => item.id != id);
        this.save(collection, filteredItems);
        return true;
    }

    // QUERY - Filter by field
    query(collection, field, value) {
        const items = this.getAll(collection);
        return items.filter(item => item[field] === value);
    }

    // Save to localStorage
    save(collection, data) {
        localStorage.setItem(`thriftvibe_${collection}`, JSON.stringify(data));
    }
}

// Create global database instance
const db = new ThriftVibeDB();