<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { ref, onMounted } from "vue";
import axios from "@/axios";
import { useColors } from "@/Composables/useColors";
import { useToast } from "vue-toastification";
import { Link } from "@inertiajs/vue3";

const { buttonPrimaryBgColor } = useColors();
const toast = useToast();

const modelName = "Inventory";

const warehouses = ref([]);
const analytics = ref({
    total_items: 0,
    total_value: 0,
    low_stock: 0,
    damaged: 0,
    categories: 0,
});

const filters = ref({
    warehouse: "",
    supplier: "",
    product: "",
    min_price: "",
    max_price: "",
});

const loading = ref(false);
const error = ref(null);

const warehouseOptions = ref([]);
const supplierOptions = ref([]);
const categoryOptions = ref([]);

const fetchInventory = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get("/api/inventory", {
            params: filters.value,
        });
        warehouses.value = response.data.data;
        fetchAnalytics();
    } catch (err) {
        error.value = "Failed to load inventory data.";
        console.error(err);
    } finally {
        loading.value = false;
    }
};

const fetchAnalytics = async () => {
    try {
        const response = await axios.get("/api/inventory/analytics", {
            params: filters.value,
        });
        analytics.value = response.data;
    } catch (err) {
        console.error("Failed to load analytics:", err);
    }
};

const fetchFilterOptions = async () => {
    try {
        const [warehouseRes, supplierRes, categoryRes] = await Promise.all([
            axios.get("/api/inventory/warehouses"),
            axios.get("/api/inventory/suppliers"),
            axios.get("/api/inventory/categories"),
        ]);
        warehouseOptions.value = warehouseRes.data;
        supplierOptions.value = supplierRes.data;
        categoryOptions.value = categoryRes.data;
    } catch (err) {
        console.error("Error loading filter options:", err);
    }
};

const clearFilters = () => {
    filters.value = {
        warehouse: "",
        supplier: "",
        product: "",
        min_price: "",
        max_price: "",
    };
    fetchInventory();
};
const isEditModalOpen = ref(false);
const editingProduct = ref(null);
const editForm = ref({
    qty: null,
    price: null,
});

const openEditModal = (product) => {
    editingProduct.value = product;
    editForm.value.qty = product.qty;
    editForm.value.price = product.price;
    isEditModalOpen.value = true;
};

const closeEditModal = () => {
    isEditModalOpen.value = false;
    editingProduct.value = null;
};

const saveEdit = async () => {
    try {
        await axios.put(
            `/api/inventory/${editingProduct.value.id}`,
            editForm.value
        );
        toast.success("Product updated successfully!");
        fetchInventory();
        closeEditModal();
    } catch (err) {
        console.error("Failed to save changes:", err);
        toast.error("Failed to update product.");
    }
};
const exportInventory = () => {
    const queryParams = new URLSearchParams(filters.value).toString();
    window.open(`/api/inventory/export?${queryParams}`, "_blank");
};

onMounted(() => {
    fetchInventory();
    fetchFilterOptions();
});
const isAddModalOpen = ref(false);

const newProductForm = ref({
    warehouse_id: "",
    product_name: "",
    category_id: "",
    supplier_id: "",
    qty: 0,
    critical_level_qty: 0,
    cost_price: 0,
    selling_price: 0,
});

const openAddModal = () => {
    isAddModalOpen.value = true;
};

const closeAddModal = () => {
    isAddModalOpen.value = false;
    Object.keys(newProductForm.value).forEach(
        (key) => (newProductForm.value[key] = "")
    );
};

const saveNewProduct = async () => {
    try {
        await axios.post("/api/inventory", newProductForm.value);
        toast.success("Product added successfully!");
        closeAddModal();
        fetchInventory(); // refresh list
    } catch (err) {
        if (err.response?.status === 422) {
            toast.error(err.response.data.message);
        } else {
            toast.error("Failed to add product.");
            console.error(err);
        }
    }
};
</script>

