<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { ref, onMounted } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { defineProps } from "vue";
import axios from "axios";
import { useToast } from "vue-toastification";

// Props from controller
const props = defineProps({
    pendingTransfers: Array,
    selectedTransferId: Number,
});
const toast = useToast();

const totalItems = ref(1067);
const activeTasks = ref(props.pendingTransfers.length);

const selectedTransfer = ref(null);
const showDetailsPanel = ref(false);

const operations = ref([
    {
        label: "Order Fulfillment",
        description: "5 orders",
        icon: "mdi-clipboard-check",
    },
    {
        label: "Stock Transfers",
        description: `${props.pendingTransfers.length} pending`,
        icon: "mdi-swap-horizontal",
    },
    {
        label: "Stock Counting",
        description: "Cycle counts",
        icon: "mdi-counter",
    },
    {
        label: "Stock Adjustment",
        description: "4 total",
        icon: "mdi-calculator",
    },
    {
        label: "Product Labeling",
        description: "Print labels",
        icon: "mdi-barcode",
    },
    { label: "Reports", description: "Analytics", icon: "mdi-chart-line" },
    { label: "Settings", description: "Configuration", icon: "mdi-cog" },
    {
        label: "Dispatch",
        description: "Delivery confirmation",
        icon: "mdi-truck-delivery",
    },
]);

// function handleOperationClick(label) {
//     if (label === "Stock Transfers") {
//         router.visit("/warehouse-stock-transfer-v2/create");
//     }else if (label === "Stock Adjustment") {
//         router.visit("/warehouse-operation/stock-adjustment");
//     }
// }
function handleOperationClick(label) {
    console.log("Clicked:", label);  // DEBUG LOG
    if (label === "Stock Transfers") {
        router.visit("/warehouse-stock-transfer-v2/create");
    } else if (label === "Stock Adjustment") {
        router.visit("/warehouse-operation/stock-adjustment");
    }
}


function viewTransfer(id) {
    router.visit(`/warehouse-operation/viewtransfer/${id}`);
}

const loadTransferDetails = async (id) => {
    try {
        const res = await axios.get(
            `/warehouse-operation/transfer-details/${id}`
        );

        selectedTransfer.value = res.data.data;
        showDetailsPanel.value = true;
    } catch (error) {
        console.error("Failed to load transfer details", error);
    }
};

onMounted(() => {
    if (props.selectedTransferId) {
        loadTransferDetails(props.selectedTransferId);
    }
});

function closeDetailsPanel() {
    showDetailsPanel.value = false;
    selectedTransfer.value = null;
}
const updateTransferStatus = async (status) => {
    try {
        await axios.post(
            `/warehouse-operation/transfer-status/${selectedTransfer.value.id}`,
            { status }
        );

        // Optional: Toast success
        toast.success(`Transfer ${status} successfully`);

        // Refresh page to reflect updates (or manually update transfer status in Vue)
        router.visit("/warehouse-operation");
    } catch (error) {
        console.error("Failed to update status", error);
        alert("Failed to update status");
    }
};

const receiveTransfer = (id) => {
    router.visit(`/warehouse-stock-transfer/${id}/scan`);
};
</script>

