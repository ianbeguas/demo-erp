<template>
    <AppLayout title="Create Stock Transfer V2">
        <div class="p-6">
            <h1 class="text-lg font-semibold mb-4">
                Select Destination Warehouse
            </h1>
            <div class="mb-6">
                <select
                    v-model="destinationWarehouse"
                    class="w-full border rounded px-4 py-2"
                >
                    <option disabled value="">
                        -- Select Destination Warehouse --
                    </option>
                    <option
                        v-for="wh in warehouses"
                        :key="wh.id"
                        :value="wh.id"
                    >
                        {{ wh.name }}
                    </option>
                </select>
            </div>

            <div class="mb-6">
                <h2 class="text-md font-semibold mb-2">
                    Search Serial Numbers
                </h2>
                <div class="flex gap-2">
                    <input
                        v-model="serialInput"
                        type="text"
                        placeholder="Enter Serial Number"
                        class="border rounded px-4 py-2 w-full"
                        @keyup.enter="addSerial"
                    />
                    <button
                        @click="addSerial"
                        class="bg-blue-500 text-white px-4 py-2 rounded"
                    >
                        Add
                    </button>
                </div>
                <div class="mt-2 text-sm text-gray-600">
                    Enter multiple serials and press 'Add'.
                </div>
                <div class="mt-2">
                    <span
                        v-for="(serial, index) in serials"
                        :key="index"
                        class="inline-block bg-gray-200 px-2 py-1 rounded mr-2 mt-2"
                    >
                        {{ serial }}
                        <button
                            @click="removeSerial(index)"
                            class="ml-1 text-red-500"
                        >
                            x
                        </button>
                    </span>
                </div>
                <button
                    @click="searchSerials"
                    class="mt-4 bg-indigo-600 text-white px-4 py-2 rounded"
                    :disabled="serials.length === 0 || !destinationWarehouse"
                >
                    Search Serials
                </button>
            </div>

            <div v-if="products.length > 0">
                <h2 class="text-md font-semibold mb-2">Products Found</h2>
                <div class="grid grid-cols-1 gap-3">
                    <div
                        v-for="product in products"
                        :key="product.id"
                        class="flex items-start bg-white rounded shadow p-3"
                    >
                        <div class="flex-1">
                            <div class="font-medium">
                                {{
                                    product.supplier_product_detail.product.name
                                }}
                                <span class="text-xs text-gray-500 ml-2"
                                    >[{{ product.sku }}]</span
                                >
                            </div>
                            <div class="text-sm text-gray-500">
                                Stock: {{ product.qty }}
                            </div>
                            <div
                                v-if="
                                    destinationWarehouse ===
                                    product.warehouse.id
                                "
                                class="text-xs text-red-500"
                            >
                                Cannot transfer to same warehouse.
                            </div>
                            <div class="mt-2 text-sm text-gray-600">
                                Matched Serials:
                                <ul class="list-disc list-inside">
                                    <li
                                        v-for="serial in product.matched_serials"
                                        :key="serial"
                                    >
                                        {{ serial }}
                                    </li>
                                </ul>
                            </div>
                            
                            <div class="mt-2 text-sm text-gray-700">
                                Quantity to Transfer:
                                {{ product.matched_serials.length }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <button
                        class="bg-indigo-600 text-white px-4 py-2 rounded"
                        :disabled="
                            products.length === 0 || !destinationWarehouse
                        "
                        @click="proceedWithTransfer"
                    >
                        Submit Transfer ({{ products.length }} items)
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { ref, onMounted } from "vue";
import axios from "axios";
import { useToast } from "vue-toastification";
import { router } from '@inertiajs/vue3';

const warehouses = ref([]);
const destinationWarehouse = ref(null);
const serialInput = ref("");
const serials = ref([]);
const products = ref([]);
const transferQuantities = ref({});
const toast = useToast();

const fetchWarehouses = async () => {
    const res = await axios.get("/api/warehouses");
    warehouses.value = res.data.data;
};

const addSerial = () => {
    const trimmed = serialInput.value.trim();
    if (trimmed && !serials.value.includes(trimmed)) {
        serials.value.push(trimmed);
    }
    serialInput.value = "";
};

const removeSerial = (index) => {
    serials.value.splice(index, 1);
};

const searchSerials = async () => {
    try {
        const res = await axios.post(
            "/api/warehouse-stock-transfer/search-serials",
            { serials: serials.value }
        );
        products.value = res.data.data;

        if (products.value.length === 0) {
            toast.info("No products found for the given serial numbers.");
        } else {
            products.value.forEach((p) => {
                transferQuantities.value[p.id] = p.matched_serials.length;
            });
        }
    } catch (error) {
        console.error(error);
        toast.error("Error searching serials.");
    }
};


// const proceedWithTransfer = async () => {
//     try {
//         const payload = {
//             destination_warehouse_id: destinationWarehouse.value,
//             products: products.value.map((p) => ({
//                 id: p.id,
//                 qty: p.matched_serials.length,
//             })),
//         };

//         await axios.post("/api/warehouse-stock-transfer/transfer", payload);

//         toast.success("Stock Transfer Created Successfully");

        
//         router.visit("/warehouse-operation");
//     } catch (error) {
//         console.error(error);
//         if (error.response?.status === 422) {
//             const msg = error.response.data.message;
//             toast.error(msg);
//         } else {
//             toast.error("Transfer failed.");
//         }
//     }
// };
const proceedWithTransfer = async () => {
    try {
        const payload = {
            origin_warehouse_id: products.value[0]?.warehouse_id ?? null, // Use first product's warehouse
            destination_warehouse_id: destinationWarehouse.value,
            transfer_date: new Date().toISOString().split('T')[0],
            details: products.value.map((p) => ({
                origin_warehouse_product_id: p.id,
                expected_qty: p.matched_serials.length,
                serials: p.matched_serials.map(serialNumber => ({
                    serial_number: serialNumber,
                    batch_number: null,
                    manufactured_at: null,
                    expired_at: null
                }))
            })),
        };

        await axios.post("/api/warehouse-stock-transfers", payload);

        toast.success("Stock Transfer Created Successfully");
        router.visit("/warehouse-operation");
    } catch (error) {
        console.error(error);
        if (error.response?.status === 422) {
            toast.error(error.response.data.message);
        } else {
            toast.error("Transfer failed.");
        }
    }
};





onMounted(() => {
    fetchWarehouses();
});
</script>