<template>
    <AppLayout :title="modelName">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Inventory Overview
                </h2>
                <div class="flex gap-2">
                    <button
                        @click="isAddModalOpen = true"
                        class="bg-green-600 text-white px-4 py-2 rounded shadow hover:bg-green-700"
                    >
                        + Add Product
                    </button>
                    <Link
                        href="/inventory/import"
                        class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700"
                    >
                        Import CSV
                    </Link>
                </div>
            </div>
        </template>

        <div class="p-6 space-y-6">
            <!-- Filters -->
            <div class="bg-white p-4 shadow rounded space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-semibold mb-1"
                            >Warehouse</label
                        >
                        <select
                            v-model="filters.warehouse"
                            class="w-full border rounded px-2 py-1"
                        >
                            <option value="">All Warehouses</option>
                            <option
                                v-for="wh in warehouseOptions"
                                :key="wh.id"
                                :value="wh.id"
                            >
                                {{ wh.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1"
                            >Supplier</label
                        >
                        <select
                            v-model="filters.supplier"
                            class="w-full border rounded px-2 py-1"
                        >
                            <option value="">All Suppliers</option>
                            <option
                                v-for="s in supplierOptions"
                                :key="s.id"
                                :value="s.id"
                            >
                                {{ s.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1"
                            >Search</label
                        >
                        <input
                            type="text"
                            placeholder="Search product..."
                            v-model="filters.product"
                            class="w-full border rounded px-2 py-1"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1"
                            >Min Price</label
                        >
                        <input
                            type="number"
                            v-model="filters.min_price"
                            class="w-full border rounded px-2 py-1"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1"
                            >Max Price</label
                        >
                        <input
                            type="number"
                            v-model="filters.max_price"
                            class="w-full border rounded px-2 py-1"
                        />
                    </div>
                </div>

                <div class="flex justify-end space-x-2 mt-4">
                    <button
                        @click="fetchInventory"
                        class="px-4 py-2 rounded text-white"
                        :style="{ backgroundColor: buttonPrimaryBgColor }"
                    >
                        Search
                    </button>
                    <button
                        @click="clearFilters"
                        class="px-4 py-2 rounded border text-sm"
                    >
                        Reset
                    </button>
                    <button
                        @click="exportInventory"
                        class="px-4 py-2 rounded border text-sm"
                    >
                        Export CSV
                    </button>
                </div>
            </div>

            <!-- Analytics -->
            <div
                class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4"
            >
                <div class="p-4 bg-white shadow rounded text-center">
                    <p class="text-sm font-semibold">Total Items</p>
                    <p class="text-2xl font-bold mt-1">
                        {{ analytics.total_items }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        Across all warehouses
                    </p>
                </div>

                <div class="p-4 bg-white shadow rounded text-center">
                    <p class="text-sm font-semibold">Total Stock Value</p>
                    <p class="text-2xl font-bold mt-1">
                        ₱{{ analytics.total_value.toLocaleString() }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        Based on cost price
                    </p>
                </div>

                <div class="p-4 bg-white shadow rounded text-center">
                    <p class="text-sm font-semibold">Low Stock Items</p>
                    <p class="text-2xl font-bold mt-1 text-red-500">
                        {{ analytics.low_stock }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">Need replenishment</p>
                </div>

                <div class="p-4 bg-white shadow rounded text-center">
                    <p
                        class="text-sm font-semibold flex items-center justify-center"
                    >
                        <span class="mr-1">⚠️</span> Damaged and Lost Items
                    </p>
                    <p class="text-2xl font-bold mt-1 text-orange-600">
                        {{ analytics.damaged }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        Items marked as damaged and lost
                    </p>
                </div>

                <div class="p-4 bg-white shadow rounded text-center">
                    <p class="text-sm font-semibold">Categories</p>
                    <p class="text-2xl font-bold mt-1">
                        {{ analytics.categories }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">Product categories</p>
                </div>
            </div>

            <!-- Error / Loading -->
            <div v-if="loading" class="text-gray-500">Loading...</div>
            <div v-if="error" class="text-red-500">{{ error }}</div>

            <!-- Results -->
            <div v-if="warehouses.length" class="space-y-6">
                <div
                    v-for="warehouse in warehouses"
                    :key="warehouse.id"
                    class="border rounded p-4"
                >
                    <h2 class="text-lg font-semibold mb-2">
                        {{ warehouse.name }}
                    </h2>

                    <div v-if="warehouse.products.length">
                        <table class="min-w-full text-sm border">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-4 py-2 text-left">Product</th>
                                    <th class="px-4 py-2 text-left">
                                        Category
                                    </th>
                                    <th class="px-4 py-2 text-left">
                                        Variation
                                    </th>
                                    <th class="px-4 py-2 text-left">
                                        Supplier
                                    </th>
                                    <th class="px-4 py-2 text-left">Qty</th>
                                    <th class="px-4 py-2 text-left">Price</th>
                                    <th class="px-4 py-2 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="product in warehouse.products"
                                    :key="product.id"
                                    class="border-t"
                                >
                                    <!-- <td class="px-4 py-2">
                                        {{
                                            product.supplier_product_detail
                                                ?.product?.name || "-"
                                        }}
                                    </td> -->
                                    <td class="px-4 py-2">
                                        {{
                                            product.supplier_product_detail
                                                ?.product?.name || "-"
                                        }}
                                        <template
                                            v-if="
                                                product.stock_adjustments
                                                    ?.length
                                            "
                                        >
                                            <div
                                                class="text-xs text-red-600 font-semibold"
                                            >
                                                <span
                                                    v-for="adjustment in product.stock_adjustments"
                                                    :key="adjustment.id"
                                                >
                                                    [{{
                                                        adjustment.reason.toUpperCase()
                                                    }}]
                                                </span>
                                            </div>
                                        </template>
                                    </td>

                                    <td class="px-4 py-2">
                                        {{
                                            product.supplier_product_detail
                                                ?.product?.category?.name || "-"
                                        }}
                                    </td>
                                    <td class="px-4 py-2">
                                        {{
                                            product.supplier_product_detail
                                                ?.variation?.name || "-"
                                        }}
                                    </td>
                                    <td class="px-4 py-2">
                                        {{
                                            product.supplier_product_detail
                                                ?.supplier?.name || "-"
                                        }}
                                    </td>
                                    <!-- <td class="px-4 py-2">{{ product.qty }}</td> -->
                                    <td class="px-4 py-2">
                                        {{ product.qty }}
                                        <div
                                            class="text-xs"
                                            :class="{
                                                'text-red-600 font-semibold':
                                                    product.critical_level_qty >
                                                        0 &&
                                                    product.qty <=
                                                        product.critical_level_qty,
                                                'text-gray-500': !(
                                                    product.critical_level_qty >
                                                        0 &&
                                                    product.qty <=
                                                        product.critical_level_qty
                                                ),
                                            }"
                                        >
                                            low stock:
                                            {{ product.critical_level_qty }}
                                        </div>
                                    </td>

                                    <td class="px-4 py-2">
                                        ₱{{
                                            Number(product.price || 0).toFixed(
                                                2
                                            )
                                        }}
                                        <div class="text-xs text-gray-500">
                                            Supplier Cost: ₱{{
                                                Number(
                                                    product
                                                        .supplier_product_detail
                                                        ?.price || 0
                                                ).toFixed(2)
                                            }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <button
                                            @click="openEditModal(product)"
                                            class="text-sm text-blue-600 hover:underline"
                                        >
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-gray-500">No products found.</div>
                </div>
            </div>
            <div v-else-if="!loading" class="text-gray-500">
                No warehouses found.
            </div>
        </div>
        <!-- Edit Modal -->
        <div
            v-if="isEditModalOpen"
            class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50"
        >
            <div class="bg-white p-6 rounded shadow-lg w-full max-w-md">
                <h2 class="text-lg font-semibold mb-4">Edit Product</h2>

                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-1"
                        >Quantity</label
                    >
                    <input
                        type="number"
                        v-model="editForm.qty"
                        class="w-full border rounded px-2 py-1"
                    />
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-1"
                        >Price</label
                    >
                    <input
                        type="number"
                        v-model="editForm.price"
                        class="w-full border rounded px-2 py-1"
                    />
                </div>

                <div class="flex justify-end space-x-2">
                    <button
                        @click="closeEditModal"
                        class="px-4 py-2 border rounded"
                    >
                        Cancel
                    </button>
                    <button
                        @click="saveEdit"
                        class="px-4 py-2 text-white rounded"
                        :style="{ backgroundColor: buttonPrimaryBgColor }"
                    >
                        Save
                    </button>
                </div>
            </div>
        </div>

        <!-- Add Product Modal -->
        <div
            v-if="isAddModalOpen"
            class="fixed inset-0 z-50 bg-black bg-opacity-50 flex items-center justify-center"
        >
            <div class="bg-white p-6 rounded shadow-lg w-full max-w-2xl">
                <h2 class="text-lg font-bold mb-2">Add New Item</h2>
                <p class="mb-4 text-sm text-gray-600">
                    Add a new item to your inventory system.
                </p>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Warehouse Dropdown -->
                    <div>
                        <label class="text-sm text-gray-700">Warehouse</label>
                        <select
                            v-model="newProductForm.warehouse_id"
                            class="w-full border rounded px-3 py-2 text-sm"
                        >
                            <option disabled value="">
                                -- Select Warehouse --
                            </option>
                            <option
                                v-for="w in warehouseOptions"
                                :key="w.id"
                                :value="w.id"
                            >
                                {{ w.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Product Name -->
                    <div>
                        <label class="text-sm text-gray-700"
                            >Product Name</label
                        >
                        <input
                            type="text"
                            v-model="newProductForm.product_name"
                            class="w-full border rounded px-3 py-2 text-sm"
                            placeholder="Enter product name"
                        />
                    </div>

                    <!-- Category -->
                    <div>
                        <label class="text-sm text-gray-700">Category</label>
                        <select
                            v-model="newProductForm.category_id"
                            class="w-full border rounded px-3 py-2 text-sm"
                        >
                            <option disabled value="">
                                -- Select Category --
                            </option>
                            <option
                                v-for="c in categoryOptions"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Supplier -->
                    <div>
                        <label class="text-sm text-gray-700">Supplier</label>
                        <select
                            v-model="newProductForm.supplier_id"
                            class="w-full border rounded px-3 py-2 text-sm"
                        >
                            <option disabled value="">
                                -- Select Supplier --
                            </option>
                            <option
                                v-for="s in supplierOptions"
                                :key="s.id"
                                :value="s.id"
                            >
                                {{ s.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Qty -->
                    <div>
                        <label class="text-sm text-gray-700"
                            >Current Stock</label
                        >
                        <input
                            type="number"
                            v-model="newProductForm.qty"
                            class="w-full border rounded px-3 py-2 text-sm"
                            placeholder="0"
                        />
                    </div>

                    <!-- Min Stock -->
                    <div>
                        <label class="text-sm text-gray-700">Min Stock</label>
                        <input
                            type="number"
                            v-model="newProductForm.critical_level_qty"
                            class="w-full border rounded px-3 py-2 text-sm"
                            placeholder="0"
                        />
                    </div>

                    <!-- Cost Price -->
                    <div>
                        <label class="text-sm text-gray-700">Cost Price</label>
                        <input
                            type="number"
                            v-model="newProductForm.cost_price"
                            class="w-full border rounded px-3 py-2 text-sm"
                            placeholder="0.00"
                        />
                    </div>

                    <!-- Selling Price -->
                    <div>
                        <label class="text-sm text-gray-700"
                            >Selling Price</label
                        >
                        <input
                            type="number"
                            v-model="newProductForm.selling_price"
                            class="w-full border rounded px-3 py-2 text-sm"
                            placeholder="0.00"
                        />
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex justify-end mt-6 space-x-2">
                    <button
                        @click="closeAddModal"
                        class="border px-4 py-2 rounded text-gray-700 hover:bg-gray-100"
                    >
                        Cancel
                    </button>
                    <button
                        @click="saveNewProduct"
                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
                    >
                        Create Item
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