<template>
    <AppLayout title="Warehouse Operations">
        <div class="p-4 sm:p-6">
            <!-- Header -->
            <div class="mb-4">
                <h1 class="text-xl font-semibold">Warehouse Dashboard</h1>
                <p class="text-sm text-gray-500">Last sync: 3:19:03 PM</p>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <!-- Total Items -->
                <div
                    class="bg-white shadow rounded-lg p-4 flex items-center space-x-4"
                >
                    <span class="mdi mdi-cube text-2xl text-blue-500"></span>
                    <div>
                        <div class="text-lg font-bold">{{ totalItems }}</div>
                        <div class="text-sm text-gray-500">Total Items</div>
                    </div>
                </div>
                <!-- Active Tasks -->
                <div
                    class="bg-white shadow rounded-lg p-4 flex items-center space-x-4"
                >
                    <span
                        class="mdi mdi-clipboard-text text-2xl text-green-500"
                    ></span>
                    <div>
                        <div class="text-lg font-bold">{{ activeTasks }}</div>
                        <div class="text-sm text-gray-500">Active Tasks</div>
                    </div>
                </div>
            </div>

            <!-- Today's Tasks -->
            <div
                v-for="transfer in props.pendingTransfers"
                :key="transfer.id"
                class="bg-white shadow rounded-lg p-4 flex justify-between items-center mb-3"
            >
                <div>
                    <div class="font-semibold">
                        Stock Transfer #{{ transfer.number }}
                    </div>
                    <div class="text-sm text-gray-500">
                        From: {{ transfer.origin_warehouse.name }} → To:
                        {{ transfer.destination_warehouse.name }}
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <span
                        class="text-xs font-semibold px-2 py-1 rounded-full"
                        :class="{
                            'bg-yellow-100 text-yellow-600':
                                transfer.status === 'pending',
                            'bg-green-100 text-green-600':
                                transfer.status === 'approved',
                        }"
                    >
                        {{ transfer.status }}
                    </span>

                    <button
                        @click="viewTransfer(transfer.id)"
                        class="bg-indigo-500 hover:bg-indigo-600 text-white text-xs font-semibold px-3 py-1 rounded transition-all"
                    >
                        View
                    </button>

                    <!-- Show Receive button only if Approved -->
                    <button
                        v-if="transfer.status === 'approved'"
                        @click="receiveTransfer(transfer.id)"
                        class="bg-blue-500 hover:bg-blue-600 text-white text-xs font-semibold px-3 py-1 rounded transition-all"
                    >
                        Receive
                    </button>
                </div>
            </div>

            <!-- Operations -->
            <div>
                <h2 class="text-md font-semibold mb-2">Warehouse Operations</h2>
                <p class="text-sm text-gray-500 mb-4">
                    Access all warehouse functions
                </p>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div
                        v-for="op in operations"
                        :key="op.label"
                        class="bg-white shadow rounded-lg p-4 text-center hover:bg-gray-50 cursor-pointer transition-all"
                        @click="handleOperationClick(op.label)"
                    >
                        <span
                            :class="['mdi', op.icon, 'text-2xl text-primary']"
                        ></span>
                        <div class="mt-2 text-sm font-medium">
                            {{ op.label }}
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ op.description }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Details Panel -->
            <Transition name="slide">
                <div
                    v-if="showDetailsPanel"
                    class="fixed top-0 right-0 w-full md:w-1/3 h-full bg-white shadow-lg z-50 overflow-auto"
                >
                    <div class="p-4 flex justify-between items-center border-b">
                        <h2 class="text-lg font-semibold">
                            Stock Transfer Details
                        </h2>
                        <button
                            @click="closeDetailsPanel"
                            class="text-gray-500 hover:text-black"
                        >
                            ✕
                        </button>
                    </div>
                    <div v-if="selectedTransfer" class="p-4 space-y-4">
                        <div>
                            <div class="text-sm text-gray-500">
                                Transfer Number
                            </div>
                            <div class="font-semibold">
                                {{ selectedTransfer.number }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">From</div>
                            <div class="font-semibold">
                                {{ selectedTransfer.origin_warehouse.name }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">To</div>
                            <div class="font-semibold">
                                {{
                                    selectedTransfer.destination_warehouse.name
                                }}
                            </div>
                        </div>
                        <!-- Product Details Table -->
                        <table class="w-full text-sm mt-4">
                            <thead>
                                <tr>
                                    <th class="text-left p-2">Product</th>
                                    <th class="text-left p-2">QTY</th>
                                    <th class="text-left p-2">Serials</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="detail in selectedTransfer.details"
                                    :key="detail.id"
                                    class="border-t"
                                >
                                    <td class="p-2">
                                        {{
                                            detail.origin_product
                                                .supplier_product_detail.product
                                                .name
                                        }}
                                    </td>
                                    <td class="p-2">
                                        {{ detail.transferred_qty }} /
                                        {{ detail.expected_qty }}
                                    </td>
                                    
                                    <td class="p-2">
                                        <span
                                            v-if="detail.matched_serials.length"
                                        >
                                            {{
                                                detail.matched_serials.join(
                                                    ", "
                                                )
                                            }}
                                        </span>
                                        <span v-else>No matched serials</span>
                                    </td>
                                </tr>
                                <div
                                    class="flex justify-end space-x-3 mt-6"
                                    v-if="
                                        selectedTransfer.status !== 'approved'
                                    "
                                >
                                    <button
                                        @click="
                                            updateTransferStatus('approved')
                                        "
                                        class="bg-green-500 hover:bg-green-600 text-white text-sm font-semibold px-4 py-2 rounded"
                                    >
                                        Approve
                                    </button>
                                    <button
                                        @click="
                                            updateTransferStatus('rejected')
                                        "
                                        class="bg-red-500 hover:bg-red-600 text-white text-sm font-semibold px-4 py-2 rounded"
                                    >
                                        Reject
                                    </button>
                                    <button
                                        @click="
                                            updateTransferStatus('cancelled')
                                        "
                                        class="bg-gray-500 hover:bg-gray-600 text-white text-sm font-semibold px-4 py-2 rounded"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </tbody>
                        </table>
                    </div>
                </div>
            </Transition>
        </div>
    </AppLayout>
</template>

<style scoped>
.text-primary {
    color: #4f46e5;
}
.slide-enter-active,
.slide-leave-active {
    transition: transform 0.3s ease;
}
.slide-enter-from,
.slide-leave-to {
    transform: translateX(100%);
}
</style>
