<template>
    <AppLayout title="Create Stock Transfer">
        <div class="p-6">
            <h1 class="text-lg font-semibold mb-4">Select Destination Warehouse</h1>
            <div class="mb-6">
                <select v-model="destinationWarehouse" class="w-full border rounded px-4 py-2">
                    <option disabled value="">-- Select Destination Warehouse --</option>
                    <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
                </select>
            </div>

            <h2 class="text-md font-semibold mb-2">Select Products to Transfer</h2>
            <div v-for="(group, warehouseId) in groupedProducts" :key="warehouseId" class="mb-6">
                <h3 class="text-sm font-bold mb-2 text-gray-700">{{ group[0].warehouse.name }}</h3>
                <div class="grid grid-cols-1 gap-3">
                    <div v-for="product in group" :key="product.id" class="flex items-start bg-white rounded shadow p-3">
                        <input
                            type="checkbox"
                            :value="product.id"
                            :checked="selected.includes(product.id)"
                            @change="toggleProduct(product.id)"
                            :disabled="!destinationWarehouse || destinationWarehouse === product.warehouse.id"
                            class="form-checkbox h-5 w-5 text-indigo-600 mr-4 mt-1"
                        />
                        <div class="flex-1">
                            <div class="font-medium">
                                {{ product.supplier_product_detail.product.name }}
                                <span class="text-xs text-gray-500 ml-2">[{{ product.sku }}]</span>
                            </div>
                            <div class="text-sm text-gray-500">Stock: {{ product.qty }}</div>
                            <div v-if="destinationWarehouse === product.warehouse.id" class="text-xs text-red-500">
                                Cannot transfer to same warehouse.
                            </div>
                            <div v-if="selected.includes(product.id)" class="mt-2">
                                <input
                                    type="number"
                                    v-model.number="transferQuantities[product.id]"
                                    :max="product.qty"
                                    min="1"
                                    class="border rounded px-2 py-1 w-24"
                                    placeholder="Qty"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <button
                    class="bg-indigo-600 text-white px-4 py-2 rounded"
                    :disabled="selected.length === 0 || !destinationWarehouse"
                    @click="proceedWithTransfer"
                >
                    Proceed with Transfer ({{ selected.length }} selected)
                </button>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { ref, onMounted, computed } from "vue";
import axios from "axios";
import { useToast } from "vue-toastification";

const products = ref([]);
const selected = ref([]);
const warehouses = ref([]);
const destinationWarehouse = ref(null);
const transferQuantities = ref({});
const toast = useToast();

const fetchWarehouses = async () => {
    const res = await axios.get("/api/warehouses");
    warehouses.value = res.data.data;
};

const fetchProducts = async () => {
    const res = await axios.get("/api/warehouse-stock-transfer/products");
    products.value = res.data.data;
};

const toggleProduct = (id) => {
    id = Number(id);
    if (selected.value.includes(id)) {
        selected.value = selected.value.filter((p) => p !== id);
        delete transferQuantities.value[id];
    } else {
        selected.value.push(id);
        transferQuantities.value[id] = 1;
    }
};

const groupedProducts = computed(() => {
    const map = {};
    products.value.forEach((product) => {
        const warehouseId = product.warehouse.id;
        if (!map[warehouseId]) map[warehouseId] = [];
        map[warehouseId].push(product);
    });
    return map;
});

const proceedWithTransfer = async () => {
    try {
        if (!destinationWarehouse.value || selected.value.length === 0) {
            return toast.error("Select products and destination warehouse.");
        }

        const payload = {
            destination_warehouse_id: destinationWarehouse.value,
            products: selected.value.map(id => ({
                id: id,
                qty: transferQuantities.value[id] || 1
            }))
        };
        console.log("Transfer Payload", payload);


        const response = await axios.post("/api/warehouse-stock-transfer/transfer", payload);
        toast.success("Transfer initiated!");

        if (response.data.redirect_url) {
            window.location.href = response.data.redirect_url;
        }
    } catch (error) {
        console.error(error);
        if (error.response?.status === 422) {
            const msg = error.response.data.message;
            const details = error.response.data.details;
            const fullMessage = details ? `${msg}: ${details}` : msg;
            toast.error(fullMessage);
        } else {
            toast.error("Transfer failed. Please try again.");
        }
    }
};

onMounted(() => {
    fetchWarehouses();
    fetchProducts();
});
</script>
